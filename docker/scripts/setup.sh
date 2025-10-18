#!/bin/bash
set -e

echo "======================================"
echo "EasyMonitor - Laravel Application Setup"
echo "======================================"
echo ""

# Wait for database to be ready
echo "[1/6] Waiting for database to be ready..."
until PGPASSWORD=$DB_PASSWORD psql -h "$DB_HOST" -U "$DB_USERNAME" -d "$DB_DATABASE" -c '\q' 2>/dev/null; do
  echo "  ⏳ PostgreSQL is unavailable - waiting..."
  sleep 2
done
echo "  ✓ PostgreSQL is ready!"
echo ""

# Wait for Redis to be ready
echo "[2/6] Waiting for Redis to be ready..."
until redis-cli -h "$REDIS_HOST" ping 2>/dev/null | grep -q PONG; do
  echo "  ⏳ Redis is unavailable - waiting..."
  sleep 2
done
echo "  ✓ Redis is ready!"
echo ""

# Install Composer dependencies
echo "[3/6] Installing Composer dependencies..."
if [ ! -f "composer.lock" ]; then
    echo "  ⏳ Running: composer install --no-interaction --prefer-dist --optimize-autoloader"
    composer install --no-interaction --prefer-dist --optimize-autoloader
else
    echo "  ⏳ Running: composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev"
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi
echo "  ✓ Composer dependencies installed!"
echo ""

# Generate application key if not exists
echo "[4/6] Checking application key..."
if grep -q "APP_KEY=$" .env || grep -q "APP_KEY=\"\"" .env; then
    echo "  ⏳ Generating application key..."
    php artisan key:generate --ansi
    echo "  ✓ Application key generated!"
else
    echo "  ✓ Application key already exists!"
fi
echo ""

# Install NPM dependencies and build assets
echo "[5/6] Installing NPM dependencies and building assets..."
if [ ! -d "node_modules" ]; then
    echo "  ⏳ Running: npm install"
    npm install
fi
echo "  ⏳ Running: npm run build"
npm run build
echo "  ✓ Assets built successfully!"
echo ""

# Run database migrations
echo "[6/6] Running database migrations..."
php artisan migrate --force --no-interaction
echo "  ✓ Migrations completed!"
echo ""

echo "======================================"
echo "✓ Setup completed successfully!"
echo "======================================"
echo ""
echo "Your application is ready at: ${APP_URL:-http://localhost}"
echo ""
