<?php

/**
 * Tests for Zebra_Database caching functionality (Memcache and Redis)
 */

require_once 'bootstrap.php';

class CacheTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->connectToDatabase();
        $this->insertTestData();
    }

    protected function tearDown(): void
    {
        $this->clearCache();
        parent::tearDown();
    }

    public function testMemcacheCachingBasic()
    {
        if (!$this->isMemcacheAvailable()) {
            $this->markTestSkipped('Memcache extension not available or server not running');
        }

        $this->setupMemcacheCache();
        $this->clearCache();

        // First query - should be cached
        $this->db->query('SELECT * FROM test_users ORDER BY id', '', 60);
        $result1 = $this->db->fetch_assoc_all();
        $this->assertNotEmpty($result1);

        // Verify cache was created by checking Memcache directly
        $memcache = new Memcache();
        $memcache->connect(TEST_MEMCACHE_HOST, TEST_MEMCACHE_PORT);
        // The cache key format should match what Zebra_Database uses
        $cache_key = md5($this->db->memcache_key_prefix . 'SELECT * FROM test_users ORDER BY id');
        $cached_data = $memcache->get($cache_key);
        $memcache->close();

        $this->assertNotFalse($cached_data, 'Data should be cached in Memcache');

        // Second identical query - should come from cache
        $this->db->query('SELECT * FROM test_users ORDER BY id', '', 60);
        $result2 = $this->db->fetch_assoc_all();
        $this->assertEquals($result1, $result2, 'Cached results should match original results');
    }

    public function testRedisCachingBasic()
    {
        if (!$this->isRedisAvailable()) {
            $this->markTestSkipped('Redis extension not available or server not running');
        }

        $this->setupRedisCache();
        $this->clearCache();

        // First query - should be cached
        $this->db->query('SELECT * FROM test_users ORDER BY id', '', 60);
        $result1 = $this->db->fetch_assoc_all();
        $this->assertNotEmpty($result1);

        // Verify cache was created by checking Redis directly
        $redis = new Redis();
        $redis->connect(TEST_REDIS_HOST, TEST_REDIS_PORT);

        // The cache key format should match what Zebra_Database uses
        $cache_key = md5($this->db->redis_key_prefix . 'SELECT * FROM test_users ORDER BY id');
        $cached_data = $redis->get($cache_key);
        $redis->close();

        $this->assertNotFalse($cached_data, 'Data should be cached in Redis');

        // Second identical query - should come from cache
        $this->db->query('SELECT * FROM test_users ORDER BY id', '', 60);
        $result2 = $this->db->fetch_assoc_all();
        $this->assertEquals($result1, $result2, 'Cached results should match original results');
    }

    public function testMemcacheCacheKeyPrefix()
    {
        if (!$this->isMemcacheAvailable()) {
            $this->markTestSkipped('Memcache extension not available or server not running');
        }

        $this->setupMemcacheCache();
        $this->db->memcache_key_prefix = 'custom_prefix_';
        $this->clearCache();

        // Execute query to create cache
        $this->db->query('SELECT COUNT(*) as count FROM test_users', '', 60);
        $this->db->fetch_assoc_all();

        // Check that cache key uses custom prefix
        $memcache = new Memcache();
        $memcache->connect(TEST_MEMCACHE_HOST, TEST_MEMCACHE_PORT);

        $cache_key = md5('custom_prefix_' . 'SELECT COUNT(*) as count FROM test_users');
        $cached_data = $memcache->get($cache_key);
        $memcache->close();

        $this->assertNotFalse($cached_data, 'Cache should exist with custom prefix');
    }

    public function testRedisCacheKeyPrefix()
    {
        if (!$this->isRedisAvailable()) {
            $this->markTestSkipped('Redis extension not available or server not running');
        }

        $this->setupRedisCache();
        $this->db->redis_key_prefix = 'custom_redis_';
        $this->clearCache();

        // Execute query to create cache
        $this->db->query('SELECT COUNT(*) as count FROM test_users', '', 60);
        $this->db->fetch_assoc_all();

        // Check that cache key uses custom prefix
        $redis = new Redis();
        $redis->connect(TEST_REDIS_HOST, TEST_REDIS_PORT);

        $cache_key = md5('custom_redis_' . 'SELECT COUNT(*) as count FROM test_users');
        $cached_data = $redis->get($cache_key);
        $redis->close();

        $this->assertNotFalse($cached_data, 'Cache should exist with custom prefix');
    }

    public function testMemcacheCompression()
    {
        if (!$this->isMemcacheAvailable()) {
            $this->markTestSkipped('Memcache extension not available or server not running');
        }

        $this->setupMemcacheCache();
        $this->db->memcache_compressed = true;
        $this->clearCache();

        // Execute query that should create a compressed cache entry
        $this->db->query('SELECT * FROM test_users ORDER BY id', '', 60);
        $result = $this->db->fetch_assoc_all();
        $this->assertNotEmpty($result);

        // Verify cache exists (we can't easily verify compression, but we can verify it works)
        $memcache = new Memcache();
        $memcache->connect(TEST_MEMCACHE_HOST, TEST_MEMCACHE_PORT);

        $cache_key = md5($this->db->memcache_key_prefix . 'SELECT * FROM test_users ORDER BY id');
        $cached_data = $memcache->get($cache_key);
        $memcache->close();

        $this->assertNotFalse($cached_data, 'Compressed data should be cached');
    }

    public function testRedisCompression()
    {
        if (!$this->isRedisAvailable()) {
            $this->markTestSkipped('Redis extension not available or server not running');
        }

        $this->setupRedisCache();
        $this->db->redis_compressed = true;
        $this->clearCache();

        // Execute query that should create a compressed cache entry
        $this->db->query('SELECT * FROM test_users ORDER BY id', '', 60);
        $result = $this->db->fetch_assoc_all();
        $this->assertNotEmpty($result);

        // Verify cache exists (we can't easily verify compression, but we can verify it works)
        $redis = new Redis();
        $redis->connect(TEST_REDIS_HOST, TEST_REDIS_PORT);

        $cache_key = md5($this->db->redis_key_prefix . 'SELECT * FROM test_users ORDER BY id');
        $cached_data = $redis->get($cache_key);
        $redis->close();

        $this->assertNotFalse($cached_data, 'Compressed data should be cached');
    }

    public function testDifferentQueriesDifferentCacheKeys()
    {
        if (!$this->isMemcacheAvailable()) {
            $this->markTestSkipped('Memcache extension not available or server not running');
        }

        $this->setupMemcacheCache();
        $this->clearCache();

        // Execute different queries
        $this->db->query('SELECT * FROM test_users WHERE age > 25', '', 60);
        $this->db->fetch_assoc_all();
        $this->db->query('SELECT * FROM test_users WHERE age < 35', '', 60);
        $this->db->fetch_assoc_all();

        // Check that both queries created separate cache entries
        $memcache = new Memcache();
        $memcache->connect(TEST_MEMCACHE_HOST, TEST_MEMCACHE_PORT);

        $cache_key1 = md5($this->db->memcache_key_prefix . 'SELECT * FROM test_users WHERE age > 25');
        $cache_key2 = md5($this->db->memcache_key_prefix . 'SELECT * FROM test_users WHERE age < 35');

        $cached_data1 = $memcache->get($cache_key1);
        $cached_data2 = $memcache->get($cache_key2);
        $memcache->close();

        $this->assertNotFalse($cached_data1, 'First query should be cached');
        $this->assertNotFalse($cached_data2, 'Second query should be cached');
        $this->assertNotEquals($cached_data1, $cached_data2, 'Different queries should have different cache data');
    }

    public function testCacheWithParameters()
    {
        if (!$this->isRedisAvailable()) {
            $this->markTestSkipped('Redis extension not available or server not running');
        }

        $this->setupRedisCache();
        $this->clearCache();

        // Execute parameterized query
        $this->db->query('SELECT * FROM test_users WHERE age = ?', [30], 60);
        $result1 = $this->db->fetch_assoc_all();
        $this->assertNotEmpty($result1);

        // Same query with different parameter
        $this->db->query('SELECT * FROM test_users WHERE age = ?', [25], 60);
        $result2 = $this->db->fetch_assoc_all();

        $this->assertNotEquals($result1, $result2, 'Different parameters should return different results');

        // Same query with same parameter should come from cache
        $this->db->query('SELECT * FROM test_users WHERE age = ?', [30], 60);
        $result3 = $this->db->fetch_assoc_all();
        $this->assertEquals($result1, $result3, 'Same parameterized query should return cached result');

        // Check that both queries created separate cache entries
        $redis = new Redis();
        $redis->connect(TEST_REDIS_HOST, TEST_REDIS_PORT);
        $cache_key = md5($this->db->redis_key_prefix . 'SELECT * FROM test_users WHERE age = \'30\'');
        $cached_data = [@unserialize(gzuncompress(base64_decode($redis->get($cache_key))))[0]];
        $redis->close();
        $this->assertEquals($result1, $cached_data, 'Cached and non-cached versions should be the same');

    }

    public function testMemcacheConnectionFailure()
    {
        // Test graceful handling when Memcache server is not available
        $this->db->caching_method = 'memcache';
        $this->db->memcache_host = 'nonexistent_host';
        $this->db->memcache_port = 99999;

        // Query should still work even if caching fails
        $this->db->query('SELECT * FROM test_users ORDER BY id LIMIT 1');
        $result = $this->db->fetch_assoc_all();
        $this->assertNotEmpty($result, 'Query should work even when Memcache connection fails');
    }

    public function testRedisConnectionFailure()
    {
        // Test graceful handling when Redis server is not available
        $this->db->caching_method = 'redis';
        $this->db->redis_host = 'nonexistent_host';
        $this->db->redis_port = 99999;

        // Query should still work even if caching fails
        $this->db->query('SELECT * FROM test_users ORDER BY id LIMIT 1');
        $result = $this->db->fetch_assoc_all();
        $this->assertNotEmpty($result, 'Query should work even when Redis connection fails');
    }

    // WHAT ACTUALLY GETS CACHED
    // (these use disk caching so that they run without a memcache or redis server being available)

    private function diskCachingProbe() {
        $path = getTempPath('cache');
        array_map('unlink', glob($path . '/*'));

        $db = new DatabaseProbe();
        $db->debug = true;
        $db->halt_on_errors = false;
        $db->caching_method = 'disk';
        $db->cache_path = $path;
        $db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT);

        return $db;
    }

    /**
     * Only queries that return a result set are cached. Passing a cache lifetime to an INSERT, UPDATE
     * or DELETE caches nothing, so such a query has to report that it was not served from cache - the
     * flag used to be set optimistically as soon as caching was asked for and only cleared inside the
     * branch that caches result sets, which action queries never reach.
     */
    public function testActionQueriesAreNotReportedAsComingFromCache() {
        $db = $this->diskCachingProbe();

        $db->query('SELECT * FROM test_users', '', 60);
        $this->assertFalse($db->lastFromCache(), 'A cache miss is not "from cache"');

        $db->query('SELECT * FROM test_users', '', 60);
        $this->assertTrue($db->lastFromCache(), 'The second identical query is a cache hit');

        $db->query('UPDATE test_users SET age = age', '', 60);
        $this->assertFalse($db->lastFromCache(), 'An action query is never served from cache');

        $db->query("INSERT INTO test_users (name, email, age) VALUES ('Cached Action', 'ca@example.com', 20)", '', 60);
        $this->assertFalse($db->lastFromCache(), 'Neither is an insert');

        $db->shutdown();
    }

    /**
     * And the observable half of the same thing - a cached action query really does run every time.
     */
    public function testCachedActionQueriesStillExecuteEveryTime() {
        $db = $this->diskCachingProbe();

        $db->query('DROP TABLE IF EXISTS test_counter');
        $db->query('CREATE TABLE test_counter (id INT)');
        $db->query('INSERT INTO test_counter VALUES (1)');

        // the same statement, with a cache lifetime, three times over
        for ($i = 0; $i < 3; $i++) $db->query('UPDATE test_counter SET id = id + 1', '', 60);

        $row = $db->fetch_assoc($db->query('SELECT id FROM test_counter'));

        $this->assertEquals(4, (int)$row['id'], 'Each of the three updates must have run - 1 + 3');

        $db->query('DROP TABLE test_counter');
        $db->shutdown();
    }
}
