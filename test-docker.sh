#!/bin/bash

echo "Testing Docker setup..."

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker Desktop."
    exit 1
fi

echo "✅ Docker is running"

# Build and start containers
echo "Building and starting containers..."
docker compose up -d --build

# Wait for container to be ready
echo "Waiting for container to be ready..."
sleep 10

# Test health endpoint
echo "Testing health endpoint..."
if curl -f http://localhost:8080/health > /dev/null 2>&1; then
    echo "✅ API is responding"
else
    echo "❌ API is not responding"
    docker compose logs
    exit 1
fi

echo "✅ Docker setup is working correctly!"
echo "API available at: http://localhost:8080"
echo "Test page: http://localhost:8080/test.html"
