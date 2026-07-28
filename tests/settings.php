<?php

/**
 * The settings the test suite runs with, and the helpers that read them.
 *
 * This file only declares things - no connections, no directories created, nothing written. That is what
 * lets anything include it safely, which phpstan needs: it has to know these constants exist, and the only
 * way it learns about a constant is by executing the define(). Including bootstrap.php for that would have
 * it connect to MySQL and stop the analysis when it cannot.
 *
 * bootstrap.php includes this file and then does the work that has side effects.
 */

/**
 * Reads a setting from the environment.
 *
 * We cannot use "?:" here - an empty value is perfectly valid for some of these (an empty password, most
 * obviously) and "?:" would silently replace it with the fallback. Only a value that is not set at all
 * may fall back.
 *
 * @param   string  $name       name of the environment variable, as set in phpunit.xml
 * @param   mixed   $default    what to use when it is not set at all
 *
 * @return  mixed
 */
function test_env($name, $default) {
    $value = getenv($name);
    return $value === false ? $default : $value;
}

// connection credentials - see tests/phpunit.xml.dist
define('TEST_DB_HOST', test_env('DB_HOST', '127.0.0.1'));
define('TEST_DB_USER', test_env('DB_USER', 'root'));
define('TEST_DB_PASS', test_env('DB_PASS', ''));
define('TEST_DB_NAME', test_env('DB_NAME', 'zebra_test'));
// cast so that the constant has one type rather than two - getenv() hands back a string and the fallback
// is a number, and connect() documents this argument as a string. bootstrap.php casts it back to an int
// for mysqli's constructor, which wants one.
define('TEST_DB_PORT', (string)test_env('DB_PORT', 3306));

// caching servers - these have no valid empty value, so "?:" is fine here
define('TEST_MEMCACHE_HOST', getenv('MEMCACHE_HOST') ?: 'localhost');
define('TEST_MEMCACHE_PORT', getenv('MEMCACHE_PORT') ?: 11211);
define('TEST_REDIS_HOST', getenv('REDIS_HOST') ?: 'localhost');
define('TEST_REDIS_PORT', getenv('REDIS_PORT') ?: 6379);

// paths the suite reads from and writes to
define('TEST_TMP_PATH', __DIR__ . '/tmp');
define('TEST_FIXTURES_PATH', __DIR__ . '/Fixtures');
define('TEST_SUPPORT_PATH', __DIR__ . '/Support');

/**
 * Returns a path under tmp/, creating it if it is not there yet.
 *
 * @param   string  $subdir     optional subdirectory of tmp/
 *
 * @return  string
 */
function getTempPath($subdir = '') {

    $path = TEST_TMP_PATH;

    if ($subdir) {
        $path .= '/' . trim($subdir, '/');
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    return $path;

}

/**
 * @return  array<mixed>
 */
function loadTestQueries() {
    return include TEST_FIXTURES_PATH . '/test_queries.php';
}

/**
 * @return  array<mixed>
 */
function loadMaliciousInputs() {
    $file = TEST_FIXTURES_PATH . '/malicious_inputs.json';
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true);
    }
    return [];
}

/**
 * Removes whatever the tests left behind in tmp/.
 *
 * @return  void
 */
function cleanupTempFiles() {
    $files = glob(TEST_TMP_PATH . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        } elseif (is_dir($file) && basename($file) !== '.gitkeep') {
            $subFiles = glob($file . '/*');
            foreach ($subFiles as $subFile) {
                if (is_file($subFile)) {
                    unlink($subFile);
                }
            }
        }
    }
}
