<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * The "?" placeholders that query() and the shorthand methods accept, and the replacements that are
 * escaped and substituted into them.
 *
 * Note that the library does not use native prepared statements: values are escaped and quoted and then
 * substituted into the SQL. What is tested here is that substitution and its edge cases.
 */
class ParameterizedQueryTest extends DatabaseTestCase
{
    protected function setUp(): void {
        parent::setUp();
        $this->connectToDatabase();
        $this->insertTestData();
    }

    // BASIC PARAMETER SUBSTITUTION

    public function testQueryWithASingleParameter() {
        // a single "?" is replaced by the single given replacement
        $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", ['John Doe']);

        $this->assertNotFalse($result);
        $this->assertEquals(1, $this->db->returned_rows);

        $row = $this->db->fetch_assoc($result);
        $this->assertEquals('John Doe', $row['name']);
    }

    public function testQueryWithoutParameters() {
        // with nothing to substitute the statement is sent as it was written
        $result = $this->db->query("SELECT COUNT(*) as total FROM test_users");

        $this->assertNotFalse($result);
        $row = $this->db->fetch_assoc($result);
        $this->assertGreaterThan(0, (int)$row['total']);
    }

    public function testQueryWithMultipleParameters() {
        $result = $this->db->query(
            "SELECT * FROM test_users WHERE age > ? AND is_active = ?",
            [25, 1]
        );

        $this->assertNotFalse($result);
        $this->assertGreaterThan(0, $this->db->returned_rows);
    }

    public function testParametersOfDifferentTypes() {
        // integers, floats and booleans are all escaped and quoted, and MySQL compares the quoted values
        // against the numeric columns without complaint
        $result = $this->db->query(
            "SELECT * FROM test_users WHERE age = ? AND score = ? AND is_active = ?",
            [30, 85.50, true]
        );

        $this->assertNotFalse($result);
        $this->assertEquals(1, $this->db->returned_rows, "The row should be matched despite the mixed types");

        $row = $this->db->fetch_assoc($result);
        $this->assertIsArray($row, "A row should have been returned");
        $this->assertEquals('John Doe', $row['name']);
        $this->assertEquals(30, (int)$row['age']);
    }

    // UNBUFFERED QUERY FALLBACK TESTS

    public function testUnbufferedQueryWithParameters() {
        // query_unbuffered() is the public way in - it sets the private "unbuffered" flag, runs the
        // query and unsets the flag again, so there is no need to reach into the object to test this
        $result = $this->db->query_unbuffered("SELECT * FROM test_users WHERE name = ?", ['John Doe']);

        $this->assertNotFalse($result);

        $row = $this->db->fetch_assoc($result);
        $this->assertIsArray($row, "A row should have been returned");
        $this->assertEquals('John Doe', $row['name']);

        // an unbuffered result has to be read to the end before the connection can be used again
        while ($this->db->fetch_assoc($result)) {
        }

        $this->assertNotFalse($this->db->query("SELECT 1"), "The connection should be usable afterwards");
    }

    public function testUnbufferedQueryWithoutParameters() {
        $result = $this->db->query_unbuffered("SELECT * FROM test_users ORDER BY name");

        $this->assertNotFalse($result);

        // read the whole set and check we got everything, in order
        $names = [];
        while ($row = $this->db->fetch_assoc($result)) $names[] = $row['name'];

        $this->assertSame(['Bob Johnson', 'Jane Smith', 'John Doe'], $names);

        $this->assertNotFalse($this->db->query("SELECT 1"), "The connection should be usable afterwards");
    }

    // an array as a replacement - expanded into a list, empty, single-item, or mixed with scalars - is
    // ArrayParameterTest's subject

    // ERROR HANDLING

    public function testInvalidSqlWithParameters() {
        $result = $this->db->query("INVALID SQL QUERY ?", ['test']);

        $this->assertFalse($result);

        $error = $this->db->error();
        $this->assertNotEmpty($error);
    }

    public function testMismatchedNumberOfParameters() {
        // more placeholders than replacements
        $result = $this->db->query("SELECT * FROM test_users WHERE name = ? AND age = ?", ['John Doe']);

        $this->assertFalse($result);

        // and more replacements than placeholders
        $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", ['John Doe', 30]);

        $this->assertFalse($result);
    }

    public function testParametersGivenAsSomethingOtherThanAnArray() {
        $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", 'John Doe');

        $this->assertFalse($result);
    }

    // PARAMETERS IN QUERIES OTHER THAN SELECT

    public function testParameterizedInsert() {
        $result = $this->db->query(
            "INSERT INTO test_users (name, email, age) VALUES (?, ?, ?)",
            ['Prepared Insert User', 'prepared@example.com', 35]
        );

        $this->assertTrue($result);
        $this->assertEquals(1, $this->db->affected_rows);

        $insert_id = $this->db->insert_id();
        $this->assertGreaterThan(0, $insert_id);

        $verify = $this->db->query("SELECT * FROM test_users WHERE id = ?", [$insert_id]);
        $row = $this->db->fetch_assoc($verify);
        $this->assertEquals('Prepared Insert User', $row['name']);
    }

    public function testParameterizedUpdate() {
        $result = $this->db->query(
            "UPDATE test_users SET age = ? WHERE name = ?",
            [31, 'John Doe']
        );

        $this->assertTrue($result);
        $this->assertEquals(1, $this->db->affected_rows);

        $verify = $this->db->query("SELECT age FROM test_users WHERE name = ?", ['John Doe']);
        $row = $this->db->fetch_assoc($verify);
        $this->assertEquals(31, (int)$row['age']);
    }

    public function testParameterizedDelete() {
        $this->db->query(
            "INSERT INTO test_users (name, email, age) VALUES (?, ?, ?)",
            ['Delete Me', 'delete@example.com', 25]
        );

        $result = $this->db->query(
            "DELETE FROM test_users WHERE name = ? AND email = ?",
            ['Delete Me', 'delete@example.com']
        );

        $this->assertTrue($result);
        $this->assertEquals(1, $this->db->affected_rows);

        $verify = $this->db->query("SELECT COUNT(*) as count FROM test_users WHERE name = ?", ['Delete Me']);
        $row = $this->db->fetch_assoc($verify);
        $this->assertEquals(0, (int)$row['count']);
    }

    // COMPARISON BETWEEN PREPARED AND REGULAR QUERIES

    public function testParameterizedAndLiteralQueriesReturnTheSameThing() {
        // the same query, once with a replacement and once with the value written into the SQL
        $prepared_result = $this->db->query("SELECT * FROM test_users WHERE age > ?", [25]);
        $prepared_count = $this->db->returned_rows;

        $prepared_rows = [];
        while ($row = $this->db->fetch_assoc($prepared_result)) {
            $prepared_rows[] = $row;
        }

        $regular_result = $this->db->query("SELECT * FROM test_users WHERE age > 25");
        $regular_count = $this->db->returned_rows;

        $regular_rows = [];
        while ($row = $this->db->fetch_assoc($regular_result)) {
            $regular_rows[] = $row;
        }

        $this->assertEquals($prepared_count, $regular_count);
        $this->assertEquals(count($prepared_rows), count($regular_rows));

        // neither query asked for an order, so both are put in one before they are compared
        usort($prepared_rows, function($a, $b) { return $a['id'] - $b['id']; });
        usort($regular_rows, function($a, $b) { return $a['id'] - $b['id']; });

        for ($i = 0; $i < count($prepared_rows); $i++) {
            $this->assertEquals($prepared_rows[$i]['id'], $regular_rows[$i]['id']);
            $this->assertEquals($prepared_rows[$i]['name'], $regular_rows[$i]['name']);
        }
    }

    // PERFORMANCE AND EFFICIENCY TESTS

    public function testTheSameParameterizedQueryRunRepeatedly() {
        // running the same parameterized query repeatedly must not leak state between runs

        $test_names = ['Test 1', 'Test 2', 'Test 3', 'Test 4', 'Test 5'];

        foreach ($test_names as $index => $name) {
            $result = $this->db->query(
                "INSERT INTO test_users (name, email, age) VALUES (?, ?, ?)",
                [$name, "test$index@example.com", 20 + $index]
            );

            $this->assertTrue($result);
            $this->assertEquals(1, $this->db->affected_rows);
        }

        $verify = $this->db->query("SELECT COUNT(*) as count FROM test_users WHERE name LIKE 'Test %'");
        $row = $this->db->fetch_assoc($verify);
        $this->assertEquals(5, (int)$row['count']);
    }

    // SPECIAL CHARACTERS AND ESCAPING

    public function testParametersContainingSpecialCharacters() {
        $special_name = "O'Reilly & Associates <script>alert('xss')</script>";
        $special_email = "test+email@example.com";

        $result = $this->db->query(
            "INSERT INTO test_users (name, email, age) VALUES (?, ?, ?)",
            [$special_name, $special_email, 40]
        );

        $this->assertTrue($result);

        $verify = $this->db->query("SELECT * FROM test_users WHERE email = ?", [$special_email]);
        $row = $this->db->fetch_assoc($verify);

        $this->assertEquals($special_name, $row['name']);
        $this->assertEquals($special_email, $row['email']);
    }

    public function testParametersContainingEncodedBinaryData() {
        // the fixture tables have no binary column, so the bytes make the trip base64 encoded
        $binary_data = "\x00\x01\x02\x03\xFF\xFE";

        $result = $this->db->query(
            "INSERT INTO test_users (name, email, age) VALUES (?, ?, ?)",
            [base64_encode($binary_data), 'binary@example.com', 30]
        );

        $this->assertTrue($result);

        $verify = $this->db->query("SELECT * FROM test_users WHERE email = ?", ['binary@example.com']);
        $row = $this->db->fetch_assoc($verify);

        $this->assertEquals(base64_encode($binary_data), $row['name']);
    }

    // RESOURCE MANAGEMENT

    public function testResultsCanBeFreedAfterEveryQuery() {
        // freeing the result after every query must leave the connection usable
        for ($i = 0; $i < 10; $i++) {
            $result = $this->db->query("SELECT ? as iteration", [$i]);
            $this->assertNotFalse($result);

            $row = $this->db->fetch_assoc($result);
            $this->assertEquals($i, (int)$row['iteration']);

            $this->assertTrue($this->db->free_result($result), "Freeing the result should report success");
        }

        // the connection must still be perfectly usable after all that freeing
        $row = $this->db->fetch_assoc($this->db->query("SELECT COUNT(*) AS total FROM test_users"));
        $this->assertEquals(3, (int)$row['total'], "The connection should still work after freeing 10 results");
    }

    // EDGE CASES

    public function testVeryLongQueryWithAParameter() {
        $long_condition = str_repeat("name != 'dummy' AND ", 100) . "1=1";
        $query = "SELECT * FROM test_users WHERE $long_condition AND name = ?";

        $result = $this->db->query($query, ['John Doe']);

        $this->assertNotFalse($result);
        $this->assertLessThanOrEqual(1, $this->db->returned_rows);
    }

    public function testManyParametersInOneQuery() {
        $conditions = [];
        $params = [];

        for ($i = 0; $i < 20; $i++) {
            $conditions[] = "name != ?";
            $params[] = "dummy_name_$i";
        }

        $conditions[] = "name = ?";
        $params[] = 'John Doe';

        $query = "SELECT * FROM test_users WHERE " . implode(' AND ', $conditions);

        $result = $this->db->query($query, $params);

        $this->assertNotFalse($result);
    }
}
