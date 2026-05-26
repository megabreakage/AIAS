#!/bin/bash

# Run Laravel tests with MySQL database
# Supports both parallel and sequential execution
# Uses MySQL for testing to match production environment

# Generate unique database name for this test run to avoid conflicts
TEST_DB_NAME="aias_test_$(date +%s)_$$"

# Function to create test database
create_test_database() {
    mysql -u root -h 127.0.0.1 -e "CREATE DATABASE IF NOT EXISTS \`${TEST_DB_NAME}\`;"
}

# Function to drop test database
drop_test_database() {
    mysql -u root -h 127.0.0.1 -e "DROP DATABASE IF EXISTS \`${TEST_DB_NAME}\`;"
}

# Check if paratest is available and parallel flag is requested
if command -v vendor/bin/paratest >/dev/null 2>&1 && [[ "$*" == *"--parallel"* ]]; then
    # Remove --parallel from args since paratest handles it differently
    ARGS=("${@/--parallel/}")

    # Create test database
    create_test_database

    # Run tests in parallel with paratest
    APP_ENV=testing \
    DB_CONNECTION=mysql \
    DB_HOST=127.0.0.1 \
    DB_PORT=3306 \
    DB_DATABASE="$TEST_DB_NAME" \
    DB_USERNAME=root \
    DB_PASSWORD="" \
    CACHE_STORE=array \
    QUEUE_CONNECTION=sync \
    php artisan config:clear && \
    vendor/bin/paratest "${ARGS[@]}"

    EXIT_CODE=$?

    # Clean up test database
    drop_test_database

    exit $EXIT_CODE
else
    # Create test database
    create_test_database

    # Run tests sequentially with MySQL
    APP_ENV=testing \
    DB_CONNECTION=mysql \
    DB_HOST=127.0.0.1 \
    DB_PORT=3306 \
    DB_DATABASE="$TEST_DB_NAME" \
    DB_USERNAME=root \
    DB_PASSWORD="" \
    CACHE_STORE=array \
    QUEUE_CONNECTION=sync \
    php artisan config:clear && \
    php artisan test "$@"

    EXIT_CODE=$?

    # Clean up test database
    drop_test_database

    exit $EXIT_CODE
fi
