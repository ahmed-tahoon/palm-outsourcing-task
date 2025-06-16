<?php

namespace App\Console\Commands;

use App\Services\ScrapingService;
use Illuminate\Console\Command;

class TestScraping extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:scraping
                            {url? : Single URL to test scraping}
                            {--demo : Run demo with sample URLs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the scraping service functionality';

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
        $this->info('🚀 Testing Web Scraping Service');
        $this->line('');

        if ($this->option('demo')) {
            $this->runDemo();
            return Command::SUCCESS;
        }

        $url = $this->argument('url');

        if (!$url) {
            $url = $this->ask('Enter a product URL to scrape');
        }

        if (!$url) {
            $this->error('No URL provided');
            return Command::FAILURE;
        }

        return $this->testSingleUrl($url);
    }

    private function runDemo()
    {
        $this->info('Running demo with sample URLs...');
        $this->line('');

        // Sample URLs for testing (Note: These are examples - actual URLs may change)
        $demoUrls = [
            // You can add actual product URLs here for testing
            'https://httpbin.org/html', // Test URL that returns HTML
        ];

        $this->warn('⚠️  Demo mode: Using test URLs');
        $this->warn('Add real product URLs to the demo array in the command for actual testing');
        $this->line('');

        foreach ($demoUrls as $index => $url) {
            $this->info("Testing URL " . ($index + 1) . ": {$url}");
            $this->testSingleUrl($url);
            $this->line('');
        }
    }

    private function testSingleUrl(string $url): int
    {
        $this->info("Testing URL: {$url}");
        $this->line('');

        // Show user agent rotation
        $this->comment('🔄 User Agent Rotation Test:');
        $reflection = new \ReflectionClass($this->scrapingService);
        $method = $reflection->getMethod('getRandomUserAgent');
        $method->setAccessible(true);

        for ($i = 0; $i < 3; $i++) {
            $userAgent = $method->invoke($this->scrapingService);
            $this->line('  ' . ($i + 1) . '. ' . substr($userAgent, 0, 80) . '...');
        }
        $this->line('');

        // Test the scraping
        $this->comment('🕷️  Starting scrape...');

        $startTime = microtime(true);
        $success = $this->scrapingService->scrapeAndStore($url);
        $endTime = microtime(true);

        $duration = round($endTime - $startTime, 2);

        if ($success) {
            $this->info("✅ Scraping successful! Duration: {$duration}s");

            // Show the latest scraped product
            $latestProduct = \App\Models\Product::latest()->first();
            if ($latestProduct) {
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Title', $latestProduct->title ?? 'N/A'],
                        ['Price', $latestProduct->price ?? 'N/A'],
                        ['Source', $latestProduct->source ?? 'N/A'],
                        ['URL', $latestProduct->url ?? 'N/A'],
                        ['Image URL', $latestProduct->image_url ? substr($latestProduct->image_url, 0, 50) . '...' : 'N/A'],
                    ]
                );
            }

            return Command::SUCCESS;
        } else {
            $this->error("❌ Scraping failed! Duration: {$duration}s");
            $this->warn("Check the logs for more details");
            return Command::FAILURE;
        }
    }
}
