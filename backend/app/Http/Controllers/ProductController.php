<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ScrapingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    private ScrapingService $scrapingService;

    public function __construct(ScrapingService $scrapingService)
    {
        $this->scrapingService = $scrapingService;
    }

    /**
     * Display a listing of all products
     */
    public function index(): JsonResponse
    {
        try {
            $products = Product::orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $products->items(),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch products: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch products'
            ], 500);
        }
    }
    public function scrape(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid URL provided',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $url = $request->input('url');
            $success = $this->scrapingService->scrapeAndStore($url);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Product scraped and stored successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to scrape product from the provided URL'
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Scraping failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while scraping'
            ], 500);
        }
    }
}
