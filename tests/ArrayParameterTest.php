<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * An array given for a single placeholder is expanded into a comma separated list, so that "IN (?)" takes
 * the whole set it is handed - whatever is in it, and however much of it there is.
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
        // a hundred names that match nothing, and two that do
        $many_names = [];
        for ($i = 1; $i <= 100; $i++) {
            $many_names[] = "User $i";
        }

        $many_names[] = 'John Doe';
        $many_names[] = 'Jane Smith';

        $result = $this->db->query(
            "SELECT * FROM test_users WHERE name IN (?)",
            [$many_names]
        );

        $this->assertNotFalse($result);
        $this->assertEquals(2, $this->db->returned_rows);

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
        $this->assertEquals(3, $this->db->returned_rows); // every test user has one of these ages

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
            [[25, 30.0, '35']] // an int, a float and a string
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
        $this->assertEquals(3, $this->db->returned_rows); // every user, active or not
    }

    public function testMixedTypeArrayParameter() {
        $this->db->insert('test_users', [
            'name' => '42', // a name that looks like a number
            'email' => 'number@example.com',
            'age' => 42
        ]);

        $result = $this->db->query(
            "SELECT * FROM test_users WHERE name IN (?)",
            [['42', 42, 'John Doe', null]]
        );

        $this->assertNotFalse($result);
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
        $this->assertEquals(2, $this->db->returned_rows); // John is 30 and Jane is 25

        $names = [];
        while ($row = $this->db->fetch_assoc($result)) {
            $names[] = $row['name'];
        }

        $this->assertContains('John Doe', $names);
        $this->assertContains('Jane Smith', $names);
        $this->assertNotContains('Bob Johnson', $names); // Bob is 35, which is not in the age list
    }

    public function testMixedArrayAndScalarParameters() {
        $result = $this->db->query(
            "SELECT * FROM test_users WHERE name IN (?) AND is_active = ?",
            [['John Doe', 'Jane Smith'], 1]
        );

        $this->assertNotFalse($result);
        $this->assertEquals(2, $this->db->returned_rows); // both are active

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

        // both John and Bob are over 25 with an email in the list
        $names = [];
        while ($row = $this->db->fetch_assoc($result)) {
            $names[] = $row['name'];
        }

        $this->assertContains('John Doe', $names);
    }

    // ARRAY PARAMETER ERROR HANDLING

    public function testArrayParameterWithWrongPlaceholderCount() {
        // two arrays for the one placeholder there is
        $result = $this->db->query(
            "SELECT * FROM test_users WHERE name IN (?)",
            [['John Doe'], ['Jane Smith']]
        );

        $this->assertFalse($result);
    }

    /**
     * A nested array is flattened into the list rather than refused or imploded into the word "Array"
     */
    public function testNestedArrayParameter() {
        $result = null;
        $raised = $this->diagnosticsRaisedBy(function() use (&$result) {
            $result = $this->db->query(
                "SELECT name FROM test_users WHERE name IN (?)",
                [['John Doe', ['Jane Smith']]]
            );
        });

        $this->assertNotFalse($result, "A nested array must not break the query");
        $this->assertSame([], $raised, "Nor may it raise an array-to-string conversion notice");

        $names = array_column($this->db->fetch_assoc_all($result), 'name');
        sort($names);

        $this->assertSame(['Jane Smith', 'John Doe'], $names, "The nested value is part of the list");
    }

    // SPECIAL CHARACTER HANDLING IN ARRAYS

    public function testArrayParameterWithSpecialCharacters() {
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

        // the one value in the list that is not an attack, so there is something to match
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
        $this->assertEquals(1, $this->db->returned_rows);

        $row = $this->db->fetch_assoc($result);
        $this->assertEquals('normal_value', $row['name']);

        // the table the payload tried to drop is still there
        $count_result = $this->db->query("SELECT COUNT(*) as count FROM test_users");
        $count_row = $this->db->fetch_assoc($count_result);
        $this->assertGreaterThan(0, (int)$count_row['count']);
    }

    // PERFORMANCE AND EDGE CASES

    public function testVeryLargeArrayParameter() {
        // a thousand values that match nothing, and one that does
        $large_array = [];
        for ($i = 1; $i <= 1000; $i++) {
            $large_array[] = "nonexistent_user_$i";
        }

        $large_array[] = 'John Doe';

        $result = $this->db->query(
            "SELECT * FROM test_users WHERE name IN (?)",
            [$large_array]
        );

        $this->assertNotFalse($result);
        $this->assertEquals(1, $this->db->returned_rows);

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
        $this->assertEquals(2, $this->db->returned_rows); // a repeated value does not repeat the row

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
        $this->db->query("INSERT INTO test_products (name, price, category_id) VALUES ('Laptop', 999.99, 1)");
        $this->db->query("INSERT INTO test_products (name, price, category_id) VALUES ('Book', 19.99, 2)");

        $result = $this->db->query(
            "SELECT users.*, products.name as product_name FROM test_users users
             JOIN test_products products ON users.age > 25
             WHERE users.name IN (?)",
            [['John Doe', 'Jane Smith', 'Bob Johnson']]
        );

        $this->assertNotFalse($result);

        // the join is on a condition that is not a relation, so every user meets every product
        $this->assertGreaterThan(0, $this->db->returned_rows);
    }

    public function testArrayParameterWithOrderAndLimit() {
        $result = $this->db->query(
            "SELECT * FROM test_users WHERE name IN (?) ORDER BY age DESC LIMIT 2",
            [['John Doe', 'Jane Smith', 'Bob Johnson']]
        );

        $this->assertNotFalse($result);
        $this->assertLessThanOrEqual(2, $this->db->returned_rows);

        // every age is no greater than the one before it
        $previous_age = PHP_INT_MAX;
        while ($row = $this->db->fetch_assoc($result)) {
            $current_age = (int)$row['age'];
            $this->assertLessThanOrEqual($previous_age, $current_age);
            $previous_age = $current_age;
        }
    }

    // ARRAY CASTING TESTS

    /**
     * A scalar given where an array would do is quoted as the single value it is, so "IN (?)" with a
     * scalar behaves exactly like "IN (?)" with a one-element array
     */
    public function testAScalarReplacementBehavesLikeASingleElementArray() {
        $this->db->query("SELECT name FROM test_users WHERE name IN (?)", ['John Doe']);
        $from_scalar = $this->db->fetch_assoc_all();

        $this->db->query("SELECT name FROM test_users WHERE name IN (?)", [['John Doe']]);
        $from_array = $this->db->fetch_assoc_all();

        $this->assertSame([['name' => 'John Doe']], $from_scalar);
        $this->assertSame($from_array, $from_scalar);
    }

    public function testImplodeMethodWithArrays() {
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
     * An empty array was imploded to an empty string, producing "IN ()" - a syntax error. It becomes a
     * subquery returning no rows, which is the only replacement that is correct in both directions: "IN"
     * matches nothing and "NOT IN" matches everything, which is what an empty set means. A literal NULL
     * gets "IN" right and "NOT IN" wrong, and an empty string gets both wrong by matching rows whose
     * value happens to be the empty string.
     *
     * @group regression
     */
    public function testEmptyArrayInAnInClauseMatchesNothing() {
        $result = $this->db->query("SELECT id FROM test_users WHERE name IN (?)", [[]]);

        $this->assertNotFalse($result, "An empty array must not produce invalid SQL");

        $ids = [];
        while ($row = $this->db->fetch_assoc($result)) $ids[] = $row['id'];

        $this->assertSame([], $ids, "IN over an empty set matches nothing");
    }

    /**
     * @group regression
     */
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

    /**
     * @group regression
     */
    public function testEmptyArrayDoesNotMatchRowsWithAnEmptyStringValue() {
        // a row whose value is the empty string, which is what an empty string replacement matches
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
