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
echo "[4/8] Checking application key..."
if grep -q "APP_KEY=$" .env || grep -q "APP_KEY=\"\"" .env; then
    echo "  ⏳ Generating application key..."
    php artisan key:generate --ansi
    echo "  ✓ Application key generated!"
else
    echo "  ✓ Application key already exists!"
fi
echo ""

# Generate JWT secret for probe nodes if not exists
echo "[5/8] Checking JWT secret..."
if grep -q "JWT_SECRET=$" .env || grep -q "JWT_SECRET=\"\"" .env || ! grep -q "^JWT_SECRET=" .env; then
    echo "  ⏳ Generating JWT secret..."
    JWT_SECRET=$(openssl rand -base64 32)
    # Update or add JWT_SECRET to .env
    if grep -q "^JWT_SECRET=" .env; then
        sed -i.bak "s|^JWT_SECRET=.*|JWT_SECRET=${JWT_SECRET}|" .env && rm .env.bak
    else
        echo "JWT_SECRET=${JWT_SECRET}" >> .env
    fi
    echo "  ✓ JWT secret generated!"
else
    echo "  ✓ JWT secret already exists!"
fi
echo ""

# Generate probe node JWT token if not exists
echo "[6/8] Checking probe node token..."
if grep -q "PROBE_JWT_TOKEN=$" .env || grep -q "PROBE_JWT_TOKEN=\"\"" .env || ! grep -q "^PROBE_JWT_TOKEN=" .env; then
    echo "  ⏳ Generating probe node token..."
    # Use the artisan command to generate the token
    php artisan probe:generate-token \
        --node-id="${PROBE_NODE_ID:-local-node-1}" \
        --expires=365 \
        --no-interaction 2>/dev/null || true
    echo "  ✓ Probe node token generated!"
else
    echo "  ✓ Probe node token already exists!"
fi
echo ""

# Install NPM dependencies and build assets
echo "[7/8] Installing NPM dependencies and building assets..."
if [ ! -d "node_modules" ]; then
    echo "  ⏳ Running: npm install"
    npm install
fi
echo "  ⏳ Running: npm run build"
npm run build
echo "  ✓ Assets built successfully!"
echo ""

# Run database migrations
echo "[8/8] Running database migrations..."
php artisan migrate --force --no-interaction
echo "  ✓ Migrations completed!"
echo ""

# Create storage symlink (for status page logos and other public uploads)
echo "[9/8] Creating storage symlink..."
php artisan storage:link 2>/dev/null || true
echo "  ✓ Storage symlink ready!"
echo ""

echo "======================================"
echo "✓ Setup completed successfully!"
echo "======================================"
echo ""
echo "Your application is ready at: ${APP_URL:-http://localhost}"
echo ""
