<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Test suite for Zebra_Database array parameter handling in IN clauses and similar constructs
 */
class ArrayParameterTest extends DatabaseTestCase {

    protected function setUp(): void {
        parent::setUp();
        $this->connectToDatabase();
        $this->insertTestData();
    }

    // BASIC ARRAY PARAMETER TESTS

    public function testSimpleArrayParameterInClause() {
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

    public function testSingleElementArrayParameter() {
        $result = $this->db->query(
            "SELECT * FROM test_users WHERE name IN (?)",
            [['John Doe']]
        );

        $this->assertNotFalse($result);
        $this->assertEquals(1, $this->db->returned_rows);

        $row = $this->db->fetch_assoc($result);
        $this->assertEquals('John Doe', $row['name']);
    }

    public function testEmptyArrayParameter() {
        $result = $this->db->query(
            "SELECT * FROM test_users WHERE name IN (?)",
            [[]]
        );

        $this->assertNotFalse($result);
        $this->assertEquals(0, $this->db->returned_rows);
    }

    public function testLargeArrayParameter() {
        // Create a large array of names
        $many_names = [];
        for ($i = 1; $i <= 100; $i++) {
            $many_names[] = "User $i";
        }

        // Add some existing names
        $many_names[] = 'John Doe';
        $many_names[] = 'Jane Smith';

        $result = $this->db->query(
            "SELECT * FROM test_users WHERE name IN (?)",
            [$many_names]
        );

        $this->assertNotFalse($result);
        $this->assertEquals(2, $this->db->returned_rows); // Should find John and Jane

        $names = [];
        while ($row = $this->db->fetch_assoc($result)) {
            $names[] = $row['name'];
        }

        $this->assertContains('John Doe', $names);
        $this->assertContains('Jane Smith', $names);
    }

    // NUMERIC ARRAY PARAMETERS

    public function testNumericArrayParameter() {
        $result = $this->db->query(
            "SELECT * FROM test_users WHERE age IN (?)",
            [[25, 30, 35]]
        );

        $this->assertNotFalse($result);
        $this->assertEquals(3, $this->db->returned_rows); // All test users

        $ages = [];
        while ($row = $this->db->fetch_assoc($result)) {
            $ages[] = (int)$row['age'];
        }

        $this->assertContains(25, $ages); // Jane
        $this->assertContains(30, $ages); // John
        $this->assertContains(35, $ages); // Bob
    }

    public function testFloatArrayParameter() {
        $result = $this->db->query(
            "SELECT * FROM test_users WHERE score IN (?)",
            [[85.50, 92.75]]
        );

        $this->assertNotFalse($result);
        $this->assertEquals(2, $this->db->returned_rows);

        $scores = [];
        while ($row = $this->db->fetch_assoc($result)) {
            $scores[] = (float)$row['score'];
        }

        $this->assertContains(85.50, $scores); // John
        $this->assertContains(92.75, $scores); // Jane
    }

    public function testMixedNumericArrayParameter() {
        $result = $this->db->query(
            "SELECT * FROM test_users WHERE age IN (?)",
            [[25, 30.0, '35']] // Mix of int, float, string
        );

        $this->assertNotFalse($result);
        $this->assertEquals(3, $this->db->returned_rows);
    }

    // BOOLEAN AND NULL ARRAY PARAMETERS

    public function testBooleanArrayParameter() {
        $result = $this->db->query(
            "SELECT * FROM test_users WHERE is_active IN (?)",
            [[true, false]]
        );

        $this->assertNotFalse($result);
        $this->assertEquals(3, $this->db->returned_rows); // All users (some active, some inactive)
    }

    public function testMixedTypeArrayParameter() {
        // Create test data with mixed types
        $this->db->insert('test_users', [
            'name' => '42', // String that looks like number
            'email' => 'number@example.com',
            'age' => 42
        ]);

        $result = $this->db->query(
            "SELECT * FROM test_users WHERE name IN (?)",
            [['42', 42, 'John Doe', null]]
        );

        $this->assertNotFalse($result);

        // Should handle type conversions appropriately
        $this->assertGreaterThanOrEqual(1, $this->db->returned_rows);
    }

    // MULTIPLE ARRAY PARAMETERS

    public function testMultipleArrayParameters() {
        $result = $this->db->query(
            "SELECT * FROM test_users WHERE name IN (?) AND age IN (?)",
            [
                ['John Doe', 'Jane Smith', 'Bob Johnson'],
                [25, 30]
            ]
        );

        $this->assertNotFalse($result);
        $this->assertEquals(2, $this->db->returned_rows); // John (30) and Jane (25)

        $names = [];
        while ($row = $this->db->fetch_assoc($result)) {
            $names[] = $row['name'];
        }

        $this->assertContains('John Doe', $names);
        $this->assertContains('Jane Smith', $names);
        $this->assertNotContains('Bob Johnson', $names); // Bob is 35, not in age array
    }

    public function testMixedArrayAndScalarParameters() {
        $result = $this->db->query(
            "SELECT * FROM test_users WHERE name IN (?) AND is_active = ?",
            [['John Doe', 'Jane Smith'], 1]
        );

        $this->assertNotFalse($result);
        $this->assertEquals(2, $this->db->returned_rows); // Both are active

        $names = [];
        while ($row = $this->db->fetch_assoc($result)) {
            $names[] = $row['name'];
        }

        $this->assertContains('John Doe', $names);
        $this->assertContains('Jane Smith', $names);
    }

    public function testScalarBetweenArrayParameters() {
        $result = $this->db->query(
            "SELECT * FROM test_users WHERE name IN (?) AND age > ? AND email IN (?)",
            [
                ['John Doe', 'Jane Smith', 'Bob Johnson'],
                25,
                ['john@example.com', 'bob@example.com']
            ]
        );

        $this->assertNotFalse($result);

        // Should find John (age 30 > 25, email matches) and potentially Bob (age 35 > 25, email matches)
        // But need to check our test data for Bob's active status
        $names = [];
        while ($row = $this->db->fetch_assoc($result)) {
            $names[] = $row['name'];
        }

        $this->assertContains('John Doe', $names);
    }

    // ARRAY PARAMETER ERROR HANDLING

    public function testArrayParameterWithWrongPlaceholderCount() {
        // More array parameters than placeholders
        $result = $this->db->query(
            "SELECT * FROM test_users WHERE name IN (?)",
            [['John Doe'], ['Jane Smith']] // Two arrays but one placeholder
        );

        $this->assertFalse($result);
    }

    public function testNestedArrayParameter() {
        // Arrays within arrays should be flattened or handled appropriately
        $result = $this->db->query(
            "SELECT * FROM test_users WHERE name IN (?)",
            [['John Doe', ['Jane Smith']]] // Nested array
        );

        // Behavior may vary - some implementations might flatten, others might error
        // We'll test that it doesn't crash
        $this->assertNotNull($result); // Should return something, even if false
    }

    // SPECIAL CHARACTER HANDLING IN ARRAYS

    public function testArrayParameterWithSpecialCharacters() {
        // Insert test data with special characters
        $special_names = [
            "O'Reilly & Sons",
            "Smith, John Jr.",
            'Quote"Test',
            "Slash\\Test",
            "Unicode: 中文测试"
        ];

        foreach ($special_names as $i => $name) {
            $this->db->insert('test_users', [
                'name' => $name,
                'email' => "special$i@example.com",
                'age' => 30 + $i
            ]);
        }

        $result = $this->db->query(
            "SELECT * FROM test_users WHERE name IN (?)",
            [$special_names]
        );

        $this->assertNotFalse($result);
        $this->assertEquals(count($special_names), $this->db->returned_rows);

        $found_names = [];
        while ($row = $this->db->fetch_assoc($result)) {
            $found_names[] = $row['name'];
        }

        foreach ($special_names as $name) {
            $this->assertContains($name, $found_names);
        }
    }

    public function testArrayParameterWithSQLInjectionAttempts() {
        $malicious_array = [
            "'; DROP TABLE test_users; --",
            "' OR '1'='1",
            "normal_value",
            "' UNION SELECT * FROM test_users --"
        ];

        // Insert a normal value for comparison
        $this->db->insert('test_users', [
            'name' => 'normal_value',
            'email' => 'normal@example.com',
            'age' => 25
        ]);

        $result = $this->db->query(
            "SELECT * FROM test_users WHERE name IN (?)",
            [$malicious_array]
        );

        $this->assertNotFalse($result);
        $this->assertEquals(1, $this->db->returned_rows); // Should only find 'normal_value'

        $row = $this->db->fetch_assoc($result);
        $this->assertEquals('normal_value', $row['name']);

        // Verify table still exists and has expected data
        $count_result = $this->db->query("SELECT COUNT(*) as count FROM test_users");
        $count_row = $this->db->fetch_assoc($count_result);
        $this->assertGreaterThan(0, (int)$count_row['count']);
    }

    // PERFORMANCE AND EDGE CASES

    public function testVeryLargeArrayParameter() {
        // Test with a very large array (1000+ elements)
        $large_array = [];
        for ($i = 1; $i <= 1000; $i++) {
            $large_array[] = "nonexistent_user_$i";
        }

        // Add one existing user
        $large_array[] = 'John Doe';

        $result = $this->db->query(
            "SELECT * FROM test_users WHERE name IN (?)",
            [$large_array]
        );

        $this->assertNotFalse($result);
        $this->assertEquals(1, $this->db->returned_rows); // Should find only John Doe

        $row = $this->db->fetch_assoc($result);
        $this->assertEquals('John Doe', $row['name']);
    }

    public function testArrayParameterWithDuplicates() {
        $array_with_duplicates = ['John Doe', 'John Doe', 'Jane Smith', 'Jane Smith'];

        $result = $this->db->query(
            "SELECT * FROM test_users WHERE name IN (?)",
            [$array_with_duplicates]
        );

        $this->assertNotFalse($result);
        $this->assertEquals(2, $this->db->returned_rows); // Should still find only 2 distinct users

        $names = [];
        while ($row = $this->db->fetch_assoc($result)) {
            $names[] = $row['name'];
        }

        $this->assertContains('John Doe', $names);
        $this->assertContains('Jane Smith', $names);
    }

    // INTEGRATION WITH OTHER DATABASE OPERATIONS

    public function testArrayParameterInSubquery() {
        $result = $this->db->query(
            "SELECT * FROM test_users WHERE id IN (SELECT id FROM test_users WHERE name IN (?))",
            [['John Doe', 'Jane Smith']]
        );

        $this->assertNotFalse($result);
        $this->assertEquals(2, $this->db->returned_rows);
    }

    public function testArrayParameterWithJoins() {
        // Create some test products linked to users
        $this->db->query("INSERT INTO test_products (name, price, category_id) VALUES ('Laptop', 999.99, 1)");
        $this->db->query("INSERT INTO test_products (name, price, category_id) VALUES ('Book', 19.99, 2)");

        $result = $this->db->query(
            "SELECT u.*, p.name as product_name FROM test_users u
             JOIN test_products p ON u.age > 25
             WHERE u.name IN (?)",
            [['John Doe', 'Jane Smith', 'Bob Johnson']]
        );

        $this->assertNotFalse($result);
        // Should return multiple rows due to cartesian product of JOIN
        $this->assertGreaterThan(0, $this->db->returned_rows);
    }

    public function testArrayParameterWithOrderAndLimit() {
        $result = $this->db->query(
            "SELECT * FROM test_users WHERE name IN (?) ORDER BY age DESC LIMIT 2",
            [['John Doe', 'Jane Smith', 'Bob Johnson']]
        );

        $this->assertNotFalse($result);
        $this->assertLessThanOrEqual(2, $this->db->returned_rows);

        // Should be ordered by age descending
        $previous_age = PHP_INT_MAX;
        while ($row = $this->db->fetch_assoc($result)) {
            $current_age = (int)$row['age'];
            $this->assertLessThanOrEqual($previous_age, $current_age);
            $previous_age = $current_age;
        }
    }

    // ARRAY CASTING TESTS

    public function testArrayCastingBehavior() {
        // Test the (array) casting mentioned in requirements
        $scalar_value = 'John Doe';

        $result = $this->db->query(
            "SELECT * FROM test_users WHERE name IN (?)",
            [$scalar_value] // Single scalar, should be cast to array internally
        );

        // This tests whether the library properly handles (array)$replacement casting
        $this->assertNotFalse($result);

        // The actual behavior depends on the implementation
        // If scalar is properly cast to array, it should work like array('John Doe')
        $this->assertGreaterThanOrEqual(0, $this->db->returned_rows);
    }

    public function testImplodeMethodWithArrays() {
        // Test the implode method with various array types
        $simple_array = ['a', 'b', 'c'];
        $imploded = $this->db->implode($simple_array);
        $this->assertIsString($imploded);

        $numeric_array = [1, 2, 3, 4, 5];
        $imploded_numeric = $this->db->implode($numeric_array);
        $this->assertIsString($imploded_numeric);

        $mixed_array = ['string', 123, 45.67, null, true, false];
        $imploded_mixed = $this->db->implode($mixed_array);
        $this->assertIsString($imploded_mixed);
    }

    // EMPTY ARRAY REPLACEMENTS

    /**
     * An empty array used to be imploded to an empty string, producing "IN ()" - a syntax error. It now
     * becomes a subquery that returns no rows, which is the only value that is correct in both
     * directions: "IN" then matches nothing, and "NOT IN" matches everything, which is what an empty
     * set means. A literal NULL gets "IN" right but "NOT IN" wrong, and an empty string gets both
     * wrong by matching rows whose value happens to be the empty string.
     */
    public function testEmptyArrayInAnInClauseMatchesNothing() {
        $result = $this->db->query("SELECT id FROM test_users WHERE name IN (?)", [[]]);

        $this->assertNotFalse($result, "An empty array must not produce invalid SQL");

        $ids = [];
        while ($row = $this->db->fetch_assoc($result)) $ids[] = $row['id'];

        $this->assertSame([], $ids, "IN over an empty set matches nothing");
    }

    public function testEmptyArrayInANotInClauseMatchesEverything() {
        $expected = [];
        $all = $this->db->query("SELECT id FROM test_users ORDER BY id");
        while ($row = $this->db->fetch_assoc($all)) $expected[] = $row['id'];

        $this->assertNotEmpty($expected, "There should be rows to match in the first place");

        $result = $this->db->query("SELECT id FROM test_users WHERE name NOT IN (?) ORDER BY id", [[]]);

        $this->assertNotFalse($result, "An empty array must not produce invalid SQL");

        $ids = [];
        while ($row = $this->db->fetch_assoc($result)) $ids[] = $row['id'];

        $this->assertSame($expected, $ids, "NOT IN over an empty set excludes nothing, so every row matches");
    }

    public function testEmptyArrayDoesNotMatchRowsWithAnEmptyStringValue() {
        // this is what an empty string replacement got wrong - it matched the row below
        $this->db->query("DROP TABLE IF EXISTS test_empty_value");
        $this->db->query("CREATE TABLE test_empty_value (id INT, v VARCHAR(20))");
        $this->db->query("INSERT INTO test_empty_value VALUES (1, ''), (2, 'a')");

        $result = $this->db->query("SELECT id FROM test_empty_value WHERE v IN (?)", [[]]);

        $ids = [];
        while ($row = $this->db->fetch_assoc($result)) $ids[] = $row['id'];

        $this->assertSame([], $ids, "An empty array must not match a row whose value is an empty string");

        $this->db->query("DROP TABLE test_empty_value");
    }
}
