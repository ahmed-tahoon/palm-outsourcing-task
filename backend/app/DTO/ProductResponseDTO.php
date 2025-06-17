<?php

namespace App\DTO;

/**
 * Data Transfer Object for product responses
 * Follows Single Responsibility Principle - only handles response data
 */
readonly class ProductResponseDTO
{
    public function __construct(
        public bool $success,
        public ?array $data = null,
        public ?string $message = null,
        public ?string $error = null,
        public ?array $metadata = null,
        public ?int $statusCode = null
    ) {}

    /**
     * Create successful response
     */
    public static function success(array $data, string $message = 'Success', array $metadata = []): self
    {
        return new self(
            success: true,
            data: $data,
            message: $message,
            metadata: $metadata,
            statusCode: 200
        );
    }

    /**
     * Create error response
     */
    public static function error(string $error, int $statusCode = 400, array $metadata = []): self
    {
        return new self(
            success: false,
            error: $error,
            metadata: $metadata,
            statusCode: $statusCode
        );
    }

    /**
     * Create from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            success: $data['success'] ?? false,
            data: $data['data'] ?? null,
            message: $data['message'] ?? null,
            error: $data['error'] ?? null,
            metadata: $data['metadata'] ?? null,
            statusCode: $data['status_code'] ?? null
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        $response = [
            'success' => $this->success,
        ];

        if ($this->data !== null) {
            $response['data'] = $this->data;
        }

        if ($this->message !== null) {
            $response['message'] = $this->message;
        }

        if ($this->error !== null) {
            $response['error'] = $this->error;
        }

        if ($this->metadata !== null) {
            $response['metadata'] = $this->metadata;
        }

        return $response;
    }

    /**
     * Convert to JSON response array with proper HTTP status
     */
    public function toJsonResponse(): array
    {
        return [
            'data' => $this->toArray(),
            'status' => $this->statusCode ?? ($this->success ? 200 : 400)
        ];
    }

    /**
     * Check if response has data
     */
    public function hasData(): bool
    {
        return $this->data !== null && !empty($this->data);
    }

    /**
     * Get HTTP status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode ?? ($this->success ? 200 : 400);
    }
}
