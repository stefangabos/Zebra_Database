#!/usr/bin/env bash
#
# Runs the test suite.
#
# Connection settings come from phpunit.xml if present, or from phpunit.xml.dist otherwise - see
# the comments in phpunit.xml.dist for how to point the suite at your own database.
#
# Any arguments are passed straight through to PHPUnit, so the usual flags all work:
#
#   ./run-tests.sh                                  run everything
#   ./run-tests.sh --testdox                        readable output
#   ./run-tests.sh --filter dcount                  only tests matching "dcount"
#   ./run-tests.sh CacheTest.php                    a single file
#   ./run-tests.sh --coverage-html coverage-html    with a coverage report (needs xdebug or pcov)
#
# Set PHP to use an interpreter that is not the one on the PATH - handy for checking the suite
# against another version, and required on setups where "php" is not on the PATH at all:
#
#   PHP=/Applications/MAMP/bin/php/php8.3.14/bin/php ./run-tests.sh
#
# The memcache and redis tests skip themselves unless both a suitable extension and a running server
# are found. To include them, start the two servers first - they need no configuration and hold nothing
# of value, so they can be killed again afterwards:
#
#   redis-server --port 6379 --save '' --appendonly no &
#   memcached -p 11211 -m 64 &
#
# That is 8 tests covering the memcache and redis caching backends, which are otherwise not exercised
# at all. The socket test works out the socket path from the server itself, so it needs no setup.

set -euo pipefail

cd "$(dirname "$0")"

PHP="${PHP:-php}"

if ! command -v "$PHP" > /dev/null 2>&1; then
    echo "PHP interpreter '$PHP' not found - put php on your PATH or set PHP=/path/to/php." >&2
    exit 1
fi

if [ ! -f ../vendor/bin/phpunit ]; then
    echo "PHPUnit not found - run 'composer install' in the project root first." >&2
    exit 1
fi

echo "Using $("$PHP" -r 'echo PHP_BINARY, " (", PHP_VERSION, ")";')"

exec "$PHP" ../vendor/bin/phpunit "$@"
