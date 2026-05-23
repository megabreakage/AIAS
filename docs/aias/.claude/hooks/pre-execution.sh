#!/bin/bash

# Pre-execution hook for Claude AI Agent — AIAS (Adaptive Intelligent Audit System)
# This hook ensures proper test execution practices using the ./test.sh script

# Extract the command being executed
COMMAND="$1"

# Check if this is a test command
if echo "$COMMAND" | grep -q "artisan test\|phpunit"; then
    # Check if the command is NOT using the test.sh script
    if ! echo "$COMMAND" | grep -q "./test.sh"; then
        echo "⚠️  WARNING: Direct artisan test command detected!"
        echo "📋 Please use './test.sh' script for running tests."
        echo "💡 The test.sh script ensures:"
        echo "   - Unique MySQL database per test run (e.g., aias_test_<timestamp>_<pid>)"
        echo "   - Proper isolation between test runs (no MySQL lock conflicts)"
        echo "   - Both central AND tenant migrations are run (RefreshDatabaseWithTenancy)"
        echo "   - Automatic cleanup of test databases after completion"
        echo "   - Production-accurate MySQL environment (not SQLite)"
        echo ""
        echo "🔧 Recommended commands:"
        echo "   ./test.sh                                    # All tests"
        echo "   ./test.sh tests/Feature/FindingTest.php      # Specific file"
        echo "   ./test.sh --filter=test_method_name          # Specific test"
        echo "   ./test.sh --parallel                         # Parallel execution"
        echo ""
        echo "⚠️  AIAS-specific: All test classes MUST use RefreshDatabaseWithTenancy trait:"
        echo "   use Tests\Traits\RefreshDatabaseWithTenancy;"
        echo "   class MyTest extends TestCase {"
        echo "       use RefreshDatabaseWithTenancy;  // ✅ NOT use RefreshDatabase"
        echo "   }"
        echo ""

        # Ask for confirmation to proceed anyway
        read -p "Do you want to proceed with the direct command anyway? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi
fi

# Allow the command to proceed
exit 0
