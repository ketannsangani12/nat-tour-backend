#!/bin/sh

set -e  # Exit immediately if a command exits with a non-zero status

role=${CONTAINER_ROLE:-app}  # Default to 'app' if CONTAINER_ROLE is not set

if [ "$role" = "app" ]; then
    # App Role - Tasks related to the public-facing app

    cd /var/www

    echo "Running Laravel migrations..."
    php artisan migrate --force --isolated
    php artisan tenants:migrate --force --isolated

    echo "Executing additional artisan commands..."
    php artisan optimize:clear

    echo "Building assets using npm..."
    export NODE_OPTIONS=--openssl-legacy-provider
    npm run prod

    /usr/bin/supervisord -c /etc/supervisord.conf

elif [ "$role" = "queue" ]; then
    # Queue Role - Tasks related to queue workers

    cd /var/www

    echo "Executing additional artisan commands..."
    php artisan optimize:clear

    echo "Restarting Laravel queue workers..."
    php artisan queue:restart

    echo "Clearing Laravel schedule cache..."
    php artisan schedule:clear-cache

    # Start Supervisor for queue management
    echo "Starting Supervisor for process management..."
    /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisor-queue.conf

else
    # If the role doesn't match 'app' or 'queue'
    echo "Invalid container role \"$role\". Exiting..."
    exit 1
fi
