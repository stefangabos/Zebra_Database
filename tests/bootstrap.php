<?php

/**
 * Bootstrap file for PHPUnit tests
 * Sets up the test environment and includes necessary classes
 *
 * Everything here has side effects - it connects, it creates directories, it creates the test database.
 * The settings themselves, and the helpers that read them, live in settings.php so that phpcs and phpstan
 * can learn what exists without any of this running.
 */

// include the Zebra_Database class
require_once __DIR__ . '/../Zebra_Database.php';

// include support classes
require_once __DIR__ . '/Support/DatabaseTestCase.php';
require_once __DIR__ . '/Support/TestDataFactory.php';
require_once __DIR__ . '/Support/DatabaseProbe.php';

// the settings and the helpers - declarations only, no side effects
require_once __DIR__ . '/settings.php';

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

    // the port is cast because it arrives as a string from the environment while mysqli wants an integer -
    // the library's own connect() method takes it as a string, which is why the constant is left as one
    $connection = new mysqli(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, '', (int)TEST_DB_PORT);

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

// Register shutdown function to clean up temp files
register_shutdown_function('cleanupTempFiles');
