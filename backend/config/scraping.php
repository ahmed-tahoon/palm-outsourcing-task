<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Scraping Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for the web scraping service
    |
    */

    'request_delay' => env('SCRAPING_REQUEST_DELAY', 2),
    'max_retries' => env('SCRAPING_MAX_RETRIES', 3),
    'timeout' => env('SCRAPING_TIMEOUT', 30),
    'connect_timeout' => env('SCRAPING_CONNECT_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Proxy Configuration
    |--------------------------------------------------------------------------
    |
    | List of proxy servers to rotate through. Can be HTTP, HTTPS, or SOCKS
    | Example: ['http://proxy1:8080', 'https://proxy2:8080']
    |
    */
    'proxies' => [
        // Add your proxy servers here when needed
        // 'http://your-proxy-server:8080',
        // 'https://your-proxy-server:8080',
    ],

    /*
    |--------------------------------------------------------------------------
    | User Agents
    |--------------------------------------------------------------------------
    |
    | Additional user agents to include in rotation
    |
    */
    'additional_user_agents' => [
        // Add custom user agents here
    ],

    /*
    |--------------------------------------------------------------------------
    | Site-specific Settings
    |--------------------------------------------------------------------------
    |
    | Custom settings for specific e-commerce sites
    |
    */
    'sites' => [
        'amazon' => [
            'enabled' => true,
            'rate_limit' => 3, // seconds between requests
            'selectors' => [
                'title' => '#productTitle, .product-title, h1.a-size-large, h1#title',
                'price' => '.a-price-whole, .a-price .a-offscreen, .a-price-range, span.a-price.a-text-price.a-size-medium.apexPriceToPay, .a-price.a-text-price.a-size-medium.apexPriceToPay .a-offscreen',
                'image' => '#landingImage, .a-dynamic-image, #imgBlkFront, img[data-a-dynamic-image]',
                'description' => '#feature-bullets ul, .a-unordered-list.a-vertical.a-spacing-mini, .product-description',
            ],
        ],

        'jumia' => [
            'enabled' => true,
            'rate_limit' => 2,
            'selectors' => [
                'title' => 'h1.-fs20.-pts.-pbxs, .name, .product-name, h1',
                'price' => '.-b.-ltr.-tal.-fs24.-prxs, .price, .-prc, .special-price, span.-tal.-gy5',
                'image' => '.-df.-i-ctr.img._img, .gallery img, .product-image img, img',
            ],
        ],

        'ebay' => [
            'enabled' => true,
            'rate_limit' => 2,
            'selectors' => [
                'title' => '#x-title-label-lbl, .notranslate, h1#title, .x-item-title-label h1',
                'price' => '.notranslate .price, .notranslate.primary, #prcIsum, .u-flL.condText',
                'image' => '#icImg, .img-zoom-wrap img, .vi-image img',
            ],
        ],
    ],
];
