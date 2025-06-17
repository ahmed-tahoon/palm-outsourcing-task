<?php

namespace App\DTO;

/**
 * Data Transfer Object for scraping requests
 * Follows Single Responsibility Principle - only handles request data
 */
readonly class ScrapingRequestDTO
{
    public function __construct(
        public string $url,
        public ?string $strategy = null,
        public ?int $timeout = null,
        public ?string $userAgent = null,
        public ?string $proxy = null,
        public bool $async = false,
        public ?int $priority = null,
        public ?array $metadata = null
    ) {}

    /**
     * Create from request array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            url: $data['url'],
            strategy: $data['strategy'] ?? null,
            timeout: isset($data['timeout']) ? (int) $data['timeout'] : null,
            userAgent: $data['user_agent'] ?? null,
            proxy: $data['proxy'] ?? null,
            async: (bool) ($data['async'] ?? false),
            priority: isset($data['priority']) ? (int) $data['priority'] : null,
            metadata: $data['metadata'] ?? null
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'strategy' => $this->strategy,
            'timeout' => $this->timeout,
            'user_agent' => $this->userAgent,
            'proxy' => $this->proxy,
            'async' => $this->async,
            'priority' => $this->priority,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Validate the request data
     */
    public function isValid(): bool
    {
        return !empty($this->url) && filter_var($this->url, FILTER_VALIDATE_URL) !== false;
    }
}
