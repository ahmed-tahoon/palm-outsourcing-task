<?php

namespace App\Http\Controllers;

use App\Contracts\ProductServiceInterface;
use App\Contracts\ResponseFormatterInterface;
use App\DTO\ScrapingRequestDTO;
use App\DTO\ProductResponseDTO;
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
    private ResponseFormatterInterface $responseFormatter;
    private array $performanceMetrics = [];

    public function __construct(
        ProductServiceInterface $productService,
        ResponseFormatterInterface $responseFormatter
    ) {
        $this->productService = $productService;
        $this->responseFormatter = $responseFormatter;

        // Apply rate limiting middleware
        $this->middleware('throttle:api')->except(['index', 'show']);
    }

    /**
     * Display a listing of products with advanced filtering and caching
     */
    public function index(Request $request): Response
    {
        $startTime = microtime(true);

        try {
            // Validate and sanitize input
            $filters = $this->validateAndSanitizeFilters($request);
            $perPage = $this->getPerPage($request);

            // Generate cache key based on filters
            $cacheKey = $this->generateCacheKey('products_index', $filters, $perPage);

            // Try to get from cache first (responsive caching)
            $products = Cache::remember($cacheKey, 300, function () use ($filters, $perPage) {
                return $this->productService->getProducts($filters, $perPage);
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
                    ]
                ],
                message: 'Products retrieved successfully',
                metadata: [
                    'response_time' => microtime(true) - $startTime,
                    'cache_hit' => Cache::has($cacheKey),
                    'filters_applied' => $filters,
                ]
            );

            return $this->formatResponse($request, $response);
        } catch (\Exception $e) {
            Log::error('Failed to fetch products: ' . $e->getMessage(), [
                'filters' => $filters ?? [],
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
            ]);

            $response = ProductResponseDTO::error(
                error: 'Failed to retrieve products',
                statusCode: 500,
                metadata: ['response_time' => microtime(true) - $startTime]
            );

            return $this->formatResponse($request, $response);
        }
    }

    /**
     * Scrape product with advanced validation and rate limiting
     */
    public function scrape(Request $request): Response
    {
        $startTime = microtime(true);

        // Apply rate limiting per IP
        if (!$this->checkRateLimit($request)) {
            $response = ProductResponseDTO::error(
                error: 'Too many requests. Please try again later.',
                statusCode: 429,
                metadata: [
                    'rate_limit_exceeded' => true,
                    'retry_after' => 60
                ]
            );

            return $this->formatResponse($request, $response);
        }

        try {
            // Validate request
            $validationResult = $this->validateScrapingRequest($request);
            if (!$validationResult['valid']) {
                $response = ProductResponseDTO::error(
                    error: 'Invalid request data',
                    statusCode: 400,
                    metadata: [
                        'validation_errors' => $validationResult['errors'],
                        'response_time' => microtime(true) - $startTime
                    ]
                );

                return $this->formatResponse($request, $response);
            }

            // Create scraping request DTO
            $scrapingRequest = ScrapingRequestDTO::fromArray($request->all());

            // Execute scraping through service layer
            $result = $this->productService->scrapeAndStore($scrapingRequest);

            // Record performance metrics
            $this->recordPerformanceMetrics($request, microtime(true) - $startTime, $result->success);

            return $this->formatResponse($request, $result);
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
                    'error_id' => uniqid('error_', true)
                ]
            );

            return $this->formatResponse($request, $response);
        }
    }





    /**
     * Format response using Strategy pattern (responsive design)
     */
    private function formatResponse(Request $request, ProductResponseDTO $response): Response
    {
        $acceptHeader = $request->header('Accept', 'application/json');

        // Add CORS headers for responsive design
        $formattedResponse = $this->responseFormatter->formatByAcceptHeader($response, $acceptHeader);

        return $formattedResponse->withHeaders([
            'X-Response-Time' => $response->metadata['response_time'] ?? 0,
            'X-API-Version' => '2.0',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, Accept',
        ]);
    }

    /**
     * Validate and sanitize filters (defensive programming)
     */
    private function validateAndSanitizeFilters(Request $request): array
    {
        $allowedFilters = ['price_min', 'price_max', 'brand', 'category', 'source', 'availability'];
        $filters = [];

        foreach ($allowedFilters as $filter) {
            if ($request->has($filter)) {
                $value = $request->input($filter);

                // Sanitize based on filter type
                switch ($filter) {
                    case 'price_min':
                    case 'price_max':
                        $filters[$filter] = max(0, (float) $value);
                        break;
                    default:
                        $filters[$filter] = strip_tags(trim($value));
                        break;
                }
            }
        }

        return $filters;
    }

    /**
     * Get and validate per_page parameter
     */
    private function getPerPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', 20);
        return max(1, min(100, $perPage)); // Clamp between 1 and 100
    }

    /**
     * Generate cache key for intelligent caching
     */
    private function generateCacheKey(string $prefix, array $params = [], ?int $perPage = null): string
    {
        $keyData = array_merge($params, ['per_page' => $perPage]);
        return $prefix . '_' . md5(serialize($keyData));
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
     * Validate scraping request with comprehensive checks
     */
    private function validateScrapingRequest(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url|max:2048',
            'strategy' => 'sometimes|string|in:amazon,ebay,jumia,generic',
            'timeout' => 'sometimes|integer|min:5|max:120',
            'async' => 'sometimes|boolean',
            'priority' => 'sometimes|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return [
                'valid' => false,
                'errors' => $validator->errors()->toArray(),
            ];
        }

        // Additional URL validation (check if domain is supported)
        $url = $request->input('url');
        $supportedDomains = $this->productService->getSupportedDomains();
        $domain = parse_url($url, PHP_URL_HOST);

        $isDomainSupported = false;
        foreach ($supportedDomains as $supportedDomain) {
            if (str_contains($domain, $supportedDomain)) {
                $isDomainSupported = true;
                break;
            }
        }

        if (!$isDomainSupported) {
            return [
                'valid' => false,
                'errors' => ['url' => ['Domain not supported for scraping']],
            ];
        }

        return ['valid' => true];
    }


    /**
     * Record performance metrics for monitoring
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
        ];

        // Store in cache for analysis (you could also use a dedicated metrics service)
        Cache::put('metrics_' . uniqid(), $metrics, 3600);
    }
}
