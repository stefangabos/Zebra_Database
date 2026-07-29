<?php

/**
 * Tests for Zebra_Database caching functionality (Memcache and Redis)
 */

require_once 'bootstrap.php';

class CacheTest extends DatabaseTestCase
{
    protected function setUp(): void {
        parent::setUp();
        $this->connectToDatabase();
        $this->insertTestData();
    }

    protected function tearDown(): void {
        $this->clearCache();
        parent::tearDown();
    }

    public function testMemcacheCachingBasic() {
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

    public function testRedisCachingBasic() {
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

    public function testMemcacheCacheKeyPrefix() {
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

    public function testRedisCacheKeyPrefix() {
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

    public function testMemcacheCompression() {
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

    /**
     * redis_compressed asks the redis extension to compress what it is given, the way memcache_compressed
     * asks memcache to. The property was declared and documented but never read by anything, so redis data
     * was stored exactly the same either way - and the test that used to be here set the property, checked
     * that *something* had been cached, and passed no matter what.
     *
     * What tells the two apart is the connection's own option, so that is what gets asked. Reading the
     * value back has to keep working either way, since the extension decompresses on the way out.
     */
    public function testRedisCompressionIsAskedOfTheExtension() {
        if (!$this->isRedisAvailable()) {
            $this->markTestSkipped('Redis extension not available or server not running');
        }

        if (!$this->isRedisCompressionAvailable()) {
            $this->markTestSkipped('This build of the redis extension cannot compress');
        }

        $query = 'SELECT * FROM test_users ORDER BY id';

        $db = $this->redisCachingProbe(true);

        $db->query($query, '', 60);
        $stored = $db->fetch_assoc_all();
        $this->assertNotEmpty($stored);

        // what the library hands to redis is base64 text - so if the extension really compressed it, what
        // is sitting on the server is not that text any more
        $raw = $this->rawRedisValue($query);

        $this->assertNotFalse($raw, 'Something has to have been cached');
        $this->assertDoesNotMatchRegularExpression('/^[A-Za-z0-9+\/=]+$/', $raw, 'Stored compressed rather than as the plain base64 payload');

        // and the library did not compress it as well: with the extension doing the work, what it was handed
        // is the serialized data as it is, so it unserializes without being uncompressed first
        $handed_over = base64_decode($this->rawRedisValue($query, true));

        $this->assertIsArray(@unserialize($handed_over), 'The library left the compressing to the extension');
        $this->assertFalse(@gzuncompress($handed_over), 'Rather than compressing it a second time');

        // and it still reads back, which is the half that breaks if only the writing side compresses
        $db->query($query, '', 60);

        $this->assertTrue($db->lastFromCache(), 'The second read comes from the cache');
        $this->assertEquals($stored, $db->fetch_assoc_all(), 'And gives back what was put in');

        $db->shutdown();
    }

    public function testRedisIsNotAskedToCompressUnlessItIsAskedFor() {
        if (!$this->isRedisAvailable()) {
            $this->markTestSkipped('Redis extension not available or server not running');
        }

        if (!$this->isRedisCompressionAvailable()) {
            $this->markTestSkipped('This build of the redis extension cannot compress');
        }

        $query = 'SELECT * FROM test_users ORDER BY id';

        $db = $this->redisCachingProbe(false);

        $db->query($query, '', 60);
        $db->fetch_assoc_all();

        // left alone, what reaches the server is exactly what the library produced - the library compresses
        // what it caches either way, so this is not uncompressed data, only data compressed once
        $this->assertMatchesRegularExpression(
            '/^[A-Za-z0-9+\/=]+$/',
            $this->rawRedisValue($query),
            'The default is to leave the extension alone'
        );

        $db->shutdown();
    }

    /**
     * Whether this build of the redis extension can compress at all.
     *
     * Asked of the extension rather than worked out from what the library logged: the message the library
     * writes is a translated string, so a test that reads it is one rewording away from silently not
     * skipping any more - and then failing on the builds it was meant to step around.
     */
    private function isRedisCompressionAvailable() {
        if (!defined('Redis::OPT_COMPRESSION') || !defined('Redis::COMPRESSION_LZF')) return false;

        $redis = new Redis();
        $redis->connect(TEST_REDIS_HOST, TEST_REDIS_PORT);

        try {
            $available = @$redis->setOption(Redis::OPT_COMPRESSION, Redis::COMPRESSION_LZF)
                && $redis->getOption(Redis::OPT_COMPRESSION) == Redis::COMPRESSION_LZF;
        } catch (Exception $error) {
            $available = false;
        }

        $redis->close();

        return $available;
    }

    /**
     * A probe caching to redis, connected through the library so that the option really is the one the
     * library set rather than one the test set behind its back
     */
    private function redisCachingProbe($compressed) {
        $redis = new Redis();
        $redis->connect(TEST_REDIS_HOST, TEST_REDIS_PORT);
        $redis->flushAll();
        $redis->close();

        $db = new DatabaseProbe();
        $db->debug = true;
        $db->halt_on_errors = false;
        $db->cache_path = getTempPath('cache');
        $db->caching_method = 'redis';
        $db->redis_host = TEST_REDIS_HOST;
        $db->redis_port = TEST_REDIS_PORT;
        $db->redis_key_prefix = 'zebra_test_';
        $db->redis_compressed = $compressed;
        $db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT);

        return $db;
    }

    /**
     * The bytes sitting on the redis server for the given query, read over a connection that was told
     * nothing about compression - so what comes back is what is actually stored
     */
    private function rawRedisValue($query, $decompressing = false) {
        $redis = new Redis();
        $redis->connect(TEST_REDIS_HOST, TEST_REDIS_PORT);

        // asking for it the way the library would, so that the extension undoes its own compression on the
        // way out and what comes back is what the library handed over
        if ($decompressing) $redis->setOption(Redis::OPT_COMPRESSION, Redis::COMPRESSION_LZF);

        $value = $redis->get(md5('zebra_test_' . $query));

        $redis->close();

        return $value;
    }

    public function testDifferentQueriesDifferentCacheKeys() {
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

    public function testCacheWithParameters() {
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

    public function testMemcacheConnectionFailure() {
        // Test graceful handling when Memcache server is not available
        $this->db->caching_method = 'memcache';
        $this->db->memcache_host = 'nonexistent_host';
        $this->db->memcache_port = 99999;

        // Query should still work even if caching fails
        $this->db->query('SELECT * FROM test_users ORDER BY id LIMIT 1');
        $result = $this->db->fetch_assoc_all();
        $this->assertNotEmpty($result, 'Query should work even when Memcache connection fails');
    }

    public function testRedisConnectionFailure() {
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
     * Nothing is asking the disk to compress anything, so the library does it - which is what keeps a cache
     * file from being the query's whole result set written out in the clear
     */
    public function testWhatIsCachedToDiskIsCompressedByTheLibrary() {
        $db = $this->diskCachingProbe();

        $db->query('SELECT * FROM test_users', '', 60);

        $files = glob(getTempPath('cache') . '/*');

        $this->assertCount(1, $files, 'One query, one cache file');

        $content = base64_decode(file_get_contents($files[0]));

        $this->assertNotFalse(@gzuncompress($content), 'The file holds compressed data');
        $this->assertIsArray(@unserialize(gzuncompress($content)), 'And that uncompresses to the rows');

        $db->shutdown();
    }

    /**
     * Cache files written before the library learned to leave the compressing to the caching server are all
     * compressed, whatever the settings said. Reading has to cope with both shapes - a changed setting, or an
     * upgrade, is no reason to hand the caller a miss or, worse, a warning
     */
    public function testACacheWrittenTheOldWayIsStillRead() {
        $db = $this->diskCachingProbe();

        // what an older version of the library would have left behind: always compressed
        $rows = [['id' => '1', 'name' => 'Written by an older version'], ['returned_rows' => 1, 'found_rows' => 0, 'column_info' => []]];

        file_put_contents(
            getTempPath('cache') . '/' . md5('SELECT * FROM test_users'),
            base64_encode(gzcompress(serialize($rows)))
        );

        $raised = $this->diagnosticsRaisedBy(function() use ($db) {
            $db->query('SELECT * FROM test_users', '', 60);
        });

        $this->assertSame([], $raised, 'Reading it must not warn');
        $this->assertTrue($db->lastFromCache(), 'The old file is a hit rather than a miss');
        $this->assertSame('Written by an older version', $db->fetch_assoc()['name']);

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
