<?php

namespace App\Services;

use App\Contracts\ProductServiceInterface;
use App\DTO\ProductResponseDTO;
use App\DTO\ScrapingRequestDTO;
use App\Services\ScrapingService;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Product Service implementing SOLID principles
 *
 * Single Responsibility: Handles all product-related business logic
 * Open/Closed: Extensible through strategy pattern and interfaces
 * Liskov Substitution: Implements ProductServiceInterface contract
 * Interface Segregation: Focused interface for product operations
 * Dependency Inversion: Depends on abstractions (ScrapingService)
 */
class ProductService implements ProductServiceInterface
{
    private ScrapingService $scrapingService;

    public function __construct(ScrapingService $scrapingService)
    {
        $this->scrapingService = $scrapingService;
    }

    /**
     * Get paginated products with filters
     */
    public function getProducts(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Product::query();

        // Apply filters
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'LIKE', '%' . $filters['search'] . '%')
                    ->orWhere('description', 'LIKE', '%' . $filters['search'] . '%');
            });
        }

        if (!empty($filters['price_min'])) {
            $query->where('price', '>=', $filters['price_min']);
        }

        if (!empty($filters['price_max'])) {
            $query->where('price', '<=', $filters['price_max']);
        }

        if (!empty($filters['brand'])) {
            $query->where('brand', $filters['brand']);
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (!empty($filters['availability'])) {
            $query->where('availability', $filters['availability']);
        }

        if (!empty($filters['tags'])) {
            $query->whereIn('tags', $filters['tags']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        // Apply sorting (default parameters will be handled by the request)
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Scrape and store product from URL
     */
    public function scrapeAndStore(ScrapingRequestDTO $request): ProductResponseDTO
    {
        try {
            // Use the appropriate scraping method based on domain
            $result = $this->getScrapedData($request->url);

            if (!$result) {
                return ProductResponseDTO::error(
                    error: 'Failed to scrape product data',
                    statusCode: 422,
                    metadata: [
                        'url' => $request->url,
                        'strategy' => $request->strategy,
                        'reason' => 'No data extracted',
                    ]
                );
            }

            // Store the product in the database
            $product = $this->storeProduct($result, $request);

            return ProductResponseDTO::success(
                data: [
                    'product' => $product->toArray(),
                    'scraping_info' => [
                        'url' => $request->url,
                        'strategy' => $request->strategy ?? 'auto-detected',
                        'scraped_at' => now()->toISOString(),
                        'source' => $result['source'] ?? 'unknown',
                    ]
                ],
                message: 'Product scraped and stored successfully',
                metadata: [
                    'product_id' => $product->id,
                    'source' => $this->detectSource($request->url),
                    'priority' => $request->priority ?? 50,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Product scraping failed: ' . $e->getMessage(), [
                'url' => $request->url,
                'strategy' => $request->strategy,
                'trace' => $e->getTraceAsString(),
            ]);

            return ProductResponseDTO::error(
                error: 'Product scraping failed: ' . $e->getMessage(),
                statusCode: 500,
                metadata: [
                    'url' => $request->url,
                    'error_type' => get_class($e),
                    'timestamp' => now()->toISOString(),
                ]
            );
        }
    }

    /**
     * Get scraped data using appropriate strategy
     */
    private function getScrapedData(string $url): ?array
    {
        // Determine scraping strategy based on URL
        if (str_contains($url, 'amazon.')) {
            return $this->scrapingService->scrapeAmazonProduct($url);
        } elseif (str_contains($url, 'jumia.')) {
            return $this->scrapingService->scrapeJumiaProduct($url);
        } elseif (str_contains($url, 'ebay.')) {
            return $this->scrapingService->scrapeEbayProduct($url);
        } else {
            return $this->scrapingService->scrapeGenericProduct($url);
        }
    }

    /**
     * Get supported domains
     */
    public function getSupportedDomains(): array
    {
        return [
            'amazon' => [
                'amazon.com',
                'amazon.co.uk',
                'amazon.de',
                'amazon.fr',
                'amazon.it',
                'amazon.es',
                'amazon.ca',
                'amazon.com.au',
                'amazon.co.jp',
                'amazon.in',
            ],
            'ebay' => [
                'ebay.com',
                'ebay.co.uk',
                'ebay.de',
                'ebay.fr',
                'ebay.it',
            ],
            'jumia' => [
                'jumia.com',
                'jumia.ng',
                'jumia.ke',
                'jumia.egypt',
                'jumia.ma',
            ],
        ];
    }

    /**
     * Get scraping statistics
     */
    public function getScrapingStatistics(): array
    {
        return Cache::remember('scraping_statistics', 300, function () {
            return [
                'total_products' => Product::count(),
                'products_by_source' => Product::groupBy('source')
                    ->selectRaw('source, count(*) as count')
                    ->pluck('count', 'source')
                    ->toArray(),
                'recent_scrapes' => Product::where('created_at', '>=', now()->subDay())
                    ->count(),
                'success_rate' => $this->calculateSuccessRate(),
                'average_response_time' => $this->getAverageResponseTime(),
                'last_updated' => now()->toISOString(),
            ];
        });
    }

    /**
     * Store scraped product data
     */
    private function storeProduct(array $data, ScrapingRequestDTO $request): Product
    {
        $productData = [
            'title' => $data['title'] ?? 'Unknown Product',
            'description' => $data['description'] ?? null,
            'price' => $data['price'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'product_url' => $request->url,
            'source' => $data['source'] ?? $this->detectSource($request->url),
            'brand' => $data['brand'] ?? null,
            'category' => $data['category'] ?? null,
            'availability' => $data['availability'] ?? 'unknown',
            'rating' => $data['rating'] ?? null,
            'review_count' => $data['review_count'] ?? null,
            'metadata' => json_encode($request->metadata ?? []),
            'scraped_at' => now(),
        ];

        // Check if product already exists by URL
        $existingProduct = Product::where('product_url', $request->url)->first();

        if ($existingProduct) {
            $existingProduct->update($productData);
            return $existingProduct;
        }

        return Product::create($productData);
    }

    /**
     * Detect source from URL
     */
    private function detectSource(string $url): string
    {
        $domain = parse_url($url, PHP_URL_HOST);
        $domain = preg_replace('/^www\./', '', $domain);

        if (str_contains($domain, 'amazon')) {
            return 'amazon';
        }
        if (str_contains($domain, 'ebay')) {
            return 'ebay';
        }
        if (str_contains($domain, 'jumia')) {
            return 'jumia';
        }

        return 'manual';
    }

    /**
     * Parse price from string
     */
    private function parsePrice(?string $priceString): ?float
    {
        if (!$priceString) {
            return null;
        }

        // Remove currency symbols and extract numeric value
        $price = preg_replace('/[^\d.,]/', '', $priceString);
        $price = str_replace(',', '.', $price);

        return is_numeric($price) ? (float) $price : null;
    }

    /**
     * Calculate success rate
     */
    private function calculateSuccessRate(): float
    {
        $total = Product::count();
        if ($total === 0) {
            return 0.0;
        }

        $successful = Product::whereNotNull('title')->count();
        return round(($successful / $total) * 100, 2);
    }

    /**
     * Get average response time (placeholder implementation)
     */
    private function getAverageResponseTime(): float
    {
        // This would typically be calculated from stored metrics
        return 2.5; // seconds (placeholder)
    }
}
