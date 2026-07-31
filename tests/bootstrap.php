<?php

/**
 * Bootstrap for the test suite - loads the library and the support classes, reads the configuration, and
 * prepares whatever the tests need to exist before they run.
 *
 * PHPUnit is pointed at this file by tests/phpunit.xml.dist.
 */

// the library under test
require_once __DIR__ . '/../Zebra_Database.php';

// support classes (also listed in composer.json under autoload-dev, this is for running phpunit directly)
require_once __DIR__ . '/Support/DatabaseTestCase.php';
require_once __DIR__ . '/Support/DatabaseProbe.php';
require_once __DIR__ . '/Support/ChildProcess.php';
require_once __DIR__ . '/Support/ChildProcessHandle.php';

// the settings and the helpers - declarations only, no side effects, which is what lets phpcs and phpstan
// read them without any of the setup below running
require_once __DIR__ . '/settings.php';

// the scratch directories - "child" holds the scripts ChildProcess writes. Everything the suite writes goes
// under here, so that it is all cleaned up together and nothing depends on there being a /tmp
foreach ([TEST_TMP_PATH, TEST_TMP_PATH . '/cache', TEST_TMP_PATH . '/logs', TEST_TMP_PATH . '/child'] as $path)
    if (!is_dir($path)) mkdir($path, 0777, true);

register_shutdown_function('cleanupTempFiles');

/* ---------------------------------------------------------------------------------------------------------
 * DATABASE SETUP
 *
 * The fixture tables are created once for the whole run. Each test starts from empty ones - see
 * resetState() in the base class.
 * ------------------------------------------------------------------------------------------------------ */

try {

    // the port is cast because it arrives as a string from the environment while mysqli wants an integer -
    // the library's own connect() method takes it as a string, which is why the constant is left as one
    $connection = new mysqli(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, '', (int)TEST_DB_PORT);

    if ($connection->connect_error) throw new Exception('Could not connect to MySQL: ' . $connection->connect_error);

    // the tables are utf8mb4, so this connection has to be as well, or the rows written here go in through
    // whatever the server's default happens to be and the suite ends up testing that instead
    $connection->set_charset('utf8mb4');

    // what MySQL does with a value too big for its column depends on STRICT_TRANS_TABLES, so the suite
    // states which it expects and checks for it - a test that branches on the server it meets asserts nothing
    $mode = $connection->query('SELECT @@SESSION.sql_mode AS mode')->fetch_assoc();

    if (strpos($mode['mode'], 'STRICT_TRANS_TABLES') === false && strpos($mode['mode'], 'STRICT_ALL_TABLES') === false)
        throw new Exception(
            'The server this suite is pointed at does not run in strict mode (sql_mode is "' . $mode['mode'] . '").' . "\n"
            . 'Add STRICT_TRANS_TABLES to sql_mode in the server\'s configuration - it is the default from MySQL 5.7 onwards.'
        );

    $connection->query("CREATE DATABASE IF NOT EXISTS `" . TEST_DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $connection->select_db(TEST_DB_NAME);

    // the tables are dropped before being created rather than created only if missing: a database left over
    // from an older checkout would otherwise keep whatever shape it had then, and the suite would pass here
    // and fail in CI - or worse, the other way round - for reasons nothing in the repository can explain
    foreach (['test_products', 'test_categories', 'test_users'] as $table)
        $connection->query('DROP TABLE IF EXISTS `' . $table . '`');

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

} catch (Exception $error) {
    echo 'Database setup failed: ' . $error->getMessage() . "\n";
    echo "The suite needs a MySQL it can reach with the credentials in tests/phpunit.xml.dist.\n";
    exit(1);
}
