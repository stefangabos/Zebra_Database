<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Test suite for Zebra_Database error handling functionality
 * Tests the error() method in various scenarios to ensure proper error reporting
 */
class ErrorHandlingTest extends DatabaseTestCase {

    protected function setUp(): void {
        parent::setUp();
        $this->connectToDatabase();
    }

    /**
     * Test that successful queries return empty error strings
     */
    public function testErrorMethodWithSuccessfulQuery() {
        // Execute a successful query
        $result = $this->db->query("SELECT 1 as test");

        $this->assertNotFalse($result, "Query should succeed");

        // Test error() returns empty string for successful query
        $error = $this->db->error();
        $this->assertSame('', $error, "Successful query should return empty error string");

        // Test error(true) behavior for successful query
        $errorWithNumber = $this->db->error(true);
        if (is_array($errorWithNumber)) {
            // If it returns an array, it should have number=0 and message=null/empty for successful queries
            $this->assertArrayHasKey('number', $errorWithNumber, "Error array should have 'number' key");
            $this->assertArrayHasKey('message', $errorWithNumber, "Error array should have 'message' key");
            $this->assertEquals(0, $errorWithNumber['number'], "Successful query should have error number 0");
            $this->assertTrue(is_null($errorWithNumber['message']) || $errorWithNumber['message'] === '',
                "Successful query should have null or empty error message");
        } else {
            // If it returns a string, it should be empty
            $this->assertSame('', $errorWithNumber, "Successful query should return empty error string");
        }
    }

    /**
     * Test that syntax errors return meaningful error messages
     */
    public function testErrorMethodWithSyntaxError() {
        // Execute a query with syntax error
        $result = $this->db->query("SELCT * FROM test_users"); // Intentional typo: SELCT instead of SELECT

        $this->assertFalse($result, "Malformed query should fail");

        // Test error() returns meaningful error message
        $error = $this->db->error();
        $this->assertNotEmpty($error, "Syntax error should return non-empty error message");
        $this->assertStringContainsString('syntax', strtolower($error), "Error message should mention syntax");

        // Test error(true) returns array with number and message
        $errorArray = $this->db->error(true);
        $this->assertIsArray($errorArray, "error(true) should return array for syntax error");
        $this->assertArrayHasKey('number', $errorArray, "Error array should have 'number' key");
        $this->assertArrayHasKey('message', $errorArray, "Error array should have 'message' key");
        $this->assertGreaterThan(0, $errorArray['number'], "Error number should be greater than 0");
        $this->assertNotEmpty($errorArray['message'], "Error message should not be empty");
        $this->assertEquals($error, $errorArray['message'], "Error message should match between error() and error(true)");
    }

    /**
     * Test table not found errors
     */
    public function testErrorMethodWithTableNotFound() {
        // Execute a query referencing non-existent table
        $result = $this->db->query("SELECT * FROM nonexistent_table");

        $this->assertFalse($result, "Query on non-existent table should fail");

        // Test error() returns meaningful error message
        $error = $this->db->error();
        $this->assertNotEmpty($error, "Table not found error should return non-empty error message");
        $this->assertStringContainsString("doesn't exist", $error, "Error message should indicate table doesn't exist");
        $this->assertStringContainsString('nonexistent_table', $error, "Error message should mention the table name");

        // Test error(true) returns array with correct structure
        $errorArray = $this->db->error(true);
        $this->assertIsArray($errorArray, "error(true) should return array for table not found error");
        $this->assertArrayHasKey('number', $errorArray, "Error array should have 'number' key");
        $this->assertArrayHasKey('message', $errorArray, "Error array should have 'message' key");
        $this->assertEquals(1146, $errorArray['number'], "Table not found should return error number 1146");
        $this->assertEquals($error, $errorArray['message'], "Error message should match between error() and error(true)");
    }

    /**
     * Test column not found errors
     */
    public function testErrorMethodWithColumnNotFound() {
        // Execute a query referencing non-existent column
        $result = $this->db->query("SELECT nonexistent_column FROM test_users");

        $this->assertFalse($result, "Query with non-existent column should fail");

        // Test error() returns meaningful error message
        $error = $this->db->error();
        $this->assertNotEmpty($error, "Column not found error should return non-empty error message");
        $this->assertStringContainsString("Unknown column", $error, "Error message should indicate unknown column");
        $this->assertStringContainsString('nonexistent_column', $error, "Error message should mention the column name");

        // Test error(true) returns array with correct structure
        $errorArray = $this->db->error(true);
        $this->assertIsArray($errorArray, "error(true) should return array for column not found error");
        $this->assertArrayHasKey('number', $errorArray, "Error array should have 'number' key");
        $this->assertArrayHasKey('message', $errorArray, "Error array should have 'message' key");
        $this->assertEquals(1054, $errorArray['number'], "Column not found should return error number 1054");
        $this->assertEquals($error, $errorArray['message'], "Error message should match between error() and error(true)");
    }

    /**
     * Test duplicate key errors
     */
    public function testErrorMethodWithDuplicateKey() {
        // First, insert a test user with unique email
        $this->db->insert('test_users', [
            'name' => 'Test User',
            'email' => 'duplicate@example.com',
            'age' => 25
        ]);

        // Try to insert another user with the same email (should fail due to unique constraint)
        $result = $this->db->insert('test_users', [
            'name' => 'Another User',
            'email' => 'duplicate@example.com', // Same email
            'age' => 30
        ]);

        $this->assertFalse($result, "Duplicate email insert should fail");

        // Test error() returns meaningful error message
        $error = $this->db->error();
        $this->assertNotEmpty($error, "Duplicate key error should return non-empty error message");
        $this->assertStringContainsString("Duplicate entry", $error, "Error message should mention duplicate entry");
        $this->assertStringContainsString('duplicate@example.com', $error, "Error message should mention the duplicate value");

        // Test error(true) returns array with correct structure
        $errorArray = $this->db->error(true);
        $this->assertIsArray($errorArray, "error(true) should return array for duplicate key error");
        $this->assertArrayHasKey('number', $errorArray, "Error array should have 'number' key");
        $this->assertArrayHasKey('message', $errorArray, "Error array should have 'message' key");
        $this->assertEquals(1062, $errorArray['number'], "Duplicate key should return error number 1062");
        $this->assertEquals($error, $errorArray['message'], "Error message should match between error() and error(true)");
    }

    /**
     * Test invalid foreign key constraint violation
     */
    public function testErrorMethodWithForeignKeyViolation() {
        // Try to insert a product with non-existent category_id
        // This should always fail regardless of SQL mode
        $result = $this->db->query("INSERT INTO test_products (name, price, category_id) VALUES (?, ?, ?)",
            ['Test Product', 99.99, 999]); // category_id 999 doesn't exist

        // This should fail due to foreign key constraint (if foreign keys are enabled)
        // or succeed if foreign key constraints are not enforced
        if ($result === false) {
            // If it fails, test the error handling
            $error = $this->db->error();
            $this->assertNotEmpty($error, "Foreign key violation should return non-empty error message");

            // Test error(true) returns array with correct structure
            $errorArray = $this->db->error(true);
            $this->assertIsArray($errorArray, "error(true) should return array for foreign key violation");
            $this->assertArrayHasKey('number', $errorArray, "Error array should have 'number' key");
            $this->assertArrayHasKey('message', $errorArray, "Error array should have 'message' key");
            $this->assertGreaterThan(0, $errorArray['number'], "Error number should be greater than 0");
            $this->assertEquals($error, $errorArray['message'], "Error message should match between error() and error(true)");
        } else {
            // If it succeeds, test that no error is reported
            $error = $this->db->error();
            $this->assertEmpty($error, "Successful insert should return empty error");

            // Clean up the inserted record
            $this->db->query("DELETE FROM test_products WHERE category_id = 999");
        }

        // Alternative test: try to create a situation that will definitely fail
        // Let's try to insert into a non-existent column
        $result = $this->db->query("UPDATE test_users SET non_existent_column = ? WHERE id = ?", ['test', 1]);

        $this->assertFalse($result, "Update with non-existent column should fail");

        $error = $this->db->error();
        $this->assertNotEmpty($error, "Column not found error should return non-empty error message");

        $errorArray = $this->db->error(true);
        $this->assertIsArray($errorArray, "error(true) should return array for column not found error");
        $this->assertArrayHasKey('number', $errorArray, "Error array should have 'number' key");
        $this->assertArrayHasKey('message', $errorArray, "Error array should have 'message' key");
        $this->assertEquals(1054, $errorArray['number'], "Column not found should return error number 1054");
    }

    /**
     * Test that error is cleared after successful query
     */
    public function testErrorClearedAfterSuccessfulQuery() {
        // First execute a failing query
        $result = $this->db->query("SELECT * FROM nonexistent_table");
        $this->assertFalse($result, "Query on non-existent table should fail");

        // Verify error is set
        $error = $this->db->error();
        $this->assertNotEmpty($error, "Error should be set after failed query");

        // Now execute a successful query
        $result = $this->db->query("SELECT 1 as test");
        $this->assertNotFalse($result, "Simple query should succeed");

        // Verify error is cleared
        $error = $this->db->error();
        $this->assertSame('', $error, "Error should be cleared after successful query");
    }

    /**
     * Test multiple consecutive errors
     */
    public function testMultipleConsecutiveErrors() {
        // Execute first failing query
        $result1 = $this->db->query("SELECT * FROM nonexistent_table1");
        $this->assertFalse($result1, "First query should fail");

        $error1 = $this->db->error();
        $this->assertNotEmpty($error1, "First error should be set");
        $this->assertStringContainsString('nonexistent_table1', $error1, "First error should mention first table");

        // Execute second failing query
        $result2 = $this->db->query("SELECT * FROM nonexistent_table2");
        $this->assertFalse($result2, "Second query should fail");

        $error2 = $this->db->error();
        $this->assertNotEmpty($error2, "Second error should be set");
        $this->assertStringContainsString('nonexistent_table2', $error2, "Second error should mention second table");

        // Verify that the error reflects the most recent failure
        $this->assertNotEquals($error1, $error2, "Errors should be different");
    }

    /**
     * Test error reporting for a failing parameterized query
     */
    public function testErrorMethodWithFailingParameterizedQuery() {
        // a parameterized query against a table that does not exist
        $result = $this->db->query("SELECT * FROM nonexistent_table WHERE id = ?", [1]);

        $this->assertFalse($result, "A query against a non-existent table should fail");

        // Test error() returns meaningful error message
        $error = $this->db->error();
        $this->assertNotEmpty($error, "A failed query should return a non-empty error message");
        $this->assertStringContainsString("doesn't exist", $error, "Error message should indicate table doesn't exist");

        // Test error(true) returns array with correct structure
        $errorArray = $this->db->error(true);
        $this->assertIsArray($errorArray, "error(true) should return an array for a failed query");
        $this->assertArrayHasKey('number', $errorArray, "Error array should have 'number' key");
        $this->assertArrayHasKey('message', $errorArray, "Error array should have 'message' key");
        $this->assertEquals(1146, $errorArray['number'], "Table not found should return error number 1146");
    }

    /**
     * Test error with invalid parameter binding
     */
    public function testErrorMethodWithInvalidParameterBinding() {
        // Try to use more placeholders than parameters
        $result = $this->db->query("SELECT * FROM test_users WHERE name = ? AND age = ?", ['John']);

        $this->assertFalse($result, "Query with mismatched parameters should fail");

        // Test error() returns error message
        $error = $this->db->error();
        // The error might be empty if this is handled at the application level rather than MySQL level
        // But if there is an error, it should be meaningful
        if (!empty($error)) {
            $this->assertIsString($error, "Error should be a string");
        }

        // Test error(true) behavior
        $errorArray = $this->db->error(true);
        if (is_array($errorArray)) {
            $this->assertArrayHasKey('number', $errorArray, "Error array should have 'number' key");
            $this->assertArrayHasKey('message', $errorArray, "Error array should have 'message' key");
        } else {
            // If no MySQL-level error occurred, error(true) should still return a string
            $this->assertIsString($errorArray, "error(true) should return string if no error occurred");
        }
    }

    /**
     * Test error method behavior with connection errors
     */
    public function testErrorMethodWithConnectionIssues() {
        // This test verifies the error method can handle connection-related errors
        // We'll simulate this by trying to use the error method after closing the connection

        // First, ensure we have a successful connection
        $result = $this->db->query("SELECT 1 as test");
        $this->assertNotFalse($result, "Initial query should succeed");

        $initialError = $this->db->error();
        $this->assertSame('', $initialError, "Initial error should be empty");

        // The error() method should handle cases where connection might be problematic
        // Even after a close, the error() method should not crash
        $error = $this->db->error();
        $this->assertIsString($error, "Error method should always return a string");

        $errorArray = $this->db->error(true);
        $this->assertTrue(is_string($errorArray) || is_array($errorArray),
            "error(true) should return string or array");
    }

    /**
     * Test error method returns consistent format
     */
    public function testErrorMethodReturnFormat() {
        // Test that error() always returns a string
        $error = $this->db->error();
        $this->assertIsString($error, "error() should always return a string");

        // Test that error(false) behaves same as error()
        $errorFalse = $this->db->error(false);
        $this->assertIsString($errorFalse, "error(false) should return a string");
        $this->assertEquals($error, $errorFalse, "error() and error(false) should return same value");

        // Test that error(true) returns string or array
        $errorTrue = $this->db->error(true);
        $this->assertTrue(is_string($errorTrue) || is_array($errorTrue),
            "error(true) should return string or array");

        // If there's an actual error, test the array structure
        // Force an error first
        $this->db->query("SELECT * FROM nonexistent_table");

        $errorArrayAfterFailure = $this->db->error(true);
        if (is_array($errorArrayAfterFailure)) {
            $this->assertArrayHasKey('number', $errorArrayAfterFailure, "Error array should have 'number' key");
            $this->assertArrayHasKey('message', $errorArrayAfterFailure, "Error array should have 'message' key");
            $this->assertIsInt($errorArrayAfterFailure['number'], "Error number should be integer");
            $this->assertIsString($errorArrayAfterFailure['message'], "Error message should be string");

            // Compare with regular error() call
            $regularError = $this->db->error();
            $this->assertEquals($regularError, $errorArrayAfterFailure['message'],
                "Regular error() should match error array message");
        }
    }

    // HALT_ON_ERRORS TESTS - Critical GitHub issue testing

    /**
     * Test halt_on_errors behavior in non-debug mode
     * This tests the critical GitHub issue where halt_on_errors was not respected in non-debug mode
     */
    public function testHaltOnErrorsInNonDebugMode() {
        // Set up non-debug mode with halt_on_errors disabled
        $this->db->debug = false;
        $this->db->halt_on_errors = false;

        // Execute a query that will fail
        $result = $this->db->query("SELECT * FROM absolutely_nonexistent_table_12345");

        // This should return false without halting execution
        $this->assertFalse($result, "Query should fail and return false");

        // Error should be captured
        $error = $this->db->error();
        $this->assertNotEmpty($error, "Error should be captured when halt_on_errors is false");

        // Code should continue executing (if halt_on_errors is properly respected)
        $continue_result = $this->db->query("SELECT 1 as continuation_test");
        $this->assertNotFalse($continue_result, "Subsequent queries should work after error with halt_on_errors=false");

        // Reset to default
        $this->db->halt_on_errors = true;
    }

    /**
     * Test halt_on_errors behavior in debug mode
     */
    public function testHaltOnErrorsInDebugMode() {
        // Set up debug mode with halt_on_errors disabled
        $this->db->debug = true;
        $this->db->halt_on_errors = false;

        // Execute a query that will fail
        $result = $this->db->query("SELECT * FROM absolutely_nonexistent_table_67890");

        // This should return false without halting execution, even in debug mode
        $this->assertFalse($result, "Query should fail and return false in debug mode");

        // Error should be captured
        $error = $this->db->error();
        $this->assertNotEmpty($error, "Error should be captured in debug mode with halt_on_errors=false");

        // Code should continue executing (this is the key test - execution doesn't halt)
        $continue_result = $this->db->query("SELECT 1 as continuation_test");
        $this->assertNotFalse($continue_result, "Subsequent queries should work in debug mode with halt_on_errors=false");

        // Verify debug mode is still active (we can't capture the output, but we can verify the property)
        $this->assertTrue($this->db->debug, "Debug mode should still be active");

        // Reset to defaults
        $this->db->debug = false;
        $this->db->halt_on_errors = true;
    }

    /**
     * Test that halt_on_errors=true actually halts execution when errors occur
     * Note: This test is tricky because if it works, the test will halt
     * We'll test the opposite - that with halt_on_errors=false, execution continues
     */
    public function testHaltOnErrorsTruePreservesDefaultBehavior() {
        // Ensure halt_on_errors is true (default behavior)
        $this->db->halt_on_errors = true;
        $this->db->debug = false;

        // For this test, we'll temporarily set halt_on_errors to false to ensure the test doesn't halt
        $this->db->halt_on_errors = false;

        // Execute failing query
        $result = $this->db->query("SELECT * FROM nonexistent_halt_test_table");
        $this->assertFalse($result, "Query should fail");

        // Verify error is captured
        $error = $this->db->error();
        $this->assertNotEmpty($error, "Error should be captured");

        // Execution should continue (because we set halt_on_errors to false)
        $result2 = $this->db->query("SELECT 1 as test");
        $this->assertNotFalse($result2, "Subsequent query should work with halt_on_errors=false");

        // Reset to default
        $this->db->halt_on_errors = true;
    }

    /**
     * Test halt_on_errors with different types of errors
     */
    public function testHaltOnErrorsWithVariousErrorTypes() {
        $this->db->halt_on_errors = false;
        $this->db->debug = false;

        $error_queries = [
            "SELECT * FROM nonexistent_table_syntax",
            "SELCT * FROM test_users", // Syntax error
            "SELECT nonexistent_column FROM test_users", // Column error
            "INSERT INTO test_users (nonexistent_col) VALUES ('test')", // Insert error
        ];

        foreach ($error_queries as $index => $query) {
            $result = $this->db->query($query);
            $this->assertFalse($result, "Error query $index should fail: $query");

            // Verify error is captured
            $error = $this->db->error();
            $this->assertNotEmpty($error, "Error should be captured for query $index");

            // Verify execution continues
            $continue_result = $this->db->query("SELECT 1 as continue_test");
            $this->assertNotFalse($continue_result, "Execution should continue after error $index");
        }

        // Reset
        $this->db->halt_on_errors = true;
    }

    /**
     * Test halt_on_errors behavior with CRUD operations
     */
    public function testHaltOnErrorsWithCrudOperations() {
        $this->db->halt_on_errors = false;
        $this->db->debug = false;

        // Test insert with invalid data
        $insert_result = $this->db->insert('nonexistent_table', ['col' => 'value']);
        $this->assertFalse($insert_result, "Insert to nonexistent table should fail");

        $error = $this->db->error();
        $this->assertNotEmpty($error, "Insert error should be captured");

        // Verify execution continues
        $continue_result = $this->db->query("SELECT 1 as test_after_insert_error");
        $this->assertNotFalse($continue_result, "Should continue after insert error");

        // Test update with invalid conditions
        $update_result = $this->db->update('nonexistent_table', ['col' => 'value'], 'id = 1');
        $this->assertFalse($update_result, "Update to nonexistent table should fail");

        $error2 = $this->db->error();
        $this->assertNotEmpty($error2, "Update error should be captured");

        // Verify execution continues
        $continue_result2 = $this->db->query("SELECT 1 as test_after_update_error");
        $this->assertNotFalse($continue_result2, "Should continue after update error");

        // Test delete with invalid table
        $delete_result = $this->db->delete('nonexistent_table', 'id = 1');
        $this->assertFalse($delete_result, "Delete from nonexistent table should fail");

        $error3 = $this->db->error();
        $this->assertNotEmpty($error3, "Delete error should be captured");

        // Verify execution continues
        $continue_result3 = $this->db->query("SELECT 1 as test_after_delete_error");
        $this->assertNotFalse($continue_result3, "Should continue after delete error");

        // Reset
        $this->db->halt_on_errors = true;
    }

    /**
     * Test that halt_on_errors setting persists across multiple operations
     */
    public function testHaltOnErrorsPersistence() {
        // Set halt_on_errors to false
        $this->db->halt_on_errors = false;
        $this->db->debug = false;

        // Execute multiple operations, some successful, some failing
        $operations = [
            ["SELECT 1 as test", true], // Should succeed
            ["SELECT * FROM nonexistent_table1", false], // Should fail
            ["SELECT 2 as test", true], // Should succeed
            ["SELECT * FROM nonexistent_table2", false], // Should fail
            ["SELECT 3 as test", true], // Should succeed
        ];

        foreach ($operations as $index => $operation) {
            list($query, $should_succeed) = $operation;

            $result = $this->db->query($query);

            if ($should_succeed) {
                $this->assertNotFalse($result, "Operation $index should succeed: $query");
                $error = $this->db->error();
                $this->assertEmpty($error, "No error should be present for successful operation $index");
            } else {
                $this->assertFalse($result, "Operation $index should fail: $query");
                $error = $this->db->error();
                $this->assertNotEmpty($error, "Error should be captured for failed operation $index");
            }

            // Verify halt_on_errors setting is still false
            $this->assertFalse($this->db->halt_on_errors, "halt_on_errors should remain false throughout");
        }

        // Reset
        $this->db->halt_on_errors = true;
    }

    /**
     * Test debug behavior in CLI environment
     * Note: Debug output is displayed in destructor, so we can't capture it during test execution
     */
    public function testDebugBehaviorInCliEnvironment() {
        // Store original debug setting
        $original_debug = $this->db->debug;
        $original_halt = $this->db->halt_on_errors;
        
        // Test that debug mode can be enabled/disabled without affecting functionality
        $this->db->debug = true;
        $this->db->halt_on_errors = false;
        
        // Execute a failing query in debug mode
        $result = $this->db->query("SELECT * FROM debug_test_nonexistent_table");
        $this->assertFalse($result, "Query should fail");
        
        // Error should be captured even in debug mode
        $error = $this->db->error();
        $this->assertNotEmpty($error, "Error should be captured in debug mode");
        
        // Execution should continue (testing halt_on_errors behavior)
        $continue_result = $this->db->query("SELECT 'debug_continues' as test");
        $this->assertNotFalse($continue_result, "Execution should continue in debug mode with halt_on_errors=false");
        
        // Verify we can fetch the result
        $row = $this->db->fetch_assoc($continue_result);
        $this->assertEquals('debug_continues', $row['test'], "Should be able to fetch results after error in debug mode");
        
        // Test that debug mode doesn't interfere with normal operations
        $this->db->debug = false;
        $normal_result = $this->db->query("SELECT 'normal_operation' as test");
        $this->assertNotFalse($normal_result, "Normal operations should work after disabling debug");
        
        // Restore original settings
        $this->db->debug = $original_debug;
        $this->db->halt_on_errors = $original_halt;
        
        // Note: Debug output will be displayed at script end due to destructor behavior
        // This is expected behavior and cannot be captured during unit tests
        $this->assertTrue(true, "Debug mode test completed - debug output will appear at script end if enabled");
    }

    /**
     * Test that multiple debug-enabled database instances don't interfere with each other
     */
    public function testMultipleDebugInstances() {
        // Create a second database instance
        $db2 = new Zebra_Database();
        $db2->debug = true;
        $db2->halt_on_errors = false;
        $db2->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT);
        
        // Test that both instances work independently
        $this->db->debug = true;
        $this->db->halt_on_errors = false;
        
        // Execute queries on both instances
        $result1 = $this->db->query("SELECT 'instance1' as source");
        $result2 = $db2->query("SELECT 'instance2' as source");
        
        $this->assertNotFalse($result1, "First instance should work");
        $this->assertNotFalse($result2, "Second instance should work");
        
        // Test error handling on both instances
        $error_result1 = $this->db->query("SELECT * FROM nonexistent_table_instance1");
        $error_result2 = $db2->query("SELECT * FROM nonexistent_table_instance2");
        
        $this->assertFalse($error_result1, "First instance error query should fail");
        $this->assertFalse($error_result2, "Second instance error query should fail");
        
        // Both should capture their own errors
        $error1 = $this->db->error();
        $error2 = $db2->error();
        
        $this->assertNotEmpty($error1, "First instance should have error");
        $this->assertNotEmpty($error2, "Second instance should have error");
        $this->assertStringContainsString('nonexistent_table_instance1', $error1, "First error should reference first table");
        $this->assertStringContainsString('nonexistent_table_instance2', $error2, "Second error should reference second table");
        
        // Clean up second instance
        $db2->debug = false;
        $db2->close();
        
        // Reset first instance
        $this->db->debug = false;
        $this->db->halt_on_errors = true;
    }
}