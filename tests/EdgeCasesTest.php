<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Test suite for Zebra_Database edge cases and error conditions
 */
class EdgeCasesTest extends DatabaseTestCase
{
    protected function setUp(): void {
        parent::setUp();
        $this->connectToDatabase();
    }

    // SQL injection through replacements is covered in SecurityTest, and through array replacements in
    // ArrayParameterTest - the two tests that used to stand here were the same payloads run the same way

    // MEMORY AND RESOURCE TESTS

    /**
     * A value too long for its column is refused rather than quietly cut down to size. This used to assert
     * one thing or the other depending on whether the server ran in strict mode, so it passed either way
     * and pinned neither - the bootstrap now requires strict mode, which is what makes one answer correct
     */
    public function testAValueTooLongForItsColumnIsRefused() {
        $large_text = str_repeat('A', 10000); // 10KB into a varchar(100)

        $result = $this->db->insert('test_users', [
            'name' => $large_text,
            'email' => 'large@example.com',
            'age' => 30
        ]);

        $this->assertFalse($result, "The insert has to be refused, not truncated");
        $this->assertStringContainsString('Data too long', $this->db->error());
        $this->assertEquals(0, $this->db->dcount('*', 'test_users'), "And nothing may have been written");
    }

    public function testManySmallQueries() {
        // Test system stability with many small queries
        for ($i = 0; $i < 100; $i++) {
            $result = $this->db->query("SELECT ? as test_value", [$i]);
            $row = $this->db->fetch_assoc($result);
            $this->assertEquals($i, (int)$row['test_value']);
        }

        // nothing leaked into the connection along the way - a result left unread or a pointer left
        // half way through would show up here rather than as a hundred passing assertions above
        $row = $this->db->fetch_assoc($this->db->query("SELECT COUNT(*) AS total FROM test_users"));
        $this->assertSame('0', $row['total'], "The connection is still usable and the table untouched");
    }

    // NULL AND EMPTY VALUE HANDLING

    public function testNullValueHandling() {
        $this->db->insert('test_users', [
            'name' => 'Null Test User',
            'email' => null,
            'age' => null,
            'score' => null
        ]);

        // Test various NULL comparisons
        $result = $this->db->query("SELECT * FROM test_users WHERE email IS NULL");
        $this->assertEquals(1, $this->db->returned_rows);

        $result = $this->db->query("SELECT * FROM test_users WHERE age IS NULL");
        $this->assertEquals(1, $this->db->returned_rows);

        // Test NULL in array parameters
        $result = $this->db->query("SELECT * FROM test_users WHERE email IN (?)", [[null, 'test@example.com']]);
        $this->assertNotFalse($result);
    }

    public function testEmptyStringHandling() {
        $this->db->insert('test_users', [
            'name' => '',
            'email' => 'empty@example.com',
            'age' => 25
        ]);

        $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", ['']);
        $this->assertEquals(1, $this->db->returned_rows);

        $row = $this->db->fetch_assoc($result);
        $this->assertEquals('', $row['name']);
    }

    public function testZeroValueHandling() {
        $this->db->insert('test_users', [
            'name' => 'Zero User',
            'email' => 'zero@example.com',
            'age' => 0,
            'score' => 0.0
        ]);

        $result = $this->db->query("SELECT * FROM test_users WHERE age = ?", [0]);
        $this->assertEquals(1, $this->db->returned_rows);

        $result = $this->db->query("SELECT * FROM test_users WHERE score = ?", [0.0]);
        $this->assertEquals(1, $this->db->returned_rows);
    }

    // UNICODE AND CHARACTER ENCODING

    public function testUnicodeHandling() {
        $unicode_text = '🌟 Unicode Test 中文 العربية русский 🚀';

        $this->db->insert('test_users', [
            'name' => $unicode_text,
            'email' => 'unicode@example.com',
            'age' => 25
        ]);

        $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", [$unicode_text]);
        $row = $this->db->fetch_assoc($result);

        $this->assertEquals($unicode_text, $row['name']);
    }

    public function testSpecialCharacterHandling() {
        $special_chars = "!@#$%^&*()_+-=[]{}|;':\",./<>?`~\\";

        $this->db->insert('test_users', [
            'name' => $special_chars,
            'email' => 'special@example.com',
            'age' => 30
        ]);

        $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", [$special_chars]);
        $row = $this->db->fetch_assoc($result);

        $this->assertEquals($special_chars, $row['name']);
    }

    // DATA TYPE EDGE CASES

    public function testFloatPrecisionHandling() {
        $precise_float = 123456.789012345;

        $this->db->insert('test_users', [
            'name' => 'Float Test',
            'email' => 'float@example.com',
            'age' => 30,
            'score' => $precise_float
        ]);

        $result = $this->db->query("SELECT * FROM test_users WHERE email = ?", ['float@example.com']);
        $row = $this->db->fetch_assoc($result);

        // MySQL may have limited precision, so we test within reasonable bounds
        $this->assertEqualsWithDelta($precise_float, (float)$row['score'], 0.01);
    }

    public function testLargeIntegerHandling() {
        $large_int = 2147483647; // Max 32-bit signed integer

        $this->db->insert('test_users', [
            'name' => 'Large Int Test',
            'email' => 'largeint@example.com',
            'age' => $large_int
        ]);

        $result = $this->db->query("SELECT * FROM test_users WHERE email = ?", ['largeint@example.com']);
        $row = $this->db->fetch_assoc($result);

        $this->assertEquals($large_int, (int)$row['age']);
    }

    public function testNegativeNumberHandling() {
        $this->db->insert('test_users', [
            'name' => 'Negative Test',
            'email' => 'negative@example.com',
            'age' => -5,
            'score' => -123.45
        ]);

        $result = $this->db->query("SELECT * FROM test_users WHERE age = ?", [-5]);
        $this->assertEquals(1, $this->db->returned_rows);

        $row = $this->db->fetch_assoc($result);
        $this->assertEquals(-5, (int)$row['age']);
        $this->assertEquals(-123.45, (float)$row['score']);
    }

    // array replacements - empty, single, large and mixed-type - belong to ArrayParameterTest, which tests
    // all four and rather more thoroughly than the copies that used to sit here

    // CONNECTION EDGE CASES

    public function testQueryAfterConnectionLoss() {
        // This is difficult to test without actually killing the connection
        // We'll test the error handling mechanism instead

        $this->db->halt_on_errors = false; // Don't halt on errors for this test

        // Try to query a non-existent table
        $result = $this->db->query("SELECT * FROM definitely_nonexistent_table_12345");

        $this->assertFalse($result);

        $error = $this->db->error();
        $this->assertNotEmpty($error);

        // Reset
        $this->db->halt_on_errors = true;
    }

    // TRANSACTION EDGE CASES

    public function testTransactionWithError() {
        $this->db->halt_on_errors = false;

        $this->db->transaction_start();

        // Successful insert
        $result1 = $this->db->insert('test_users', [
            'name' => 'Transaction Test 1',
            'email' => 'trans1@example.com',
            'age' => 25
        ]);
        $this->assertTrue($result1);

        $result2 = $this->db->query("INSERT INTO nonexistent_table VALUES (1, 2, 3)");
        $this->assertFalse($result2);

        // a transaction that had a failing query in it is rolled back and reports failure - both halves
        // matter, and asserting only that a boolean came back said nothing about either
        $this->assertFalse($this->db->transaction_complete(), "A transaction that had an error has failed");
        $this->assertEquals(0, $this->db->dcount('*', 'test_users'), "The insert before the failure is gone too");

        // Reset error handling
        $this->db->halt_on_errors = true;
    }

    // ESCAPE METHOD EDGE CASES

    public function testEscapeMethod() {
        $dangerous_string = "'; DROP TABLE test_users; --";
        $escaped = $this->db->escape($dangerous_string);

        // The escape method should make the string safe
        $this->assertIsString($escaped);
        $this->assertNotEquals($dangerous_string, $escaped);
    }

    public function testEscapeWithNullValue() {
        // escaping NULL gives an empty string, and must not reach mysqli_real_escape_string - passing
        // NULL to a string argument of an internal function is deprecated as of PHP 8.1
        $escaped = $this->db->escape(null);

        $this->assertSame('', $escaped);
    }

    public function testImplodeHandlesNullsWithoutADeprecation() {
        // implode() hands each item to escape(), so an array holding a NULL used to reach
        // mysqli_real_escape_string with it
        $this->assertSame("'a','','b'", $this->db->implode(['a', null, 'b']));
    }

    public function testEscapeEmptyString() {
        $escaped = $this->db->escape('');

        $this->assertEquals('', $escaped);
    }

    // UTILITY METHOD EDGE CASES

    public function testImplodeMethod() {
        // Test with various array types
        $simple_array = [1, 2, 3];
        $result = $this->db->implode($simple_array);
        $this->assertIsString($result);

        $string_array = ['a', 'b', 'c'];
        $result = $this->db->implode($string_array);
        $this->assertIsString($result);

        $mixed_array = [1, 'two', 3.5, null];
        $result = $this->db->implode($mixed_array);
        $this->assertIsString($result);

        $empty_array = [];
        $result = $this->db->implode($empty_array);
        $this->assertIsString($result);
    }

    // TABLE AND DATABASE OPERATIONS EDGE CASES

    public function testTableExistsNonexistent() {
        $exists = $this->db->table_exists('definitely_nonexistent_table_12345');
        $this->assertFalse($exists);
    }

    public function testTableExistsValid() {
        $exists = $this->db->table_exists('test_users');
        $this->assertTrue($exists);
    }

    public function testGetTablesInvalidDatabase() {
        $this->db->halt_on_errors = false;

        $result = $this->db->get_tables('nonexistent_database_12345');
        $this->assertIsArray($result);
        $this->assertEmpty($result);

        $this->db->halt_on_errors = true;
    }

    public function testGetTableColumnsInvalidTable() {
        $this->db->halt_on_errors = false;

        $result = $this->db->get_table_columns('nonexistent_table_12345');
        $this->assertFalse($result);

        $this->db->halt_on_errors = true;
    }

    public function testGetTableStatusInvalidTable() {
        $this->db->halt_on_errors = false;

        // get_table_status() is documented as returning an array, so an unknown table means an empty
        // array rather than FALSE - there is no error here, simply nothing that matches
        $result = $this->db->get_table_status('nonexistent_table_12345');
        $this->assertSame([], $result);

        $this->db->halt_on_errors = true;
    }

    // CACHING EDGE CASES

    public function testCachingWithInvalidPath() {
        $this->db->cache_path = '/invalid/path/that/does/not/exist';
        $this->db->caching_method = 'disk';

        $this->db->halt_on_errors = false;

        $result = $this->db->query("SELECT 1 as test", '', 3600); // Try to cache

        // Should break
        $this->assertFalse($result);

        $this->db->halt_on_errors = true;
    }

    // RESOURCE MANAGEMENT EDGE CASES

    public function testMultipleResultsHandling() {
        $result1 = $this->db->query("SELECT 1 as val");
        $result2 = $this->db->query("SELECT 2 as val");

        // Both results should be valid
        $this->assertNotFalse($result1);
        $this->assertNotFalse($result2);

        // Should be able to fetch from both
        $row1 = $this->db->fetch_assoc($result1);
        $row2 = $this->db->fetch_assoc($result2);

        $this->assertEquals(1, (int)$row1['val']);
        $this->assertEquals(2, (int)$row2['val']);
    }

    // BOUNDARY VALUE TESTING

    public function testMaximumFieldLengths() {
        // Test with maximum typical varchar length (might need adjustment based on schema)
        $long_string = str_repeat('x', 100); // Adjust based on your field limits

        $result = $this->db->insert('test_users', [
            'name' => $long_string,
            'email' => 'longstring@example.com',
            'age' => 25
        ]);

        $this->assertTrue($result);

        $verify = $this->db->query("SELECT * FROM test_users WHERE email = ?", ['longstring@example.com']);
        $row = $this->db->fetch_assoc($verify);

        $this->assertEquals($long_string, $row['name']);
    }

    // TABLE_EXISTS AND LIKE WILDCARDS

    /**
     * table_exists() is built on "SHOW TABLES ... LIKE", so "_" - a single character wildcard - has to
     * be escaped or an ordinary table name becomes a pattern. Underscores are extremely common in table
     * names, so without the escaping table_exists('order_items') also matches a table called
     * 'orderXitems' and reports a table that does not exist as existing.
     *
     * "%" is deliberately left unescaped, as matching several tables with it is a supported use.
     */
    public function testTableExistsMatchesUnderscoresLiterally() {
        $this->db->query("DROP TABLE IF EXISTS order_items");
        $this->db->query("DROP TABLE IF EXISTS orderXitems");
        $this->db->query("CREATE TABLE orderXitems (id INT)");

        // only orderXitems exists - order_items does not
        $this->assertFalse($this->db->table_exists('order_items'), "An underscore must not act as a wildcard");
        $this->assertTrue($this->db->table_exists('orderXitems'), "The table that does exist should be found");

        $this->db->query("DROP TABLE orderXitems");
    }

    public function testTableExistsStillSupportsThePercentWildcard() {
        $this->db->query("DROP TABLE IF EXISTS zebra_wild_one");
        $this->db->query("DROP TABLE IF EXISTS zebra_wild_two");
        $this->db->query("CREATE TABLE zebra_wild_one (id INT)");
        $this->db->query("CREATE TABLE zebra_wild_two (id INT)");

        $this->assertTrue($this->db->table_exists('zebra_wild_%'), "% should still match several tables");
        $this->assertFalse($this->db->table_exists('nothing_like_this_%'), "% matching nothing should be FALSE");

        // and with the underscore escaped, the prefix has to be a literal one
        $this->assertFalse($this->db->table_exists('zebraXwild_%'), "The underscore in the prefix is literal");

        $this->db->query("DROP TABLE zebra_wild_one");
        $this->db->query("DROP TABLE zebra_wild_two");
    }
}
