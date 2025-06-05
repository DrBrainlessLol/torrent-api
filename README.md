# Torrent API for AnimeTimes.cc

A RESTful API for scraping anime torrent data from AnimeTimes.cc and mapping it to AniList entries for metadata enrichment.

## Features

- 🔍 **Torrent Scraping**: Extract torrent/magnet links and metadata from AnimeTimes.cc
- 🎯 **Smart Search**: Search functionality with caching for better performance
- 🎬 **AniList Integration**: Map torrents to AniList entries for rich metadata
- 📊 **Rate Limiting**: Built-in rate limiting to prevent overloading target sites
- 💾 **Caching**: Intelligent caching system to reduce API calls
- 🔧 **Streaming Ready**: Designed for integration with WebTorrent/Vidstack

## API Endpoints

### Health Check
```
GET /health
```
Returns API status and available endpoints.

### Latest Torrents
```
GET /torrents?page=1&limit=25
```
Get the latest anime torrents from AnimeTimes.cc.

**Parameters:**
- `page` (optional): Page number (default: 1)
- `limit` (optional): Results per page (default: 25)

### Search Torrents
```
GET /search?q=query&page=1
```
Search for specific anime torrents.

**Parameters:**
- `q` (required): Search query
- `page` (optional): Page number (default: 1)

### Torrent Details
```
GET /torrent/{id}
```
Get detailed information about a specific torrent including magnet links.

**Parameters:**
- `id` (required): Torrent ID from the URL

### AniList Search
```
GET /anilist/search?q=query
```
Search AniList database for anime information.

**Parameters:**
- `q` (required): Search query

### Map Torrent to AniList
```
POST /anilist/map
Content-Type: application/json

{
    "torrent_title": "Torrent title",
    "anilist_id": 12345 // optional
}
```
Map a torrent title to an AniList entry for metadata enrichment.

### Generate Stream
```
POST /stream/generate
Content-Type: application/json

{
    "magnet_link": "magnet:?xt=urn:btih:..."
}
```
Prepare torrent for streaming (placeholder for WebTorrent integration).

## Installation

1. Clone/copy the files to your web server
2. Ensure PHP 7.4+ is installed
3. Make sure the `cache` and `logs` directories are writable
4. Access the API at `/torrent-api/`

## Configuration

Edit `config.php` to customize:

- Rate limiting settings
- Cache duration
- Request timeouts
- Debug mode
- AniList API settings

## Usage Examples

### Basic Usage
```javascript
// Get latest torrents
const response = await fetch('/torrent-api/torrents');
const data = await response.json();

// Search for anime
const searchResponse = await fetch('/torrent-api/search?q=Naruto');
const searchData = await searchResponse.json();

// Get torrent details with magnet links
const detailsResponse = await fetch('/torrent-api/torrent/anime-id');
const details = await detailsResponse.json();
```

### Integration with Streaming
```javascript
// Map torrent to AniList for metadata
const mapResponse = await fetch('/torrent-api/anilist/map', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        torrent_title: '[SubsPlease] Attack on Titan - 01 [1080p].mkv'
    })
});
const mapping = await mapResponse.json();

// Get magnet link for streaming
const torrentResponse = await fetch('/torrent-api/torrent/attack-on-titan-01');
const torrentData = await torrentResponse.json();

// Use with WebTorrent or Vidstack
const magnetLink = torrentData.data.torrents[0].url;
```

## Response Format

All endpoints return JSON responses with the following structure:

### Success Response
```json
{
    "success": true,
    "data": {
        // Response data
    }
}
```

### Error Response
```json
{
    "success": false,
    "error": "Error message"
}
```

## Data Structure

### Torrent Object
```json
{
    "id": "anime-title",
    "title": "Anime Title",
    "url": "https://animetime.cc/anime/anime-title",
    "image": "https://example.com/image.jpg",
    "type": "anime",
    "status": "ongoing",
    "episodes": 12,
    "year": 2024,
    "genres": ["Action", "Adventure"],
    "description": "Anime description",
    "torrents": [
        {
            "type": "magnet",
            "url": "magnet:?xt=urn:btih:...",
            "quality": "1080p",
            "size": "1.2 GB",
            "seeders": null,
            "leechers": null
        }
    ],
    "scraped_at": "2024-01-01 12:00:00"
}
```

### AniList Object
```json
{
    "id": 12345,
    "title": {
        "romaji": "Shingeki no Kyojin",
        "english": "Attack on Titan",
        "native": "進撃の巨人"
    },
    "format": "TV",
    "status": "finished",
    "episodes": 25,
    "year": 2013,
    "genres": ["Action", "Drama"],
    "cover_image": {
        "large": "https://s4.anilist.co/file/anilistcdn/media/anime/cover/large/bx16498-C6FPmWm59CyP.jpg"
    },
    "description": "Anime description",
    "score": 85,
    "popularity": 15000
}
```

## Rate Limiting

- Default: 60 requests per minute for web scraping
- AniList API: 90 requests per minute
- Caching helps reduce actual requests to external services

## Caching

- Default cache duration: 1 hour
- Cached data includes:
  - Latest torrents
  - Search results
  - Torrent details
  - AniList search results

## Error Handling

The API includes comprehensive error handling:
- Invalid requests return 400 Bad Request
- Not found resources return 404 Not Found
- Rate limit exceeded returns 429 Too Many Requests
- Internal errors return 500 Internal Server Error

## Development

### Testing
Use the included `test.html` file to test all API endpoints:
```
http://localhost/torrent-api/test.html
```

### Logging
Errors are logged to `logs/api.log` when `LOG_ERRORS` is enabled in config.

### Debugging
Enable `DEBUG_MODE` in config.php for detailed error messages.

## Security Considerations

- The API is designed to be read-only from external services
- Rate limiting prevents abuse
- Input validation on all parameters
- No sensitive data is stored or transmitted

## Future Enhancements

- [ ] Database integration for persistent caching
- [ ] User authentication system
- [ ] Webhook notifications for new torrents
- [ ] Advanced filtering options
- [ ] Torrent quality analysis
- [ ] Direct WebTorrent streaming integration
- [ ] Cloudflare Stream integration

## License

This project is for educational purposes. Please respect the terms of service of AnimeTimes.cc and AniList when using this API.
