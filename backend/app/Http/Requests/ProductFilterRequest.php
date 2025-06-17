<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\DTO\ProductResponseDTO;

/**
 * Product Filter Request Validation
 * Follows Single Responsibility Principle - only handles product filter validation
 * Implements responsive validation for product listing and search
 */
class ProductFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'page' => 'sometimes|integer|min:1|max:1000',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'price_min' => 'sometimes|numeric|min:0|max:999999.99',
            'price_max' => 'sometimes|numeric|min:0|max:999999.99|gte:price_min',
            'brand' => 'sometimes|string|max:100|alpha_dash',
            'category' => 'sometimes|string|max:100|alpha_dash',
            'source' => 'sometimes|string|in:amazon,ebay,jumia,manual',
            'availability' => 'sometimes|string|in:in_stock,out_of_stock,limited,unknown',
            'sort_by' => 'sometimes|string|in:created_at,price,title,brand,updated_at',
            'sort_order' => 'sometimes|string|in:asc,desc',
            'search' => 'sometimes|string|min:2|max:200',
            'tags' => 'sometimes|array|max:10',
            'tags.*' => 'string|max:50|alpha_dash',
            'date_from' => 'sometimes|date|before_or_equal:date_to',
            'date_to' => 'sometimes|date|after_or_equal:date_from|before_or_equal:today',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'page.max' => 'Page number cannot exceed 1000.',
            'per_page.max' => 'Items per page cannot exceed 100.',
            'price_min.numeric' => 'Minimum price must be a valid number.',
            'price_max.numeric' => 'Maximum price must be a valid number.',
            'price_max.gte' => 'Maximum price must be greater than or equal to minimum price.',
            'brand.alpha_dash' => 'Brand can only contain letters, numbers, dashes and underscores.',
            'category.alpha_dash' => 'Category can only contain letters, numbers, dashes and underscores.',
            'source.in' => 'Source must be one of: amazon, ebay, jumia, manual.',
            'availability.in' => 'Availability must be one of: in_stock, out_of_stock, limited, unknown.',
            'sort_by.in' => 'Sort field must be one of: created_at, price, title, brand, updated_at.',
            'sort_order.in' => 'Sort order must be either asc or desc.',
            'search.min' => 'Search query must be at least 2 characters.',
            'search.max' => 'Search query cannot exceed 200 characters.',
            'tags.max' => 'Cannot have more than 10 tags.',
            'tags.*.alpha_dash' => 'Tags can only contain letters, numbers, dashes and underscores.',
            'date_from.before_or_equal' => 'Start date must be before or equal to end date.',
            'date_to.after_or_equal' => 'End date must be after or equal to start date.',
            'date_to.before_or_equal' => 'End date cannot be in the future.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'per_page' => 'items per page',
            'price_min' => 'minimum price',
            'price_max' => 'maximum price',
            'sort_by' => 'sort field',
            'sort_order' => 'sort direction',
            'date_from' => 'start date',
            'date_to' => 'end date',
        ];
    }

    /**
     * Configure the validator instance with custom rules.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            // Validate price range logic
            $priceMin = $this->input('price_min');
            $priceMax = $this->input('price_max');

            if ($priceMin && $priceMax && $priceMin > $priceMax) {
                $validator->errors()->add('price_range', 'Price range is invalid: minimum cannot be greater than maximum.');
            }

            // Validate reasonable price range
            if ($priceMin && $priceMax && ($priceMax - $priceMin) > 999999) {
                $validator->errors()->add('price_range', 'Price range is too wide. Please narrow your search.');
            }

            // Validate date range logic
            $dateFrom = $this->input('date_from');
            $dateTo = $this->input('date_to');

            if ($dateFrom && $dateTo) {
                $from = \Carbon\Carbon::parse($dateFrom);
                $to = \Carbon\Carbon::parse($dateTo);

                if ($from->diffInDays($to) > 365) {
                    $validator->errors()->add('date_range', 'Date range cannot exceed 365 days.');
                }
            }

            // Validate search with filters combination
            $search = $this->input('search');
            $brand = $this->input('brand');
            $category = $this->input('category');

            if ($search && strlen($search) < 2 && !$brand && !$category) {
                $validator->errors()->add('filters', 'Please provide either a search term (min 2 characters) or select a brand/category.');
            }
        });
    }

    /**
     * Handle a failed validation attempt with responsive error formatting.
     */
    protected function failedValidation(Validator $validator): void
    {
        $response = ProductResponseDTO::error(
            error: 'Invalid filter parameters',
            statusCode: 422,
            metadata: [
                'validation_errors' => $validator->errors()->toArray(),
                'failed_rules' => $this->getFailedRules($validator),
                'allowed_values' => $this->getAllowedValues(),
                'timestamp' => now()->toISOString(),
            ]
        );

        throw new HttpResponseException(
            response()->json($response->toArray(), $response->getStatusCode())
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Validation-Errors' => count($validator->errors()),
                    'X-Filter-Help' => 'Check allowed_values in metadata for valid options',
                ])
        );
    }

    /**
     * Get sanitized and validated filters
     */
    public function getFilters(): array
    {
        $validated = $this->validated();

        // Remove pagination params from filters
        $filters = collect($validated)->except(['page', 'per_page', 'sort_by', 'sort_order'])->toArray();

        // Clean empty values
        return array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });
    }

    /**
     * Get pagination parameters
     */
    public function getPaginationParams(): array
    {
        return [
            'page' => $this->input('page', 1),
            'per_page' => $this->input('per_page', 20),
        ];
    }

    /**
     * Get sorting parameters
     */
    public function getSortParams(): array
    {
        return [
            'sort_by' => $this->input('sort_by', 'created_at'),
            'sort_order' => $this->input('sort_order', 'desc'),
        ];
    }

    /**
     * Get failed validation rules for debugging
     */
    private function getFailedRules(Validator $validator): array
    {
        $failedRules = [];
        foreach ($validator->failed() as $field => $rules) {
            $failedRules[$field] = array_keys($rules);
        }
        return $failedRules;
    }

    /**
     * Get allowed values for dropdowns and selections
     */
    private function getAllowedValues(): array
    {
        return [
            'source' => ['amazon', 'ebay', 'jumia', 'manual'],
            'availability' => ['in_stock', 'out_of_stock', 'limited', 'unknown'],
            'sort_by' => ['created_at', 'price', 'title', 'brand', 'updated_at'],
            'sort_order' => ['asc', 'desc'],
            'per_page_options' => [10, 20, 50, 100],
        ];
    }

    /**
     * Prepare input for validation with sanitization
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            // Sanitize search query
            'search' => $this->input('search') ? trim(strip_tags($this->input('search'))) : null,

            // Normalize brand and category
            'brand' => $this->input('brand') ? strtolower(trim($this->input('brand'))) : null,
            'category' => $this->input('category') ? strtolower(trim($this->input('category'))) : null,

            // Ensure numeric values
            'price_min' => $this->input('price_min') ? (float) $this->input('price_min') : null,
            'price_max' => $this->input('price_max') ? (float) $this->input('price_max') : null,
            'page' => $this->input('page') ? (int) $this->input('page') : 1,
            'per_page' => $this->input('per_page') ? (int) $this->input('per_page') : 20,

            // Normalize sort parameters
            'sort_by' => $this->input('sort_by') ? strtolower($this->input('sort_by')) : 'created_at',
            'sort_order' => $this->input('sort_order') ? strtolower($this->input('sort_order')) : 'desc',

            // Clean tags array
            'tags' => $this->input('tags') ? array_filter(array_map('trim', $this->input('tags'))) : null,
        ]);
    }

    /**
     * Check if this is a search request
     */
    public function isSearchRequest(): bool
    {
        return !empty($this->input('search'));
    }

    /**
     * Check if filters are applied
     */
    public function hasFilters(): bool
    {
        $filters = $this->getFilters();
        return !empty($filters);
    }

    /**
     * Get cache key for this filter combination
     */
    public function getCacheKey(): string
    {
        $key = collect($this->validated())
            ->except(['page']) // Exclude page from cache key for better hit rate
            ->sortKeys()
            ->toArray();

        return 'products_' . md5(serialize($key));
    }
}
