# Amazon Product Scraping API Demo

## Available API Endpoints

### 1. Scrape a Single Product
```bash
curl -X POST http://localhost:8000/api/products/scrape \
  -H "Content-Type: application/json" \
  -d '{"url": "https://www.amazon.com/dp/PRODUCT_ID"}'
```

### 2. Batch Scrape Multiple Products
```bash
curl -X POST http://localhost:8000/api/products/batch-scrape \
  -H "Content-Type: application/json" \
  -d '{
    "urls": [
      "https://www.amazon.com/dp/PRODUCT_ID_1",
      "https://www.amazon.com/dp/PRODUCT_ID_2",
      "https://www.amazon.com/dp/PRODUCT_ID_3"
    ]
  }'
```

### 3. Get All Products
```bash
curl -X GET http://localhost:8000/api/products
```

### 4. Get Latest Products
```bash
curl -X GET http://localhost:8000/api/products/latest
```

### 5. Get Product Statistics
```bash
curl -X GET http://localhost:8000/api/products/stats
```

## Example Amazon URLs to Test With:

**Note**: Replace these with actual Amazon product URLs for testing:

- Electronics: `https://www.amazon.com/dp/B08N5WRWNW`
- Books: `https://www.amazon.com/dp/B07XJ8C8F5`
- Home & Kitchen: `https://www.amazon.com/dp/B08F7PTF53`

## Response Examples:

### Successful Scrape Response:
```json
{
  "success": true,
  "message": "Product scraped and stored successfully"
}
```

### Batch Scrape Response:
```json
{
  "success": true,
  "message": "Scraping completed: 2/3 successful",
  "results": {
    "https://www.amazon.com/dp/B08N5WRWNW": true,
    "https://www.amazon.com/dp/B07XJ8C8F5": true,
    "https://www.amazon.com/dp/B08F7PTF53": false
  }
}
```

## Using JavaScript/Frontend:

```javascript
// Scrape a single Amazon product
async function scrapeAmazonProduct(url) {
  try {
    const response = await fetch('/api/products/scrape', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify({ url: url })
    });
    
    const result = await response.json();
    
    if (result.success) {
      console.log('Product scraped successfully!');
    } else {
      console.error('Scraping failed:', result.message);
    }
  } catch (error) {
    console.error('Error:', error);
  }
}

// Usage
scrapeAmazonProduct('https://www.amazon.com/dp/PRODUCT_ID');
``` 
