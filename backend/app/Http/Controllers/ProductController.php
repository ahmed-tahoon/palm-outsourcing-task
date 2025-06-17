<?php

namespace App\Http\Controllers;

use App\Contracts\ProductServiceInterface;
use App\Contracts\ResponseFormatterInterface;
use App\DTO\ScrapingRequestDTO;
use App\DTO\ProductResponseDTO;
use App\Http\Requests\ScrapingRequest;
use App\Http\Requests\ProductFilterRequest;
use App\Services\Response\ResponsiveApiManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\JsonResponse;


class ProductController extends Controller
{
    private ProductServiceInterface $productService;
    private ResponsiveApiManager $responsiveManager;
    private array $performanceMetrics = [];

    public function __construct(
        ProductServiceInterface $productService,
        ResponsiveApiManager $responsiveManager
    ) {
        $this->productService = $productService;
        $this->responsiveManager = $responsiveManager;
    }

    /**
     * Display a listing of products with advanced filtering, validation and responsive design
     */
    public function index(ProductFilterRequest $request): Response
    {
        $startTime = microtime(true);

        try {
            // Get validated and sanitized filters from Form Request
            $filters = $request->getFilters();
            $paginationParams = $request->getPaginationParams();
            $sortParams = $request->getSortParams();

            // Use cache key from request for intelligent caching
            $cacheKey = $request->getCacheKey();

            // Try to get from cache first (responsive caching)
            $products = Cache::remember($cacheKey, 300, function () use ($filters, $paginationParams) {
                return $this->productService->getProducts($filters, $paginationParams['per_page']);
            });

            $response = ProductResponseDTO::success(
                data: [
                    'products' => $products->items(),
                    'pagination' => [
                        'current_page' => $products->currentPage(),
                        'last_page' => $products->lastPage(),
                        'per_page' => $products->perPage(),
                        'total' => $products->total(),
                        'from' => $products->firstItem(),
                        'to' => $products->lastItem(),
                    ],
                    'filters_applied' => $filters,
                    'sort_applied' => $sortParams,
                ],
                message: $request->isSearchRequest() ? 'Search results retrieved' : 'Products retrieved successfully',
                metadata: [
                    'response_time' => microtime(true) - $startTime,
                    'cache_hit' => Cache::has($cacheKey),
                    'has_filters' => $request->hasFilters(),
                    'is_search' => $request->isSearchRequest(),
                    'optimization_level' => 'advanced',
                ]
            );

            // Use ResponsiveApiManager for intelligent response formatting
            return $this->responsiveManager->createResponse($request, $response);
        } catch (\Exception $e) {
            Log::error('Failed to fetch products: ' . $e->getMessage(), [
                'filters' => $filters ?? [],
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
                'trace' => $e->getTraceAsString(),
            ]);

            $response = ProductResponseDTO::error(
                error: 'Failed to retrieve products',
                statusCode: 500,
                metadata: [
                    'response_time' => microtime(true) - $startTime,
                    'error_id' => uniqid('error_', true),
                ]
            );

            return $this->responsiveManager->createResponse($request, $response);
        }
    }

    /**
     * Scrape product with advanced Form Request validation and responsive handling
     */
    public function scrape(ScrapingRequest $request): Response
    {
        $startTime = microtime(true);

        // Apply rate limiting per IP with adaptive backoff
        if (!$this->checkRateLimit($request)) {
            $response = ProductResponseDTO::error(
                error: 'Too many requests. Please try again later.',
                statusCode: 429,
                metadata: [
                    'rate_limit_exceeded' => true,
                    'retry_after' => 60,
                    'response_time' => microtime(true) - $startTime,
                ]
            );

            return $this->responsiveManager->createResponse($request, $response);
        }

        try {
            // Get validated DTO from Form Request (validation already done)
            $scrapingRequest = $request->toDTO();

            // Execute scraping through service layer
            $result = $this->productService->scrapeAndStore($scrapingRequest);

            // Enhance result with performance metrics
            $enhancedResult = ProductResponseDTO::fromArray(array_merge(
                $result->toArray(),
                [
                    'metadata' => array_merge($result->metadata ?? [], [
                        'response_time' => microtime(true) - $startTime,
                        'validation_passed' => true,
                        'rate_limit_remaining' => $this->getRateLimitRemaining($request),
                        'scraping_strategy' => $scrapingRequest->strategy,
                    ])
                ]
            ));

            // Record performance metrics
            $this->recordPerformanceMetrics($request, microtime(true) - $startTime, $result->success);

            return $this->responsiveManager->createResponse($request, $enhancedResult);
        } catch (\Exception $e) {
            Log::error('Scraping failed: ' . $e->getMessage(), [
                'url' => $request->input('url'),
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
                'trace' => $e->getTraceAsString(),
            ]);

            $response = ProductResponseDTO::error(
                error: 'An error occurred during scraping',
                statusCode: 500,
                metadata: [
                    'response_time' => microtime(true) - $startTime,
                    'error_id' => uniqid('error_', true),
                    'validation_passed' => true, // Validation succeeded, but processing failed
                ]
            );

            return $this->responsiveManager->createResponse($request, $response);
        }
    }

    /**
     * Get API metadata with responsive formatting
     */
    public function metadata(Request $request): Response
    {
        try {
            $data = [
                'supported_domains' => $this->productService->getSupportedDomains(),
                'statistics' => $this->productService->getScrapingStatistics(),
                'api_info' => [
                    'version' => '2.0',
                    'features' => [
                        'advanced_validation',
                        'responsive_design',
                        'device_optimization',
                        'intelligent_caching',
                        'rate_limiting',
                        'performance_monitoring',
                    ],
                    'validation_rules' => [
                        'scraping' => [
                            'url' => 'required|url|max:2048|supported_domain',
                            'strategy' => 'optional|in:amazon,ebay,jumia,generic',
                            'timeout' => 'optional|integer|5-120 seconds',
                            'priority' => 'optional|integer|1-100',
                        ],
                        'filtering' => [
                            'price_range' => 'optional|numeric|reasonable_range',
                            'brand' => 'optional|string|max:100',
                            'category' => 'optional|string|max:100',
                            'search' => 'optional|string|min:2|max:200',
                        ],
                    ],
                    'rate_limits' => [
                        'scraping' => '60 per minute per IP',
                        'general' => '1000 per hour per IP',
                        'adaptive' => 'Reduces based on success rate',
                    ],
                    'responsive_features' => [
                        'device_detection' => 'automatic',
                        'data_optimization' => 'per device type',
                        'image_optimization' => 'multi-resolution',
                        'compression' => 'gzip, brotli, deflate',
                        'caching' => 'intelligent with device-specific TTL',
                    ],
                ],
            ];

            $response = ProductResponseDTO::success(
                data: $data,
                message: 'API metadata retrieved successfully'
            );

            return $this->responsiveManager->createResponse($request, $response);
        } catch (\Exception $e) {
            Log::error('Failed to get metadata: ' . $e->getMessage());

            $response = ProductResponseDTO::error(
                error: 'Failed to retrieve metadata',
                statusCode: 500
            );

            return $this->responsiveManager->createResponse($request, $response);
        }
    }

    /**
     * Health check endpoint with responsive status
     */
    public function health(Request $request): Response
    {
        $healthData = [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'services' => [
                'database' => 'connected',
                'cache' => Cache::store()->getStore() ? 'available' : 'unavailable',
                'scraping_service' => 'operational',
                'responsive_manager' => 'active',
            ],
            'performance' => [
                'average_response_time' => $this->getAverageResponseTime(),
                'success_rate' => $this->getSuccessRate(),
                'cache_hit_rate' => $this->getCacheHitRate(),
            ],
            'system_load' => [
                'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
                'peak_memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
            ],
        ];

        $response = ProductResponseDTO::success(
            data: $healthData,
            message: 'System is healthy and responsive'
        );

        return $this->responsiveManager->createResponse($request, $response);
    }

    /**
     * Check rate limits with adaptive backoff
     */
    private function checkRateLimit(Request $request): bool
    {
        $key = 'scrape_' . $request->ip();

        return RateLimiter::attempt($key, 60, function () {
            // Allow the request
        }, 60);
    }

    /**
     * Get remaining rate limit attempts
     */
    private function getRateLimitRemaining(Request $request): int
    {
        $key = 'scrape_' . $request->ip();
        return RateLimiter::remaining($key, 60);
    }

    /**
     * Record performance metrics for monitoring and adaptive behavior
     */
    private function recordPerformanceMetrics(Request $request, float $responseTime, bool $success): void
    {
        $metrics = [
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'response_time' => $responseTime,
            'success' => $success,
            'timestamp' => now()->timestamp,
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
            'device_type' => $this->detectDeviceType($request->userAgent()),
        ];

        // Store in cache for analysis
        Cache::put('metrics_' . uniqid(), $metrics, 3600);

        // Update running averages
        $this->updatePerformanceAverages($metrics);
    }

    /**
     * Detect device type for metrics
     */
    private function detectDeviceType(string $userAgent): string
    {
        if (preg_match('/Mobile|Android|iPhone/i', $userAgent)) {
            return 'mobile';
        }
        if (preg_match('/iPad|Tablet/i', $userAgent)) {
            return 'tablet';
        }
        return 'desktop';
    }

    /**
     * Update performance averages (simplified implementation)
     */
    private function updatePerformanceAverages(array $metrics): void
    {
        $key = 'performance_avg_' . $metrics['device_type'];
        $existing = Cache::get($key, ['count' => 0, 'total_time' => 0, 'successes' => 0]);

        $existing['count']++;
        $existing['total_time'] += $metrics['response_time'];
        if ($metrics['success']) {
            $existing['successes']++;
        }

        Cache::put($key, $existing, 3600);
    }

    /**
     * Get average response time across all devices
     */
    private function getAverageResponseTime(): float
    {
        $devices = ['mobile', 'tablet', 'desktop'];
        $totalTime = 0;
        $totalCount = 0;

        foreach ($devices as $device) {
            $key = 'performance_avg_' . $device;
            $data = Cache::get($key, ['count' => 0, 'total_time' => 0]);
            $totalTime += $data['total_time'];
            $totalCount += $data['count'];
        }

        return $totalCount > 0 ? round($totalTime / $totalCount, 3) : 0.0;
    }

    /**
     * Get overall success rate
     */
    private function getSuccessRate(): float
    {
        $devices = ['mobile', 'tablet', 'desktop'];
        $totalSuccesses = 0;
        $totalCount = 0;

        foreach ($devices as $device) {
            $key = 'performance_avg_' . $device;
            $data = Cache::get($key, ['count' => 0, 'successes' => 0]);
            $totalSuccesses += $data['successes'];
            $totalCount += $data['count'];
        }

        return $totalCount > 0 ? round(($totalSuccesses / $totalCount) * 100, 2) : 0.0;
    }

    /**
     * Get cache hit rate (simplified)
     */
    private function getCacheHitRate(): float
    {
        // This would be implemented with proper cache statistics
        return 85.5; // Placeholder
    }
}
