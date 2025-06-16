<?php

namespace App\Console\Commands;

use App\Services\ScrapingService;
use Illuminate\Console\Command;

class ScrapeProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:products
                            {urls?* : URLs to scrape (optional)}
                            {--file= : File containing URLs to scrape}
                            {--limit=10 : Maximum number of URLs to process}
                            {--delay=2 : Delay between requests in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrape products from e-commerce websites';

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
        $this->info('Starting product scraping...');

        $urls = $this->getUrlsToScrape();

        if (empty($urls)) {
            $this->error('No URLs provided. Use --help for usage information.');
            return Command::FAILURE;
        }

        $limit = (int) $this->option('limit');
        $urls = array_slice($urls, 0, $limit);

        $this->info("Processing " . count($urls) . " URLs...");

        $progressBar = $this->output->createProgressBar(count($urls));
        $progressBar->start();

        $successCount = 0;
        $failureCount = 0;

        foreach ($urls as $url) {
            $this->line('');
            $this->info("Scraping: " . $url);

            try {
                $success = $this->scrapingService->scrapeAndStore($url);

                if ($success) {
                    $successCount++;
                    $this->line('<info>✓ Success</info>');
                } else {
                    $failureCount++;
                    $this->line('<error>✗ Failed to extract product data</error>');
                }
            } catch (\Exception $e) {
                $failureCount++;
                $this->line('<error>✗ Error: ' . $e->getMessage() . '</error>');
            }

            $progressBar->advance();

            // Apply delay between requests
            $delay = (int) $this->option('delay');
            if ($delay > 0) {
                sleep($delay);
            }
        }

        $progressBar->finish();
        $this->line('');
        $this->line('');

        // Summary
        $this->info("Scraping completed!");
        $this->table(
            ['Status', 'Count'],
            [
                ['Successful', $successCount],
                ['Failed', $failureCount],
                ['Total', count($urls)],
            ]
        );

        return $successCount > 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Get URLs to scrape from various sources
     */
    private function getUrlsToScrape(): array
    {
        $urls = [];

        // Get URLs from command arguments
        $argumentUrls = $this->argument('urls');
        if (!empty($argumentUrls)) {
            $urls = array_merge($urls, $argumentUrls);
        }

        // Get URLs from file
        $file = $this->option('file');
        if ($file && file_exists($file)) {
            $fileUrls = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $urls = array_merge($urls, $fileUrls);
        }

        // If no URLs provided, use sample URLs for demonstration
        if (empty($urls)) {
            $urls = $this->getSampleUrls();
        }

        // Filter and validate URLs
        return array_filter($urls, function ($url) {
            return filter_var(trim($url), FILTER_VALIDATE_URL);
        });
    }

    /**
     * Get sample URLs for demonstration
     */
    private function getSampleUrls(): array
    {
        return [
            'https://www.amazon.com/dp/B08N5WRWNW',
            'https://www.jumia.com.eg/generic-wireless-bluetooth-headphones-black-46688502.html',
            // Add more sample URLs as needed
        ];
    }
}
