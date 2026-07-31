<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * What error() reports, in both of its shapes, for every kind of failure the server can hand back - and
 * what halt_on_errors does about them when the script is allowed to carry on.
 */
class ErrorHandlingTest extends DatabaseTestCase {

    protected function setUp(): void {
        parent::setUp();
        $this->connectToDatabase();
    }

    /**
     * @group regression
     */
    public function testErrorMethodWithSuccessfulQuery() {
        $result = $this->db->query("SELECT 1 as test");

        $this->assertNotFalse($result, "Query should succeed");

        $error = $this->db->error();
        $this->assertSame('', $error, "Successful query should return empty error string");

        // the array form is only returned when there is an error to describe - with nothing to report both
        // forms give the same empty string, which is what the docblock promises. error(TRUE) once came back
        // as an array holding a NULL message while error() said there was no error at all
        $this->assertSame('', $this->db->error(true), "With no error, error(TRUE) is an empty string too");
    }

    public function testErrorMethodWithSyntaxError() {
        $result = $this->db->query("SELCT * FROM test_users"); // the typo is the point of the test

        $this->assertFalse($result, "Malformed query should fail");

        $error = $this->db->error();
        $this->assertNotEmpty($error, "Syntax error should return non-empty error message");
        $this->assertStringContainsString('syntax', strtolower($error), "Error message should mention syntax");

        $errorArray = $this->db->error(true);
        $this->assertIsArray($errorArray, "error(true) should return array for syntax error");
        $this->assertArrayHasKey('number', $errorArray, "Error array should have 'number' key");
        $this->assertArrayHasKey('message', $errorArray, "Error array should have 'message' key");
        $this->assertGreaterThan(0, $errorArray['number'], "Error number should be greater than 0");
        $this->assertNotEmpty($errorArray['message'], "Error message should not be empty");
        $this->assertEquals($error, $errorArray['message'], "Error message should match between error() and error(true)");
    }

    public function testErrorMethodWithTableNotFound() {
        $result = $this->db->query("SELECT * FROM nonexistent_table");

        $this->assertFalse($result, "Query on non-existent table should fail");

        $error = $this->db->error();
        $this->assertNotEmpty($error, "Table not found error should return non-empty error message");
        $this->assertStringContainsString("doesn't exist", $error, "Error message should indicate table doesn't exist");
        $this->assertStringContainsString('nonexistent_table', $error, "Error message should mention the table name");

        $errorArray = $this->db->error(true);
        $this->assertIsArray($errorArray, "error(true) should return array for table not found error");
        $this->assertArrayHasKey('number', $errorArray, "Error array should have 'number' key");
        $this->assertArrayHasKey('message', $errorArray, "Error array should have 'message' key");
        $this->assertEquals(1146, $errorArray['number'], "Table not found should return error number 1146");
        $this->assertEquals($error, $errorArray['message'], "Error message should match between error() and error(true)");
    }

    public function testErrorMethodWithColumnNotFound() {
        $result = $this->db->query("SELECT nonexistent_column FROM test_users");

        $this->assertFalse($result, "Query with non-existent column should fail");

        $error = $this->db->error();
        $this->assertNotEmpty($error, "Column not found error should return non-empty error message");
        $this->assertStringContainsString("Unknown column", $error, "Error message should indicate unknown column");
        $this->assertStringContainsString('nonexistent_column', $error, "Error message should mention the column name");

        $errorArray = $this->db->error(true);
        $this->assertIsArray($errorArray, "error(true) should return array for column not found error");
        $this->assertArrayHasKey('number', $errorArray, "Error array should have 'number' key");
        $this->assertArrayHasKey('message', $errorArray, "Error array should have 'message' key");
        $this->assertEquals(1054, $errorArray['number'], "Column not found should return error number 1054");
        $this->assertEquals($error, $errorArray['message'], "Error message should match between error() and error(true)");
    }

    public function testErrorMethodWithDuplicateKey() {
        $this->db->insert('test_users', [
            'name' => 'Test User',
            'email' => 'duplicate@example.com',
            'age' => 25
        ]);

        // the email column carries a unique key, so the second row collides with the first
        $result = $this->db->insert('test_users', [
            'name' => 'Another User',
            'email' => 'duplicate@example.com',
            'age' => 30
        ]);

        $this->assertFalse($result, "Duplicate email insert should fail");

        $error = $this->db->error();
        $this->assertNotEmpty($error, "Duplicate key error should return non-empty error message");
        $this->assertStringContainsString("Duplicate entry", $error, "Error message should mention duplicate entry");
        $this->assertStringContainsString('duplicate@example.com', $error, "Error message should mention the duplicate value");

        $errorArray = $this->db->error(true);
        $this->assertIsArray($errorArray, "error(true) should return array for duplicate key error");
        $this->assertArrayHasKey('number', $errorArray, "Error array should have 'number' key");
        $this->assertArrayHasKey('message', $errorArray, "Error array should have 'message' key");
        $this->assertEquals(1062, $errorArray['number'], "Duplicate key should return error number 1062");
        $this->assertEquals($error, $errorArray['message'], "Error message should match between error() and error(true)");
    }

    /**
     * A foreign key violation is reported like any other server error.
     *
     * The shared fixture tables carry no foreign key between them, so the constraint this needs is one the
     * test makes for itself.
     */
    public function testErrorMethodWithForeignKeyViolation() {
        $this->db->query('DROP TABLE IF EXISTS test_child');
        $this->db->query('DROP TABLE IF EXISTS test_parent');
        $this->db->query('CREATE TABLE test_parent (id INT PRIMARY KEY) ENGINE=InnoDB');
        $this->db->query('
            CREATE TABLE test_child (
                id          INT PRIMARY KEY,
                parent_id   INT,
                FOREIGN KEY (parent_id) REFERENCES test_parent(id)
            ) ENGINE=InnoDB
        ');

        $result = $this->db->query('INSERT INTO test_child (id, parent_id) VALUES (?, ?)', [1, 999]);

        $this->assertFalse($result, "A row pointing at a parent that does not exist has to be refused");

        $error = $this->db->error(true);

        $this->assertIsArray($error, "error(TRUE) describes it with a number and a message");
        // 1452 is "Cannot add or update a child row: a foreign key constraint fails"
        $this->assertEquals(1452, $error['number']);
        $this->assertEquals($this->db->error(), $error['message'], "Both forms report the same message");

        $this->db->query('DROP TABLE test_child');
        $this->db->query('DROP TABLE test_parent');
    }

    /**
     * An unknown column named in an UPDATE is error 1054, the same as in a SELECT
     */
    public function testErrorMethodWithUnknownColumnInAnUpdate() {
        $result = $this->db->query("UPDATE test_users SET non_existent_column = ? WHERE id = ?", ['test', 1]);

        $this->assertFalse($result, "Update with non-existent column should fail");

        $errorArray = $this->db->error(true);

        $this->assertIsArray($errorArray, "error(true) should return array for column not found error");
        $this->assertEquals(1054, $errorArray['number'], "Column not found should return error number 1054");
        $this->assertStringContainsString('non_existent_column', $errorArray['message']);
    }

    public function testErrorClearedAfterSuccessfulQuery() {
        $result = $this->db->query("SELECT * FROM nonexistent_table");
        $this->assertFalse($result, "Query on non-existent table should fail");

        $error = $this->db->error();
        $this->assertNotEmpty($error, "Error should be set after failed query");

        // the next successful query is what clears it - error() describes the query that ran last
        $result = $this->db->query("SELECT 1 as test");
        $this->assertNotFalse($result, "Simple query should succeed");

        $error = $this->db->error();
        $this->assertSame('', $error, "Error should be cleared after successful query");
    }

    public function testMultipleConsecutiveErrors() {
        $result1 = $this->db->query("SELECT * FROM nonexistent_table1");
        $this->assertFalse($result1, "First query should fail");

        $error1 = $this->db->error();
        $this->assertNotEmpty($error1, "First error should be set");
        $this->assertStringContainsString('nonexistent_table1', $error1, "First error should mention first table");

        $result2 = $this->db->query("SELECT * FROM nonexistent_table2");
        $this->assertFalse($result2, "Second query should fail");

        $error2 = $this->db->error();
        $this->assertNotEmpty($error2, "Second error should be set");
        $this->assertStringContainsString('nonexistent_table2', $error2, "Second error should mention second table");

        // what error() holds is the failure that came last, not the first one
        $this->assertNotEquals($error1, $error2, "Errors should be different");
    }

    public function testErrorMethodWithFailingParameterizedQuery() {
        $result = $this->db->query("SELECT * FROM nonexistent_table WHERE id = ?", [1]);

        $this->assertFalse($result, "A query against a non-existent table should fail");

        $error = $this->db->error();
        $this->assertNotEmpty($error, "A failed query should return a non-empty error message");
        $this->assertStringContainsString("doesn't exist", $error, "Error message should indicate table doesn't exist");

        $errorArray = $this->db->error(true);
        $this->assertIsArray($errorArray, "error(true) should return an array for a failed query");
        $this->assertArrayHasKey('number', $errorArray, "Error array should have 'number' key");
        $this->assertArrayHasKey('message', $errorArray, "Error array should have 'message' key");
        $this->assertEquals(1146, $errorArray['number'], "Table not found should return error number 1146");
    }

    public function testErrorMethodWithInvalidParameterBinding() {
        // two placeholders, one replacement
        $result = $this->db->query("SELECT * FROM test_users WHERE name = ? AND age = ?", ['John']);

        $this->assertFalse($result, "Query with mismatched parameters should fail");

        // the library refuses the query itself, so it never reaches MySQL and there is no MySQL error to
        // report - error() describes the last thing the *server* was asked to do, and here that is nothing
        $this->assertSame('', $this->db->error(), "A query the library refused leaves no MySQL error behind");
        $this->assertSame('', $this->db->error(true), "And the array form agrees with it");
    }

    /**
     * error() is reached through "or die($db->error())", so it has to survive being called when there is no
     * connection left to ask - the connection is gone here rather than merely idle, which is the case the
     * try/catch inside the method exists for
     */
    public function testErrorMethodAfterTheConnectionHasBeenClosed() {
        $this->assertNotFalse($this->db->query("SELECT 1 as test"), "Initial query should succeed");
        $this->assertSame('', $this->db->error(), "Nothing has gone wrong yet");

        $this->db->close();

        $raised = $this->diagnosticsRaisedBy(function() {
            $this->assertSame('', $this->db->error(), "There is no connection to report an error from");
            $this->assertSame('', $this->db->error(true), "And the array form says the same");
        });

        $this->assertSame([], $raised, "Asking a closed connection for its error must not warn");
    }

    /**
     * With nothing to report the argument makes no difference - all three calls give the same empty string
     */
    public function testErrorMethodReturnsAnEmptyStringWhileNothingHasFailed() {
        $this->assertSame('', $this->db->error(), "error() with nothing to report");
        $this->assertSame('', $this->db->error(false), "error(FALSE) is the same call");
        $this->assertSame('', $this->db->error(true), "and so is error(TRUE), until there is something to describe");
    }

    /**
     * And the array shape is what comes back once something has actually failed
     */
    public function testErrorMethodDescribesAFailureAsAnArray() {
        $this->db->query("SELECT * FROM nonexistent_table");

        $error = $this->db->error(true);

        $this->assertIsArray($error, "error(TRUE) describes a failure as an array");
        $this->assertSame(['number', 'message'], array_keys($error), "holding exactly a number and a message");
        $this->assertIsInt($error['number']);
        $this->assertIsString($error['message']);
        $this->assertSame($this->db->error(), $error['message'], "which is the same message the string form gives");
    }

    // HALT_ON_ERRORS

    /**
     * With debugging off, halt_on_errors is not honoured either way round - the failure is recorded, the
     * call returns FALSE, and the script carries on. HaltingTest covers the half that ends the script
     */
    public function testHaltOnErrorsInNonDebugMode() {
        $this->db->debug = false;
        $this->db->halt_on_errors = false;

        $result = $this->db->query("SELECT * FROM absolutely_nonexistent_table_12345");

        $this->assertFalse($result, "Query should fail and return false");

        $error = $this->db->error();
        $this->assertNotEmpty($error, "Error should be captured when halt_on_errors is false");

        // the connection is still usable, which is what "does not halt" means from in here
        $continue_result = $this->db->query("SELECT 1 as continuation_test");
        $this->assertNotFalse($continue_result, "Subsequent queries should work after error with halt_on_errors=false");

        $this->db->halt_on_errors = true;
    }

    public function testHaltOnErrorsInDebugMode() {
        $this->db->debug = true;
        $this->db->halt_on_errors = false;

        $result = $this->db->query("SELECT * FROM absolutely_nonexistent_table_67890");

        $this->assertFalse($result, "Query should fail and return false in debug mode");

        $error = $this->db->error();
        $this->assertNotEmpty($error, "Error should be captured in debug mode with halt_on_errors=false");

        // debugging on is what lets halting happen at all, and with it switched off nothing stops
        $continue_result = $this->db->query("SELECT 1 as continuation_test");
        $this->assertNotFalse($continue_result, "Subsequent queries should work in debug mode with halt_on_errors=false");

        $this->assertTrue($this->db->debug, "Debug mode should still be active");

        $this->db->debug = false;
        $this->db->halt_on_errors = true;
    }

    public function testHaltOnErrorsWithVariousErrorTypes() {
        $this->db->halt_on_errors = false;
        $this->db->debug = false;

        $error_queries = [
            "SELECT * FROM nonexistent_table_syntax",
            "SELCT * FROM test_users", // a syntax error
            "SELECT nonexistent_column FROM test_users", // an unknown column
            "INSERT INTO test_users (nonexistent_col) VALUES ('test')", // and one being written to
        ];

        foreach ($error_queries as $index => $query) {
            $result = $this->db->query($query);
            $this->assertFalse($result, "Error query $index should fail: $query");

            $error = $this->db->error();
            $this->assertNotEmpty($error, "Error should be captured for query $index");

            $continue_result = $this->db->query("SELECT 1 as continue_test");
            $this->assertNotFalse($continue_result, "Execution should continue after error $index");
        }

        $this->db->halt_on_errors = true;
    }

    public function testHaltOnErrorsWithCrudOperations() {
        $this->db->halt_on_errors = false;
        $this->db->debug = false;

        $insert_result = $this->db->insert('nonexistent_table', ['col' => 'value']);
        $this->assertFalse($insert_result, "Insert to nonexistent table should fail");

        $error = $this->db->error();
        $this->assertNotEmpty($error, "Insert error should be captured");

        $continue_result = $this->db->query("SELECT 1 as test_after_insert_error");
        $this->assertNotFalse($continue_result, "Should continue after insert error");

        $update_result = $this->db->update('nonexistent_table', ['col' => 'value'], 'id = 1');
        $this->assertFalse($update_result, "Update to nonexistent table should fail");

        $error2 = $this->db->error();
        $this->assertNotEmpty($error2, "Update error should be captured");

        $continue_result2 = $this->db->query("SELECT 1 as test_after_update_error");
        $this->assertNotFalse($continue_result2, "Should continue after update error");

        $delete_result = $this->db->delete('nonexistent_table', 'id = 1');
        $this->assertFalse($delete_result, "Delete from nonexistent table should fail");

        $error3 = $this->db->error();
        $this->assertNotEmpty($error3, "Delete error should be captured");

        $continue_result3 = $this->db->query("SELECT 1 as test_after_delete_error");
        $this->assertNotFalse($continue_result3, "Should continue after delete error");

        $this->db->halt_on_errors = true;
    }

    public function testHaltOnErrorsPersistence() {
        $this->db->halt_on_errors = false;
        $this->db->debug = false;

        // failures and successes in turn, so that a setting reset by either of them would show up
        $operations = [
            ["SELECT 1 as test", true],
            ["SELECT * FROM nonexistent_table1", false],
            ["SELECT 2 as test", true],
            ["SELECT * FROM nonexistent_table2", false],
            ["SELECT 3 as test", true],
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

            $this->assertFalse($this->db->halt_on_errors, "halt_on_errors should remain false throughout");
        }

        $this->db->halt_on_errors = true;
    }

    /**
     * The console is printed by a shutdown function, so what it holds cannot be read from in here - what
     * can be is that turning debugging on changes nothing about the queries themselves. HaltingTest reads
     * the console itself, from a child process that has ended
     */
    public function testDebugBehaviorInCliEnvironment() {
        $original_debug = $this->db->debug;
        $original_halt = $this->db->halt_on_errors;

        $this->db->debug = true;
        $this->db->halt_on_errors = false;

        $result = $this->db->query("SELECT * FROM debug_test_nonexistent_table");
        $this->assertFalse($result, "Query should fail");

        $error = $this->db->error();
        $this->assertNotEmpty($error, "Error should be captured in debug mode");

        $continue_result = $this->db->query("SELECT 'debug_continues' as test");
        $this->assertNotFalse($continue_result, "Execution should continue in debug mode with halt_on_errors=false");

        $row = $this->db->fetch_assoc($continue_result);
        $this->assertEquals('debug_continues', $row['test'], "Should be able to fetch results after error in debug mode");

        // and switching it off again leaves the instance as usable as it was
        $this->db->debug = false;
        $normal_result = $this->db->query("SELECT 'normal_operation' as test");
        $this->assertNotFalse($normal_result, "Normal operations should work after disabling debug");

        $this->db->debug = $original_debug;
        $this->db->halt_on_errors = $original_halt;
    }

    /**
     * Each instance keeps its own errors - two of them debugging at once do not share a console between
     * them, which they would if any of that bookkeeping were static
     */
    public function testMultipleDebugInstances() {
        $db2 = new Zebra_Database();
        $db2->debug = true;
        $db2->halt_on_errors = false;
        $db2->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT);

        $this->db->debug = true;
        $this->db->halt_on_errors = false;

        $result1 = $this->db->query("SELECT 'instance1' as source");
        $result2 = $db2->query("SELECT 'instance2' as source");

        $this->assertNotFalse($result1, "First instance should work");
        $this->assertNotFalse($result2, "Second instance should work");

        // a failure on each, against a different table, so the two errors cannot be mistaken for each other
        $error_result1 = $this->db->query("SELECT * FROM nonexistent_table_instance1");
        $error_result2 = $db2->query("SELECT * FROM nonexistent_table_instance2");

        $this->assertFalse($error_result1, "First instance error query should fail");
        $this->assertFalse($error_result2, "Second instance error query should fail");

        $error1 = $this->db->error();
        $error2 = $db2->error();

        $this->assertNotEmpty($error1, "First instance should have error");
        $this->assertNotEmpty($error2, "Second instance should have error");
        $this->assertStringContainsString('nonexistent_table_instance1', $error1, "First error should reference first table");
        $this->assertStringContainsString('nonexistent_table_instance2', $error2, "Second error should reference second table");

        $db2->debug = false;
        $db2->close();

        $this->db->debug = false;
        $this->db->halt_on_errors = true;
    }
}
