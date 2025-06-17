<?php

namespace App\Services\Response;

use App\Contracts\ResponseFormatterInterface;
use App\DTO\ProductResponseDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Adaptive Response Formatter implementing Strategy pattern
 * Handles multiple output formats with responsive design principles
 */
class AdaptiveResponseFormatter implements ResponseFormatterInterface
{
    private array $formatStrategies = [];

    public function __construct()
    {
        $this->initializeFormatStrategies();
    }

    public function formatJson(ProductResponseDTO $response): JsonResponse
    {
        return new JsonResponse(
            $response->toArray(),
            $response->getStatusCode(),
            [
                'Content-Type' => 'application/json',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function formatXml(ProductResponseDTO $response): Response
    {
        $xml = $this->arrayToXml($response->toArray(), 'response');

        return new Response(
            $xml,
            $response->getStatusCode(),
            [
                'Content-Type' => 'application/xml',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function formatByAcceptHeader(ProductResponseDTO $response, string $acceptHeader): Response
    {
        // Parse Accept header and determine best format
        $preferredFormat = $this->parseAcceptHeader($acceptHeader);

        switch ($preferredFormat) {
            case 'xml':
                return $this->formatXml($response);
            case 'json':
            default:
                // Convert JsonResponse to Response for consistent return type
                $jsonResponse = $this->formatJson($response);
                return new Response(
                    $jsonResponse->getContent(),
                    $jsonResponse->getStatusCode(),
                    array_merge($jsonResponse->headers->all(), [
                        'Content-Type' => ['application/json']
                    ])
                );
        }
    }

    public function supports(string $format): bool
    {
        return in_array($format, $this->getSupportedFormats());
    }

    public function getSupportedFormats(): array
    {
        return ['json', 'xml'];
    }

    /**
     * Initialize format strategies
     */
    private function initializeFormatStrategies(): void
    {
        $this->formatStrategies = [
            'application/json' => 'json',
            'application/xml' => 'xml',
            'text/xml' => 'xml',
            'application/x-xml' => 'xml',
            '*/*' => 'json', // Default to JSON
        ];
    }

    /**
     * Parse Accept header to determine preferred format
     */
    private function parseAcceptHeader(string $acceptHeader): string
    {
        $accepts = explode(',', $acceptHeader);

        foreach ($accepts as $accept) {
            $accept = trim(explode(';', $accept)[0]); // Remove quality values

            if (isset($this->formatStrategies[$accept])) {
                return $this->formatStrategies[$accept];
            }
        }

        return 'json'; // Default fallback
    }

    /**
     * Convert array to XML string
     */
    private function arrayToXml(array $data, string $rootElement = 'root'): string
    {
        $xml = new \SimpleXMLElement("<?xml version='1.0' encoding='UTF-8'?><{$rootElement}></{$rootElement}>");
        $this->arrayToXmlRecursive($data, $xml);

        return $xml->asXML();
    }

    /**
     * Recursively convert array to XML
     */
    private function arrayToXmlRecursive(array $data, \SimpleXMLElement $xml): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (is_numeric($key)) {
                    $key = 'item';
                }
                $subnode = $xml->addChild($key);
                $this->arrayToXmlRecursive($value, $subnode);
            } else {
                if (is_numeric($key)) {
                    $key = 'item';
                }
                $xml->addChild($key, htmlspecialchars($value ?? ''));
            }
        }
    }
}
