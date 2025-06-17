<?php

namespace App\Contracts;

use App\DTO\ProductResponseDTO;
use App\DTO\ScrapingRequestDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Product service interface following Dependency Inversion Principle
 * Abstracts product operations from the controller layer
 */
interface ProductServiceInterface
{
    /**
     * Get paginated products with filters
     */
    public function getProducts(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Scrape and store product from URL
     */
    public function scrapeAndStore(ScrapingRequestDTO $request): ProductResponseDTO;

   

    /**
     * Get supported domains
     */
    public function getSupportedDomains(): array;

    /**
     * Get scraping statistics
     */
    public function getScrapingStatistics(): array;
}
