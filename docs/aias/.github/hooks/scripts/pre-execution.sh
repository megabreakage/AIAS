#!/bin/bash

# Pre-execution hook for GitHub Copilot Agent — AIAS (Adaptive Intelligent Audit System)
# Validates that tests are run via ./test.sh, ensuring proper MySQL database isolation.

COMMAND="$1"

if echo "$COMMAND" | grep -q "artisan test\|phpunit\|./vendor/bin/pest\|pest "; then
    if ! echo "$COMMAND" | grep -q "./test.sh"; then
        echo "⚠️  WARNING: Direct test command detected!"
        echo ""
        echo "📋 GitHub Copilot: Use './test.sh' for running AIAS tests."
        echo ""
        echo "💡 Why ./test.sh?"
        echo "   - Creates unique MySQL database per run (e.g., aias_test_<timestamp>_<pid>)"
        echo "   - Prevents MySQL lock conflicts between test runs"
        echo "   - Runs both central AND tenant migrations automatically"
        echo "   - Matches production MySQL environment (not SQLite)"
        echo "   - Auto-cleans test databases after completion"
        echo ""
        echo "🔧 Correct commands:"
        echo "   ./test.sh                                          # All tests"
        echo "   ./test.sh tests/Feature/FindingTest.php            # Specific file"
        echo "   ./test.sh --filter=test_can_create_finding         # Specific test"
        echo "   ./test.sh --filter=it_can_create_a_finding         # Pest it() test"
        echo "   ./test.sh --parallel                               # Parallel execution"
        echo ""
        echo "📌 AIAS Test Requirements:"
        echo "   All test files MUST use the RefreshDatabaseWithTenancy trait and Pest v3 syntax:"
        echo ""
        echo "   uses(TestCase::class, RefreshDatabaseWithTenancy::class)->in(__FILE__);"
        echo ""
        echo "   it('can create a finding', function () {"
        echo "       // test body"
        echo "   });"
        echo ""
        echo "   NOT: class FindingTest extends TestCase { public function test_... }"
        echo "   NOT: use RefreshDatabase;  (use RefreshDatabaseWithTenancy instead)"
        echo ""

        read -p "Proceed with direct command anyway? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi
fi

exit 0
