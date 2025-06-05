# Torrent API for AnimeTimes.cc

A RESTful API that scrapes anime torrent data from AnimeTimes.cc and dynamically maps torrents to AniList entries for rich metadata enrichment using real-time API calls.

## Key Features

- 🔍 **Live Torrent Scraping**: Real-time extraction of torrent/magnet links from AnimeTimes.cc
- 🎯 **Dynamic AniList Mapping**: Live API integration with AniList GraphQL API for metadata
- 🧠 **Intelligent Title Matching**: Multi-strategy fuzzy matching with similarity algorithms  
- 📊 **Rate Limiting**: Respects AniList's 90 req/min limit and site scraping limits
- 💾 **Smart Caching**: 1-hour cache for performance without stale data
- 🐳 **Docker Ready**: Complete containerization with development and production configs
- 🔧 **WebTorrent Compatible**: Designed for streaming integration
- 🎮 **Interactive Testing**: Built-in web interface for testing all endpoints

## How AniList Mapping Works

The API **dynamically fetches AniList IDs** in real-time using multiple fallback strategies with **NO static database**:

### 1. Smart Title Cleaning
Removes quality markers (`1080p`, `[SubsPlease]`, `WEB-DL`, etc.) and extracts clean anime titles from complex torrent names.

### 2. Six-Strategy Search System
1. **Primary Clean Title**: `"[SubsPlease] Attack on Titan S04"` → `"Attack on Titan S04"`
2. **Base Title Only**: `"Attack on Titan S04"` → `"Attack on Titan"`
3. **Pre-Season Extraction**: Removes season indicators for broader matching
4. **Original Title Fallback**: Uses unmodified torrent title if cleaning fails
5. **Basic Pattern Extraction**: Extracts core title using regex patterns
6. **Japanese Title Detection**: Finds alternative titles in parentheses

### 3. Advanced Similarity Matching
- **Levenshtein Distance**: Character-by-character comparison
- **Jaro-Winkler Algorithm**: Phonetic and positional similarity
- **Multi-title Comparison**: Checks English, Romaji, and synonym variants
- **Season Boost**: +30% confidence for matching season numbers
- **Base Title Boost**: +20% confidence for exact base title matches

### 4. Real-time GraphQL Integration
Every mapping makes **live API calls** to AniList's GraphQL endpoint - no pre-built databases or static mappings.

## Quick Start

### 🐳 Docker (Recommended)

The easiest way to get started is with Docker:

```powershell
# Windows PowerShell
./docker.ps1 up

# Linux/macOS or cross-platform
docker compose up -d --build
```

**API**: http://localhost:8080  
**Test Interface**: http://localhost:8080/test.html

> 📖 **Detailed Docker Setup**: See [DOCKER.md](DOCKER.md) for complete Docker configuration, production deployment, and troubleshooting.

### 🔧 Manual Setup

Requirements: **PHP 7.4+** with `curl` and `dom` extensions

```bash
# Clone and setup
git clone <repository-url>
cd torrent-api

# Create required directories
mkdir -p cache logs
chmod 777 cache logs

# Configure web server to point to project root
# Test locally: php -S localhost:8000
```

**Test Interface**: http://localhost:8000/test.html

## API Endpoints

Base URL: `http://localhost:8080` (Docker) or `http://localhost:8000` (Manual)

### Core Endpoints

#### Health Check
```http
GET /health
```
Returns API status, version, and available endpoints.

**Response:**
```json
{
    "status": "healthy",
    "service": "Torrent API", 
    "version": "1.0.0",
    "endpoints": {...}
}
```

#### Latest Torrents
```http
GET /torrents?page=1&limit=25
```
Scrapes latest anime torrents from AnimeTimes.cc homepage.

**Parameters:**
- `page` (optional): Page number, default 1
- `limit` (optional): Results per page, default 25

#### Search Torrents  
```http
GET /search?q=attack+on+titan&page=1
```
Searches AnimeTimes.cc for specific anime torrents.

**Parameters:**
- `q` (required): Search query
- `page` (optional): Page number, default 1

#### Torrent Details
```http
GET /torrent/{torrent-id}
```
Gets full torrent details including magnet links and metadata.

### AniList Integration

#### Search AniList Database
```http
GET /anilist/search?q=attack+on+titan
```
Directly searches AniList database for anime information.

**Parameters:**
- `q` (required): Anime title to search

#### Map Torrent to AniList
```http
POST /anilist/map
Content-Type: application/json

{
    "torrent_title": "[SubsPlease] Attack on Titan S04 - 01 [1080p].mkv",
    "anilist_id": 16498
}
```

**Parameters:**
- `torrent_title` (required): Full torrent title to map
- `anilist_id` (optional): Specific AniList ID if known

**Live Mapping Process:**
1. Cleans torrent title: `"[SubsPlease] Attack on Titan S04 - 01 [1080p].mkv"` → `"Attack on Titan S04"`
2. Makes **live GraphQL call** to AniList API with cleaned title
3. Uses fuzzy matching against English/Romaji titles and synonyms
4. Applies season-aware confidence boosting
5. Returns best match with confidence score

### Stream Generation (Future)
```http
POST /stream/generate
Content-Type: application/json

{
    "magnet_link": "magnet:?xt=urn:btih:..."
}
```
Prepares torrent data for WebTorrent streaming integration.

{
    "magnet_link": "magnet:?xt=urn:btih:..."
}
```
Placeholder endpoint for WebTorrent streaming integration.

## Response Examples

### Successful Torrent Mapping
```json
{
    "success": true,
    "data": {
        "torrent_title": "[SubsPlease] Attack on Titan S04 - 01 [1080p].mkv",
        "anilist_match": {
            "id": 16498,
            "title": {
                "romaji": "Shingeki no Kyojin",
                "english": "Attack on Titan"
            },
            "episodes": 25,
            "year": 2013,
            "genres": ["Action", "Drama", "Fantasy"],
            "cover_image": {
                "large": "https://s4.anilist.co/file/anilistcdn/media/anime/cover/large/bx16498.jpg"
            },
            "description": "Centuries ago, mankind was slaughtered...",
            "score": 85,
            "popularity": 15000
        },
        "confidence": 0.95,
        "matched_at": "2025-01-14 12:30:00"
    }
}
```

### AniList Search Results
```json
{
    "success": true,
    "data": [
        {
            "id": 16498,
            "title": {
                "romaji": "Shingeki no Kyojin",
                "english": "Attack on Titan"
            },
            "format": "TV",
            "status": "FINISHED",
            "episodes": 25,
            "genres": ["Action", "Drama"],
            "year": 2013
        }
    ],
    "query": "attack on titan"
}
```

### Latest Torrents
```json
{
    "success": true,
    "data": [
        {
            "id": "solo-leveling-s02-ep01",
            "title": "[SubsPlease] Solo Leveling S02 - 01 [1080p].mkv",
            "url": "/torrent/solo-leveling-s02-ep01",
            "magnet": "magnet:?xt=urn:btih:...",
            "size": "1.2 GB",
            "seeders": 120,
            "leechers": 45,
            "uploaded": "2025-01-14 10:30:00"
        }
    ],
    "pagination": {
        "page": 1,
        "limit": 25,
        "total": 25
    }
}
```

### Error Response
```json
{
    "success": false,
    "error": "torrent_title is required"
}
```

### Torrent Details with Magnet Links
```json
{
    "success": true,
    "data": {
        "id": "attack-on-titan-s04-01",
        "title": "Attack on Titan S04 - 01",
        "url": "https://animetime.cc/anime/attack-on-titan-s04-01",
        "torrents": [
            {
                "type": "magnet",
                "url": "magnet:?xt=urn:btih:...",
                "quality": "1080p",
                "size": "1.4 GB"
            }
        ],
        "scraped_at": "2025-06-05 12:00:00"
    }
}
```

## Installation & Setup

### Prerequisites
- **PHP 7.4+** with extensions: `curl`, `dom`, `json`, `mbstring`
- **Docker** (recommended) or web server (Apache/Nginx)
- Internet connection for AniList API and AnimeTimes.cc scraping

### Environment Configuration

Copy and customize the configuration:

```bash
# Default config is in config.php - no .env file needed
# Key settings to modify:

// Rate limiting
define('SCRAPER_RATE_LIMIT', 60);    // requests per minute
define('ANILIST_RATE_LIMIT', 90);    // AniList API limit

// Caching
define('CACHE_DURATION', 3600);      // 1 hour in seconds

// Debug mode
define('DEBUG_MODE', true);          // Set false for production
define('LOG_ERRORS', true);
```

### Docker Setup (Recommended)

1. **Quick Start**:
   ```powershell
   # Windows PowerShell
   ./docker.ps1 up
   
   # Cross-platform
   docker compose up -d --build
   ```

2. **Production Deployment**:
   ```powershell
   docker compose -f docker-compose.prod.yml up -d
   ```

3. **Access Points**:
   - **API**: http://localhost:8080
   - **Test Interface**: http://localhost:8080/test.html

> 📖 **Complete Docker Guide**: See [DOCKER.md](DOCKER.md) for detailed Docker configuration, production deployment, and troubleshooting.

### Manual Setup

1. **Setup Directories**:
   ```powershell
   mkdir cache, logs
   # On Linux: chmod 755 cache logs
   ```

2. **Apache VirtualHost**:
   ```apache
   <VirtualHost *:80>
       DocumentRoot /path/to/torrent-api
       <Directory /path/to/torrent-api>
           AllowOverride All
           Require all granted
           
           # Enable CORS
           Header always set Access-Control-Allow-Origin "*"
           Header always set Access-Control-Allow-Methods "GET, POST, OPTIONS"
           Header always set Access-Control-Allow-Headers "Content-Type"
       </Directory>
   </VirtualHost>
   ```

3. **Test Installation**:
   ```powershell
   # Built-in PHP server for development
   php -S localhost:8000
   
   # Test API health
   curl http://localhost:8000/health
   ```

4. **Access Points**:
   - **API**: http://localhost:8000
   - **Test Interface**: http://localhost:8000/test.html

## Configuration

Key settings in `config.php`:

| Setting | Default | Description |
|---------|---------|-------------|
| `CACHE_DURATION` | 3600 | Cache lifetime in seconds |
| `ANILIST_RATE_LIMIT` | 90 | AniList API requests per minute |
| `SCRAPER_RATE_LIMIT` | 60 | Web scraping rate limit |
| `DEBUG_MODE` | true | Enable detailed error messages |
| `LOG_ERRORS` | true | Log errors to file |
| `USER_AGENT` | Custom | User agent for web requests |

### Advanced Configuration
```php
// Cache settings
define('CACHE_DIR', __DIR__ . '/cache');
define('CACHE_DURATION', 3600); // 1 hour

// API endpoints
define('ANILIST_API_URL', 'https://graphql.anilist.co');
define('ANIMETIME_BASE_URL', 'https://animetime.cc');

// Rate limiting
define('ANILIST_RATE_LIMIT', 90); // AniList allows 90 req/min
define('SCRAPER_RATE_LIMIT', 60); // Be respectful to AnimeTimes

// WebTorrent integration (future)
define('WEBTORRENT_ENABLED', true);
define('MAX_CONNECTIONS', 55);
```

## Development & Testing

### Interactive Testing
The project includes a comprehensive test interface:

```powershell
# Start the API
./docker.ps1 up
# OR: php -S localhost:8000

# Open test interface
start http://localhost:8080/test.html
```

**Test Interface Features:**
- ✅ Health check
- 🔍 Latest torrents scraping
- 🔎 Torrent search
- 📋 Torrent details with magnet links
- 🎯 AniList search
- 🧠 Torrent-to-AniList mapping

### Command-Line Testing

```powershell
# Test AniList mapping with debug output
php debug_mapping.php

# Test scraper functionality
php debug_scraper.php

# Test specific torrent title
$title = "[SubsPlease] Solo Leveling S02 - 01 [1080p].mkv"
php -r "
require 'config.php';
require 'mapping/AniListMapper.php';
$mapper = new AniListMapper();
$result = $mapper->mapTorrentToAniList('$title');
echo json_encode($result, JSON_PRETTY_PRINT);
"
```

### Docker Development Workflow

```powershell
# Development commands
./docker.ps1 up      # Start with live reloading
./docker.ps1 logs    # View container logs  
./docker.ps1 restart # Restart containers
./docker.ps1 down    # Stop all containers

# Production testing
docker compose -f docker-compose.prod.yml up -d
```

## Usage Examples

### Basic API Usage

```javascript
// Base URL for your API
const API_BASE = 'http://localhost:8080'; // Docker
// const API_BASE = 'http://localhost:8000'; // Manual setup

// Get latest anime torrents
const latestResponse = await fetch(`${API_BASE}/torrents?page=1&limit=10`);
const latest = await latestResponse.json();
console.log('Latest torrents:', latest.data);

// Search for specific anime
const searchResponse = await fetch(`${API_BASE}/search?q=Attack on Titan`);
const searchResults = await searchResponse.json();
console.log('Search results:', searchResults.data);

// Get detailed torrent info with magnet links
const torrentId = searchResults.data[0].id;
const detailsResponse = await fetch(`${API_BASE}/torrent/${torrentId}`);
const details = await detailsResponse.json();
console.log('Magnet link:', details.data.torrents[0].url);
```

### AniList Integration Example

```javascript
// Search AniList database directly
const anilistSearch = await fetch(`${API_BASE}/anilist/search?q=Solo Leveling`);
const anilistResults = await anilistSearch.json();

// Map a torrent title to AniList entry
const mappingResponse = await fetch(`${API_BASE}/anilist/map`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        torrent_title: '[SubsPlease] Solo Leveling S02 - 01 [1080p].mkv'
    })
});
const mapping = await mappingResponse.json();

if (mapping.success && mapping.data.anilist_match) {
    console.log('Matched AniList ID:', mapping.data.anilist_match.id);
    console.log('Confidence:', mapping.data.confidence);
    console.log('Cover image:', mapping.data.anilist_match.cover_image.large);
}
```

### Complete Streaming Integration

```javascript
async function getAnimeForStreaming(searchQuery) {
    try {
        // 1. Search for anime torrents
        const searchResponse = await fetch(`${API_BASE}/search?q=${encodeURIComponent(searchQuery)}`);
        const searchData = await searchResponse.json();
        
        if (!searchData.success || searchData.data.length === 0) {
            throw new Error('No torrents found');
        }
        
        // 2. Get the first torrent's details
        const torrent = searchData.data[0];
        const detailsResponse = await fetch(`${API_BASE}/torrent/${torrent.id}`);
        const details = await detailsResponse.json();
        
        // 3. Map to AniList for rich metadata
        const mappingResponse = await fetch(`${API_BASE}/anilist/map`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ torrent_title: torrent.title })
        });
        const mapping = await mappingResponse.json();
        
        // 4. Return combined data for streaming
        return {
            torrent: details.data,
            anilist: mapping.success ? mapping.data.anilist_match : null,
            confidence: mapping.success ? mapping.data.confidence : 0,
            magnetLink: details.data.torrents[0]?.url,
            ready: !!details.data.torrents[0]?.url
        };
        
    } catch (error) {
        console.error('Error getting anime data:', error);
        return null;
    }
}

// Usage
const animeData = await getAnimeForStreaming('Attack on Titan S04');
if (animeData?.ready) {
    // Use magnetLink with WebTorrent, Vidstack, or other streaming solution
    console.log('Ready to stream:', animeData.magnetLink);
    console.log('Anime metadata:', animeData.anilist);
}
```

### cURL Examples

```powershell
# Get health status
curl http://localhost:8080/health

# Search for anime
curl "http://localhost:8080/search?q=Demon+Slayer"

# Map torrent title to AniList
curl -X POST http://localhost:8080/anilist/map `
  -H "Content-Type: application/json" `
  -d '{"torrent_title": "[SubsPlease] Demon Slayer - 01 [1080p].mkv"}'

# Get latest torrents with pagination
curl "http://localhost:8080/torrents?page=1&limit=5"
```

## Response Format

All endpoints return JSON responses with the following structure:

### Success Response
```json
{
    "success": true,## Response Format

All endpoints return JSON responses with consistent structure:

### Success Response
```json
{
    "success": true,
    "data": {
        // Response data varies by endpoint
    },
    // Additional fields like "pagination", "query" for some endpoints
}
```

### Error Response
```json
{
    "success": false,
    "error": "Descriptive error message"
}
```

### HTTP Status Codes
- `200 OK`: Successful request
- `400 Bad Request`: Missing required parameters
- `404 Not Found`: Resource not found
- `405 Method Not Allowed`: Wrong HTTP method
- `429 Too Many Requests`: Rate limit exceeded
- `500 Internal Server Error`: Server error

## Data Structure

### Torrent Object
```json
{
    "id": "solo-leveling-s02-01",
    "title": "[SubsPlease] Solo Leveling S02 - 01 [1080p].mkv",
    "url": "https://animetime.cc/anime/solo-leveling-s02-01",
    "image": "https://animetime.cc/covers/solo-leveling.jpg",
    "type": "anime", 
    "status": "ongoing",
    "episodes": 12,
    "year": 2025,
    "genres": ["Action", "Adventure", "Fantasy"],
    "description": "Anime description from AnimeTimes",
    "torrents": [
        {
            "type": "magnet",
            "url": "magnet:?xt=urn:btih:abc123...",
            "quality": "1080p",
            "size": "1.2 GB",
            "seeders": 150,
            "leechers": 25
        }
    ],
    "scraped_at": "2025-06-05 12:00:00"
}
```

### AniList Object
```json
{
    "id": 140960,
    "title": {
        "romaji": "Ore dake Level Up na Ken Season 2",
        "english": "Solo Leveling Season 2",
        "native": "俺だけレベルアップな件 Season 2"
    },
    "format": "TV",
    "status": "RELEASING", 
    "episodes": 13,
    "season": "WINTER",
    "seasonYear": 2025,
    "year": 2025,
    "genres": ["Action", "Adventure", "Fantasy"],
    "studios": [{"name": "A-1 Pictures"}],
    "cover_image": {
        "large": "https://s4.anilist.co/file/anilistcdn/media/anime/cover/large/bx140960.jpg",
        "medium": "https://s4.anilist.co/file/anilistcdn/media/anime/cover/medium/bx140960.jpg"
    },
    "description": "The second season of Solo Leveling...",
    "score": 86,
    "popularity": 75000,
    "synonyms": ["Solo Leveling S2", "나 혼자만 레벨업 시즌 2"]
}
```

### Mapping Result Object
```json
{
    "torrent_title": "[SubsPlease] Solo Leveling S02 - 01 [1080p].mkv",
    "anilist_match": {
        // Full AniList object as shown above
    },
    "confidence": 0.95,
    "matched_at": "2025-06-05 14:30:00"
}
```

## Rate Limiting & Performance

### Rate Limits
- **AniList API**: 90 requests per minute (GraphQL endpoint)
- **Web Scraping**: 60 requests per minute (respectful to AnimeTimes.cc)
- **Caching**: 1-hour cache reduces external API calls

### Performance Tips
- Use caching effectively - repeated requests return cached results
- Batch multiple mappings when possible
- The test interface shows real response times
- Monitor rate limits in production

### Caching Details
```json
{
    "cache_keys": [
        "anilist_search_<md5_of_query>",
        "anilist_anime_<anilist_id>", 
        "scraper_latest_<page>",
        "scraper_search_<md5_of_query>_<page>"
    ],
    "cache_duration": "3600 seconds (1 hour)",
    "cache_location": "./cache/ directory"
}
```

## Error Handling & Debugging

### Common Errors

| Error | Cause | Solution |
|-------|-------|----------|
| `torrent_title is required` | Missing POST data | Include `torrent_title` in request body |
| `Query parameter "q" is required` | Missing search query | Add `?q=search+term` to URL |
| `AniList API rate limit exceeded` | Too many requests | Wait 1 minute or use cached results |
| `Failed to make AniList request` | Network/API issue | Check internet connection and AniList status |
| `Torrent not found` | Invalid torrent ID | Verify torrent ID from search results |

### Debug Mode

Enable debugging in `config.php`:
```php
define('DEBUG_MODE', true);
define('LOG_ERRORS', true);
```

**Debug Tools:**
- `debug_mapping.php` - Test mapping functionality with detailed output
- `debug_scraper.php` - Test scraping with verbose logging
- `test.html` - Interactive testing interface
- `logs/api.log` - Error and debug logging

### Troubleshooting

**No mapping results:**
1. Check if torrent title is too complex
2. Try with simpler title variations
3. Use debug_mapping.php to see step-by-step process
4. Verify AniList API connectivity

**Slow responses:**
1. Clear cache directory: `rm -rf cache/*`
2. Check network connectivity to AniList and AnimeTimes
3. Monitor rate limiting logs

## Security & Best Practices

### Security Considerations
- ✅ **Read-only API**: No data modification endpoints
- ✅ **Input validation**: All parameters validated
- ✅ **Rate limiting**: Prevents abuse of external services
- ✅ **CORS enabled**: Safe for browser-based apps
- ✅ **No sensitive data**: No user data stored or transmitted
- ✅ **Error handling**: No sensitive info in error messages

### Production Deployment
```powershell
# Use production Docker compose
docker compose -f docker-compose.prod.yml up -d

# Disable debug mode in config.php
define('DEBUG_MODE', false);

# Set up proper logging
define('LOG_ERRORS', true);
define('LOG_FILE', '/var/log/torrent-api.log');

# Configure reverse proxy with SSL
```

### Monitoring
- Monitor `logs/api.log` for errors
- Track rate limit usage
- Monitor cache hit rates
- Set up health check monitoring

## Contributing & Development

### Project Structure
```
torrent-api/
├── index.php              # Main API router
├── config.php             # Configuration settings
├── mapping/
│   └── AniListMapper.php   # AniList integration logic
├── scraper/
│   └── AnimeTimeScraper.php # Web scraping logic
├── test.html              # Interactive test interface
├── debug_mapping.php      # Mapping debug tool
├── debug_scraper.php      # Scraper debug tool
├── docker/               # Docker configuration
├── cache/               # API response cache
└── logs/               # Error and debug logs
```

### Development Workflow
1. **Setup**: Use Docker for consistent environment
2. **Testing**: Use test.html for interactive testing
3. **Debugging**: Use debug_*.php scripts for detailed analysis
4. **Documentation**: Update README.md for any changes

### Future Enhancements
- [ ] **Database Integration**: Persistent storage for better performance
- [ ] **Authentication**: User accounts and API keys  
- [ ] **Webhooks**: Real-time notifications for new torrents
- [ ] **Advanced Filtering**: Quality, genre, year filtering
- [ ] **Torrent Analysis**: Automatic quality detection
- [ ] **WebTorrent Streaming**: Direct browser streaming
- [ ] **Mobile API**: Mobile-optimized endpoints
- [ ] **Analytics**: Usage statistics and popular anime tracking

## License & Legal

This project is for **educational and research purposes only**. 

### Important Notes:
- **Respect ToS**: Follow AnimeTimes.cc and AniList terms of service
- **Rate Limiting**: Built-in respect for external service limits
- **No Redistribution**: Don't redistribute copyrighted content
- **Educational Use**: Intended for learning web scraping and API integration

### AniList API
This project uses the [AniList GraphQL API](https://anilist.gitbook.io/anilist-apiv2-docs/) which is free and open for non-commercial use.

### AnimeTimes.cc
Web scraping is done respectfully with rate limiting and standard HTTP practices.
