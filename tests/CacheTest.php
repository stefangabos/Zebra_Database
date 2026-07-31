<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Caching a query's result set, over each of the three backends the library speaks to - memcache, redis
 * and the disk - covering what is written, what is read back, and what is never cached at all.
 */
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

        // the first run is what fills the cache
        $this->db->query('SELECT * FROM test_users ORDER BY id', '', 60);
        $result1 = $this->db->fetch_assoc_all();
        $this->assertNotEmpty($result1);

        // read straight out of memcache, under the key the library computes
        $memcache = new Memcache();
        $memcache->connect(TEST_MEMCACHE_HOST, TEST_MEMCACHE_PORT);
        $cache_key = md5($this->db->memcache_key_prefix . 'SELECT * FROM test_users ORDER BY id');
        $cached_data = $memcache->get($cache_key);
        $memcache->close();

        $this->assertNotFalse($cached_data, 'Data should be cached in Memcache');

        // the same query again, which the cache answers
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

        // the first run is what fills the cache
        $this->db->query('SELECT * FROM test_users ORDER BY id', '', 60);
        $result1 = $this->db->fetch_assoc_all();
        $this->assertNotEmpty($result1);

        // read straight out of redis, under the key the library computes
        $redis = new Redis();
        $redis->connect(TEST_REDIS_HOST, TEST_REDIS_PORT);

        $cache_key = md5($this->db->redis_key_prefix . 'SELECT * FROM test_users ORDER BY id');
        $cached_data = $redis->get($cache_key);
        $redis->close();

        $this->assertNotFalse($cached_data, 'Data should be cached in Redis');

        // the same query again, which the cache answers
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

        $this->db->query('SELECT COUNT(*) as count FROM test_users', '', 60);
        $this->db->fetch_assoc_all();

        // the entry is filed under the prefix that was set, rather than the default one
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

        $this->db->query('SELECT COUNT(*) as count FROM test_users', '', 60);
        $this->db->fetch_assoc_all();

        // the entry is filed under the prefix that was set, rather than the default one
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

        $this->db->query('SELECT * FROM test_users ORDER BY id', '', 60);
        $result = $this->db->fetch_assoc_all();
        $this->assertNotEmpty($result);

        // the entry is there and readable, which is as far as memcache lets a test look
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
     * was stored exactly the same either way.
     *
     * What tells the two apart is the connection's own option, so that is what gets asked. Reading the
     * value back has to keep working either way, since the extension decompresses on the way out.
     *
     * @group regression
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

        return $this->probe([
            'caching_method'    => 'redis',
            'redis_host'        => TEST_REDIS_HOST,
            'redis_port'        => TEST_REDIS_PORT,
            'redis_key_prefix'  => 'zebra_test_',
            'redis_compressed'  => $compressed,
        ]);
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

        $this->db->query('SELECT * FROM test_users WHERE age > 25', '', 60);
        $this->db->fetch_assoc_all();
        $this->db->query('SELECT * FROM test_users WHERE age < 35', '', 60);
        $this->db->fetch_assoc_all();

        // each statement has an entry of its own, under a key of its own
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

        $this->db->query('SELECT * FROM test_users WHERE age = ?', [30], 60);
        $result1 = $this->db->fetch_assoc_all();
        $this->assertNotEmpty($result1);

        // the same statement with a different replacement is a different query
        $this->db->query('SELECT * FROM test_users WHERE age = ?', [25], 60);
        $result2 = $this->db->fetch_assoc_all();

        $this->assertNotEquals($result1, $result2, 'Different parameters should return different results');

        // and back to the first replacement, which is the one that was cached
        $this->db->query('SELECT * FROM test_users WHERE age = ?', [30], 60);
        $result3 = $this->db->fetch_assoc_all();
        $this->assertEquals($result1, $result3, 'Same parameterized query should return cached result');

        // the key is built from the statement with the replacements already in it
        $redis = new Redis();
        $redis->connect(TEST_REDIS_HOST, TEST_REDIS_PORT);
        $cache_key = md5($this->db->redis_key_prefix . 'SELECT * FROM test_users WHERE age = \'30\'');
        $cached_data = [@unserialize(gzuncompress(base64_decode($redis->get($cache_key))))[0]];
        $redis->close();
        $this->assertEquals($result1, $cached_data, 'Cached and non-cached versions should be the same');

    }

    public function testMemcacheConnectionFailure() {
        $this->db->caching_method = 'memcache';
        $this->db->memcache_host = 'nonexistent_host';
        $this->db->memcache_port = 99999;

        // a cache that cannot be reached does not take the query down with it
        $this->db->query('SELECT * FROM test_users ORDER BY id LIMIT 1');
        $result = $this->db->fetch_assoc_all();
        $this->assertNotEmpty($result, 'Query should work even when Memcache connection fails');
    }

    public function testRedisConnectionFailure() {
        $this->db->caching_method = 'redis';
        $this->db->redis_host = 'nonexistent_host';
        $this->db->redis_port = 99999;

        // a cache that cannot be reached does not take the query down with it
        $this->db->query('SELECT * FROM test_users ORDER BY id LIMIT 1');
        $result = $this->db->fetch_assoc_all();
        $this->assertNotEmpty($result, 'Query should work even when Redis connection fails');
    }

    // WHAT ACTUALLY GETS CACHED
    // (these use disk caching so that they run without a memcache or redis server being available)

    private function diskCachingProbe() {
        $path = getTempPath('cache');
        array_map('unlink', glob($path . '/*'));

        $db = $this->probe(['caching_method' => 'disk', 'cache_path' => $path]);

        return $db;
    }

    /**
     * Only queries that return a result set are cached. Passing a cache lifetime to an INSERT, UPDATE
     * or DELETE caches nothing, so such a query has to report that it was not served from cache - the flag
     * was set optimistically as soon as caching was asked for and cleared only inside the branch that
     * caches result sets, which an action query never reaches.
     *
     * @group regression
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

    }

    /**
     * A cache file left behind by an older version is compressed whatever the settings say, since leaving
     * the compressing to the caching server came later. Reading copes with both shapes - a changed setting,
     * or an upgrade, is no reason to hand the caller a miss or, worse, a warning
     *
     * @group regression
     */
    public function testACacheWrittenTheOldWayIsStillRead() {
        $db = $this->diskCachingProbe();

        // a file in the shape an older version of the library wrote: compressed, whatever the settings
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

    }

    /**
     * The observable half of the same thing - a cached action query really does run every time
     *
     * @group regression
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
    }
}
