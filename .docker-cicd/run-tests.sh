#!/bin/bash
set -e

echo "=== CI/CD Test Runner ==="

# 1. Wait for MySQL
echo "Waiting for MySQL..."
ATTEMPTS=0
until mysql -h mysql-cicd -uroot -proot_password -e "SELECT 1" 2>&1; do
    ATTEMPTS=$((ATTEMPTS + 1))
    echo "  MySQL not ready yet (attempt $ATTEMPTS), retrying in 2s..."
    if [ "$ATTEMPTS" -ge 30 ]; then
        echo "ERROR: MySQL did not become ready after 30 attempts. Exiting."
        exit 1
    fi
    sleep 2
done
echo "MySQL is ready."

# 2. Grant nat_user privileges (needed for stancl/tenancy to create tenant DBs)
echo "Granting database privileges..."
mysql -h mysql-cicd -uroot -proot_password -e "GRANT ALL PRIVILEGES ON *.* TO 'nat_user'@'%' WITH GRANT OPTION; FLUSH PRIVILEGES;"

# 3. Install dependencies
echo "Installing Composer dependencies..."
cd /var/www
composer install --no-interaction --prefer-dist

# 4. Set up .env
echo "Setting up environment..."
cp .env.cicd .env
php artisan key:generate

# 5. Run central migrations
echo "Running central migrations..."
rm -f database/schema/*.dump
php artisan migrate --force --isolated

# 6. Create tenant via tinker
echo "Creating tenant 'dixies'..."
php artisan tinker --execute="
    \$tenant = App\Models\Tenant::create(['id' => 'dixies']);
    \$tenant->domains()->create(['domain' => 'dixies.tour-backend.local']);
    echo 'Tenant created: ' . \$tenant->id . PHP_EOL;
"

# 7. Run tenant migrations
echo "Running tenant migrations..."
php artisan tenants:migrate --force --isolated

# 8. Seed test data
echo "Seeding test data..."
php artisan tenants:seed --class='Tests\Seeders\CicdSeeder' --force

# 9. Run tests
echo "Running tests..."
php artisan config:clear
set +e

if [ -n "$TEST_SHARD_TOTAL" ] && [ "$TEST_SHARD_TOTAL" -gt 1 ]; then
    echo "Sharding enabled: running shard $TEST_SHARD_INDEX of $TEST_SHARD_TOTAL"
    mapfile -t ALL_TESTS < <(find tests -iname "*Test.php" | sort)
    SHARD_TESTS=()
    for i in "${!ALL_TESTS[@]}"; do
        if (( (i % TEST_SHARD_TOTAL) + 1 == TEST_SHARD_INDEX )); then
            SHARD_TESTS+=("${ALL_TESTS[$i]}")
        fi
    done
    echo "Running ${#SHARD_TESTS[@]} of ${#ALL_TESTS[@]} test files in this shard"
    php artisan test --no-coverage "${SHARD_TESTS[@]}" 2>&1
else
    php artisan test --no-coverage 2>&1
fi
TEST_EXIT=$?
echo ""
echo "Tests exited with code: $TEST_EXIT"
exit $TEST_EXIT
