# Amazon Proxy Service for Web Scraping

A lightweight Go microservice that provides Amazon-specific proxy rotation for web scraping applications.

## Features

- Amazon-optimized proxy pool management
- Random proxy selection for Amazon scraping
- RESTful API endpoints
- CORS enabled for Laravel/Next.js integration
- Lightweight and fast
- Multiple proxy types (HTTP, HTTPS, SOCKS5)

## Quick Start

1. **Install dependencies:**
   ```bash
   go mod tidy
   ```

2. **Run the service:**
   ```bash
   go run main.go
   ```

3. **Service will start on port 8080**

## API Endpoints

### Get Random Proxy
```
GET /proxy/random
GET /api/v1/proxy/random
```

**Response:**
```json
{
  "success": true,
  "proxy": "http://user1:pass1@proxy1.example.com:8080",
  "message": "Random proxy selected",
  "count": 1
}
```

### Get All Proxies
```
GET /proxies
GET /api/v1/proxies
```

**Response:**
```json
{
  "success": true,
  "proxies": [
    "http://amazon_user1:pass123@us-proxy.amazon-scraper.com:8080",
    "http://amazon_user2:pass456@rotating-proxy.amazon-data.com:3128",
    "socks5://amazon_user3:pass789@residential-proxy.amazon-tools.com:1080",
    "https://amazon_user4:pass101112@datacenter-proxy.amazon-api.com:8888",
    "http://amazon_mobile:mobile_pass@mobile-proxy.amazon-mobile.com:9050"
  ],
  "count": 5,
  "message": "Retrieved 5 proxies"
}
```

### Health Check
```
GET /health
GET /api/v1/health
```

**Response:**
```json
{
  "status": "healthy",
  "service": "proxy-service",
  "timestamp": "2024-01-15T10:30:00Z",
  "version": "1.0.0"
}
```

## Usage in Laravel for Amazon Scraping

In your Laravel scraping service, you can integrate this Amazon proxy service like this:

```php
<?php

class AmazonScrapingService
{
    private $proxyServiceUrl = 'http://localhost:8080';
    
    public function getRandomAmazonProxy()
    {
        $response = Http::get($this->proxyServiceUrl . '/proxy/random');
        
        if ($response->successful()) {
            $data = $response->json();
            return $data['proxy'] ?? null;
        }
        
        return null;
    }
    
    public function scrapeAmazonProduct($productUrl)
    {
        $proxy = $this->getRandomAmazonProxy();
        
        $response = Http::withOptions([
            'proxy' => $proxy,
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Accept-Encoding' => 'gzip, deflate',
                'Connection' => 'keep-alive',
            ]
        ])->get($productUrl);
        
        return $response->body();
    }
}
```

## Configuration

- **Port:** Default is 8080 (can be changed in main.go)
- **CORS Origins:** Configured for localhost:3000 (Next.js) and localhost:8000 (Laravel)
- **Amazon Proxies:** 5 Amazon-optimized proxies configured (replace with real Amazon-compatible proxies)

## Production Notes for Amazon Scraping

1. Replace the sample Amazon proxy configurations in `main.go` with real Amazon-compatible proxy servers
2. Use residential proxies for better success rates with Amazon
3. Rotate user agents along with proxies for better anonymity
4. Consider rate limiting to avoid detection
5. Monitor proxy performance and success rates specifically for Amazon endpoints

## Docker (Optional)

You can also run this in Docker:

```dockerfile
FROM golang:1.21-alpine AS builder
WORKDIR /app
COPY go.mod go.sum ./
RUN go mod download
COPY . .
RUN go build -o proxy-service

FROM alpine:latest
RUN apk --no-cache add ca-certificates
WORKDIR /root/
COPY --from=builder /app/proxy-service .
EXPOSE 8080
CMD ["./proxy-service"]
```

Build and run:
```bash
docker build -t proxy-service .
docker run -p 8080:8080 proxy-service
``` 