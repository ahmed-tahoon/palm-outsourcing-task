<?php

namespace App\Console\Commands;

use App\Services\ScrapingService;
use App\Models\Product;
use Illuminate\Console\Command;

class ScrapeAmazon extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:amazon
                            {url? : Amazon product URL to scrape}
                            {--batch : Run batch scraping of multiple URLs}
                            {--list : List all scraped Amazon products}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrape products from Amazon and store them in the database';

    private ScrapingService $scrapingService;

    public function __construct(ScrapingService $scrapingService)
    {
        parent::__construct();
        $this->scrapingService = $scrapingService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🛒 Amazon Product Scraping Service');
        $this->line('');

        if ($this->option('list')) {
            return $this->listProducts();
        }

        if ($this->option('batch')) {
            return $this->batchScrape();
        }

        $url = $this->argument('url');

        if (!$url) {
            $url = $this->ask('Enter an Amazon product URL to scrape');
        }

        if (!$url) {
            $this->error('No URL provided');
            return Command::FAILURE;
        }

        if (!str_contains($url, 'amazon.')) {
            $this->error('Please provide a valid Amazon product URL');
            return Command::FAILURE;
        }

        return $this->scrapeSingleProduct($url);
    }

    private function scrapeSingleProduct(string $url): int
    {
        $this->info("🕷️  Scraping Amazon product: {$url}");
        $this->line('');

        $startTime = microtime(true);
        $success = $this->scrapingService->scrapeAndStore($url);
        $endTime = microtime(true);

        $duration = round($endTime - $startTime, 2);

        if ($success) {
            $this->info("✅ Product scraped successfully! Duration: {$duration}s");

            // Show the scraped product
            $product = Product::where('url', $url)->first();
            if ($product) {
                $this->showProductDetails($product);
            }

            return Command::SUCCESS;
        } else {
            $this->error("❌ Failed to scrape product! Duration: {$duration}s");
            $this->warn("Check the logs for more details:");
            $this->warn("tail -f storage/logs/laravel.log");
            return Command::FAILURE;
        }
    }

    private function batchScrape(): int
    {
        $this->info('🔄 Batch scraping Amazon products...');
        $this->line('');

        // Example Amazon URLs (replace with real product URLs)
        $amazonUrls = [
            // Add real Amazon product URLs here for testing
            // Example: 'https://www.amazon.com/dp/B08N5WRWNW',
            // Example: 'https://www.amazon.com/dp/B07XJ8C8F5',
        ];

        if (empty($amazonUrls)) {
            $this->warn('⚠️  No URLs configured for batch scraping.');
            $this->info('Add Amazon product URLs to the $amazonUrls array in this command.');

            if ($this->confirm('Would you like to add URLs manually?')) {
                $amazonUrls = [];
                while (true) {
                    $url = $this->ask('Enter an Amazon product URL (or press Enter to finish)');
                    if (empty($url)) {
                        break;
                    }
                    if (str_contains($url, 'amazon.')) {
                        $amazonUrls[] = $url;
                        $this->info("✅ Added: {$url}");
                    } else {
                        $this->error("❌ Invalid Amazon URL: {$url}");
                    }
                }
            }

            if (empty($amazonUrls)) {
                return Command::FAILURE;
            }
        }

        $this->info("Starting batch scrape of " . count($amazonUrls) . " Amazon URLs...");
        $this->line('');

        $progressBar = $this->output->createProgressBar(count($amazonUrls));
        $progressBar->start();

        $results = [];
        foreach ($amazonUrls as $url) {
            $results[$url] = $this->scrapingService->scrapeAndStore($url);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->line('');
        $this->line('');

        // Show results
        $successCount = array_sum($results);
        $totalCount = count($results);

        $this->info("🎉 Batch scraping completed!");
        $this->table(
            ['Status', 'Count'],
            [
                ['Successful', $successCount],
                ['Failed', $totalCount - $successCount],
                ['Total', $totalCount],
            ]
        );

        return $successCount > 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function listProducts(): int
    {
        $products = Product::where('source', 'Amazon')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        if ($products->isEmpty()) {
            $this->warn('No Amazon products found in the database.');
            $this->info('Run: php artisan scrape:amazon <amazon-url> to scrape some products first.');
            return Command::SUCCESS;
        }

        $this->info("📋 Latest Amazon products (showing " . $products->count() . "):");
        $this->line('');

        foreach ($products as $product) {
            $this->showProductDetails($product);
            $this->line('');
        }

        $totalCount = Product::where('source', 'Amazon')->count();
        $this->info("Total Amazon products in database: {$totalCount}");

        return Command::SUCCESS;
    }

    private function showProductDetails(Product $product): void
    {
        $this->table(
            ['Field', 'Value'],
            [
                ['ID', $product->id],
                ['Title', $this->truncate($product->title, 60)],
                ['Price', '$' . number_format($product->price, 2)],
                ['Source', $product->source],
                ['Created', $product->created_at->format('Y-m-d H:i:s')],
                ['URL', $this->truncate($product->url, 60)],
                ['Image', $product->image_url ? 'Yes' : 'No'],
                ['Description', $product->description ? $this->truncate($product->description, 40) : 'No'],
            ]
        );
    }

    private function truncate(string $text, int $length): string
    {
        return strlen($text) > $length ? substr($text, 0, $length - 3) . '...' : $text;
    }
}
