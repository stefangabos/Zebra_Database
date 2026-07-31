<?php

/**
 * The base class every test class in this suite extends.
 *
 * It holds whatever the tests share - the instance under test, a clean slate before each test, cleanup
 * after each one, and the assertions used in more than one file - so that the test classes themselves
 * hold nothing but tests.
 */
abstract class DatabaseTestCase extends PHPUnit\Framework\TestCase {

    /**
     * The instance under test, fresh for every test
     *
     * @var Zebra_Database|null
     */
    protected $db;

    /**
     * Probes created during a test, shut down in tearDown() so that a failing assertion cannot leave one
     * behind
     *
     * @var array<DatabaseProbe>
     */
    private $probes = [];

    protected function setUp(): void {
        $this->db = new Zebra_Database();
        $this->db->debug = false;
        // an absolute path, so that the suite passes wherever it is started from - a relative one only
        // resolves when the working directory happens to be tests/
        $this->db->cache_path = getTempPath('cache');
        $this->resetState();
    }

    protected function tearDown(): void {

        foreach ($this->probes as $probe) $probe->shutdown();

        $this->probes = [];

        if ($this->db) {
            // the debugging console is printed by a shutdown function that reads this flag when it runs, so
            // any test that turned debugging on - or that failed before it could turn it back off - would
            // otherwise spill a whole console into the test output
            $this->db->debug = false;
            $this->db->close();
        }

        $this->db = null;

    }

    /**
     * Returns a probe - a subclass of the library that lets the tests read what it recorded about the
     * queries it ran.
     *
     * The probe is connected and debugging, since debug_info is only filled in while debugging is on, and
     * it is shut down in tearDown() - so a test does not have to remember to do it, and a failing assertion
     * cannot leave one behind to print its console into the test output.
     *
     * @param   array<string, mixed>    $settings   properties to set before connecting. The caching ones
     *                                              have to be in place by then, since establishing the
     *                                              connection is what reads them
     *
     * @return  DatabaseProbe
     */
    protected function probe($settings = []) {

        $probe = new DatabaseProbe();

        $probe->debug = true;
        $probe->halt_on_errors = false;
        $probe->cache_path = getTempPath('cache');

        foreach ($settings as $property => $value) $probe->$property = $value;

        $probe->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT);

        $this->probes[] = $probe;

        return $probe;

    }

    protected function connectToDatabase() {
        $this->db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT);

        // connect() is lazy, so a query is what says whether the connection can actually be made
        $result = $this->db->query("SELECT 1 as test");
        return $result !== false;
    }

    /**
     * Puts the world back the way a test expects to find it - empty fixture tables, with their
     * auto-increment counters back at the start.
     *
     * DELETE rather than TRUNCATE: TRUNCATE is a DDL statement, and at around 6ms against 0.2ms for the
     * delete it costs this suite several seconds per run for nothing. The counters do have to be reset
     * separately, since tests insert a row and then expect to find it at id 1 - which is the one thing
     * TRUNCATE does for us.
     *
     * Called from setUp() only: doing it in tearDown() as well cleans a database that the next setUp() is
     * about to clean again, and a test that dies half way through is followed by a setUp() regardless.
     *
     * @return  void
     */
    protected function resetState() {
        if ($this->db && $this->connectToDatabase()) {
            foreach (['test_users', 'test_products', 'test_categories'] as $table) {
                $this->db->query('DELETE FROM ' . $table);
                $this->db->query('ALTER TABLE ' . $table . ' AUTO_INCREMENT = 1');
            }
        }
    }

    /**
     * Puts a known set of rows in place for the tests that need something to work with.
     *
     * Keep this small and keep it stable - tests assert against these values by name, so adding a row here
     * can quietly change what a count somewhere else is supposed to be.
     *
     * @return  void
     */
    protected function insertTestData() {
        if (!$this->connectToDatabase()) {
            $this->fail('Failed to connect to test database');
        }

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

        $this->db->insert('test_categories', ['name' => 'Electronics']);
        $this->db->insert('test_categories', ['name' => 'Books']);
        $this->db->insert('test_categories', ['name' => 'Clothing']);

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
     * Whether there is both a memcache extension and a memcache server to talk to
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
     * Whether there is both a redis extension and a redis server to talk to
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
     * An instance caching to memcache.
     *
     * The caching settings are read while the connection is being made, so they have to be in place
     * before it is - which is why this replaces the instance rather than reconfiguring it
     */
    protected function setupMemcacheCache() {
        if ($this->db) {
            $this->db->close();
        }

        $this->db = new Zebra_Database();
        $this->db->debug = false;
        $this->db->cache_path = getTempPath('cache');
        $this->db->caching_method = 'memcache';
        $this->db->memcache_host = TEST_MEMCACHE_HOST;
        $this->db->memcache_port = TEST_MEMCACHE_PORT;
        $this->db->memcache_key_prefix = 'zebra_test_';
        $this->db->memcache_compressed = false;

        $this->connectToDatabase();
    }

    /**
     * An instance caching to redis, made the same way and for the same reason
     */
    protected function setupRedisCache() {
        if ($this->db) {
            $this->db->close();
        }

        $this->db = new Zebra_Database();
        $this->db->debug = false;
        $this->db->cache_path = getTempPath('cache');
        $this->db->caching_method = 'redis';
        $this->db->redis_host = TEST_REDIS_HOST;
        $this->db->redis_port = TEST_REDIS_PORT;
        $this->db->redis_key_prefix = 'zebra_test_';
        $this->db->redis_compressed = false;

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
