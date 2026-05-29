#!/bin/bash

# Run AIAS Pest tests with MySQL database isolation
# Supports parallel execution via Pest's built-in --parallel flag or composer run test
#
# Usage:
#   ./test.sh                                          # All tests (sequential)
#   ./test.sh --parallel                               # All tests (parallel, 4 processes)
#   ./test.sh tests/Feature/AuditEngagementTest.php    # Specific file
#   ./test.sh --filter="can list audit engagements"    # Specific test
#   ./test.sh --parallel tests/Feature/               # Parallel on a directory
#
# Pest-specific flags (passed through):
#   --filter="test name"    Filter tests by name (supports regex)
#   --group=group-name      Run tests in a specific group
#   --bail                  Stop on first failure
#   --coverage              Generate coverage report
#   --parallel              Run in parallel (4 processes)
#   --processes=N           Override parallel process count (default: 4)

# Generate unique database name for this test run to avoid conflicts
TEST_DB_NAME="aias_test_$(date +%s)_$$"

# Default parallel process count
PARALLEL_PROCESSES=4

# Function to create test database
create_test_database() {
    mysql -u root -h 127.0.0.1 -e "CREATE DATABASE IF NOT EXISTS \`${TEST_DB_NAME}\`;"
    if [ $? -ne 0 ]; then
        echo "ERROR: Failed to create test database '${TEST_DB_NAME}'." >&2
        echo "Ensure MySQL is running and accessible at 127.0.0.1 with user 'root'." >&2
        exit 1
    fi
}

# Function to drop test database
drop_test_database() {
    mysql -u root -h 127.0.0.1 -e "DROP DATABASE IF EXISTS \`${TEST_DB_NAME}\`;"
}

# Trap to ensure cleanup on exit (including Ctrl+C / SIGINT)
cleanup() {
    drop_test_database
}
trap cleanup EXIT

# Check if --parallel flag is present
PARALLEL=false
EXTRA_ARGS=()

for arg in "$@"; do
    if [[ "$arg" == "--parallel" ]]; then
        PARALLEL=true
    elif [[ "$arg" =~ ^--processes=([0-9]+)$ ]]; then
        PARALLEL_PROCESSES="${BASH_REMATCH[1]}"
    else
        EXTRA_ARGS+=("$arg")
    fi
done

# Create isolated test database
create_test_database

# Export test environment
export APP_ENV=testing
export DB_CONNECTION=mysql
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_DATABASE="$TEST_DB_NAME"
export DB_USERNAME=root
export DB_PASSWORD=""
export CACHE_STORE=array
export QUEUE_CONNECTION=sync

# Clear config cache before running tests
php artisan config:clear

if [ $? -ne 0 ]; then
    echo "ERROR: php artisan config:clear failed." >&2
    exit 1
fi

if [ "$PARALLEL" = true ]; then
    # Parallel execution via Pest's built-in --parallel
    vendor/bin/pest \
        --parallel \
        --processes="${PARALLEL_PROCESSES}" \
        "${EXTRA_ARGS[@]}"
else
    # Check if args look like a composer script call (no file/filter args)
    # Allow passthrough to composer run test for zero-arg invocation
    if [ ${#EXTRA_ARGS[@]} -eq 0 ]; then
        # Use composer run test (Laravel 13 predefined script)
        composer run test
    else
        # Sequential Pest run with provided arguments
        vendor/bin/pest "${EXTRA_ARGS[@]}"
    fi
fi

EXIT_CODE=$?

exit $EXIT_CODE
