# Docker Setup

## Quick Start

### Using PowerShell Script (Windows)
```powershell
./docker.ps1 up
```

### Using Docker Compose
```bash
docker compose up -d
```

Access the API at http://localhost:8080

## Available Commands

### PowerShell Script
```powershell
./docker.ps1 up        # Build and start
./docker.ps1 down      # Stop container
./docker.ps1 logs      # View logs
./docker.ps1 clean     # Clean up everything (removes orphans aswell)
./docker.ps1 dev       # Development mode (builds on up)
./docker.ps1 dev-down  # Stops development container
```

### Docker Compose
```bash
docker compose up               # Pulls from ghcr registry and starts
docker compose up -d --build    # Build and start
docker compose down             # Stop
docker compose logs -f          # View logs
```

## Development vs Production

- `docker-compose.yml` - Production (no source mounting, port 80)
- `docker-compose-dev.yml` - Development (includes source code mounting)

## Health Check

The container includes a health check that monitors:
- Apache web server status
- API endpoint availability

Check container health:
```bash
docker ps
docker inspect torrent-api | grep Health -A 10
```
