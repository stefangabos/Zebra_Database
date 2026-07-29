<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Test suite for Zebra_Database connection functionality
 */
class ConnectionTest extends PHPUnit\Framework\TestCase {
    protected $db;

    protected function setUp(): void {
        $this->db = new Zebra_Database();
        $this->db->debug = false; // Disable debug output during tests
        $this->db->cache_path = getTempPath('cache'); // the suite's own scratch directory
        // Don't call any connection methods here
    }

    protected function tearDown(): void {
        if ($this->db) {
            $this->db->close();
        }
        $this->db = null;
    }

    public function testConstructor() {
        $db = new Zebra_Database();

        // Test default property values
        $this->assertTrue($db->auto_quote_replacements);
        $this->assertEquals('disk', $db->caching_method);
        $this->assertTrue($db->debug);
        $this->assertTrue($db->halt_on_errors);
        $this->assertEquals(10, $db->max_query_time);
        $this->assertTrue($db->minimize_console);

        // Test that connection is not established yet
        // We check the connection property directly instead of get_link() which would trigger connection
        $reflection = new ReflectionClass($db);
        $connectionProperty = $reflection->getProperty('connection');
        $connectionProperty->setAccessible(true);
        $this->assertFalse($connectionProperty->getValue($db));
    }

    public function testLazyConnectionDefault() {
        // connect() method with default lazy connection behavior
        $this->db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT);

        // Connection should not be established yet (lazy connection)
        // We check the connection property directly instead of get_link() which would trigger connection
        $reflection = new ReflectionClass($this->db);
        $connectionProperty = $reflection->getProperty('connection');
        $connectionProperty->setAccessible(true);
        $this->assertFalse($connectionProperty->getValue($this->db));

        // Only after executing a query should the connection be established
        $result = $this->db->query("SELECT 1 as test");
        $this->assertNotFalse($result);
        $this->assertInstanceOf('mysqli', $this->db->get_link());
    }

    public function testImmediateConnectionWithConnectTrue() {
        // connect() method with connect argument set to TRUE
        $this->db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT, '', true);

        // Connection should be established immediately when connect=true
        $this->assertInstanceOf('mysqli', $this->db->get_link());
    }

    public function testLazyConnectionWithInvalidCredentials() {
        // Invalid credentials with lazy connection should not fail immediately
        $this->db->connect(TEST_DB_HOST, 'invalid_user', 'invalid_pass', TEST_DB_NAME, TEST_DB_PORT);

        // Connection should still be false (lazy connection)
        $reflection = new ReflectionClass($this->db);
        $connectionProperty = $reflection->getProperty('connection');
        $connectionProperty->setAccessible(true);
        $this->assertFalse($connectionProperty->getValue($this->db));

        // Error should only occur when actually trying to execute a query
        $result = $this->db->query("SELECT 1 as test");
        $this->assertFalse($result);
        // After failed query, connection should still be false/null
        $this->assertFalse($this->db->get_link());
    }

    public function testImmediateConnectionWithInvalidCredentials() {
        // Invalid credentials with immediate connection should fail right away
        $this->db->connect(TEST_DB_HOST, 'invalid_user', 'invalid_pass', TEST_DB_NAME, TEST_DB_PORT, '', true);

        // Connection should fail immediately when connect=true with invalid credentials
        $this->assertFalse($this->db->get_link());
    }

    public function testLazyConnectionWithInvalidDatabase() {
        // Invalid database with lazy connection should not fail immediately
        $this->db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, 'nonexistent_database', TEST_DB_PORT);

        // Connection should still be false (lazy connection)
        $reflection = new ReflectionClass($this->db);
        $connectionProperty = $reflection->getProperty('connection');
        $connectionProperty->setAccessible(true);
        $this->assertFalse($connectionProperty->getValue($this->db));

        // Error should only occur when actually trying to execute a query
        $result = $this->db->query("SELECT 1 as test");
        $this->assertFalse($result);
    }

    public function testImmediateConnectionWithInvalidDatabase() {
        // Invalid database with immediate connection should fail right away
        $this->db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, 'nonexistent_database', TEST_DB_PORT, '', true);

        // Connection should fail immediately when connect=true with invalid database
        $this->assertFalse($this->db->get_link());
    }

    public function testConnectionWithSocket() {
        // rather than guessing at a path, ask the server where its socket is - it is the only way of
        // getting this right across MAMP, Homebrew, a Linux package and everything else
        $probe = new Zebra_Database();
        $probe->debug = false;
        $probe->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT);
        $row = $probe->fetch_assoc($probe->query("SELECT @@socket AS socket"));
        $probe->close();

        $socket = $row ? $row['socket'] : '';

        if ($socket === '' || !file_exists($socket)) {
            $this->markTestSkipped('MySQL is not reachable over a socket on this machine');
        }

        // the host has to be "localhost" and the port has to be empty, or else mysqli connects over TCP
        // and ignores the socket entirely - this is a mysqli rule rather than anything the library does
        $this->db->connect('localhost', TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, '', $socket, true);

        $this->assertInstanceOf('mysqli', $this->db->get_link());

        // and it has to actually work, not merely connect
        $row = $this->db->fetch_assoc($this->db->query("SELECT COUNT(*) AS total FROM test_users"));
        $this->assertIsArray($row, "A query should work over the socket connection");
    }

    public function testConnectionWithSSLOptions() {
        $this->db->ssl_options = [
            'key' => null,
            'cert' => null,
            'ca' => null,
            'capath' => null,
            'cipher' => null
        ];

        $this->db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT, '', true);

        // Should still connect successfully with null SSL options
        $this->assertInstanceOf('mysqli', $this->db->get_link());
    }

    public function testConnectionWithFlags() {
        $this->db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT, '', true, MYSQLI_CLIENT_FOUND_ROWS);

        $this->assertInstanceOf('mysqli', $this->db->get_link());
    }

    public function testReconnectionAfterClose() {
        // First connection with immediate connect
        $this->db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT, '', true);
        $this->assertInstanceOf('mysqli', $this->db->get_link());

        // Close connection
        $this->db->close();
        $this->assertFalse($this->db->get_link());

        // Reconnect with immediate connect
        $this->db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT, '', true);
        $this->assertInstanceOf('mysqli', $this->db->get_link());
    }

    public function testCloseConnection() {
        $this->db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT, '', true);
        $this->assertInstanceOf('mysqli', $this->db->get_link());

        $this->db->close();
        $this->assertFalse($this->db->get_link());
    }

    public function testGetSelectedDatabase() {
        $this->db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT, '', true);

        $selected_db = $this->db->get_selected_database();
        $this->assertEquals(TEST_DB_NAME, $selected_db);
    }

    public function testSelectDatabase() {
        $this->db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT, '', true);

        // Create a temporary database for testing
        $temp_db = 'zebra_test_temp';
        $this->db->query("CREATE DATABASE IF NOT EXISTS `$temp_db`");

        $result = $this->db->select_database($temp_db);
        $this->assertTrue($result);
        $this->assertEquals($temp_db, $this->db->get_selected_database());

        // Clean up
        $this->db->query("DROP DATABASE `$temp_db`");
        $this->db->select_database(TEST_DB_NAME);
    }

    public function testSelectNonexistentDatabase() {
        $this->db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT, '', true);

        $result = $this->db->select_database('nonexistent_database');
        $this->assertFalse($result);

        // Should still be connected to the original database
        $this->assertEquals(TEST_DB_NAME, $this->db->get_selected_database());
    }

    public function testSetCharset() {
        $this->db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT, '', true);

        $result = $this->db->set_charset('utf8mb4', 'utf8mb4_unicode_ci');
        $this->assertNotFalse($result);

        // Verify charset was set
        $charset_result = $this->db->query("SELECT @@character_set_connection, @@collation_connection");
        $charset_row = $this->db->fetch_assoc($charset_result);

        $this->assertEquals('utf8mb4', $charset_row['@@character_set_connection']);
        $this->assertEquals('utf8mb4_unicode_ci', $charset_row['@@collation_connection']);
    }

    public function testSetInvalidCharset() {
        $this->db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT, '', true);

        $result = $this->db->set_charset('invalid_charset', 'invalid_collation');
        $this->assertFalse($result);
    }

    public function testConnectionPersistence() {
        // Test that connection persists across multiple operations
        $this->db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT, '', true);

        $link1 = $this->db->get_link();

        // Perform some operations
        $this->db->query("SELECT 1");
        $this->db->query("SELECT 2");

        $link2 = $this->db->get_link();

        // Should be the same connection object
        $this->assertSame($link1, $link2);
    }

    public function testMultipleConnectionsToSameDatabase() {
        $db1 = new Zebra_Database();
        $db2 = new Zebra_Database();

        $db1->debug = false;
        $db2->debug = false;

        $db1->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT, '', true);
        $db2->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT, '', true);

        // Should be different connection objects
        $this->assertNotSame($db1->get_link(), $db2->get_link());

        $db1->close();
        $db2->close();
    }

}
