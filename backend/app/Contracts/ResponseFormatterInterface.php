<?php

namespace App\Contracts;

use App\DTO\ProductResponseDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Response formatter interface for different output formats
 * Follows Interface Segregation Principle - specific to response formatting
 */
interface ResponseFormatterInterface
{
    /**
     * Format response as JSON
     */
    public function formatJson(ProductResponseDTO $response): JsonResponse;

    /**
     * Format response as XML
     */
    public function formatXml(ProductResponseDTO $response): Response;

    /**
     * Format response based on Accept header (content negotiation)
     */
    public function formatByAcceptHeader(ProductResponseDTO $response, string $acceptHeader): Response;

    /**
     * Check if formatter supports the given format
     */
    public function supports(string $format): bool;

    /**
     * Get supported formats
     */
    public function getSupportedFormats(): array;
}
