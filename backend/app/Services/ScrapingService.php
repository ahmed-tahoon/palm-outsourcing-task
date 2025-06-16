<?php

namespace App\Services;

use App\Models\Product;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class ScrapingService
{
    private Client $client;
    private array $userAgents;
    private array $proxies;
    private int $requestDelay;
    private int $maxRetries;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
            'verify' => false,
            'allow_redirects' => true,
            'http_errors' => false, // Don't throw exceptions on HTTP errors
        ]);

        // Extended user agents for better rotation
        $this->userAgents = [
            // Chrome on Windows
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36',

            // Chrome on macOS
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',

            // Chrome on Linux
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',

            // Firefox on Windows
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:120.0) Gecko/20100101 Firefox/120.0',

            // Firefox on macOS
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:121.0) Gecko/20100101 Firefox/121.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:120.0) Gecko/20100101 Firefox/120.0',

            // Safari on macOS
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',

            // Edge on Windows
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36 Edg/119.0.0.0',
        ];

        $this->proxies = [];
        $this->requestDelay = 2; // 2 seconds between requests
        $this->maxRetries = 3;
    }

    /**
     * Load proxy configuration from external service or configuration
     */
    private function loadProxies(): array
    {
        $proxies = [];

        // Try to connect to proxy service (if available)
        try {
            $response = $this->client->get('http://localhost:8080/proxies', [
                'timeout' => 5,
                'connect_timeout' => 3,
            ]);

            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody(), true);
                $servicProxies = $data['proxies'] ?? [];
                $proxies = array_merge($proxies, $servicProxies);
            }
        } catch (RequestException $e) {
            Log::debug('Proxy service unavailable: ' . $e->getMessage());
        }

        // Get proxies from configuration
        $configProxies = config('scraping.proxies', []);
        $proxies = array_merge($proxies, $configProxies);

        // Filter out invalid or example proxies
        $validProxies = [];
        foreach ($proxies as $proxy) {
            // Skip example/placeholder proxies
            if (
                str_contains($proxy, 'example.com') ||
                str_contains($proxy, 'your-proxy') ||
                str_contains($proxy, 'localhost:8080')
            ) {
                continue;
            }

            // Validate proxy format
            if (
                filter_var($proxy, FILTER_VALIDATE_URL) ||
                preg_match('/^https?:\/\/[\w\.-]+:\d+$/', $proxy)
            ) {
                $validProxies[] = $proxy;
            } else {
                Log::warning('Invalid proxy format, skipping: ' . $proxy);
            }
        }

        if (!empty($validProxies)) {
            Log::debug('Loaded ' . count($validProxies) . ' valid proxies');
        } else {
            Log::debug('No valid proxies found, proceeding with direct connection');
        }

        return $validProxies;
    }

    /**
     * Get a random user agent for request rotation
     */
    private function getRandomUserAgent(): string
    {
        return $this->userAgents[array_rand($this->userAgents)];
    }

    /**
     * Get request options with rotation (user-agent and proxy)
     */
    private function getRequestOptions(): array
    {
        $options = [
            'headers' => [
                'User-Agent' => $this->getRandomUserAgent(),
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9,fr;q=0.8,es;q=0.7',
                'Accept-Encoding' => 'gzip, deflate, br',
                'DNT' => '1',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
                'Sec-Fetch-Dest' => 'document',
                'Sec-Fetch-Mode' => 'navigate',
                'Sec-Fetch-Site' => 'none',
                'Sec-Fetch-User' => '?1',
                'Cache-Control' => 'max-age=0',
            ],
            // Additional options for better compatibility
            'allow_redirects' => [
                'max' => 10,
                'strict' => false,
                'referer' => true,
                'protocols' => ['http', 'https']
            ],
            'timeout' => config('scraping.timeout', 30),
            'connect_timeout' => config('scraping.connect_timeout', 10),
        ];

        // Load and add proxy if available
        if (empty($this->proxies)) {
            $this->proxies = $this->loadProxies();
        }

        if (!empty($this->proxies)) {
            $proxy = $this->proxies[array_rand($this->proxies)];
            $options['proxy'] = $proxy;
            Log::debug('Using proxy: ' . $proxy);
        }

        return $options;
    }

    /**
     * Make HTTP request with retry logic
     */
    private function makeRequest(string $url, int $retryCount = 0): ?string
    {
        try {
            $response = $this->client->get($url, $this->getRequestOptions());

            if ($response->getStatusCode() === 200) {
                return $response->getBody()->getContents();
            }

            if ($response->getStatusCode() === 429 && $retryCount < $this->maxRetries) {
                // Rate limited, wait longer and retry
                sleep(5 + $retryCount * 2);
                return $this->makeRequest($url, $retryCount + 1);
            }

            Log::warning("HTTP {$response->getStatusCode()} response for URL: {$url}");
            return null;
        } catch (RequestException $e) {
            if ($retryCount < $this->maxRetries) {
                Log::warning("Request failed, retrying ({$retryCount}/{$this->maxRetries}): " . $e->getMessage());
                sleep(2 + $retryCount);
                return $this->makeRequest($url, $retryCount + 1);
            }

            Log::error('Request failed after retries: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Scrape product from Amazon
     */
    public function scrapeAmazonProduct(string $url): ?array
    {
        $html = $this->makeRequest($url);
        if (!$html) {
            return null;
        }

        try {
            $crawler = new Crawler($html);

            // Amazon-specific selectors (updated for current layout)
            $title = $this->extractText($crawler, '#productTitle, .product-title, h1.a-size-large, h1#title');
            $price = $this->extractPrice($crawler, '.a-price-whole, .a-price .a-offscreen, .a-price-range, span.a-price.a-text-price.a-size-medium.apexPriceToPay, .a-price.a-text-price.a-size-medium.apexPriceToPay .a-offscreen');
            $image = $this->extractImage($crawler, '#landingImage, .a-dynamic-image, #imgBlkFront, img[data-a-dynamic-image]', $url);

            // Additional data extraction
            $description = $this->extractText($crawler, '#feature-bullets ul, .a-unordered-list.a-vertical.a-spacing-mini, .product-description');
            $rating = $this->extractText($crawler, '.a-icon-alt, span.a-icon-alt, [data-hook="average-star-rating"] .a-icon-alt');

            if ($title && $price) {
                $productData = [
                    'title' => trim($title),
                    'price' => $this->parsePrice($price),
                    'image_url' => $image,
                    'url' => $url,
                    'source' => 'Amazon',
                ];

                if ($description) {
                    $productData['description'] = trim(substr($description, 0, 500));
                }

                return $productData;
            }

            Log::warning('Amazon product data incomplete', ['title' => $title, 'price' => $price]);
            return null;
        } catch (\Exception $e) {
            Log::error('Amazon scraping failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Scrape product from Jumia
     */
    public function scrapeJumiaProduct(string $url): ?array
    {
        $html = $this->makeRequest($url);
        if (!$html) {
            return null;
        }

        try {
            $crawler = new Crawler($html);

            // Jumia-specific selectors
            $title = $this->extractText($crawler, 'h1.-fs20.-pts.-pbxs, .name, .product-name, h1');
            $price = $this->extractPrice($crawler, '.-b.-ltr.-tal.-fs24.-prxs, .price, .-prc, .special-price, span.-tal.-gy5');
            $image = $this->extractImage($crawler, '.-df.-i-ctr.img._img, .gallery img, .product-image img, img', $url);

            if ($title && $price) {
                return [
                    'title' => trim($title),
                    'price' => $this->parsePrice($price),
                    'image_url' => $image,
                    'url' => $url,
                    'source' => 'Jumia',
                ];
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Jumia scraping failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Scrape product from eBay
     */
    public function scrapeEbayProduct(string $url): ?array
    {
        $html = $this->makeRequest($url);
        if (!$html) {
            return null;
        }

        try {
            $crawler = new Crawler($html);

            $title = $this->extractText($crawler, '#x-title-label-lbl, .notranslate, h1#title, .x-item-title-label h1');
            $price = $this->extractPrice($crawler, '.notranslate .price, .notranslate.primary, #prcIsum, .u-flL.condText');
            $image = $this->extractImage($crawler, '#icImg, .img-zoom-wrap img, .vi-image img', $url);

            if ($title && $price) {
                return [
                    'title' => trim($title),
                    'price' => $this->parsePrice($price),
                    'image_url' => $image,
                    'url' => $url,
                    'source' => 'eBay',
                ];
            }

            return null;
        } catch (\Exception $e) {
            Log::error('eBay scraping failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generic product scraper for other e-commerce sites
     */
    public function scrapeGenericProduct(string $url): ?array
    {
        $html = $this->makeRequest($url);
        if (!$html) {
            return null;
        }

        try {
            $crawler = new Crawler($html);

            // Generic selectors for common e-commerce patterns
            $title = $this->extractText($crawler, 'h1, .product-title, .title, [class*="title"], [class*="name"], .product-name, .item-title');
            $price = $this->extractPrice($crawler, '[class*="price"], .cost, .amount, [data-price], .product-price, .item-price, .current-price');
            $image = $this->extractImage($crawler, '.product-image img, .main-image img, img[class*="product"], .item-image img, .gallery img:first-child', $url);

            if ($title && $price) {
                return [
                    'title' => trim($title),
                    'price' => $this->parsePrice($price),
                    'image_url' => $image,
                    'url' => $url,
                    'source' => 'Generic',
                ];
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Generic scraping failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract text from crawler using multiple selectors
     */
    private function extractText(Crawler $crawler, string $selectors): ?string
    {
        $selectorArray = explode(', ', $selectors);

        foreach ($selectorArray as $selector) {
            try {
                $element = $crawler->filter(trim($selector));
                if ($element->count() > 0) {
                    $text = $element->first()->text();
                    if (!empty(trim($text))) {
                        return $text;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * Extract price from crawler
     */
    private function extractPrice(Crawler $crawler, string $selectors): ?string
    {
        $text = $this->extractText($crawler, $selectors);

        if (!$text) {
            // Try to find price in data attributes
            $selectorArray = explode(', ', $selectors);
            foreach ($selectorArray as $selector) {
                try {
                    $element = $crawler->filter(trim($selector));
                    if ($element->count() > 0) {
                        $price = $element->first()->attr('data-price') ??
                            $element->first()->attr('content') ??
                            $element->first()->attr('value') ??
                            $element->first()->attr('data-value');
                        if ($price) return $price;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return $text;
    }

    /**
     * Extract image URL from crawler
     */
    private function extractImage(Crawler $crawler, string $selectors, string $baseUrl = null): ?string
    {
        $selectorArray = explode(', ', $selectors);

        foreach ($selectorArray as $selector) {
            try {
                $element = $crawler->filter(trim($selector));
                if ($element->count() > 0) {
                    $src = $element->first()->attr('src') ??
                        $element->first()->attr('data-src') ??
                        $element->first()->attr('data-lazy-src') ??
                        $element->first()->attr('data-original');

                    if ($src) {
                        // Handle relative URLs
                        if (strpos($src, 'http') !== 0 && $baseUrl) {
                            $parsedUrl = parse_url($baseUrl);
                            $baseUrlForImage = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
                            $src = $baseUrlForImage . (strpos($src, '/') === 0 ? '' : '/') . $src;
                        }

                        if (filter_var($src, FILTER_VALIDATE_URL)) {
                            return $src;
                        }
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * Parse price string to decimal
     */
    private function parsePrice(string $priceString): float
    {
        // Remove currency symbols and extra text
        $cleaned = preg_replace('/[^\d.,\-]/', '', $priceString);

        // Handle negative prices (shouldn't happen but just in case)
        $isNegative = strpos($cleaned, '-') !== false;
        $cleaned = str_replace('-', '', $cleaned);

        // Handle different decimal separators
        if (strpos($cleaned, ',') !== false && strpos($cleaned, '.') !== false) {
            // Both comma and dot present
            if (strrpos($cleaned, ',') > strrpos($cleaned, '.')) {
                // Comma is decimal separator (European format)
                $cleaned = str_replace('.', '', $cleaned);
                $cleaned = str_replace(',', '.', $cleaned);
            } else {
                // Dot is decimal separator (US format)
                $cleaned = str_replace(',', '', $cleaned);
            }
        } elseif (strpos($cleaned, ',') !== false) {
            // Only comma present
            $commaPos = strrpos($cleaned, ',');
            $afterComma = substr($cleaned, $commaPos + 1);

            // If 2 digits after comma, it's likely decimal separator
            if (strlen($afterComma) <= 2) {
                $cleaned = str_replace(',', '.', $cleaned);
            } else {
                $cleaned = str_replace(',', '', $cleaned);
            }
        }

        $price = (float) $cleaned;
        return $isNegative ? -$price : $price;
    }

    /**
     * Determine scraping strategy based on URL and scrape product
     */
    public function scrapeAndStore(string $url): bool
    {
        sleep($this->requestDelay); // Rate limiting

        $productData = null;

        // Determine the scraping strategy based on URL
        if (str_contains($url, 'amazon.')) {
            $productData = $this->scrapeAmazonProduct($url);
        } elseif (str_contains($url, 'jumia.')) {
            $productData = $this->scrapeJumiaProduct($url);
        } elseif (str_contains($url, 'ebay.')) {
            $productData = $this->scrapeEbayProduct($url);
        } else {
            $productData = $this->scrapeGenericProduct($url);
        }

        if ($productData) {
            try {
                // Check if product already exists by URL to avoid duplicates
                $existingProduct = Product::where('url', $url)->first();

                if ($existingProduct) {
                    // Update existing product
                    $existingProduct->update($productData);
                    Log::info('Product updated successfully', $productData);
                } else {
                    // Create new product
                    Product::create($productData);
                    Log::info('Product scraped and stored successfully', $productData);
                }

                return true;
            } catch (\Exception $e) {
                Log::error('Failed to store product: ' . $e->getMessage(), [
                    'url' => $url,
                    'data' => $productData
                ]);
                return false;
            }
        }

        Log::warning('Failed to scrape product from: ' . $url);
        return false;
    }

    /**
     * Batch scrape multiple URLs with improved error handling
     */
    public function batchScrape(array $urls): array
    {
        $results = [];
        $total = count($urls);
        $processed = 0;

        Log::info("Starting batch scrape of {$total} URLs");

        foreach ($urls as $url) {
            $processed++;
            Log::info("Processing URL {$processed}/{$total}: {$url}");

            try {
                $results[$url] = $this->scrapeAndStore($url);
            } catch (\Exception $e) {
                Log::error("Batch scrape failed for URL {$url}: " . $e->getMessage());
                $results[$url] = false;
            }

            // Progress logging
            if ($processed % 10 === 0 || $processed === $total) {
                $successCount = array_sum($results);
                Log::info("Batch scrape progress: {$processed}/{$total} processed, {$successCount} successful");
            }
        }

        $successCount = array_sum($results);
        Log::info("Batch scrape completed: {$successCount}/{$total} successful");

        return $results;
    }
}
