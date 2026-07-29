<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Stress test suite designed to intentionally break the Zebra_Database library
 * Tests resource exhaustion, extreme inputs, and boundary conditions
 */
class StressTest extends DatabaseTestCase
{
    protected function setUp(): void {
        parent::setUp();
        $this->connectToDatabase();
    }

    /**
     * Test resource exhaustion with many concurrent connections
     */
    public function testConnectionExhaustion() {
        $connections = [];
        $max_connections = 10; // Reasonable limit for testing

        // Create multiple connections
        for ($i = 0; $i < $max_connections; $i++) {
            $db = new Zebra_Database();
            $db->debug = false;
            $db->halt_on_errors = false;

            // connect() returns nothing and connects lazily, so whether the server accepted us is not
            // known until a query is actually run - which is what the loop below does
            $db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT);

            $connections[] = $db;
        }

        // every one of them has to be a connection of its own, and all of them usable at the same time -
        // counting the array said only that the loop above had run
        $links = [];

        foreach ($connections as $db) {
            $result = $db->query("SELECT CONNECTION_ID() AS id");
            $this->assertNotFalse($result, "Every connection should remain functional");
            $row = $db->fetch_assoc($result);
            $links[] = $row['id'];
        }

        $this->assertCount($max_connections, array_unique($links), "Each instance holds a connection of its own");

        // Clean up connections
        foreach ($connections as $db) {
            $db->close();
        }
    }

    /**
     * Test memory exhaustion with large result sets
     */
    public function testLargeResultSetMemoryHandling() {
        // Create a table with large data
        $this->db->query("
            CREATE TEMPORARY TABLE large_test_data (
                id INT AUTO_INCREMENT PRIMARY KEY,
                data TEXT
            )
        ");

        // Insert rows with large text data
        $large_text = str_repeat('A', 10000); // 10KB per row
        $row_count = 100; // 1MB total

        for ($i = 0; $i < $row_count; $i++) {
            $this->db->insert('large_test_data', [
                'data' => $large_text . "_row_$i"
            ]);
        }

        // Test fetching all at once (memory intensive)
        $result = $this->db->query("SELECT * FROM large_test_data");
        $this->assertNotFalse($result, "Should be able to query large dataset");

        // Test fetch_assoc_all with large dataset
        $start_memory = memory_get_usage();
        $all_rows = $this->db->fetch_assoc_all($result);
        $end_memory = memory_get_usage();

        $memory_used = $end_memory - $start_memory;

        $this->assertCount($row_count, $all_rows, "Should fetch all rows");
        $this->assertLessThan(50 * 1024 * 1024, $memory_used, "Memory usage should be reasonable (< 50MB)"); // Generous limit
    }

    /**
     * Test query performance with complex operations
     */
    public function testComplexQueryPerformance() {
        // Create tables for complex joins
        // (these are deliberately not TEMPORARY tables - MySQL cannot reopen a temporary table within a
        // single query, and the query below refers to perf_test_table2 both in the FROM and in a subquery)
        $this->db->query("DROP TABLE IF EXISTS perf_test_table1");
        $this->db->query("DROP TABLE IF EXISTS perf_test_table2");

        $this->db->query("
            CREATE TABLE perf_test_table1 (
                id INT AUTO_INCREMENT PRIMARY KEY,
                data VARCHAR(100),
                INDEX(data)
            )
        ");

        $this->db->query("
            CREATE TABLE perf_test_table2 (
                id INT AUTO_INCREMENT PRIMARY KEY,
                table1_id INT,
                value VARCHAR(100),
                INDEX(table1_id),
                INDEX(value)
            )
        ");

        // Insert test data
        for ($i = 1; $i <= 1000; $i++) {
            $this->db->insert('perf_test_table1', ['data' => "data_$i"]);
            $this->db->insert('perf_test_table2', ['table1_id' => $i, 'value' => "value_$i"]);
        }

        // Complex query with joins, subqueries, and aggregation
        $result = $this->db->query("
            SELECT 
                t1.id,
                t1.data,
                t2.value,
                (SELECT COUNT(*) FROM perf_test_table2 t2b WHERE t2b.table1_id = t1.id) as count_related
            FROM perf_test_table1 t1
            LEFT JOIN perf_test_table2 t2 ON t1.id = t2.table1_id
            WHERE t1.data LIKE '%data%'
            ORDER BY t1.id
            LIMIT 10
        ");

        $this->assertNotFalse($result, "Complex query should execute successfully");

        $rows = $this->db->fetch_assoc_all($result);

        $this->assertCount(10, $rows, "Should return 10 rows as limited");

        // the rows are what the query says they are - a wall-clock assertion used to stand here instead,
        // which says nothing about the library and goes red whenever the machine running it is busy
        $this->assertSame('1', $rows[0]['id'], "Ordered by id, so the first row is the first one inserted");
        $this->assertSame('data_1', $rows[0]['data']);
        $this->assertSame('value_1', $rows[0]['value'], "The joined row is the matching one");
        $this->assertSame('1', $rows[0]['count_related'], "And the subquery counted it");

        $this->db->query("DROP TABLE perf_test_table1");
        $this->db->query("DROP TABLE perf_test_table2");
    }

    /**
     * Test boundary conditions with extreme numeric values
     */
    public function testExtremeNumericValues() {
        $this->db->query("
            CREATE TEMPORARY TABLE numeric_test (
                id INT AUTO_INCREMENT PRIMARY KEY,
                big_int BIGINT,
                decimal_val DECIMAL(20,10),
                float_val FLOAT,
                double_val DOUBLE
            )
        ");

        // the largest values each column can actually hold - DECIMAL(20,10) leaves 10 digits before the
        // point, and FLOAT is single precision, so PHP_FLOAT_MAX would overflow it under STRICT_TRANS_TABLES
        $extreme_values = [
            'big_int' => PHP_INT_MAX,
            'decimal_val' => '9999999999.9999999999',
            'float_val' => 3.402823466e+38,
            'double_val' => 1.7976931348623157e+308
        ];

        $result = $this->db->insert('numeric_test', $extreme_values);
        $this->assertTrue($result, "Should handle extreme numeric values");

        $select_result = $this->db->query("SELECT * FROM numeric_test ORDER BY id DESC LIMIT 1");
        $row = $this->db->fetch_assoc($select_result);

        $this->assertNotEmpty($row, "Should retrieve extreme numeric values");
        $this->assertEquals(PHP_INT_MAX, $row['big_int'], "Should preserve large integer values");
    }

    /**
     * Test string handling with extreme lengths and special characters
     */
    public function testExtremeStringHandling() {
        // Test various string lengths and types
        $test_strings = [
            'empty' => '',
            'single_char' => 'a',
            'normal' => 'Hello World',
            'long' => str_repeat('Long string test ', 1000), // ~17KB
            'unicode' => '你好世界 🌍 café naïve résumé',
            'special_chars' => "!@#$%^&*()_+-={}[]|\\:;\"'<>?,./",
            'newlines' => "Line 1\nLine 2\r\nLine 3\rLine 4",
            'tabs' => "Column1\tColumn2\tColumn3",
            'mixed' => "Mixed\x00\x01\x02content\xFF\xFE",
        ];

        // test_value is a BLOB rather than TEXT because the "mixed" case above contains byte sequences
        // that are not valid UTF-8 - a TEXT column in a utf8mb4 table rejects those under STRICT_TRANS_TABLES
        $this->db->query("
            CREATE TEMPORARY TABLE string_test (
                id INT AUTO_INCREMENT PRIMARY KEY,
                test_name VARCHAR(50),
                test_value LONGBLOB
            )
        ");

        foreach ($test_strings as $name => $string) {
            $result = $this->db->insert('string_test', [
                'test_name' => $name,
                'test_value' => $string
            ]);
            $this->assertTrue($result, "Should insert string: $name");
        }

        // Verify retrieval
        foreach ($test_strings as $name => $expected_string) {
            $result = $this->db->query("SELECT test_value FROM string_test WHERE test_name = ?", [$name]);
            $row = $this->db->fetch_assoc($result);

            $this->assertNotEmpty($row, "Should find row for: $name");

            // For binary data, exact comparison might not work due to encoding
            if ($name !== 'mixed') {
                $this->assertEquals($expected_string, $row['test_value'], "Should preserve string: $name");
            }
        }
    }

    /**
     * Test rapid-fire queries to stress query execution
     */
    public function testRapidFireQueries() {
        $query_count = 100;

        for ($i = 0; $i < $query_count; $i++) {
            $result = $this->db->query("SELECT ? as iteration, NOW() as timestamp", [$i]);
            $this->assertNotFalse($result, "Query $i should succeed");

            $row = $this->db->fetch_assoc($result);
            $this->assertEquals($i, $row['iteration'], "Should return correct iteration number");
        }

        // a hundred queries in a row leave the connection exactly as they found it - which is the thing
        // that could actually break here. How long they took is a property of the machine, not of the
        // library, and asserting on it only makes the suite fail on a busy CI runner
        $this->assertSame('', $this->db->error(), "Nothing was left behind on the connection");
        $this->assertEquals(1, $this->db->returned_rows, "And the bookkeeping describes the last query, not the run");
    }

    /**
     * Test transaction handling under stress
     */
    public function testTransactionStress() {
        $this->db->query("
            CREATE TEMPORARY TABLE transaction_test (
                id INT AUTO_INCREMENT PRIMARY KEY,
                value VARCHAR(100)
            )
        ");

        $this->db->transaction_start();

        for ($i = 1; $i <= 50; $i++) {
            $result = $this->db->insert('transaction_test', ['value' => "value_$i"]);
            $this->assertTrue($result, "Insert $i should succeed in transaction");
        }

        // Check uncommitted data
        $count_result = $this->db->query("SELECT COUNT(*) as count FROM transaction_test");
        $count_row = $this->db->fetch_assoc($count_result);
        $this->assertEquals(50, $count_row['count'], "Should have 50 records before commit");

        $this->db->transaction_complete();

        // Verify data persisted
        $final_count_result = $this->db->query("SELECT COUNT(*) as count FROM transaction_test");
        $final_count_row = $this->db->fetch_assoc($final_count_result);
        $this->assertEquals(50, $final_count_row['count'], "Should have 50 records after commit");

        // Test rollback scenario - starting the transaction in "test mode" rolls everything back when
        // transaction_complete() is called, even though every single query is valid
        $this->db->transaction_start(true);

        for ($i = 51; $i <= 100; $i++) {
            $this->db->insert('transaction_test', ['value' => "rollback_value_$i"]);
        }

        $this->db->transaction_complete();

        // Verify rollback worked
        $rollback_count_result = $this->db->query("SELECT COUNT(*) as count FROM transaction_test");
        $rollback_count_row = $this->db->fetch_assoc($rollback_count_result);
        $this->assertEquals(50, $rollback_count_row['count'], "Should still have 50 records after rollback");
    }

    /**
     * Test parameter binding with large number of parameters
     */
    public function testMassiveParameterBinding() {
        $param_count = 100;
        $placeholders = str_repeat('?,', $param_count - 1) . '?';
        $values = range(1, $param_count);

        // CONCAT_WS rather than GREATEST, so that the assertion checks that every one of the parameters
        // was substituted, in the right order - GREATEST would compare the values as the strings they
        // are once quoted, making '9' the largest of 1..100
        $query = "SELECT CONCAT_WS(',', $placeholders) as joined";

        $result = $this->db->query($query, $values);
        $this->assertNotFalse($result, "Should handle $param_count parameters");

        $row = $this->db->fetch_assoc($result);
        $this->assertEquals(implode(',', $values), $row['joined'], "Should correctly process all parameters");
    }

    /**
     * Test array parameter handling with large arrays
     */
    public function testLargeArrayParameters() {
        // Insert some test data first
        for ($i = 1; $i <= 200; $i++) {
            $this->db->insert('test_users', [
                'name' => "Stress Test User $i",
                'email' => "stress$i@test.com",
                'age' => 20 + ($i % 50)
            ]);
        }

        // Create large IN clause with array parameter
        $large_id_array = range(1, 150);

        $result = $this->db->query("SELECT COUNT(*) as count FROM test_users WHERE id IN (?)", [$large_id_array]);
        $this->assertNotFalse($result, "Should handle large array parameter");

        $row = $this->db->fetch_assoc($result);
        $this->assertGreaterThan(0, $row['count'], "Should find records with large IN clause");
        $this->assertLessThanOrEqual(150, $row['count'], "Should not exceed maximum possible matches");
    }

    /**
     * Test cache system under stress
     */
    public function testCacheStress() {
        $this->db->cache_path = getTempPath('cache') . '/stress';

        // Ensure cache directory exists
        if (!is_dir($this->db->cache_path)) {
            mkdir($this->db->cache_path, 0777, true);
        }

        // Generate many cached queries
        $cache_count = 50;

        for ($i = 1; $i <= $cache_count; $i++) {
            $result = $this->db->query("SELECT ? as cache_test", ["cache_value_$i"], 3600); // 1 hour cache
            $this->assertNotFalse($result, "Cached query $i should succeed");

            $row = $this->db->fetch_assoc($result);
            $this->assertEquals("cache_value_$i", $row['cache_test'], "Should return correct cached value");
        }

        // Verify cache files were created
        $cache_files = glob($this->db->cache_path . '/*');
        $this->assertGreaterThan(0, count($cache_files), "Should create cache files");

        // Test cache retrieval
        for ($i = 1; $i <= $cache_count; $i++) {
            $result = $this->db->query("SELECT ? as cache_test", ["cache_value_$i"], 3600);
            $row = $this->db->fetch_assoc($result);
            $this->assertEquals("cache_value_$i", $row['cache_test'], "Should retrieve from cache");
        }

        // Clean up cache files
        $cache_files = glob($this->db->cache_path . '/*');
        foreach ($cache_files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($this->db->cache_path);
    }

    /**
     * Test error handling under stress conditions
     */
    public function testErrorHandlingStress() {
        $this->db->halt_on_errors = false;

        $error_inducing_queries = [
            "SELECT * FROM nonexistent_table_12345",
            "UPDATE test_users SET nonexistent_column = 'test'",
            "DELETE FROM nonexistent_table WHERE id = 1",
            "INSERT INTO test_users (nonexistent_column) VALUES ('test')",
            "SELECT invalid_function(column) FROM test_users",
            "CREATE TABLE test_users (id INT)", // Table already exists
            "DROP DATABASE nonexistent_database",
        ];

        foreach ($error_inducing_queries as $index => $query) {
            $result = $this->db->query($query);
            $this->assertFalse($result, "Error-inducing query $index should fail gracefully");

            $error = $this->db->error();
            $this->assertNotEmpty($error, "Should have error message for query $index");

            // Verify the database connection is still functional after error
            $test_result = $this->db->query("SELECT 1 as test");
            $this->assertNotFalse($test_result, "Database connection should remain functional after error $index");
        }

        $this->db->halt_on_errors = true;
    }

    /**
     * Test concurrent insert/update operations
     */
    public function testConcurrentOperationSimulation() {
        $this->db->query("
            CREATE TEMPORARY TABLE concurrent_test (
                id INT AUTO_INCREMENT PRIMARY KEY,
                counter INT DEFAULT 0,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");

        // Insert initial record
        $this->db->insert('concurrent_test', ['counter' => 0]);
        $test_id = $this->db->insert_id();

        // Simulate concurrent updates
        $update_count = 50;

        for ($i = 1; $i <= $update_count; $i++) {
            // Read current value
            $result = $this->db->query("SELECT counter FROM concurrent_test WHERE id = ?", [$test_id]);
            $row = $this->db->fetch_assoc($result);
            $current_counter = $row['counter'];

            // Update with incremented value
            $new_counter = $current_counter + 1;
            $update_result = $this->db->update('concurrent_test',
                ['counter' => $new_counter],
                'id = ?',
                [$test_id]
            );
            $this->assertTrue($update_result, "Update $i should succeed");
        }

        // Verify final counter value
        $final_result = $this->db->query("SELECT counter FROM concurrent_test WHERE id = ?", [$test_id]);
        $final_row = $this->db->fetch_assoc($final_result);

        $this->assertEquals($update_count, $final_row['counter'], "Final counter should equal update count");
    }

    /**
     * Test resource cleanup under stress
     */
    public function testResourceCleanupStress() {
        $resource_count = 20;

        for ($i = 1; $i <= $resource_count; $i++) {
            $result = $this->db->query("SELECT ? as iteration", [$i]);
            $this->assertNotFalse($result, "Query $i should succeed");

            // Don't explicitly free result - test automatic cleanup
            $row = $this->db->fetch_assoc($result);
            $this->assertEquals($i, $row['iteration'], "Should return correct iteration");
        }

        // results left unfreed must not pile up on the server - what it is still holding is the actual
        // question here, where counting collected garbage cycles could only ever come out as zero or more
        gc_collect_cycles();

        $final_test = $this->db->query("SELECT 'cleanup_test' as test");
        $this->assertNotFalse($final_test, "Database should remain functional after resource stress");
        $this->assertSame('cleanup_test', $this->db->fetch_assoc($final_test)['test'], "And still return what it is asked for");
    }
}
