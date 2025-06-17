<?php

namespace App\Services\Response;

use App\Contracts\ResponseFormatterInterface;
use App\DTO\ProductResponseDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Responsive API Manager implementing multiple SOLID patterns
 *
 * Features:
 * - Device-specific response adaptation
 * - Content negotiation and compression
 * - Performance optimization
 * - Caching strategies
 * - Error handling with fallbacks
 * - Rate limiting awareness
 * - Progressive enhancement
 */
class ResponsiveApiManager
{
    private ResponseFormatterInterface $formatter;
    private array $deviceProfiles;
    private array $compressionFormats;
    private array $performanceMetrics = [];

    public function __construct(ResponseFormatterInterface $formatter)
    {
        $this->formatter = $formatter;
        $this->initializeDeviceProfiles();
        $this->initializeCompressionFormats();
    }

    /**
     * Create responsive response based on request context
     */
    public function createResponse(
        Request $request,
        ProductResponseDTO $data,
        array $options = []
    ): Response {
        $startTime = microtime(true);

        try {
            // Analyze request context
            $context = $this->analyzeRequestContext($request);

            // Adapt data based on device and connection
            $adaptedData = $this->adaptDataForContext($data, $context);

            // Apply responsive transformations
            $optimizedData = $this->applyResponsiveOptimizations($adaptedData, $context);

            // Format response
            $response = $this->formatResponseForContext($request, $optimizedData, $context);

            // Apply performance enhancements
            $enhancedResponse = $this->applyPerformanceEnhancements($response, $context);

            // Record metrics
            $this->recordResponseMetrics($context, microtime(true) - $startTime);

            return $enhancedResponse;
        } catch (\Exception $e) {
            Log::error('Responsive API Manager error: ' . $e->getMessage());

            // Fallback to basic response
            return $this->createFallbackResponse($request, $data);
        }
    }

    /**
     * Analyze request context for responsive decisions
     */
    private function analyzeRequestContext(Request $request): array
    {
        $userAgent = $request->userAgent();
        $acceptHeader = $request->header('Accept', 'application/json');
        $acceptEncoding = $request->header('Accept-Encoding', '');

        return [
            'device_type' => $this->detectDeviceType($userAgent),
            'connection_type' => $this->detectConnectionType($request),
            'browser_capabilities' => $this->analyzeBrowserCapabilities($userAgent),
            'preferred_format' => $this->determinePreferredFormat($acceptHeader),
            'compression_support' => $this->detectCompressionSupport($acceptEncoding),
            'cache_preferences' => $this->analyzeCachePreferences($request),
            'performance_requirements' => $this->assessPerformanceRequirements($request),
            'user_agent' => $userAgent,
            'ip' => $request->ip(),
            'timestamp' => now(),
        ];
    }

    /**
     * Adapt data based on context (responsive data pattern)
     */
    private function adaptDataForContext(ProductResponseDTO $data, array $context): ProductResponseDTO
    {
        $adaptedData = $data->toArray();

        // Apply device-specific adaptations
        switch ($context['device_type']) {
            case 'mobile':
                $adaptedData = $this->adaptForMobile($adaptedData);
                break;
            case 'tablet':
                $adaptedData = $this->adaptForTablet($adaptedData);
                break;
            case 'desktop':
                $adaptedData = $this->adaptForDesktop($adaptedData);
                break;
        }

        // Apply connection-based adaptations
        if ($context['connection_type'] === 'slow') {
            $adaptedData = $this->adaptForSlowConnection($adaptedData);
        }

        return ProductResponseDTO::fromArray([
            'success' => $data->success,
            'data' => $adaptedData['data'] ?? null,
            'message' => $adaptedData['message'] ?? null,
            'error' => $adaptedData['error'] ?? null,
            'metadata' => array_merge($adaptedData['metadata'] ?? [], [
                'adapted_for' => $context['device_type'],
                'optimized_for' => $context['connection_type'],
            ]),
        ]);
    }

    /**
     * Apply responsive optimizations
     */
    private function applyResponsiveOptimizations(ProductResponseDTO $data, array $context): ProductResponseDTO
    {
        $optimizedData = $data->toArray();

        // Image optimization for different devices
        if (isset($optimizedData['data']['products'])) {
            $optimizedData['data']['products'] = $this->optimizeProductImages(
                $optimizedData['data']['products'],
                $context['device_type']
            );
        }

        // Field filtering based on device capabilities
        $optimizedData = $this->filterFieldsByDevice($optimizedData, $context);

        // Pagination optimization
        if (isset($optimizedData['data']['pagination'])) {
            $optimizedData['data']['pagination'] = $this->optimizePagination(
                $optimizedData['data']['pagination'],
                $context
            );
        }

        return ProductResponseDTO::fromArray($optimizedData);
    }

    /**
     * Format response for specific context
     */
    private function formatResponseForContext(
        Request $request,
        ProductResponseDTO $data,
        array $context
    ): Response {
        // Use the formatter with context awareness
        $response = $this->formatter->formatByAcceptHeader($data, $context['preferred_format']);

        // Add responsive headers
        $responsiveHeaders = $this->generateResponsiveHeaders($context);

        foreach ($responsiveHeaders as $key => $value) {
            $response->header($key, $value);
        }

        return $response;
    }

    /**
     * Apply performance enhancements
     */
    private function applyPerformanceEnhancements(Response $response, array $context): Response
    {
        // Compression
        if ($context['compression_support']) {
            $response = $this->applyCompression($response, $context['compression_support']);
        }

        // Caching headers
        $cacheHeaders = $this->generateCacheHeaders($context);
        foreach ($cacheHeaders as $key => $value) {
            $response->header($key, $value);
        }

        // Performance monitoring headers
        $response->header('X-Device-Type', $context['device_type']);
        $response->header('X-Connection-Type', $context['connection_type']);
        $response->header('X-Optimized', 'true');

        return $response;
    }

    /**
     * Create fallback response for error scenarios
     */
    private function createFallbackResponse(Request $request, ProductResponseDTO $data): Response
    {
        Log::warning('Using fallback response due to responsive processing error');

        $jsonResponse = new JsonResponse(
            $data->toArray(),
            $data->getStatusCode(),
            [
                'Content-Type' => 'application/json',
                'X-Fallback-Response' => 'true',
                'X-Error-Recovery' => 'responsive-manager-fallback',
            ]
        );

        // Convert to Response for consistent return type
        return new Response(
            $jsonResponse->getContent(),
            $jsonResponse->getStatusCode(),
            $jsonResponse->headers->all()
        );
    }

    /**
     * Detect device type from user agent
     */
    private function detectDeviceType(string $userAgent): string
    {
        $mobilePatterns = [
            'Mobile',
            'Android',
            'iPhone',
            'iPad',
            'Windows Phone',
            'BlackBerry'
        ];

        $tabletPatterns = [
            'iPad',
            'Android.*Tablet',
            'Kindle',
            'Silk'
        ];

        foreach ($tabletPatterns as $pattern) {
            if (preg_match("/$pattern/i", $userAgent)) {
                return 'tablet';
            }
        }

        foreach ($mobilePatterns as $pattern) {
            if (preg_match("/$pattern/i", $userAgent)) {
                return 'mobile';
            }
        }

        return 'desktop';
    }

    /**
     * Detect connection type based on request patterns
     */
    private function detectConnectionType(Request $request): string
    {
        // Check for connection hint headers
        $connectionHint = $request->header('Connection-Type');
        if ($connectionHint) {
            return strtolower($connectionHint);
        }

        // Detect based on user agent patterns
        $userAgent = $request->userAgent();
        if (preg_match('/2G|3G|Edge|GPRS/i', $userAgent)) {
            return 'slow';
        }

        if (preg_match('/4G|LTE|WiFi|Broadband/i', $userAgent)) {
            return 'fast';
        }

        return 'medium'; // Default assumption
    }

    /**
     * Adapt data for mobile devices
     */
    private function adaptForMobile(array $data): array
    {
        // Reduce data payload for mobile
        if (isset($data['data']['products'])) {
            foreach ($data['data']['products'] as &$product) {
                // Remove heavy fields for mobile
                unset($product['detailed_description']);
                unset($product['specifications']);

                // Optimize images
                if (isset($product['image'])) {
                    $product['image_mobile'] = $this->generateMobileImageUrl($product['image']);
                    unset($product['image']); // Remove original large image
                }
            }
        }

        // Reduce pagination size for mobile
        if (isset($data['data']['pagination'])) {
            $data['data']['pagination']['mobile_optimized'] = true;
        }

        return $data;
    }

    /**
     * Adapt data for slow connections
     */
    private function adaptForSlowConnection(array $data): array
    {
        // Remove all non-essential data
        if (isset($data['data']['products'])) {
            foreach ($data['data']['products'] as &$product) {
                // Keep only essential fields
                $essential = [
                    'id',
                    'title',
                    'price',
                    'availability',
                    'source'
                ];

                $product = array_intersect_key($product, array_flip($essential));
            }
        }

        // Minimize metadata
        if (isset($data['metadata'])) {
            $data['metadata'] = array_slice($data['metadata'], 0, 3, true);
        }

        return $data;
    }

    /**
     * Generate responsive headers
     */
    private function generateResponsiveHeaders(array $context): array
    {
        return [
            'X-Device-Optimized' => $context['device_type'],
            'X-Content-Adapted' => 'true',
            'X-Performance-Level' => $context['performance_requirements'],
            'Vary' => 'Accept, User-Agent, Accept-Encoding',
            'X-Response-Optimized' => now()->toISOString(),
        ];
    }

    /**
     * Generate appropriate cache headers
     */
    private function generateCacheHeaders(array $context): array
    {
        $cacheTime = match ($context['device_type']) {
            'mobile' => 300,    // 5 minutes for mobile (data changes frequently)
            'tablet' => 600,    // 10 minutes for tablet
            'desktop' => 1800,  // 30 minutes for desktop
            default => 600
        };

        return [
            'Cache-Control' => "public, max-age={$cacheTime}",
            'ETag' => md5(serialize($context)),
            'Last-Modified' => now()->format('D, d M Y H:i:s \G\M\T'),
        ];
    }

    /**
     * Initialize device profiles
     */
    private function initializeDeviceProfiles(): void
    {
        $this->deviceProfiles = [
            'mobile' => [
                'max_data_size' => 50000,      // 50KB
                'image_quality' => 'low',
                'fields_limit' => 10,
                'pagination_size' => 10,
            ],
            'tablet' => [
                'max_data_size' => 200000,     // 200KB
                'image_quality' => 'medium',
                'fields_limit' => 20,
                'pagination_size' => 20,
            ],
            'desktop' => [
                'max_data_size' => 1000000,    // 1MB
                'image_quality' => 'high',
                'fields_limit' => 50,
                'pagination_size' => 50,
            ],
        ];
    }

    /**
     * Initialize compression formats
     */
    private function initializeCompressionFormats(): void
    {
        $this->compressionFormats = [
            'gzip' => 'gzip',
            'br' => 'brotli',
            'deflate' => 'deflate',
        ];
    }

    /**
     * Additional helper methods for complete implementation
     */
    private function detectCompressionSupport(string $acceptEncoding): ?string
    {
        foreach ($this->compressionFormats as $encoding => $format) {
            if (str_contains(strtolower($acceptEncoding), $encoding)) {
                return $format;
            }
        }
        return null;
    }

    private function generateMobileImageUrl(string $originalUrl): string
    {
        // This would integrate with an image optimization service
        return preg_replace('/\.(jpg|jpeg|png)$/i', '_mobile.$1', $originalUrl);
    }

    private function recordResponseMetrics(array $context, float $responseTime): void
    {
        $key = "responsive_metrics_{$context['device_type']}_{$context['connection_type']}";

        $metrics = [
            'response_time' => $responseTime,
            'device_type' => $context['device_type'],
            'connection_type' => $context['connection_type'],
            'timestamp' => $context['timestamp'],
        ];

        Cache::put($key . '_' . time(), $metrics, 3600);
    }

    private function optimizeProductImages(array $products, string $deviceType): array
    {
        foreach ($products as &$product) {
            if (isset($product['image'])) {
                $product['image'] = $this->getOptimizedImageUrl($product['image'], $deviceType);
            }
        }
        return $products;
    }

    private function getOptimizedImageUrl(string $url, string $deviceType): string
    {
        $suffix = match ($deviceType) {
            'mobile' => '_mobile',
            'tablet' => '_tablet',
            default => ''
        };

        return preg_replace('/\.(jpg|jpeg|png)$/i', "{$suffix}.$1", $url);
    }

    // Placeholder methods for complete implementation
    private function analyzeBrowserCapabilities(string $userAgent): array
    {
        return [];
    }
    private function determinePreferredFormat(string $acceptHeader): string
    {
        return 'application/json';
    }
    private function analyzeCachePreferences(Request $request): array
    {
        return [];
    }
    private function assessPerformanceRequirements(Request $request): string
    {
        return 'standard';
    }
    private function adaptForTablet(array $data): array
    {
        return $data;
    }
    private function adaptForDesktop(array $data): array
    {
        return $data;
    }
    private function filterFieldsByDevice(array $data, array $context): array
    {
        return $data;
    }
    private function optimizePagination(array $pagination, array $context): array
    {
        return $pagination;
    }
    private function applyCompression(Response $response, string $compression): Response
    {
        return $response;
    }
}
