<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Test suite for Zebra_Database query functionality including parameterized queries
 */
class QueryTest extends DatabaseTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->connectToDatabase();
        $this->insertTestData();
    }

    public function testSimpleSelectQuery() {
        $result = $this->db->query("SELECT * FROM test_users");

        $this->assertNotFalse($result);
        $this->assertEquals(3, $this->db->returned_rows);

        $row = $this->db->fetch_assoc($result);
        $this->assertArrayHasKey('name', $row);
        $this->assertArrayHasKey('email', $row);
    }

    public function testQueryWithSingleParameterReplacement() {
        $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", ['John Doe']);

        $this->assertNotFalse($result);
        $this->assertEquals(1, $this->db->returned_rows);

        $row = $this->db->fetch_assoc($result);
        $this->assertEquals('John Doe', $row['name']);
        $this->assertEquals('john@example.com', $row['email']);
    }

    public function testQueryWithMultipleParameterReplacements() {
        $result = $this->db->query(
            "SELECT * FROM test_users WHERE age > ? AND is_active = ?",
            [25, 1]
        );

        $this->assertNotFalse($result);
        $this->assertEquals(1, $this->db->returned_rows); // John (30) and Bob (35), but Bob is inactive

        $names = [];
        while ($row = $this->db->fetch_assoc($result)) {
            $names[] = $row['name'];
        }

        $this->assertContains('John Doe', $names);
        $this->assertNotContains('Bob Johnson', $names); // Bob is inactive
    }

    public function testQueryWithNullParameter() {
        // Insert a user with null email
        $this->db->insert('test_users', [
            'name' => 'Null Email User',
            'email' => null,
            'age' => 30
        ]);

        $result = $this->db->query("SELECT * FROM test_users WHERE email IS NULL");

        $this->assertNotFalse($result);
        $this->assertEquals(1, $this->db->returned_rows);

        $row = $this->db->fetch_assoc($result);
        $this->assertEquals('Null Email User', $row['name']);
        $this->assertNull($row['email']);
    }

    public function testQueryWithArrayParameter() {
        $result = $this->db->query(
            "SELECT * FROM test_users WHERE name IN (?)",
            [['John Doe', 'Jane Smith']]
        );

        $this->assertNotFalse($result);
        $this->assertEquals(2, $this->db->returned_rows);

        $names = [];
        while ($row = $this->db->fetch_assoc($result)) {
            $names[] = $row['name'];
        }

        $this->assertContains('John Doe', $names);
        $this->assertContains('Jane Smith', $names);
        $this->assertNotContains('Bob Johnson', $names);
    }

    public function testQueryWithMixedArrayAndSingleParameters() {
        $result = $this->db->query(
            "SELECT * FROM test_users WHERE name IN (?) AND age > ?",
            [['John Doe', 'Jane Smith'], 25]
        );

        $this->assertNotFalse($result);
        $this->assertEquals(1, $this->db->returned_rows);
    }

    public function testQueryWithEmptyArrayParameter() {
        $result = $this->db->query(
            "SELECT * FROM test_users WHERE name IN (?)",
            [[]]
        );

        // Empty array should result in no matches
        $this->assertNotFalse($result);
        $this->assertEquals(0, $this->db->returned_rows);
    }

    public function testQueryWithIntegerTypes() {
        $result = $this->db->query("SELECT * FROM test_users WHERE age = ?", [30]);

        $this->assertNotFalse($result);
        $this->assertEquals(1, $this->db->returned_rows);

        $row = $this->db->fetch_assoc($result);
        $this->assertEquals('John Doe', $row['name']);
        $this->assertEquals(30, (int)$row['age']);
    }

    public function testQueryWithFloatTypes() {
        $result = $this->db->query("SELECT * FROM test_users WHERE score = ?", [85.50]);

        $this->assertNotFalse($result);
        $this->assertEquals(1, $this->db->returned_rows);

        $row = $this->db->fetch_assoc($result);
        $this->assertEquals('John Doe', $row['name']);
        $this->assertEquals(85.50, (float)$row['score']);
    }

    public function testQueryWithBooleanTypes() {
        $result = $this->db->query("SELECT * FROM test_users WHERE is_active = ?", [true]);

        $this->assertNotFalse($result);
        $this->assertEquals(2, $this->db->returned_rows); // John and Jane are active

        // Test with false
        $result = $this->db->query("SELECT * FROM test_users WHERE is_active = ?", [false]);

        $this->assertNotFalse($result);
        $this->assertEquals(1, $this->db->returned_rows); // Bob is inactive

        $row = $this->db->fetch_assoc($result);
        $this->assertEquals('Bob Johnson', $row['name']);
    }

    public function testQueryWithSpecialCharacters() {
        // Insert user with special characters
        $special_name = "O'Brien & Co. <script>alert('test')</script>";
        $this->db->insert('test_users', [
            'name' => $special_name,
            'email' => 'special@example.com',
            'age' => 40
        ]);

        $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", [$special_name]);

        $this->assertNotFalse($result);
        $this->assertEquals(1, $this->db->returned_rows);

        $row = $this->db->fetch_assoc($result);
        $this->assertEquals($special_name, $row['name']);
    }

    public function testQueryWithSQLInjectionAttempt() {
        // This should be safely handled by the escaping the library does for replacements
        $malicious_input = "'; DROP TABLE test_users; --";

        $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", [$malicious_input]);

        $this->assertNotFalse($result);
        $this->assertEquals(0, $this->db->returned_rows); // No match found, but table should still exist

        // Verify table still exists by running another query
        $result = $this->db->query("SELECT COUNT(*) as count FROM test_users");
        $this->assertNotFalse($result);

        $row = $this->db->fetch_assoc($result);
        $this->assertGreaterThan(0, (int)$row['count']); // Table still has data
    }

    public function testQueryWithoutParametersUsesRegularQuery() {
        // Queries without parameters should use regular mysqli_query
        $result = $this->db->query("SELECT COUNT(*) as total FROM test_users");

        $this->assertNotFalse($result);
        $row = $this->db->fetch_assoc($result);
        $this->assertEquals(3, (int)$row['total']);
    }

    public function testQueryWithUnbufferedMode() {
        // Set unbuffered mode - should fall back to regular query even with parameters
        $reflection = new ReflectionClass($this->db);
        $unbufferedProperty = $reflection->getProperty('unbuffered');
        $unbufferedProperty->setAccessible(true);
        $unbufferedProperty->setValue($this->db, true);

        $result = $this->db->query("SELECT * FROM test_users WHERE age > ?", [25]);

        $this->assertNotFalse($result);

        // Reset unbuffered mode
        $unbufferedProperty->setValue($this->db, false);
    }

    public function testQueryWithInvalidSQL() {
        $result = $this->db->query("INVALID SQL QUERY");

        $this->assertFalse($result);

        $error = $this->db->error();
        $this->assertNotEmpty($error);
    }

    public function testQueryWithWrongNumberOfReplacements() {
        // More placeholders than replacements
        $result = $this->db->query("SELECT * FROM test_users WHERE name = ? AND age = ?", ['John Doe']);

        $this->assertFalse($result);

        // Less placeholders than replacements
        $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", ['John Doe', 30]);

        $this->assertFalse($result);
    }

    public function testQueryWithNonArrayReplacements() {
        $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", 'John Doe');

        $this->assertFalse($result);
    }

    public function testInsertQuery() {
        $result = $this->db->query(
            "INSERT INTO test_users (name, email, age) VALUES (?, ?, ?)",
            ['Test User', 'test@example.com', 28]
        );

        $this->assertTrue($result);
        $this->assertEquals(1, $this->db->affected_rows);

        $insert_id = $this->db->insert_id();
        $this->assertGreaterThan(0, $insert_id);

        // Verify the insert
        $verify_result = $this->db->query("SELECT * FROM test_users WHERE id = ?", [$insert_id]);
        $row = $this->db->fetch_assoc($verify_result);
        $this->assertEquals('Test User', $row['name']);
        $this->assertEquals('test@example.com', $row['email']);
        $this->assertEquals(28, (int)$row['age']);
    }

    public function testUpdateQuery() {
        $result = $this->db->query(
            "UPDATE test_users SET age = ? WHERE name = ?",
            [31, 'John Doe']
        );

        $this->assertTrue($result);
        $this->assertEquals(1, $this->db->affected_rows);

        // Verify the update
        $verify_result = $this->db->query("SELECT age FROM test_users WHERE name = ?", ['John Doe']);
        $row = $this->db->fetch_assoc($verify_result);
        $this->assertEquals(31, (int)$row['age']);
    }

    public function testDeleteQuery() {
        $result = $this->db->query(
            "DELETE FROM test_users WHERE name = ?",
            ['Bob Johnson']
        );

        $this->assertTrue($result);
        $this->assertEquals(1, $this->db->affected_rows);

        // Verify the delete
        $verify_result = $this->db->query("SELECT COUNT(*) as count FROM test_users");
        $row = $this->db->fetch_assoc($verify_result);
        $this->assertEquals(2, (int)$row['count']); // Should be 2 users left
    }

    public function testQueryWithCache() {
        // Create cache directory for testing
        $cache_dir = '/tmp/zebra_test_cache';
        if (!is_dir($cache_dir)) {
            mkdir($cache_dir, 0777, true);
        }

        $this->db->cache_path = $cache_dir;
        $this->db->caching_method = 'disk';

        // First query - should be cached
        $result1 = $this->db->query("SELECT * FROM test_users WHERE name = ?", ['John Doe'], 3600);
        $this->assertNotFalse($result1);

        // Second identical query - should come from cache
        $result2 = $this->db->query("SELECT * FROM test_users WHERE name = ?", ['John Doe'], 3600);
        $this->assertNotFalse($result2);

        // Clean up cache directory
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '/*');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($cache_dir);
        }
    }

    public function testQueryWithHighlight() {
        // Test highlight parameter - should not affect query execution
        $result = $this->db->query(
            "SELECT * FROM test_users WHERE name = ?",
            ['John Doe'],
            false,
            false,
            true
        );

        $this->assertNotFalse($result);
        $this->assertEquals(1, $this->db->returned_rows);
    }

    public function testQueryWithCalcRows() {
        // Test calc_rows parameter (deprecated but should still work)
        $result = $this->db->query(
            "SELECT * FROM test_users LIMIT 1",
            '',
            false,
            true
        );

        $this->assertNotFalse($result);
        $this->assertEquals(1, $this->db->returned_rows);

        // found_rows should be greater than returned_rows if calc_rows worked
        // Note: This feature is deprecated in MySQL 8.0.17
        if ($this->db->found_rows > 0) {
            $this->assertGreaterThanOrEqual($this->db->returned_rows, $this->db->found_rows);
        }
    }
}