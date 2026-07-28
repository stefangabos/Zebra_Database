<?php

/**
 * Bootstrap file for PHPUnit tests
 * Sets up the test environment and includes necessary classes
 */

// include the Zebra_Database class
require_once __DIR__ . '/../Zebra_Database.php';

// include support classes
require_once __DIR__ . '/Support/DatabaseTestCase.php';
require_once __DIR__ . '/Support/TestDataFactory.php';
require_once __DIR__ . '/Support/DatabaseProbe.php';

// define constants for mysql connection credentials
// (we cannot use "?:" here as an empty password is perfectly valid and would be replaced by the fallback)
function test_env($name, $default) {
    $value = getenv($name);
    return $value === false ? $default : $value;
}

define('TEST_DB_HOST', test_env('DB_HOST', '127.0.0.1'));
define('TEST_DB_USER', test_env('DB_USER', 'root'));
define('TEST_DB_PASS', test_env('DB_PASS', ''));
define('TEST_DB_NAME', test_env('DB_NAME', 'zebra_test'));
define('TEST_DB_PORT', test_env('DB_PORT', 3306));

// define test constants for caching
define('TEST_MEMCACHE_HOST', getenv('MEMCACHE_HOST') ?: 'localhost');
define('TEST_MEMCACHE_PORT', getenv('MEMCACHE_PORT') ?: 11211);
define('TEST_REDIS_HOST', getenv('REDIS_HOST') ?: 'localhost');
define('TEST_REDIS_PORT', getenv('REDIS_PORT') ?: 6379);

// Define paths for test resources
define('TEST_TMP_PATH', __DIR__ . '/tmp');
define('TEST_FIXTURES_PATH', __DIR__ . '/Fixtures');
define('TEST_SUPPORT_PATH', __DIR__ . '/Support');

// Ensure tmp directories exist
if (!is_dir(TEST_TMP_PATH)) {
    mkdir(TEST_TMP_PATH, 0777, true);
}
if (!is_dir(TEST_TMP_PATH . '/cache')) {
    mkdir(TEST_TMP_PATH . '/cache', 0777, true);
}
if (!is_dir(TEST_TMP_PATH . '/logs')) {
    mkdir(TEST_TMP_PATH . '/logs', 0777, true);
}

// Create test database if it doesn't exist
try {

    $connection = new mysqli(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, '', TEST_DB_PORT);

    if ($connection->connect_error) {
        throw new Exception('Failed to connect to MySQL: ' . $connection->connect_error);
    }

    // Create test database
    $connection->query("CREATE DATABASE IF NOT EXISTS `" . TEST_DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $connection->select_db(TEST_DB_NAME);

    // Create test tables
    $connection->query("
        CREATE TABLE IF NOT EXISTS `test_users` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(100) NOT NULL,
            `email` varchar(100) DEFAULT NULL,
            `age` int(11) DEFAULT NULL,
            `score` decimal(10,2) DEFAULT NULL,
            `is_active` tinyint(1) DEFAULT 1,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $connection->query("
        CREATE TABLE IF NOT EXISTS `test_products` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(100) NOT NULL,
            `price` decimal(10,2) NOT NULL,
            `category_id` int(11) DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $connection->query("
        CREATE TABLE IF NOT EXISTS `test_categories` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(50) NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $connection->close();

} catch (Exception $e) {
    echo "Database setup failed: " . $e->getMessage() . "\n";
    echo "Please ensure MySQL is running and the test database credentials are correct.\n";
    exit(1);
}

// Helper functions for tests
function loadTestQueries() {
    return include TEST_FIXTURES_PATH . '/test_queries.php';
}

function loadMaliciousInputs() {
    $file = TEST_FIXTURES_PATH . '/malicious_inputs.json';
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true);
    }
    return [];
}

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

// Register shutdown function to clean up temp files
register_shutdown_function('cleanupTempFiles');