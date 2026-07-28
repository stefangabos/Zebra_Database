<?php

/**
 * Base test class for database tests
 * Provides common functionality for all Zebra_Database tests
 */
abstract class DatabaseTestCase extends PHPUnit\Framework\TestCase {

    protected $db;

    protected function setUp(): void {
        $this->db = new Zebra_Database();
        $this->db->debug = false;
        $this->db->cache_path = 'tmp/cache/';
        $this->cleanDatabase();
    }

    protected function tearDown(): void {
        if ($this->db) {
            // the debugging console is printed by a shutdown function that reads this flag when it runs, so
            // any test that turned debugging on - or that failed before it could turn it back off - would
            // otherwise spill a whole console into the test output
            $this->db->debug = false;
            $this->cleanDatabase();
            $this->db->close();
        }
        $this->db = null;
    }

    protected function connectToDatabase() {
        $this->db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT);

        // Test the connection by running a simple query
        $result = $this->db->query("SELECT 1 as test");
        return $result !== false;
    }

    protected function cleanDatabase() {
        if ($this->db && $this->connectToDatabase()) {
            $this->db->query("TRUNCATE TABLE test_users");
            $this->db->query("TRUNCATE TABLE test_products");
            $this->db->query("TRUNCATE TABLE test_categories");
        }
    }

    protected function insertTestData() {
        if (!$this->connectToDatabase()) {
            $this->fail('Failed to connect to test database');
        }

        // Insert test users
        $this->db->insert('test_users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
            'score' => 85.50,
            'is_active' => 1
        ]);

        $this->db->insert('test_users', [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'age' => 25,
            'score' => 92.75,
            'is_active' => 1
        ]);

        $this->db->insert('test_users', [
            'name' => 'Bob Johnson',
            'email' => 'bob@example.com',
            'age' => 35,
            'score' => 78.25,
            'is_active' => 0
        ]);

        // Insert test categories
        $this->db->insert('test_categories', ['name' => 'Electronics']);
        $this->db->insert('test_categories', ['name' => 'Books']);
        $this->db->insert('test_categories', ['name' => 'Clothing']);

        // Insert test products
        $this->db->insert('test_products', [
            'name' => 'Laptop',
            'price' => 999.99,
            'category_id' => 1
        ]);

        $this->db->insert('test_products', [
            'name' => 'Novel',
            'price' => 19.99,
            'category_id' => 2
        ]);
    }

    /**
     * Check if Memcache extension and server are available
     */
    protected function isMemcacheAvailable() {
        if (!extension_loaded('memcache')) {
            return false;
        }

        $memcache = new Memcache();
        $connected = @$memcache->connect(TEST_MEMCACHE_HOST, TEST_MEMCACHE_PORT);
        if ($connected) {
            $memcache->close();
            return true;
        }
        return false;
    }

    /**
     * Check if Redis extension and server are available
     */
    protected function isRedisAvailable() {
        if (!extension_loaded('redis')) {
            return false;
        }

        $redis = new Redis();
        try {
            $connected = $redis->connect(TEST_REDIS_HOST, TEST_REDIS_PORT);
            if ($connected) {
                $redis->close();
                return true;
            }
        } catch (Exception $e) {
            return false;
        }
        return false;
    }

    /**
     * Setup Memcache caching for database instance
     * Note: Must be called BEFORE connect()
     */
    protected function setupMemcacheCache() {
        // Close existing connection if any
        if ($this->db) {
            $this->db->close();
        }

        // Create new instance and configure caching BEFORE connecting
        $this->db = new Zebra_Database();
        $this->db->debug = false;
        // $this->db->cache_path = sys_get_temp_dir();
        $this->db->caching_method = 'memcache';
        $this->db->memcache_host = TEST_MEMCACHE_HOST;
        $this->db->memcache_port = TEST_MEMCACHE_PORT;
        $this->db->memcache_key_prefix = 'zebra_test_';
        $this->db->memcache_compressed = false;

        // Now connect with caching configured
        $this->connectToDatabase();
    }

    /**
     * Setup Redis caching for database instance
     * Note: Must be called BEFORE connect()
     */
    protected function setupRedisCache() {
        // Close existing connection if any
        if ($this->db) {
            $this->db->close();
        }

        // Create new instance and configure caching BEFORE connecting
        $this->db = new Zebra_Database();
        $this->db->debug = false;
        // $this->db->cache_path = sys_get_temp_dir();
        $this->db->caching_method = 'redis';
        $this->db->redis_host = TEST_REDIS_HOST;
        $this->db->redis_port = TEST_REDIS_PORT;
        $this->db->redis_key_prefix = 'zebra_test_';
        $this->db->redis_compressed = false;

        // Now connect with caching configured
        $this->connectToDatabase();
    }

    /**
     * Clear all cache entries for testing
     */
    protected function clearCache() {
        if ($this->db->caching_method === 'memcache' && $this->isMemcacheAvailable()) {
            $memcache = new Memcache();
            $memcache->connect(TEST_MEMCACHE_HOST, TEST_MEMCACHE_PORT);
            $memcache->flush();
            $memcache->close();
        } elseif ($this->db->caching_method === 'redis' && $this->isRedisAvailable()) {
            $redis = new Redis();
            $redis->connect(TEST_REDIS_HOST, TEST_REDIS_PORT);
            $redis->flushAll();
            $redis->close();
        }
    }

    /**
     * Assert that a query result contains expected data
     */
    protected function assertQueryResultContains($result, $expectedData, $message = '') {
        $this->assertNotFalse($result, "Query should succeed. " . $message);

        $rows = $this->db->fetch_assoc_all($result);
        $this->assertNotEmpty($rows, "Query should return data. " . $message);

        $found = false;
        foreach ($rows as $row) {
            $matches = true;
            foreach ($expectedData as $key => $value) {
                if (!isset($row[$key]) || $row[$key] != $value) {
                    $matches = false;
                    break;
                }
            }
            if ($matches) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, "Expected data not found in query results. " . $message);
    }

    /**
     * Create a temporary table for testing
     */
    protected function createTempTable($tableName, $schema) {
        $this->connectToDatabase();
        $this->db->query("DROP TABLE IF EXISTS $tableName");
        $this->db->query("CREATE TEMPORARY TABLE $tableName $schema");

        // Verify table was created
        $result = $this->db->query("SHOW TABLES LIKE '$tableName'");
        $this->assertNotFalse($result, "Temporary table $tableName should be created");
    }

    /**
     * Get a fresh database instance for testing isolation
     */
    protected function getFreshDatabaseInstance() {
        $fresh_db = new Zebra_Database();
        $fresh_db->debug = false;
        $fresh_db->cache_path = sys_get_temp_dir();
        $fresh_db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT);
        return $fresh_db;
    }

    /**
     * Assert that two database states are identical
     */
    protected function assertDatabaseStateEquals($expectedData, $table, $message = '') {
        $result = $this->db->query("SELECT * FROM $table ORDER BY id");
        $actualData = $this->db->fetch_assoc_all($result);

        $this->assertEquals($expectedData, $actualData, "Database state mismatch. " . $message);
    }
}
