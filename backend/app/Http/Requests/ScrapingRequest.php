<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\DTO\ProductResponseDTO;

/**
 * Advanced Scraping Request Validation
 * Follows Single Responsibility Principle - only handles scraping request validation
 * Implements responsive validation with detailed error messages
 */
class ScrapingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Add authorization logic here if needed
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'url' => [
                'required',
                'url',
                'max:2048',
                'regex:/^https?:\/\/(www\.)?(amazon|ebay|jumia)\.[a-z.]+\/.+/i'
            ],
            'strategy' => 'sometimes|string|in:amazon,ebay,jumia,generic',
            'timeout' => 'sometimes|integer|min:5|max:120',
            'user_agent' => 'sometimes|string|max:500',
            'proxy' => 'sometimes|string|max:255',
            'async' => 'sometimes|boolean',
            'priority' => 'sometimes|integer|min:1|max:100',
            'metadata' => 'sometimes|array',
            'metadata.*' => 'string|max:1000',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'url.required' => 'A valid product URL is required.',
            'url.url' => 'Please provide a valid URL format.',
            'url.regex' => 'URL must be from a supported domain (Amazon, eBay, or Jumia).',
            'url.max' => 'URL cannot exceed 2048 characters.',
            'strategy.in' => 'Strategy must be one of: amazon, ebay, jumia, generic.',
            'timeout.min' => 'Timeout must be at least 5 seconds.',
            'timeout.max' => 'Timeout cannot exceed 120 seconds.',
            'user_agent.max' => 'User agent cannot exceed 500 characters.',
            'proxy.max' => 'Proxy URL cannot exceed 255 characters.',
            'priority.min' => 'Priority must be at least 1.',
            'priority.max' => 'Priority cannot exceed 100.',
            'metadata.*.max' => 'Metadata values cannot exceed 1000 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'url' => 'product URL',
            'user_agent' => 'user agent string',
            'proxy' => 'proxy configuration',
        ];
    }

    /**
     * Configure the validator instance with custom rules.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            // Custom validation for URL domain support
            $url = $this->input('url');
            if ($url && !$this->isSupportedDomain($url)) {
                $validator->errors()->add('url', 'This domain is not currently supported for scraping.');
            }

            // Validate proxy format if provided
            $proxy = $this->input('proxy');
            if ($proxy && !$this->isValidProxyFormat($proxy)) {
                $validator->errors()->add('proxy', 'Proxy must be in format: protocol://host:port or host:port');
            }

            // Validate strategy matches URL domain
            $strategy = $this->input('strategy');
            if ($strategy && $url && !$this->strategyMatchesDomain($strategy, $url)) {
                $validator->errors()->add('strategy', 'Selected strategy does not match the URL domain.');
            }
        });
    }

    /**
     * Handle a failed validation attempt with responsive error formatting.
     */
    protected function failedValidation(Validator $validator): void
    {
        $response = ProductResponseDTO::error(
            error: 'Validation failed',
            statusCode: 422,
            metadata: [
                'validation_errors' => $validator->errors()->toArray(),
                'failed_rules' => $this->getFailedRules($validator),
                'timestamp' => now()->toISOString(),
            ]
        );

        throw new HttpResponseException(
            response()->json($response->toArray(), $response->getStatusCode())
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Validation-Errors' => count($validator->errors()),
                ])
        );
    }

    /**
     * Get validated data as DTO
     */
    public function toDTO(): \App\DTO\ScrapingRequestDTO
    {
        return \App\DTO\ScrapingRequestDTO::fromArray($this->validated());
    }

    /**
     * Check if the domain is supported for scraping
     */
    private function isSupportedDomain(string $url): bool
    {
        $supportedDomains = [
            'amazon.com',
            'amazon.co.uk',
            'amazon.de',
            'amazon.fr',
            'amazon.it',
            'amazon.es',
            'amazon.ca',
            'amazon.com.au',
            'amazon.co.jp',
            'amazon.in',
            'ebay.com',
            'ebay.co.uk',
            'ebay.de',
            'ebay.fr',
            'ebay.it',
            'jumia.com',
            'jumia.ng',
            'jumia.ke',
            'jumia.egypt',
            'jumia.ma',
        ];

        $domain = parse_url($url, PHP_URL_HOST);
        $domain = preg_replace('/^www\./', '', $domain);

        foreach ($supportedDomains as $supportedDomain) {
            if (str_contains($domain, $supportedDomain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate proxy format
     */
    private function isValidProxyFormat(string $proxy): bool
    {
        // Support formats: protocol://host:port, host:port
        $patterns = [
            '/^https?:\/\/[\w\.-]+:\d+$/',  // http://host:port
            '/^[\w\.-]+:\d+$/',            // host:port
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $proxy)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if strategy matches the URL domain
     */
    private function strategyMatchesDomain(string $strategy, string $url): bool
    {
        $domain = parse_url($url, PHP_URL_HOST);
        $domain = preg_replace('/^www\./', '', $domain);

        $strategyDomains = [
            'amazon' => ['amazon.'],
            'ebay' => ['ebay.'],
            'jumia' => ['jumia.'],
            'generic' => [], // Generic can handle any domain
        ];

        if ($strategy === 'generic') {
            return true;
        }

        if (!isset($strategyDomains[$strategy])) {
            return false;
        }

        foreach ($strategyDomains[$strategy] as $strategyDomain) {
            if (str_contains($domain, $strategyDomain)) {
                return true;
            }
        }

        return false;
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
     * Prepare input for validation with sanitization
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            // Trim and sanitize URL
            'url' => filter_var(trim($this->input('url', '')), FILTER_SANITIZE_URL),

            // Normalize boolean values
            'async' => filter_var($this->input('async', false), FILTER_VALIDATE_BOOLEAN),

            // Ensure timeout is integer
            'timeout' => $this->input('timeout') ? (int) $this->input('timeout') : null,

            // Ensure priority is integer
            'priority' => $this->input('priority') ? (int) $this->input('priority') : null,
        ]);
    }
}
