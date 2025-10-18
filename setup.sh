#!/bin/bash
set -e

echo "======================================"
echo "EasyMonitor - Docker Setup"
echo "======================================"
echo ""

# Check if .env exists
if [ ! -f ".env" ]; then
    echo "⚠️  .env file not found!"
    echo ""
    read -p "Would you like to copy .env.example to .env? (y/n) " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        cp .env.example .env
        echo "✓ Created .env file from .env.example"
        echo ""
        echo "⚠️  Please edit .env and set your database password!"
        echo "   Then run this script again."
        exit 0
    else
        echo "❌ Cannot continue without .env file"
        exit 1
    fi
fi

# Build and start containers
echo "[1/2] Building and starting Docker containers..."
docker compose up -d --build
echo "  ✓ Containers started!"
echo ""

# Wait a moment for containers to be fully ready
echo "⏳ Waiting for containers to initialize..."
sleep 5
echo ""

# Run setup inside container
echo "[2/2] Running application setup..."
docker exec php bash /var/www/html/docker/scripts/setup.sh

echo ""
echo "======================================"
echo "✓ All done!"
echo "======================================"
