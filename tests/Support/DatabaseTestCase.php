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
        // an absolute path, so that the suite passes wherever it is started from - a relative one only
        // resolves when the working directory happens to be tests/
        $this->db->cache_path = getTempPath('cache');
        $this->cleanDatabase();
    }

    protected function tearDown(): void {
        if ($this->db) {
            // the debugging console is printed by a shutdown function that reads this flag when it runs, so
            // any test that turned debugging on - or that failed before it could turn it back off - would
            // otherwise spill a whole console into the test output
            $this->db->debug = false;
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

    /**
     * Empties the fixture tables and puts their auto-increment counters back to where a test expects to
     * find them.
     *
     * DELETE rather than TRUNCATE: TRUNCATE is a DDL statement, and at around 6ms against 0.2ms for the
     * delete it was costing this suite several seconds per run for nothing. The counters do have to be
     * reset separately, since tests insert a row and then expect to find it at id 1 - which is the one
     * thing TRUNCATE was doing for us.
     *
     * Called from setUp() only. It used to run in tearDown() as well, which cleaned a database that the
     * next setUp() was about to clean again.
     */
    protected function cleanDatabase() {
        if ($this->db && $this->connectToDatabase()) {
            foreach (['test_users', 'test_products', 'test_categories'] as $table) {
                $this->db->query('DELETE FROM ' . $table);
                $this->db->query('ALTER TABLE ' . $table . ' AUTO_INCREMENT = 1');
            }
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
     * Runs the given callback and returns every PHP diagnostic it raised.
     *
     * Use this to assert that the library does its job without also warning, noticing or deprecating - a
     * good number of the bugs in this library have been of the "it works, but it warns" kind, and on the
     * newer PHP versions today's deprecation is tomorrow's fatal error.
     *
     * @param   callable    $callback   the code to watch
     *
     * @return  array<string>           the messages raised, in the order they were raised
     */
    protected function diagnosticsRaisedBy($callback) {
        $raised = [];

        set_error_handler(function($number, $message) use (&$raised) {
            // a handler is called even for diagnostics the library deliberately silenced with "@", and
            // those are not something the user ever sees - error_reporting() is what tells them apart
            if (!(error_reporting() & $number)) return true;
            $raised[] = $message;
            return true;
        });

        try {
            call_user_func($callback);
        } catch (Exception $exception) {
            restore_error_handler();
            throw $exception;
        }

        restore_error_handler();

        return $raised;
    }

    /**
     * Asserts that the callback raised no PHP diagnostics at all
     *
     * @param   callable    $callback
     * @param   string      $message
     *
     * @return  void
     */
    protected function assertRaisesNoDiagnostics($callback, $message = '') {
        $this->assertSame([], $this->diagnosticsRaisedBy($callback), $message);
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
        $this->db->cache_path = getTempPath('cache');
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
        $this->db->cache_path = getTempPath('cache');
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

}
