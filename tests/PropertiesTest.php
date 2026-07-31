<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * The public properties - what each one defaults to, what assigning to it does, and the fact that the
 * library stores whatever it is given rather than coercing or validating any of it.
 */
class PropertiesTest extends DatabaseTestCase
{
    protected function setUp(): void {
        parent::setUp();
        $this->connectToDatabase();
    }

    // AUTO_QUOTE_REPLACEMENTS TESTS

    public function testAutoQuoteReplacementsDefault() {
        $this->assertTrue($this->db->auto_quote_replacements);
    }

    public function testAutoQuoteReplacementsEnabled() {
        $this->db->auto_quote_replacements = true;

        $this->db->insert('test_users', [
            'name' => "Test User",
            'email' => 'test@example.com',
            'age' => 25
        ]);

        $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", ["Test User"]);
        $row = $this->db->fetch_assoc($result);

        $this->assertEquals("Test User", $row['name']);
    }

    public function testAutoQuoteReplacementsDisabled() {
        $this->db->auto_quote_replacements = false;

        // the shorthand methods quote their own values, so they are unaffected by this property
        $this->db->insert('test_users', [
            'name' => "Test User No Quotes",
            'email' => 'noquotes@example.com',
            'age' => 30
        ]);

        // with the property off the value is escaped but not quoted, so the SQL provides the quotes -
        // which is what lets a replacement be something other than a value, such as a column name
        $result = $this->db->query("SELECT * FROM test_users WHERE name = '?'", ["Test User No Quotes"]);
        $row = $this->db->fetch_assoc($result);

        $this->assertEquals("Test User No Quotes", $row['name']);

        $this->db->auto_quote_replacements = true;
    }

    // CACHE_PATH TESTS

    /**
     * The default is a "cache" directory beside the library rather than nothing at all, which is what
     * makes disk caching work without any setup.
     *
     * Asked of a fresh instance on purpose - setUp() points the shared one at the suite's own scratch
     * directory, so asking that one would be reading back the override rather than the default.
     */
    public function testCachePathDefault() {
        $untouched = new Zebra_Database();

        $this->assertIsString($untouched->cache_path);
        $this->assertStringEndsWith('/cache/', $untouched->cache_path);
    }

    public function testCachePathSetting() {
        $cache_path = getTempPath('cache');
        $this->db->cache_path = $cache_path;

        $this->assertEquals($cache_path, $this->db->cache_path);
    }

    // CACHING_METHOD TESTS

    public function testCachingMethodDefault() {
        $this->assertEquals('disk', $this->db->caching_method);
    }

    public function testCachingMethodDisk() {
        $this->db->caching_method = 'disk';
        $this->assertEquals('disk', $this->db->caching_method);
    }

    public function testCachingMethodSession() {
        $this->db->caching_method = 'session';
        $this->assertEquals('session', $this->db->caching_method);
    }

    public function testCachingMethodMemcache() {
        $this->db->caching_method = 'memcache';
        $this->assertEquals('memcache', $this->db->caching_method);
    }

    public function testCachingMethodRedis() {
        $this->db->caching_method = 'redis';
        $this->assertEquals('redis', $this->db->caching_method);
    }

    // DEBUG TESTS

    public function testDebugDefault() {
        // a fresh instance, since setUp turns debugging off on the shared one
        $fresh_db = new Zebra_Database();
        $this->assertTrue($fresh_db->debug);

        $this->assertFalse($this->db->debug);
    }

    public function testDebugBoolean() {
        $this->db->debug = false;
        $this->assertFalse($this->db->debug);

        $this->db->debug = true;
        $this->assertTrue($this->db->debug);

        $this->db->debug = false;
    }

    public function testDebugString() {
        $debug_key = 'debug_zebra_db';
        $this->db->debug = $debug_key;

        $this->assertEquals($debug_key, $this->db->debug);
    }

    public function testDebugArray() {
        // debug being an array turns the console into a log file, which needs somewhere to write to
        $this->db->log_path = getTempPath('logs') . '/debug_test.log';

        $debug_config = [true, false, true];
        $this->db->debug = $debug_config;

        $this->assertEquals($debug_config, $this->db->debug);

        $this->db->debug = false;
    }

    // DEBUG RELATED PROPERTIES

    public function testDebugAjaxDefault() {
        $this->assertFalse($this->db->debug_ajax);
    }

    public function testDebugCookieNameDefault() {
        $this->assertEquals('zebra_db', $this->db->debug_cookie_name);
    }

    public function testDebugShowBacktraceDefault() {
        $this->assertTrue($this->db->debug_show_backtrace);
    }

    /**
     * Unlike its neighbours this one is not a boolean - it holds the markup shown in the debugging console
     * until the user replaces it with a link to their own database manager
     */
    public function testDebugShowDatabaseManagerDefault() {
        $this->assertIsString($this->db->debug_show_database_manager);
        $this->assertStringContainsString('SET UP', $this->db->debug_show_database_manager);
    }

    public function testDebugShowExplainDefault() {
        $this->assertTrue($this->db->debug_show_explain);
    }

    public function testDebugShowGlobalsDefault() {
        $this->assertTrue($this->db->debug_show_globals);
    }

    public function testDebugShowRecordsDefault() {
        $this->assertEquals(20, $this->db->debug_show_records);
    }

    // HALT_ON_ERRORS TESTS

    public function testHaltOnErrorsDefault() {
        $this->assertTrue($this->db->halt_on_errors);
    }

    public function testHaltOnErrorsDisabled() {
        $this->db->halt_on_errors = false;
        $this->assertFalse($this->db->halt_on_errors);

        // with it off a failing query comes back rather than ending the script
        $result = $this->db->query("SELECT * FROM nonexistent_table");
        $this->assertFalse($result);

        $this->db->halt_on_errors = true;
    }

    // MAX_QUERY_TIME TESTS

    public function testMaxQueryTimeDefault() {
        $this->assertEquals(10, $this->db->max_query_time);
    }

    public function testMaxQueryTimeSetting() {
        $this->db->max_query_time = 30;
        $this->assertEquals(30, $this->db->max_query_time);
    }

    // NOTIFICATION PROPERTIES

    public function testNotificationAddressDefault() {
        $this->assertEquals('', $this->db->notification_address);
    }

    public function testNotificationAddressSetting() {
        $email = 'admin@example.com';
        $this->db->notification_address = $email;
        $this->assertEquals($email, $this->db->notification_address);
    }

    public function testNotifierDomainDefault() {
        $this->assertEquals('', $this->db->notifier_domain);
    }

    public function testNotifierDomainSetting() {
        $domain = 'example.com';
        $this->db->notifier_domain = $domain;
        $this->assertEquals($domain, $this->db->notifier_domain);
    }

    // MEMCACHE PROPERTIES

    public function testMemcacheCompressedDefault() {
        $this->assertFalse($this->db->memcache_compressed);
    }

    public function testMemcacheHostDefault() {
        $this->assertFalse($this->db->memcache_host);
    }

    public function testMemcacheKeyPrefixDefault() {
        $this->assertEquals('', $this->db->memcache_key_prefix);
    }

    public function testMemcachePortDefault() {
        $this->assertFalse($this->db->memcache_port);
    }

    public function testMemcachePropertiesSettings() {
        $this->db->memcache_host = 'localhost';
        $this->db->memcache_port = 11211;
        $this->db->memcache_compressed = true;
        $this->db->memcache_key_prefix = 'zebra_';

        $this->assertEquals('localhost', $this->db->memcache_host);
        $this->assertEquals(11211, $this->db->memcache_port);
        $this->assertTrue($this->db->memcache_compressed);
        $this->assertEquals('zebra_', $this->db->memcache_key_prefix);
    }

    // REDIS PROPERTIES

    public function testRedisCompressedDefault() {
        $this->assertFalse($this->db->redis_compressed);
    }

    public function testRedisHostDefault() {
        $this->assertFalse($this->db->redis_host);
    }

    public function testRedisKeyPrefixDefault() {
        $this->assertEquals('', $this->db->redis_key_prefix);
    }

    public function testRedisPortDefault() {
        $this->assertFalse($this->db->redis_port);
    }

    public function testRedisPropertiesSettings() {
        $this->db->redis_host = '127.0.0.1';
        $this->db->redis_port = 6379;
        $this->db->redis_compressed = true;
        $this->db->redis_key_prefix = 'zebra_db_';

        $this->assertEquals('127.0.0.1', $this->db->redis_host);
        $this->assertEquals(6379, $this->db->redis_port);
        $this->assertTrue($this->db->redis_compressed);
        $this->assertEquals('zebra_db_', $this->db->redis_key_prefix);
    }

    // RESULT METADATA PROPERTIES

    public function testAffectedRowsAfterInsert() {
        $this->db->insert('test_users', [
            'name' => 'Affected Rows Test',
            'email' => 'affected@example.com',
            'age' => 25
        ]);

        $this->assertEquals(1, $this->db->affected_rows);
    }

    public function testAffectedRowsAfterUpdate() {
        $this->db->insert('test_users', [
            'name' => 'Update Test User',
            'email' => 'updatetest@example.com',
            'age' => 25
        ]);

        $this->db->update('test_users', ['age' => 30], 'name = ?', ['Update Test User']);

        $this->assertEquals(1, $this->db->affected_rows);
    }

    public function testAffectedRowsAfterDelete() {
        $this->db->insert('test_users', [
            'name' => 'Delete Test User',
            'email' => 'deletetest@example.com',
            'age' => 25
        ]);

        $this->db->delete('test_users', 'name = ?', ['Delete Test User']);

        $this->assertEquals(1, $this->db->affected_rows);
    }

    public function testReturnedRowsAfterSelect() {
        for ($i = 1; $i <= 5; $i++) {
            $this->db->insert('test_users', [
                'name' => "Returned Rows Test $i",
                'email' => "returned$i@example.com",
                'age' => 20 + $i
            ]);
        }

        $this->db->query("SELECT * FROM test_users WHERE name LIKE 'Returned Rows Test%'");

        $this->assertEquals(5, $this->db->returned_rows);
    }

    public function testFoundRowsProperty() {
        for ($i = 1; $i <= 10; $i++) {
            $this->db->insert('test_users', [
                'name' => "Found Rows Test $i",
                'email' => "found$i@example.com",
                'age' => 20 + $i
            ]);
        }

        // a LIMIT without the calc_rows argument, so found_rows is an integer rather than a count
        $this->db->query("SELECT * FROM test_users WHERE name LIKE 'Found Rows Test%' LIMIT 3");

        $this->assertEquals(3, $this->db->returned_rows);
        $this->assertIsInt($this->db->found_rows);
    }

    // INSERT_ID TESTS

    public function testInsertId() {
        $this->db->insert('test_users', [
            'name' => 'Insert ID Test',
            'email' => 'insertid@example.com',
            'age' => 25
        ]);

        $insert_id = $this->db->insert_id();

        $this->assertGreaterThan(0, $insert_id);
        $this->assertIsInt($insert_id);

        $result = $this->db->query("SELECT * FROM test_users WHERE id = ?", [$insert_id]);
        $row = $this->db->fetch_assoc($result);
        $this->assertEquals('Insert ID Test', $row['name']);
    }

    public function testInsertIdAfterMultipleInserts() {
        $this->db->insert('test_users', [
            'name' => 'First Insert',
            'email' => 'first@example.com',
            'age' => 25
        ]);

        $first_id = $this->db->insert_id();

        $this->db->insert('test_users', [
            'name' => 'Second Insert',
            'email' => 'second@example.com',
            'age' => 30
        ]);

        $second_id = $this->db->insert_id();

        $this->assertGreaterThan($first_id, $second_id);
    }

    // SSL_OPTIONS TESTS

    public function testSslOptionsDefault() {
        $this->assertNull($this->db->ssl_options);
    }

    public function testSslOptionsSetting() {
        $ssl_options = [
            'key' => '/path/to/key.pem',
            'cert' => '/path/to/cert.pem',
            'ca' => '/path/to/ca.pem',
            'capath' => '/path/to/capath',
            'cipher' => 'DHE-RSA-AES256-SHA'
        ];

        $this->db->ssl_options = $ssl_options;

        $this->assertEquals($ssl_options, $this->db->ssl_options);
    }

    // RESOURCE_PATH TESTS

    public function testResourcePathDefault() {
        $this->assertNull($this->db->resource_path);
    }

    public function testResourcePathSetting() {
        $resource_path = '/custom/resource/path/';
        $this->db->resource_path = $resource_path;

        $this->assertEquals($resource_path, $this->db->resource_path);
    }

    // DISABLE_WARNINGS TESTS

    public function testDisableWarningsDefault() {
        $this->assertFalse($this->db->disable_warnings);
    }

    public function testDisableWarningsSetting() {
        $this->db->disable_warnings = true;
        $this->assertTrue($this->db->disable_warnings);

        $this->db->disable_warnings = false;
        $this->assertFalse($this->db->disable_warnings);
    }

    // LOG_PATH TESTS

    public function testLogPathDefault() {
        $this->assertEquals('', $this->db->log_path);
    }

    public function testLogPathSetting() {
        $log_path = '/var/log/zebra_db/';
        $this->db->log_path = $log_path;

        $this->assertEquals($log_path, $this->db->log_path);
    }

    // MINIMIZE_CONSOLE TESTS

    public function testMinimizeConsoleDefault() {
        $this->assertTrue($this->db->minimize_console);
    }

    public function testMinimizeConsoleSetting() {
        $this->db->minimize_console = false;
        $this->assertFalse($this->db->minimize_console);

        $this->db->minimize_console = true;
        $this->assertTrue($this->db->minimize_console);
    }

    // PROPERTIES PERSISTENCE TESTS

    public function testPropertiesPersistenceAfterConnection() {
        $this->db->debug = false;
        $this->db->max_query_time = 60;
        $this->db->auto_quote_replacements = false;

        // connecting reads the settings but leaves them alone
        $this->db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT);

        $this->assertFalse($this->db->debug);
        $this->assertEquals(60, $this->db->max_query_time);
        $this->assertFalse($this->db->auto_quote_replacements);
    }

    /**
     * The settings are plain public properties, so whatever is assigned to one is what comes back out -
     * the library neither coerces nor validates on assignment. That is worth pinning: several of them
     * deliberately accept more than one type (debug takes a boolean, a string or an array; halt_on_errors
     * takes a boolean or the string "always") and a typed property or a magic setter added later would
     * quietly break those.
     *
     * @dataProvider propertiesAndValues
     */
    public function testAPropertyReturnsExactlyWhatWasAssignedToIt($property, $value) {
        $this->db->$property = $value;

        $this->assertSame($value, $this->db->$property);
    }

    public function propertiesAndValues() {
        return [
            'debug as a boolean'            => ['debug', true],
            'debug as a query string name'  => ['debug', 'turn_debugging_on'],
            'debug as an array'             => ['debug', ['errors', 'successful-queries']],
            'halt_on_errors as a boolean'   => ['halt_on_errors', false],
            'halt_on_errors as "always"'    => ['halt_on_errors', 'always'],
            'max_query_time as an integer'  => ['max_query_time', 30],
            'max_query_time as a string'    => ['max_query_time', '30'],
            'debug_show_records as an int'  => ['debug_show_records', 50],
            'caching_method'                => ['caching_method', 'disk'],
            'cache_path'                    => ['cache_path', '/some/path/'],
        ];
    }

    // PROPERTIES GIVEN VALUES THAT MAKE NO SENSE

    public function testCachePathInvalidValues() {
        // a regular file standing where a directory should be, which is unusable as a cache path for
        // every user - a path like "/root/restricted" is perfectly writable when the suite runs as root
        $file_in_the_way = getTempPath() . '/not-a-directory';
        file_put_contents($file_in_the_way, 'this is a file');

        $invalid_paths = [
            '/nonexistent/directory/path',
            $file_in_the_way,
            null,
            123,
            [getTempPath()],
            '/dev/null', // a file rather than a directory
            '',
            '   ',
        ];

        foreach ($invalid_paths as $invalid_path) {
            $this->db->cache_path = $invalid_path;

            // the assignment itself is accepted, whatever it is
            $this->assertEquals($invalid_path, $this->db->cache_path);

            // an unusable cache path is reported as an error rather than silently ignored, so that a
            // misconfigured cache folder cannot go unnoticed - the query is expected to fail
            if ($invalid_path !== null && $invalid_path !== '') {
                $result = $this->db->query("SELECT 1 as test", [], 60);
                $this->assertFalse($result, "Query should fail loudly when the cache path cannot be used");
            }
        }

        unlink($file_in_the_way);
    }

    public function testCachingMethodInvalidValues() {
        $invalid_methods = [
            'invalid_method',
            'file',
            'mysql',
            null,
            123,
            ['disk'],
            '',
            'DISK', // the comparison is case sensitive
        ];

        foreach ($invalid_methods as $invalid_method) {
            $this->db->caching_method = $invalid_method;
            $this->assertEquals($invalid_method, $this->db->caching_method);

            // a method the library does not recognise means no caching, not a failed query
            $result = $this->db->query("SELECT 2 as test", [], 60);
            $this->assertNotFalse($result, "Query should work even with invalid caching method");
        }
    }

    public function testMaxQueryTimeExtremeValues() {
        $extreme_values = [
            -1,
            0,
            PHP_INT_MAX,
            -PHP_INT_MAX,
            'not_a_number',
            null,
            [10],
            1.5,
        ];

        foreach ($extreme_values as $extreme_value) {
            $this->db->max_query_time = $extreme_value;
            $this->assertEquals($extreme_value, $this->db->max_query_time);

            // the value is only ever compared against a query's duration, so none of these break a query
            $result = $this->db->query("SELECT 3 as test");
            $this->assertNotFalse($result, "Query should work even with extreme max_query_time");
        }
    }

    public function testDebugShowRecordsExtremeValues() {
        $extreme_values = [
            -1,
            0,
            PHP_INT_MAX,
            'invalid',
            null,
            [20],
        ];

        foreach ($extreme_values as $extreme_value) {
            $this->db->debug_show_records = $extreme_value;
            $this->assertEquals($extreme_value, $this->db->debug_show_records);

            // the value decides how many rows the console lists, and none of these stop a query running
            $original_debug = $this->db->debug;
            $this->db->debug = true;

            ob_start();
            $result = $this->db->query("SELECT 4 as test");
            $debug_output = ob_get_clean();

            $this->assertNotFalse($result, "Query should work with extreme debug_show_records");
            $this->db->debug = $original_debug;
        }
    }

    public function testMemcacheInvalidConfiguration() {
        $invalid_configs = [
            'host' => ['invalid_host_12345', null, 123, ['localhost']],
            'port' => [-1, 0, 999999, 'invalid_port', null, [11211]],
            'compressed' => ['yes', 'no', 1, 0, null, [true]],
            'key_prefix' => [null, 123, ['prefix']],
        ];

        foreach ($invalid_configs as $property => $invalid_values) {
            foreach ($invalid_values as $invalid_value) {
                $property_name = "memcache_$property";
                $this->db->$property_name = $invalid_value;
                $this->assertEquals($invalid_value, $this->db->$property_name);

                // a memcache that cannot be reached leaves the query itself alone
                $this->db->caching_method = 'memcache';
                $result = $this->db->query("SELECT 5 as test", [], 60);
                $this->assertNotFalse($result, "Query should work even with invalid memcache config");
            }
        }
    }

    public function testRedisInvalidConfiguration() {
        $invalid_configs = [
            'host' => ['invalid_redis_host_12345', null, 123, ['127.0.0.1']],
            'port' => [-1, 0, 999999, 'invalid_port', null, [6379]],
            'compressed' => ['yes', 'no', 1, 0, null, [false]],
            'key_prefix' => [null, 123, ['redis_prefix']],
        ];

        foreach ($invalid_configs as $property => $invalid_values) {
            foreach ($invalid_values as $invalid_value) {
                $property_name = "redis_$property";
                $this->db->$property_name = $invalid_value;
                $this->assertEquals($invalid_value, $this->db->$property_name);

                // a redis that cannot be reached leaves the query itself alone
                $this->db->caching_method = 'redis';
                $result = $this->db->query("SELECT 6 as test", [], 60);
                $this->assertNotFalse($result, "Query should work even with invalid redis config");
            }
        }
    }

    public function testNotificationInvalidValues() {
        $invalid_emails = [
            'not_an_email',
            '@invalid.com',
            'user@',
            123,
            null,
            ['test@example.com'],
            'very_long_email_' . str_repeat('x', 1000) . '@example.com',
        ];

        $invalid_domains = [
            'not-a-domain',
            '@domain.com',
            'http://domain.com',
            123,
            null,
            ['example.com'],
        ];

        foreach ($invalid_emails as $invalid_email) {
            $this->db->notification_address = $invalid_email;
            $this->assertEquals($invalid_email, $this->db->notification_address);

            // a notification address is only read when a slow query is found, never by an ordinary one
            $result = $this->db->query("SELECT 7 as test");
            $this->assertNotFalse($result, "Query should work with invalid notification address");
        }

        foreach ($invalid_domains as $invalid_domain) {
            $this->db->notifier_domain = $invalid_domain;
            $this->assertEquals($invalid_domain, $this->db->notifier_domain);

            $result = $this->db->query("SELECT 8 as test");
            $this->assertNotFalse($result, "Query should work with invalid notifier domain");
        }
    }

    public function testSslOptionsInvalidValues() {
        $invalid_ssl_configs = [
            'string_instead_of_array',
            123,
            null, // which is the valid one - no SSL at all
            ['invalid_key' => 'value'],
            ['key' => '/nonexistent/key.pem'],
            ['cert' => '/nonexistent/cert.pem'],
            ['ca' => '/nonexistent/ca.pem'],
            [
                'key' => null,
                'cert' => null,
                'ca' => null,
                'capath' => null,
                'cipher' => null
            ],
        ];

        foreach ($invalid_ssl_configs as $invalid_ssl) {
            $this->db->ssl_options = $invalid_ssl;
            $this->assertEquals($invalid_ssl, $this->db->ssl_options);

            // the connection is already up, so none of this reaches mysqli - the assignment is all there is
            $result = $this->db->query("SELECT 9 as test");
            $this->assertNotFalse($result, "Query should work even with invalid SSL config");
        }
    }

    public function testDebugBreakingValues() {
        $breaking_debug_values = [
            PHP_INT_MAX,
            -1,
            'invalid_debug_key_' . str_repeat('x', 1000),
            ['debug' => true, 'invalid' => 'config'],
            [true, false, true, false, true], // longer than the three switches it reads
        ];

        foreach ($breaking_debug_values as $debug_value) {
            $this->db->debug = $debug_value;
            $this->assertEquals($debug_value, $this->db->debug);

            // the output is swallowed, since what is being asserted is that the query ran at all
            ob_start();
            $result = $this->db->query("SELECT 10 as test");
            $debug_output = ob_get_clean();

            $this->assertNotFalse($result, "Query should work with breaking debug values");

            $this->db->debug = false;
        }
    }

    public function testDebuggerIpInvalidValues() {
        $invalid_ips = [
            ['not.an.ip.address'],
            ['999.999.999.999'],
            ['192.168.1'],
            [''],
            [null],
            ['127.0.0.1', 'invalid.ip'],
            'not_an_array',
            123,
            null,
        ];

        foreach ($invalid_ips as $invalid_ip) {
            $this->db->debugger_ip = $invalid_ip;
            $this->assertEquals($invalid_ip, $this->db->debugger_ip);

            // the list decides who sees the console, and an address that is in no list simply sees nothing
            $original_debug = $this->db->debug;
            $this->db->debug = true;

            ob_start();
            $result = $this->db->query("SELECT 11 as test");
            $debug_output = ob_get_clean();

            $this->assertNotFalse($result, "Query should work with invalid debugger IPs");
            $this->db->debug = $original_debug;
        }
    }

    /**
     * The settings are plain properties, so an absurdly large value is stored as it was given and a query
     * afterwards is unaffected by any of it
     */
    public function testAbsurdlyLargePropertyValuesAreStoredAsGivenAndChangeNothing() {
        $large_string = str_repeat('MEMORY_TEST_', 100000); // about 1.2MB of it
        $large_array = array_fill(0, 10000, 'array_element');

        $this->db->log_path = $large_string;
        $this->db->notification_address = $large_string;
        $this->db->memcache_key_prefix = $large_string;
        $this->db->redis_key_prefix = $large_string;
        $this->db->debugger_ip = $large_array;

        $this->assertSame($large_string, $this->db->log_path, "Stored as given, not truncated");
        $this->assertSame($large_array, $this->db->debugger_ip);

        // none of those are read by an ordinary query, so it runs exactly as it would otherwise
        $row = $this->db->fetch_assoc($this->db->query("SELECT 12 as test"));
        $this->assertSame('12', $row['test'], "A query is unaffected by any of them");

        $this->db->log_path = '';
        $this->db->notification_address = '';
        $this->db->memcache_key_prefix = '';
        $this->db->redis_key_prefix = '';
        $this->db->debugger_ip = [];
    }
}
