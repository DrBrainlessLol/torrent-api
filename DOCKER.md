# Docker Setup

## Installation

### Windows
1. Download Docker Desktop from https://www.docker.com/products/docker-desktop/
2. Install and restart your computer
3. Start Docker Desktop

## Quick Start

### Using PowerShell Script (Windows)
```powershell
./docker.ps1 up
```

### Using Docker Compose
```bash
docker compose up -d --build
```

Access the API at http://localhost:8080

## Available Commands

### PowerShell Script
```powershell
./docker.ps1 up        # Build and start
./docker.ps1 down      # Stop containers
./docker.ps1 logs      # View logs
./docker.ps1 clean     # Clean up everything
./docker.ps1 prod      # Production mode
```

### Docker Compose
```bash
docker compose up -d --build    # Build and start
docker compose down             # Stop
docker compose logs -f          # View logs
```

## Manual Docker Commands

Build:
```bash
docker build -t torrent-api .
```

Run:
```bash
docker run -d -p 8080:80 --name torrent-api torrent-api
```

## Development vs Production

- `docker-compose.yml` - Development (includes source code mounting)
- `docker-compose.prod.yml` - Production (no source mounting, port 80)

1. Uncomment the Redis service in `docker-compose.yml`
2. Update your `config.php` to use Redis instead of file caching
3. Install PHP Redis extension in Dockerfile:
   ```dockerfile
   RUN pecl install redis && docker-php-ext-enable redis
   ```

## Health Check

The container includes a health check that monitors:
- Apache web server status
- API endpoint availability

Check container health:
```bash
docker ps
docker inspect torrent-api | grep Health -A 10
```

## Troubleshooting

### View container logs:
```bash
docker-compose logs torrent-api
```

### Access container shell:
```bash
docker-compose exec torrent-api bash
```

### Rebuild after changes:
```bash
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

### Check file permissions:
```bash
docker-compose exec torrent-api ls -la /var/www/html/
```

## Security Notes

- The Apache configuration includes security headers
- Cache and logs directories have appropriate permissions
- CORS is enabled for API access
- Consider using a reverse proxy (nginx) for production
