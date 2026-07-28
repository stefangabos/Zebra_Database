<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Comprehensive security test suite for Zebra_Database
 * Tests SQL injection protection, input validation, and information disclosure
 */
class SecurityTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->connectToDatabase();
        $this->insertTestData();
    }

    // SQL INJECTION TESTS

    /**
     * Test basic SQL injection attempts through escaped parameters
     */
    public function testBasicSqlInjectionPrevention() {
        $malicious_inputs = [
            "'; DROP TABLE test_users; --",
            "' OR '1'='1",
            "' OR 1=1 --",
            "' UNION SELECT password FROM admin_users --",
            "'; DELETE FROM test_users; --",
            "' OR 1=1 /*",
            "admin'--",
            "' OR 'x'='x",
            "') OR ('1'='1",
            "1' AND (SELECT COUNT(*) FROM test_users) > 0 --"
        ];

        foreach ($malicious_inputs as $malicious_input) {
            $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", [$malicious_input]);
            
            // Should return empty result, not cause database errors or return all records
            $this->assertNotFalse($result, "Query should not fail for input: " . $malicious_input);
            
            $rows = $this->db->fetch_assoc_all($result);
            $this->assertEmpty($rows, "No rows should match malicious input: " . $malicious_input);
        }
    }

    /**
     * Test SQL injection through escape() method
     */
    public function testEscapeMethodAgainstSqlInjection() {
        $malicious_inputs = [
            "'; DROP TABLE test_users; --",
            "' OR 1=1 --",
            "\"; DROP TABLE test_users; --",
            "\\' OR \\'1\\'=\\'1",
            "0x27204f52203127203d203127202d2d", // Hex encoded ' OR '1'='1' --
        ];

        foreach ($malicious_inputs as $malicious_input) {
            $escaped = $this->db->escape($malicious_input);

            // escape() escapes, it does not quote - it wraps mysqli_real_escape_string(), so the caller
            // is the one that supplies the enclosing quotes
            $this->assertIsString($escaped, "escape() should return a string");

            // used as a quoted literal, the escaped value must be inert
            $result = $this->db->query("SELECT * FROM test_users WHERE name = '" . $escaped . "'");
            $this->assertNotFalse($result, "Query with escaped input should not fail");

            // and the injection must not have taken effect
            $this->assertTrue($this->db->table_exists('test_users'), "Table must still exist after the query");
            
            $rows = $this->db->fetch_assoc_all($result);
            $this->assertEmpty($rows, "Escaped malicious input should not match any records");
        }
    }

    /**
     * Test second-order SQL injection (stored then executed)
     */
    public function testSecondOrderSqlInjection() {
        $malicious_name = "'; DROP TABLE test_users; --";
        
        // Insert the malicious data
        $insert_result = $this->db->insert('test_users', [
            'name' => $malicious_name,
            'email' => 'malicious@test.com',
            'age' => 25
        ]);
        
        $this->assertTrue($insert_result, "Should be able to insert malicious string safely");
        
        // Retrieve and use the stored data (second-order injection test)
        $result = $this->db->query("SELECT name FROM test_users WHERE email = ?", ['malicious@test.com']);
        $row = $this->db->fetch_assoc($result);
        
        $this->assertNotEmpty($row, "Should retrieve the inserted record");
        $stored_name = $row['name'];
        
        // Now use the retrieved data in another query (this is where second-order injection would occur)
        $second_result = $this->db->query("SELECT * FROM test_users WHERE name = ?", [$stored_name]);
        $this->assertNotFalse($second_result, "Second query should not fail");
        
        $second_rows = $this->db->fetch_assoc_all($second_result);
        $this->assertCount(1, $second_rows, "Should find exactly one matching record");
    }

    /**
     * Test time-based blind SQL injection attempts
     */
    public function testTimeBasedBlindSqlInjection() {
        $time_based_payloads = [
            "' OR SLEEP(1) --",
            "'; WAITFOR DELAY '00:00:01' --",
            "' OR BENCHMARK(1000000,MD5(1)) --",
            "' AND (SELECT SLEEP(1)) --",
        ];

        foreach ($time_based_payloads as $payload) {
            $start_time = microtime(true);
            
            $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", [$payload]);
            
            $end_time = microtime(true);
            $execution_time = $end_time - $start_time;
            
            $this->assertNotFalse($result, "Time-based injection attempt should not cause query failure");
            $this->assertLessThan(0.5, $execution_time, "Query should not be delayed by time-based injection");
            
            $rows = $this->db->fetch_assoc_all($result);
            $this->assertEmpty($rows, "Time-based injection should not return data");
        }
    }

    /**
     * Test boolean-based blind SQL injection
     */
    public function testBooleanBasedBlindSqlInjection() {
        $boolean_payloads = [
            "' AND 1=1 --",
            "' AND 1=2 --", 
            "' AND (SELECT COUNT(*) FROM test_users) > 0 --",
            "' AND (SELECT SUBSTRING(@@version,1,1)) = '5' --",
        ];

        foreach ($boolean_payloads as $payload) {
            $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", [$payload]);
            
            $this->assertNotFalse($result, "Boolean injection attempt should not cause query failure");
            
            $rows = $this->db->fetch_assoc_all($result);
            $this->assertEmpty($rows, "Boolean injection should not return unauthorized data");
        }
    }

    // INPUT VALIDATION TESTS

    /**
     * Test extremely long string inputs
     */
    public function testExtremelyLongInputs() {
        $long_string = str_repeat('A', 1000000); // 1MB string
        $very_long_string = str_repeat('B', 10000000); // 10MB string
        
        $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", [$long_string]);
        $this->assertNotFalse($result, "Should handle long strings gracefully");
        
        // Test with very long string (might hit memory limits)
        try {
            $result2 = $this->db->query("SELECT * FROM test_users WHERE name = ?", [$very_long_string]);
            $this->assertNotFalse($result2, "Should handle very long strings gracefully");
        } catch (Exception $e) {
            // It's acceptable for extremely long strings to cause errors, but they shouldn't be security vulnerabilities
            $this->assertStringNotContainsString('DROP', $e->getMessage(), "Error should not contain SQL injection remnants");
        }
    }

    /**
     * Test binary data and null bytes
     */
    public function testBinaryDataAndNullBytes() {
        $binary_inputs = [
            "\x00\x01\x02\x03", // Binary data with null byte
            "test\x00injection", // Null byte injection
            chr(0) . "' OR '1'='1", // Null byte with SQL injection
            "\xFF\xFE\x00\x00", // Binary data
            pack("H*", "deadbeef"), // Hex packed binary
        ];

        foreach ($binary_inputs as $binary_input) {
            $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", [$binary_input]);
            $this->assertNotFalse($result, "Should handle binary data safely");
            
            $rows = $this->db->fetch_assoc_all($result);
            $this->assertEmpty($rows, "Binary data should not cause unauthorized data access");
        }
    }

    /**
     * Test invalid UTF-8 sequences
     */
    public function testInvalidUtf8Sequences() {
        $invalid_utf8 = [
            "\xFF\xFF", // Invalid UTF-8
            "\xC0\x80", // Overlong encoding of null byte
            "\xE0\x80\x80", // Overlong encoding  
            "\xF0\x80\x80\x80", // Overlong encoding
            "\xED\xA0\x80", // Surrogate half
        ];

        foreach ($invalid_utf8 as $invalid) {
            $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", [$invalid]);
            $this->assertNotFalse($result, "Should handle invalid UTF-8 safely");
            
            $rows = $this->db->fetch_assoc_all($result);
            $this->assertEmpty($rows, "Invalid UTF-8 should not cause data access");
        }
    }

    /**
     * Test path traversal attempts in string inputs
     */
    public function testPathTraversalInputs() {
        $path_traversal_inputs = [
            "../../../etc/passwd",
            "..\\..\\..\\windows\\system32\\config\\sam",
            "%2e%2e%2f%2e%2e%2f%2e%2e%2fetc%2fpasswd", // URL encoded
            "....//....//....//etc//passwd", // Double encoding
            "..%252f..%252f..%252fetc%252fpasswd", // Double URL encoding
        ];

        foreach ($path_traversal_inputs as $traversal_input) {
            $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", [$traversal_input]);
            $this->assertNotFalse($result, "Should handle path traversal attempts safely");
            
            $rows = $this->db->fetch_assoc_all($result);
            $this->assertEmpty($rows, "Path traversal should not access unauthorized data");
        }
    }

    // ERROR INFORMATION DISCLOSURE TESTS

    /**
     * Test that error messages don't leak sensitive information
     */
    public function testErrorInformationDisclosure() {
        // Disable halt_on_errors to capture error messages
        $this->db->halt_on_errors = false;
        $this->db->debug = false;
        
        // Try to query non-existent table
        $result = $this->db->query("SELECT * FROM definitely_nonexistent_table_12345");
        $this->assertFalse($result, "Query should fail for non-existent table");
        
        $error = $this->db->error();
        $this->assertNotEmpty($error, "Should have error message");
        
        // Error should not leak sensitive information
        $this->assertStringNotContainsString('password', strtolower($error), "Error should not contain passwords");
        $this->assertStringNotContainsString('admin', strtolower($error), "Error should not reveal admin info");
        $this->assertStringNotContainsString('root', strtolower($error), "Error should not reveal root info");
        
        // Reset
        $this->db->halt_on_errors = true;
    }

    /**
     * Test connection error information disclosure
     */
    public function testConnectionErrorInformationDisclosure() {
        $bad_db = new Zebra_Database();
        $bad_db->debug = false;
        $bad_db->halt_on_errors = false;
        
        // Try to connect with bad credentials
        // (connect() is lazy, so a query is needed to force the connection to actually be attempted -
        // without it there would be no error yet and the assertions below would never run)
        $bad_db->connect('localhost', 'nonexistent_user_12345', 'bad_password_67890', 'nonexistent_db');
        $bad_db->query("SELECT 1");

        $error = $bad_db->error();

        $this->assertNotEmpty($error, "Connecting with bad credentials should produce an error");

        // the error is expected, but it must not repeat the credentials back
        $this->assertStringNotContainsString('bad_password_67890', $error, "Error should not leak password");

        $bad_db->close();
    }

    // AUTHORIZATION AND ACCESS CONTROL TESTS

    /**
     * Test that queries cannot access unauthorized databases
     */
    public function testUnauthorizedDatabaseAccess() {
        $unauthorized_queries = [
            "SELECT * FROM mysql.user",
            "SELECT * FROM information_schema.tables",
            "SELECT * FROM performance_schema.events_statements_current",
            "SELECT * FROM sys.version",
        ];

        $this->db->halt_on_errors = false;
        
        foreach ($unauthorized_queries as $query) {
            $result = $this->db->query($query);
            
            // These may or may not fail depending on MySQL permissions
            // The important thing is they don't cause crashes or information leaks
            if ($result === false) {
                $error = $this->db->error();
                $this->assertNotEmpty($error, "Should have meaningful error for unauthorized access");
                // Error should not leak structure information
                $this->assertStringNotContainsString('password', strtolower($error), "Error should not leak sensitive info");
            } else {
                // If query succeeds, it means user has appropriate permissions
                // Just verify it doesn't crash
                $rows = $this->db->fetch_assoc_all($result);
                $this->assertIsArray($rows, "Result should be valid array");
            }
        }
        
        $this->db->halt_on_errors = true;
    }

    /**
     * Test UNION-based SQL injection attempts
     */
    public function testUnionBasedSqlInjection() {
        $union_payloads = [
            "' UNION SELECT 1,2,3,4,5,6 --",
            "' UNION SELECT user(),version(),database(),4,5,6 --",
            "' UNION SELECT table_name,column_name,3,4,5,6 FROM information_schema.columns --",
            "' UNION ALL SELECT NULL,NULL,NULL,NULL,NULL,NULL --",
            "' UNION SELECT CONCAT(user,':',password) FROM mysql.user --",
        ];

        foreach ($union_payloads as $payload) {
            $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", [$payload]);
            
            $this->assertNotFalse($result, "UNION injection should not cause query failure: " . $payload);
            
            $rows = $this->db->fetch_assoc_all($result);
            $this->assertEmpty($rows, "UNION injection should not return unauthorized data");
        }
    }

    /**
     * Test stored procedure and function injection attempts
     */
    public function testStoredProcedureInjection() {
        $procedure_payloads = [
            "'; CALL mysql.procedure() --",
            "'; EXEC xp_cmdshell('dir') --", // SQL Server specific, but good to test
            "'; CALL LOAD_FILE('/etc/passwd') --",
            "'; SELECT INTO OUTFILE '/tmp/hack.txt' --",
        ];

        foreach ($procedure_payloads as $payload) {
            $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", [$payload]);
            
            $this->assertNotFalse($result, "Procedure injection should not cause query failure");
            
            $rows = $this->db->fetch_assoc_all($result);
            $this->assertEmpty($rows, "Procedure injection should not execute unauthorized commands");
        }
    }

    /**
     * Test that debug mode doesn't leak sensitive information
     */
    public function testDebugModeInformationLeak() {
        $original_debug = $this->db->debug;
        
        // Enable debug mode
        $this->db->debug = true;
        
        // Capture output
        ob_start();
        
        $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", ['John Doe']);
        
        $debug_output = ob_get_clean();

        $this->assertNotFalse($result, "Query should still work with debugging enabled");

        // the debugging console is rendered on shutdown rather than while the query runs, so nothing at
        // all should have been echoed at this point - were that to change, connection credentials could
        // end up written into the middle of an ordinary response
        $this->assertSame('', $debug_output, "Enabling debugging should not emit output during a query");

        // Restore original debug setting
        $this->db->debug = $original_debug;
    }

    /**
     * Test auto_quote_replacements security
     */
    public function testAutoQuoteReplacementsSecurity() {
        $original_setting = $this->db->auto_quote_replacements;
        
        // Test with auto_quote_replacements disabled
        $this->db->auto_quote_replacements = false;
        
        $malicious_input = "'; DROP TABLE test_users; --";

        // with auto_quote_replacements disabled the library still escapes the value, but it no longer
        // wraps it in quotes - supplying those is then up to the SQL, hence the '?' below
        $result = $this->db->query("SELECT * FROM test_users WHERE name = '?'", [$malicious_input]);
        $this->assertNotFalse($result, "Query should work when the SQL supplies the quotes itself");

        $rows = $this->db->fetch_assoc_all($result);
        $this->assertEmpty($rows, "Should not return unauthorized data");

        // escaping still happens, so the injection is inert
        $this->assertTrue($this->db->table_exists('test_users'), "Table must still exist");
        
        // Restore original setting
        $this->db->auto_quote_replacements = $original_setting;
    }

    /**
     * Test that insert/update/delete methods are secure against injection
     */
    public function testCrudMethodsSecurity() {
        // note that "age" is an INT column, so it gets a valid number - putting a string there fails on
        // the column type under STRICT_TRANS_TABLES, which would tell us nothing about injection
        $malicious_data = [
            'name' => "'; DROP TABLE test_users; --",
            'email' => "' OR '1'='1",
            'age' => 30
        ];

        // Test insert
        $insert_result = $this->db->insert('test_users', $malicious_data);
        $this->assertTrue($insert_result, "Insert should succeed with escaped data");
        
        $insert_id = $this->db->insert_id();
        
        // Verify data was inserted safely
        $result = $this->db->query("SELECT * FROM test_users WHERE id = ?", [$insert_id]);
        $row = $this->db->fetch_assoc($result);
        
        $this->assertEquals($malicious_data['name'], $row['name'], "Malicious data should be stored as literal string");
        $this->assertEquals($malicious_data['email'], $row['email'], "Malicious data should be stored as literal string");
        
        // Test update
        $update_result = $this->db->update('test_users', 
            ['name' => "Updated'; DROP TABLE test_users; --"], 
            'id = ?', 
            [$insert_id]
        );
        $this->assertTrue($update_result, "Update should succeed with escaped data");
        
        // Test delete
        $delete_result = $this->db->delete('test_users', 'id = ?', [$insert_id]);
        $this->assertTrue($delete_result, "Delete should succeed");
        
        // Verify table still exists (wasn't dropped by injection)
        $table_check = $this->db->query("SELECT COUNT(*) as count FROM test_users");
        $this->assertNotFalse($table_check, "Table should still exist after injection attempts");
    }

    // MULTI-BYTE CHARACTER SET ESCAPING

    /**
     * The connection character set has to be changed through mysqli_set_charset() rather than with a
     * "SET NAMES" query, because mysqli only learns about the character set that way. After a "SET
     * NAMES" query the client and the server disagree, and mysqli_real_escape_string() carries on
     * escaping for the character set it still believes is in use.
     *
     * With a character set where a valid multi-byte character can end in 0x5C - gbk, big5, sjis,
     * cp932, gb18030, euc-kr - that lets a crafted value swallow the backslash that escapes the quote
     * after it, and so break out of the enclosing quotes.
     *
     * The payload below is 0xBF followed by a single quote. Escaped for the wrong character set it
     * becomes 0xBF 0x5C 0x27, and gbk reads 0xBF5C as one character, leaving the quote live. Escaped
     * correctly the 0xBF itself is escaped and the quote stays inert.
     */
    public function testMultiByteCharsetCannotBeUsedToBreakOutOfQuotes() {
        $payload = chr(0xbf) . chr(0x27) . " OR 1=1 #";

        $this->db->query("DROP TABLE IF EXISTS test_gbk");
        $this->db->query("CREATE TABLE test_gbk (id INT, secret VARCHAR(40)) CHARACTER SET gbk");
        $this->db->query("INSERT INTO test_gbk VALUES (1, 'alpha'), (2, 'bravo'), (3, 'charlie')");

        $this->assertTrue($this->db->set_charset('gbk', 'gbk_chinese_ci'), "Should be able to switch to gbk");

        $result = $this->db->query("SELECT id FROM test_gbk WHERE secret = ?", [$payload]);
        $this->assertNotFalse($result, "The query itself should run");

        $ids = [];
        while ($row = $this->db->fetch_assoc($result)) $ids[] = $row['id'];

        $this->assertSame([], $ids, "The payload must not match anything - matching every row means the quotes were broken out of");

        $this->db->query("DROP TABLE test_gbk");
    }

    /**
     * The same thing, but with the character set requested before connecting, which takes the deferred
     * path through the library instead.
     */
    public function testMultiByteCharsetIsAlsoSafeWhenSetBeforeConnecting() {
        $payload = chr(0xbf) . chr(0x27) . " OR 1=1 #";

        $deferred = new Zebra_Database();
        $deferred->debug = false;
        $deferred->halt_on_errors = false;
        $deferred->set_charset('gbk', 'gbk_chinese_ci');
        $deferred->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT);

        $deferred->query("DROP TABLE IF EXISTS test_gbk_deferred");
        $deferred->query("CREATE TABLE test_gbk_deferred (id INT, secret VARCHAR(40)) CHARACTER SET gbk");
        $deferred->query("INSERT INTO test_gbk_deferred VALUES (1, 'alpha'), (2, 'bravo')");

        $result = $deferred->query("SELECT id FROM test_gbk_deferred WHERE secret = ?", [$payload]);

        $ids = [];
        while ($row = $deferred->fetch_assoc($result)) $ids[] = $row['id'];

        $this->assertSame([], $ids, "The deferred path must escape just as correctly");

        $deferred->query("DROP TABLE test_gbk_deferred");
        $deferred->close();
    }

    // VALUES THAT LOOK LIKE MYSQL FUNCTION CALLS

    /**
     * insert(), update() and insert_bulk() let a value through unescaped when it looks like a call to a
     * MySQL function, which is how "NOW()" works as a value. What decides it is the list of function names
     * the library keeps - and that list is the only thing standing between a value being stored and a
     * value being executed.
     *
     * These pin the safe half: text that has the shape of a call but whose name is not a MySQL function
     * has to be stored as the text it is. Shorten the list, or drop the check in favour of accepting
     * anything shaped like "name(...)", and these go red.
     *
     * @dataProvider valuesThatOnlyLookLikeCalls
     */
    public function testAValueShapedLikeAFunctionCallIsStoredAsText($value) {
        $this->db->insert('test_users', ['name' => $value, 'email' => 'shaped@example.com']);

        $this->assertSame($value, $this->db->dlookup('name', 'test_users', 'email = ?', ['shaped@example.com']));
    }

    public function valuesThatOnlyLookLikeCalls() {
        return [
            'a surname with a suffix'   => ['Smith(Jr)'],
            'something INC-like'        => ['INC(5) apples'],
            'a word with a bracket'     => ['Reply(All)'],
            'an unknown function name'  => ['NO_SUCH_FUNCTION(1)'],
            'empty brackets'            => ['Whatever()'],
        ];
    }

    /**
     * And the other half, pinned deliberately rather than left implied: a value whose name *is* on the
     * list is executed rather than stored. That is the documented behaviour that makes "NOW()" useful, and
     * the docblock for update() warns that it is a security concern when the argument comes from user
     * input. It is recorded here so that it is a decision rather than a surprise.
     */
    public function testAValueNamingAKnownFunctionIsExecuted() {
        $this->db->insert('test_users', ['name' => 'CONCAT("A","B")', 'email' => 'executed@example.com']);

        $this->assertSame(
            'AB',
            $this->db->dlookup('name', 'test_users', 'email = ?', ['executed@example.com']),
            'A known function name is run, not stored - which is why the list must not grow carelessly'
        );
    }
}