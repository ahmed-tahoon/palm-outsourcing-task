<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\ProductServiceInterface;
use App\Contracts\ResponseFormatterInterface;
use App\Services\ProductService;
use App\Services\Response\AdaptiveResponseFormatter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind interfaces to implementations (Dependency Inversion Principle)
        $this->app->bind(ResponseFormatterInterface::class, AdaptiveResponseFormatter::class);

        // Bind ProductService to its interface
        $this->app->bind(ProductServiceInterface::class, ProductService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
