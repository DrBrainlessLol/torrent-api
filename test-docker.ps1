Write-Host "Testing Docker setup..." -ForegroundColor Blue

# Check if Docker is running
try {
    docker info | Out-Null
    Write-Host "✅ Docker is running" -ForegroundColor Green
} catch {
    Write-Host "❌ Docker is not running. Please start Docker Desktop." -ForegroundColor Red
    exit 1
}

# Build and start containers
Write-Host "Building and starting containers..." -ForegroundColor Blue
docker compose up -d --build

# Wait for container to be ready
Write-Host "Waiting for container to be ready..." -ForegroundColor Blue
Start-Sleep -Seconds 10

# Test health endpoint
Write-Host "Testing health endpoint..." -ForegroundColor Blue
try {
    $response = Invoke-WebRequest -Uri "http://localhost:8080/health" -TimeoutSec 5
    if ($response.StatusCode -eq 200) {
        Write-Host "✅ API is responding" -ForegroundColor Green
    } else {
        throw "Bad status code"
    }
} catch {
    Write-Host "❌ API is not responding" -ForegroundColor Red
    docker compose logs
    exit 1
}

Write-Host "✅ Docker setup is working correctly!" -ForegroundColor Green
Write-Host "API available at: http://localhost:8080" -ForegroundColor Cyan
Write-Host "Test page: http://localhost:8080/test.html" -ForegroundColor Cyan
