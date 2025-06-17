# 🛍️ Web Scraping Service

A comprehensive web scraping service built with Laravel (PHP) backend, Next.js (React) frontend, and Golang proxy management microservice. This system efficiently scrapes e-commerce product data with intelligent proxy rotation and modern user interface.

## 🏗️ System Architecture

### Core Components

1. **Laravel Backend (PHP)** - API server and web scraping engine
2. **Next.js Frontend (React)** - Modern responsive web interface
3. **Golang Proxy Service** - Dynamic proxy management and rotation
4. **MySQL Database** - Data persistence layer

### Architecture Flow

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Next.js UI    │    │  Laravel API    │    │ Golang Proxy   │
│   (Port 3000)   │───▶│  (Port 8000)    │───▶│ Service (8080)  │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         │                       ▼                       │
         │              ┌─────────────────┐              │
         │              │ MySQL Database  │              │
         │              │   (Port 3306)   │              │
         │              └─────────────────┘              │
         │                                                │
         └────────────── Auto-refresh every 30s ─────────┘
```

## ✨ Features

### Backend (Laravel)
- ✅ **Multi-site scraping** - Amazon, Jumia, and generic e-commerce sites
- ✅ **User-agent rotation** - Mimics different browsers and devices
- ✅ **Proxy integration** - Dynamic proxy rotation via Golang service
- ✅ **Rate limiting** - Configurable delays between requests
- ✅ **Robust error handling** - Graceful failure management
- ✅ **RESTful API** - Clean JSON endpoints
- ✅ **Artisan commands** - CLI tools for batch operations
- ✅ **Database logging** - Complete audit trail

### Frontend (Next.js)
- ✅ **Modern responsive design** - Mobile-first approach
- ✅ **Real-time updates** - Auto-refresh every 30 seconds
- ✅ **Beautiful UI/UX** - Professional design with Tailwind CSS
- ✅ **Loading states** - Smooth user experience
- ✅ **Error handling** - User-friendly error messages
- ✅ **Product grid layout** - Responsive card-based display

### Proxy Service (Golang)
- ✅ **Dynamic proxy rotation** - Intelligent load balancing
- ✅ **Health monitoring** - Automatic proxy failure detection
- ✅ **RESTful API** - Easy integration with Laravel
- ✅ **CORS support** - Cross-origin resource sharing
- ✅ **Statistics endpoint** - Monitoring and analytics
- ✅ **Auto-recovery** - Failed proxy re-enabling

## 🚀 Quick Start

### Prerequisites

- PHP 8.1+ with Composer
- Node.js 18+ with npm
- Golang 1.21+
- MySQL 8.0+
- Git

### 1. Clone Repository

```bash
git clone <repository-url>
cd web-scraping-service
```

### 2. Backend Setup (Laravel)

```bash
cd backend

# Install dependencies
composer install

# Environment configuration
cp .env.example .env
php artisan key:generate

# Database configuration (edit .env file)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=web_scraping
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Run migrations
php artisan migrate

# Start Laravel server
php artisan serve --port=8000
```

### 3. Frontend Setup (Next.js)

```bash
cd frontend

# Install dependencies
npm install

# Start development server
npm run dev
```

### 4. Proxy Service Setup (Golang)

```bash
cd proxy-service

# Initialize Go module
go mod init proxy-service

# Install dependencies
go mod tidy

# Run the service
go run main.go
```

### 5. Access Applications

- **Frontend**: http://localhost:3000/products
- **Laravel API**: http://localhost:8000/api/products
- **Proxy Service**: http://localhost:8080/health

## 📋 API Documentation

### Laravel Endpoints

#### Get Products
```http
GET /api/products
```
Returns the latest 50 products.

#### Get Paginated Products
```http
GET /api/products/paginated?page=1
```
Returns paginated product list.

#### Get Product Statistics
```http
GET /api/products/stats
```
Returns product statistics and analytics.

#### Scrape Single URL
```http
POST /api/scrape
Content-Type: application/json

{
    "url": "https://example.com/product"
}
```

#### Batch Scrape URLs
```http
POST /api/scrape/batch
Content-Type: application/json

{
    "urls": [
        "https://example.com/product1",
        "https://example.com/product2"
    ]
}
```

### Golang Proxy Endpoints

#### Get All Proxies
```http
GET /proxies
```

#### Get Random Proxy
```http
GET /api/v1/proxies/random
```

#### Get Proxy Statistics
```http
GET /api/v1/proxies/stats
```

#### Report Proxy Failure
```http
POST /api/v1/proxies/report-failure
Content-Type: application/json

{
    "proxy_url": "http://user:pass@proxy.com:8080"
}
```

## 🎯 Usage Examples

### Scraping via Artisan Command

```bash
# Scrape sample URLs
php artisan scrape:products

# Scrape specific URLs
php artisan scrape:products "https://amazon.com/dp/B123" "https://jumia.com/product"

# Scrape from file
echo "https://amazon.com/dp/B123" > urls.txt
php artisan scrape:products --file=urls.txt --limit=5 --delay=3
```

### Frontend Usage

1. Navigate to http://localhost:3000/products
2. View scraped products in responsive grid layout
3. Products auto-refresh every 30 seconds
4. Use manual refresh button for immediate updates

### Programmatic API Usage

```php
// Laravel/PHP
$response = Http::post('http://localhost:8000/api/scrape', [
    'url' => 'https://example.com/product'
]);

$products = Http::get('http://localhost:8000/api/products')->json();
```

```javascript
// JavaScript/Node.js
const response = await fetch('http://localhost:8000/api/products');
const data = await response.json();
console.log(data.data); // Products array
```

## 🛠️ Configuration

### Laravel Configuration

Edit `backend/.env`:

```env
# Scraping configuration
SCRAPING_DELAY=2
SCRAPING_TIMEOUT=30
SCRAPING_MAX_RETRIES=3

# Proxy service
PROXY_SERVICE_URL=http://localhost:8080

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=web_scraping
```

### Proxy Service Configuration

Edit `proxy-service/main.go`:

```go
// Add real proxy configurations
proxies := []Proxy{
    {
        Host: "your-proxy-host.com",
        Port: 8080,
        Username: "username",
        Password: "password",
        Protocol: "http",
    },
    // Add more proxies...
}
```

## 🏆 Advanced Features

### Custom Scrapers

Create custom scrapers by extending the `ScrapingService`:

```php
// app/Services/CustomScrapingService.php
class CustomScrapingService extends ScrapingService
{
    public function scrapeCustomSite(string $url): ?array
    {
        // Custom scraping logic
        return [
            'title' => $title,
            'price' => $price,
            'image_url' => $image,
        ];
    }
}
```

### Database Optimization

```sql
-- Add indexes for better performance
CREATE INDEX idx_products_created_at ON products(created_at);
CREATE INDEX idx_products_price ON products(price);
```

### Monitoring & Logging

```bash
# View Laravel logs
tail -f backend/storage/logs/laravel.log

# View scraping activity
php artisan log:clear
php artisan scrape:products --verbose
```

## 🔧 Troubleshooting

### Common Issues

#### 1. Connection Refused Errors
```bash
# Check if services are running
netstat -tulpn | grep :8000  # Laravel
netstat -tulpn | grep :8080  # Proxy service
netstat -tulpn | grep :3000  # Next.js
```

#### 2. Database Connection Issues
```bash
# Test MySQL connection
mysql -u username -p -h localhost
```

#### 3. Proxy Service Not Working
```bash
# Check Golang installation
go version

# Verify proxy service
curl http://localhost:8080/health
```

#### 4. CORS Issues
Add your domain to the CORS configuration in:
- Laravel: `config/cors.php`
- Golang: `main.go` CORS middleware

### Performance Optimization

1. **Database Indexing**: Add indexes on frequently queried columns
2. **Caching**: Implement Redis for API response caching
3. **Queue Processing**: Use Laravel queues for batch scraping
4. **Load Balancing**: Deploy multiple proxy service instances

## 📊 Monitoring & Analytics

### Key Metrics to Monitor

- Scraping success rate
- Response times
- Proxy health status
- Database performance
- Frontend user engagement

### Logging Strategy

```php
// Laravel structured logging
Log::info('Product scraped', [
    'url' => $url,
    'success' => $success,
    'duration' => $duration,
    'proxy_used' => $proxy,
]);
```

## 🚀 Production Deployment

### Docker Deployment

```dockerfile
# Dockerfile.laravel
FROM php:8.1-fpm
COPY . /var/www
RUN composer install --no-dev --optimize-autoloader
```

### Environment Variables

```env
# Production environment
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=production-db-host
```

### Security Considerations

1. **API Rate Limiting**: Implement request throttling
2. **Input Validation**: Sanitize all URLs and inputs
3. **Proxy Authentication**: Use authenticated proxies
4. **HTTPS**: Enable SSL/TLS in production
5. **Environment Variables**: Never commit sensitive data

## 🤝 Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open Pull Request

## 📝 License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details.

## 👥 Authors

- **Senior Full-Stack Developer** - *Initial work* - [YourUsername](https://github.com/yourusername)

## 🙏 Acknowledgments

- Laravel community for excellent documentation
- Next.js team for modern React framework
- Golang community for efficient proxy handling
- Tailwind CSS for beautiful styling
- Symfony DomCrawler for HTML parsing

---

## 📞 Support

For support, email your-email@example.com or create an issue in this repository.

**Built with ❤️ by a Senior Full-Stack Developer** #
