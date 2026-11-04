#!/bin/bash

set -e

echo "========================================"
echo "Starting Local Development Environment"
echo "========================================"

cd /var/www

# Set permissions
echo "Setting permissions..."
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache 2>/dev/null || true
chmod -R 777 /var/www/storage /var/www/bootstrap 2>/dev/null || true

# Install composer dependencies if vendor doesn't exist
if [ ! -d "/var/www/vendor" ]; then
    echo "Installing Composer dependencies..."
    composer install
fi

# Install npm dependencies if node_modules doesn't exist
if [ ! -d "/var/www/node_modules" ]; then
    echo "Installing NPM dependencies..."
    npm install
fi

# Generate app key if not set
if ! grep -q "^APP_KEY=base64:" /var/www/.env 2>/dev/null; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Clear and cache config
echo "Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Run migrations
echo "Running migrations..."
php artisan migrate --force --isolated || echo "Migration failed or already up to date"
php artisan tenants:migrate --force --isolated || echo "Tenant migration failed or already up to date"

# Update tenant domains for local Docker environment
echo "Updating tenant domains for local Docker..."
php artisan tinker --execute="
    DB::table('domains')->update([
        'domain' => DB::raw(\"REPLACE(domain, '.ntv.tours', '.tour-backend.local')\")
    ]);
    DB::table('domains')->update([
        'domain' => DB::raw(\"REPLACE(domain, '.testingnat.com', '.tour-backend.local')\")
    ]);
    echo 'Domains updated for local Docker environment';
" 2>/dev/null || echo "Domain update skipped"

# Build assets (optional - comment out if not needed during development)
# echo "Building assets..."
# export NODE_OPTIONS=--openssl-legacy-provider
# npm run dev

echo "Starting Supervisor..."
/usr/bin/supervisord -c /etc/supervisord.conf
