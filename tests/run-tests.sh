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
#   ./run-tests.sh --static                         also the compatibility, static analysis and
#                                                   coding standard checks
#
# The three checks that --static adds are also available on their own, and those are the ones to use
# while working through what they report, since they take arguments:
#
#   composer check-compat / check-compat-legacy / analyse / check-style
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

RUN_STATIC=0
ARGS=()

for argument in "$@"; do
    if [ "$argument" = "--static" ]; then RUN_STATIC=1; else ARGS+=("$argument"); fi
done

if ! command -v "$PHP" > /dev/null 2>&1; then
    echo "PHP interpreter '$PHP' not found - put php on your PATH or set PHP=/path/to/php." >&2
    exit 1
fi

if [ ! -f ../vendor/bin/phpunit ]; then
    echo "PHPUnit not found - run 'composer install' in the project root first." >&2
    exit 1
fi

echo "Using $("$PHP" -r 'echo PHP_BINARY, " (", PHP_VERSION, ")";')"

if [ "$RUN_STATIC" = "0" ]; then
    exec "$PHP" ../vendor/bin/phpunit ${ARGS[@]+"${ARGS[@]}"}
fi

"$PHP" ../vendor/bin/phpunit ${ARGS[@]+"${ARGS[@]}"}

# the static analysis runs from the project root, where its configuration lives - phpstan.neon and
# coding-standards.xml both name paths relative to it
cd ..

echo
echo "── php compatibility ──"
"$PHP" vendor/bin/phpcs -p --standard=tests/php-compatibility.xml --runtime-set ignore_warnings_on_exit 1

echo
echo "── static analysis ──"
# phpstan and the coding standard both end with a non-zero status while there is anything left to fix,
# which would stop the script before it got to the other one
"$PHP" vendor/bin/phpstan analyse --no-progress || true

echo
echo "── coding standard ──"
"$PHP" vendor/bin/phpcs -p --standard=coding-standards.xml --report=summary || true
