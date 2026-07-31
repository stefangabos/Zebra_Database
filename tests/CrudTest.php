<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * The four statements the library writes on the caller's behalf - insert(), update(), delete() and
 * truncate() - including the INC() keyword update() understands for counting a column up and down.
 */
class CrudTest extends DatabaseTestCase
{
    protected function setUp(): void {
        parent::setUp();
        $this->connectToDatabase();
    }

    // INSERT TESTS

    public function testInsertSimpleRecord() {
        $result = $this->db->insert('test_users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'age' => 25
        ]);

        $this->assertTrue($result);
        $this->assertEquals(1, $this->db->affected_rows);

        $insert_id = $this->db->insert_id();
        $this->assertGreaterThan(0, $insert_id);

        $verify_result = $this->db->query("SELECT * FROM test_users WHERE id = ?", [$insert_id]);
        $row = $this->db->fetch_assoc($verify_result);
        $this->assertEquals('Test User', $row['name']);
        $this->assertEquals('test@example.com', $row['email']);
        $this->assertEquals(25, (int)$row['age']);
    }

    public function testInsertWithNullValues() {
        $result = $this->db->insert('test_users', [
            'name' => 'Null User',
            'email' => null,
            'age' => 30
        ]);

        $this->assertTrue($result);

        $insert_id = $this->db->insert_id();
        $verify_result = $this->db->query("SELECT * FROM test_users WHERE id = ?", [$insert_id]);
        $row = $this->db->fetch_assoc($verify_result);

        $this->assertEquals('Null User', $row['name']);
        $this->assertNull($row['email']);
        $this->assertEquals(30, (int)$row['age']);
    }

    public function testInsertWithSpecialCharacters() {
        $special_name = "O'Brien & Co. <script>alert('test')</script>";
        $result = $this->db->insert('test_users', [
            'name' => $special_name,
            'email' => 'special@example.com',
            'age' => 35
        ]);

        $this->assertTrue($result);

        $insert_id = $this->db->insert_id();
        $verify_result = $this->db->query("SELECT * FROM test_users WHERE id = ?", [$insert_id]);
        $row = $this->db->fetch_assoc($verify_result);
        $this->assertEquals($special_name, $row['name']);
    }

    public function testInsertWithOnDuplicateKeyUpdate() {
        $this->db->insert('test_users', [
            'name' => 'Unique User',
            'email' => 'unique@example.com',
            'age' => 25
        ]);

        // the same email again, with the columns to update when it collides
        $result = $this->db->insert('test_users', [
            'name' => 'Updated Unique User',
            'email' => 'unique@example.com',
            'age' => 30
        ], ['name', 'age']);

        $this->assertTrue($result);

        $verify_result = $this->db->query("SELECT * FROM test_users WHERE email = ?", ['unique@example.com']);
        $this->assertEquals(1, $this->db->returned_rows);

        $row = $this->db->fetch_assoc($verify_result);
        $this->assertEquals('Updated Unique User', $row['name']);
        $this->assertEquals(30, (int)$row['age']);
    }

    public function testInsertWithoutOnDuplicateKeyUpdate() {
        $this->db->insert('test_users', [
            'name' => 'Duplicate User',
            'email' => 'duplicate@example.com',
            'age' => 25
        ]);

        // the same email again, with updating on collision switched off
        $result = $this->db->insert('test_users', [
            'name' => 'Another Duplicate User',
            'email' => 'duplicate@example.com',
            'age' => 30
        ], true);

        $this->assertFalse($result);
    }

    public function testInsertBulk() {
        $columns = ['name', 'email', 'age'];
        $values = [
            ['Bulk User 1', 'bulk1@example.com', 25],
            ['Bulk User 2', 'bulk2@example.com', 30],
            ['Bulk User 3', 'bulk3@example.com', 35]
        ];

        $result = $this->db->insert_bulk('test_users', $columns, $values);

        $this->assertTrue($result);
        $this->assertEquals(3, $this->db->affected_rows);

        $verify_result = $this->db->query("SELECT COUNT(*) as count FROM test_users WHERE name LIKE 'Bulk User%'");
        $row = $this->db->fetch_assoc($verify_result);
        $this->assertEquals(3, (int)$row['count']);
    }

    public function testInsertUpdate() {
        $result = $this->db->insert_update('test_users', [
            'name' => 'Insert Update User',
            'email' => 'insertupdate@example.com',
            'age' => 25
        ], ['age' => 26]);

        $this->assertTrue($result);

        // the same email again, which updates the row that is there
        $result = $this->db->insert_update('test_users', [
            'name' => 'Insert Update User Modified',
            'email' => 'insertupdate@example.com',
            'age' => 25
        ], ['age' => 27]);

        $this->assertTrue($result);

        $verify_result = $this->db->query("SELECT * FROM test_users WHERE email = ?", ['insertupdate@example.com']);
        $row = $this->db->fetch_assoc($verify_result);
        $this->assertEquals(27, (int)$row['age']);
    }

    // UPDATE TESTS

    public function testUpdateSimpleRecord() {
        $this->db->insert('test_users', [
            'name' => 'Update Test User',
            'email' => 'update@example.com',
            'age' => 25
        ]);

        $result = $this->db->update('test_users', [
            'age' => 30,
            'name' => 'Updated Test User'
        ], 'email = ?', ['update@example.com']);

        $this->assertTrue($result);
        $this->assertEquals(1, $this->db->affected_rows);

        $verify_result = $this->db->query("SELECT * FROM test_users WHERE email = ?", ['update@example.com']);
        $row = $this->db->fetch_assoc($verify_result);
        $this->assertEquals('Updated Test User', $row['name']);
        $this->assertEquals(30, (int)$row['age']);
    }

    public function testUpdateWithNullValue() {
        $this->db->insert('test_users', [
            'name' => 'Null Update User',
            'email' => 'nullupdate@example.com',
            'age' => 25
        ]);

        $result = $this->db->update('test_users', [
            'email' => null
        ], 'name = ?', ['Null Update User']);

        $this->assertTrue($result);

        $verify_result = $this->db->query("SELECT * FROM test_users WHERE name = ?", ['Null Update User']);
        $row = $this->db->fetch_assoc($verify_result);
        $this->assertNull($row['email']);
    }

    public function testUpdateMultipleRecords() {
        for ($i = 1; $i <= 3; $i++) {
            $this->db->insert('test_users', [
                'name' => "Multi Update User $i",
                'email' => "multi$i@example.com",
                'age' => 20 + $i
            ]);
        }

        $result = $this->db->update('test_users', [
            'is_active' => 0
        ], 'name LIKE ?', ['Multi Update User%']);

        $this->assertTrue($result);
        $this->assertEquals(3, $this->db->affected_rows);

        $verify_result = $this->db->query("SELECT COUNT(*) as count FROM test_users WHERE name LIKE ? AND is_active = 0", ['Multi Update User%']);
        $row = $this->db->fetch_assoc($verify_result);
        $this->assertEquals(3, (int)$row['count']);
    }

    public function testUpdateWithNoMatchingRecords() {
        $result = $this->db->update('test_users', [
            'age' => 50
        ], 'name = ?', ['Non-existent User']);

        $this->assertTrue($result); // the statement ran, it simply matched nothing
        $this->assertEquals(0, $this->db->affected_rows);
    }

    public function testUpdateWithComplexWhereClause() {
        for ($i = 1; $i <= 5; $i++) {
            $this->db->insert('test_users', [
                'name' => "Complex Update User $i",
                'email' => "complex$i@example.com",
                'age' => 20 + $i,
                'score' => 50.0 + $i
            ]);
        }

        $result = $this->db->update('test_users', [
            'is_active' => 0
        ], 'age BETWEEN ? AND ? AND score > ?', [22, 25, 52.0]);

        $this->assertTrue($result);
        $this->assertGreaterThan(0, $this->db->affected_rows);
    }

    // INC() TESTS
    // (the special keyword update() understands for incrementing and decrementing a column)

    /**
     * A single row to count up and down from - age 30, score 85.50
     */
    private function insertCounterRow() {
        $this->db->insert('test_users', [
            'name'  => 'Counter',
            'email' => 'counter@example.com',
            'age'   => 30,
            'score' => 85.50,
        ]);
    }

    private function counter($column) {
        return $this->db->dlookup($column, 'test_users', 'email = ?', ['counter@example.com']);
    }

    public function testUpdateIncrementsWithALiteralValue() {
        $this->insertCounterRow();

        $this->db->update('test_users', ['age' => 'INC(5)'], 'email = ?', ['counter@example.com']);

        $this->assertEquals(35, $this->counter('age'));
    }

    public function testUpdateDecrementsWithALiteralValue() {
        $this->insertCounterRow();

        $this->db->update('test_users', ['age' => 'INC(-3)'], 'email = ?', ['counter@example.com']);

        $this->assertEquals(27, $this->counter('age'));
    }

    /**
     * The value to increment by may be given as a parameter marker, with the value itself in the
     * replacements array - this is what the docblock for update() shows, and it broke when the INC()
     * pattern was tightened to digits only so that a plain string looking like "INC(foo)" is not taken
     * for the keyword
     *
     * @group regression
     */
    public function testUpdateIncrementsWithAParameterMarker() {
        $this->insertCounterRow();

        $result = $this->db->update('test_users', ['age' => 'INC(?)'], 'email = ?', [7, 'counter@example.com']);

        $this->assertTrue($result);
        $this->assertEquals(37, $this->counter('age'));
    }

    public function testUpdateDecrementsWithAParameterMarker() {
        $this->insertCounterRow();

        $result = $this->db->update('test_users', ['age' => 'INC(-?)'], 'email = ?', [4, 'counter@example.com']);

        $this->assertTrue($result);
        $this->assertEquals(26, $this->counter('age'));
    }

    /**
     * The other half of that - a value which merely looks like the keyword is stored as the string it is
     */
    public function testUpdateTreatsAnIncLikeStringAsAPlainValue() {
        $this->insertCounterRow();

        $this->db->update('test_users', ['name' => 'INC(foo)'], 'email = ?', ['counter@example.com']);

        $this->assertSame('INC(foo)', $this->counter('name'));
    }

    /**
     * The keyword has to be the whole of the value. Starting it was enough, so a string like "INC(5)
     * apples" was taken for an instruction to add 5 to the column - on a text column that either failed
     * outright or wrote a number over what the caller meant to store, and either way the string never
     * made it into the database
     *
     * @dataProvider incLikeStrings
     *
     * @group regression
     */
    public function testUpdateOnlyTreatsIncAsTheKeywordWhenItIsTheWholeValue($value) {
        $this->insertCounterRow();

        $result = $this->db->update('test_users', ['name' => $value], 'email = ?', ['counter@example.com']);

        $this->assertTrue($result);
        $this->assertSame($value, $this->counter('name'));
    }

    public function incLikeStrings() {
        return [
            'trailing text'         => ['INC(5)abc'],
            'trailing words'        => ['INC(5) apples'],
            'trailing whitespace'   => ['INC(5) '],
            'leading whitespace'    => [' INC(5)'],
            'twice over'            => ['INC(5)INC(9)'],
            'a marker and text'     => ['INC(?) each'],
        ];
    }

    public function testUpdateIncrementsSeveralColumnsAtOnce() {
        $this->insertCounterRow();

        $this->db->update('test_users', [
            'age'   => 'INC(1)',
            'score' => 'INC(?)',
        ], 'email = ?', [10, 'counter@example.com']);

        $this->assertEquals(31, $this->counter('age'));
        $this->assertEquals(95.50, $this->counter('score'));
    }

    // DELETE TESTS

    public function testDeleteSimpleRecord() {
        $this->db->insert('test_users', [
            'name' => 'Delete Test User',
            'email' => 'delete@example.com',
            'age' => 25
        ]);

        $result = $this->db->delete('test_users', 'email = ?', ['delete@example.com']);

        $this->assertTrue($result);
        $this->assertEquals(1, $this->db->affected_rows);

        $verify_result = $this->db->query("SELECT COUNT(*) as count FROM test_users WHERE email = ?", ['delete@example.com']);
        $row = $this->db->fetch_assoc($verify_result);
        $this->assertEquals(0, (int)$row['count']);
    }

    public function testDeleteMultipleRecords() {
        for ($i = 1; $i <= 3; $i++) {
            $this->db->insert('test_users', [
                'name' => "Multi Delete User $i",
                'email' => "multidelete$i@example.com",
                'age' => 20 + $i
            ]);
        }

        $result = $this->db->delete('test_users', 'name LIKE ?', ['Multi Delete User%']);

        $this->assertTrue($result);
        $this->assertEquals(3, $this->db->affected_rows);

        $verify_result = $this->db->query("SELECT COUNT(*) as count FROM test_users WHERE name LIKE ?", ['Multi Delete User%']);
        $row = $this->db->fetch_assoc($verify_result);
        $this->assertEquals(0, (int)$row['count']);
    }

    public function testDeleteWithNoMatchingRecords() {
        $result = $this->db->delete('test_users', 'name = ?', ['Non-existent User']);

        $this->assertTrue($result); // the statement ran, it simply matched nothing
        $this->assertEquals(0, $this->db->affected_rows);
    }

    public function testDeleteWithComplexWhereClause() {
        for ($i = 1; $i <= 5; $i++) {
            $this->db->insert('test_users', [
                'name' => "Complex Delete User $i",
                'email' => "complexdelete$i@example.com",
                'age' => 20 + $i,
                'score' => 50.0 + $i,
                'is_active' => $i % 2 // alternating active/inactive
            ]);
        }

        $result = $this->db->delete('test_users', 'age > ? AND is_active = ?', [22, 1]);

        $this->assertTrue($result);
        $this->assertGreaterThan(0, $this->db->affected_rows);

        // the rows that did not match the condition are still there
        $verify_result = $this->db->query("SELECT COUNT(*) as count FROM test_users WHERE name LIKE ?", ['Complex Delete User%']);
        $row = $this->db->fetch_assoc($verify_result);
        $this->assertLessThan(5, (int)$row['count']);
    }

    public function testDeleteAllRecords() {
        $this->db->insert('test_users', ['name' => 'Delete All User 1', 'email' => 'deleteall1@example.com', 'age' => 25]);
        $this->db->insert('test_users', ['name' => 'Delete All User 2', 'email' => 'deleteall2@example.com', 'age' => 30]);

        // no condition, so every row goes
        $result = $this->db->delete('test_users');

        $this->assertTrue($result);
        $this->assertGreaterThanOrEqual(2, $this->db->affected_rows);

        $verify_result = $this->db->query("SELECT COUNT(*) as count FROM test_users");
        $row = $this->db->fetch_assoc($verify_result);
        $this->assertEquals(0, (int)$row['count']);
    }

    // TRUNCATE TESTS

    public function testTruncateTable() {
        for ($i = 1; $i <= 5; $i++) {
            $this->db->insert('test_users', [
                'name' => "Truncate User $i",
                'email' => "truncate$i@example.com",
                'age' => 20 + $i
            ]);
        }

        $result = $this->db->truncate('test_users');

        $this->assertTrue($result);

        $verify_result = $this->db->query("SELECT COUNT(*) as count FROM test_users");
        $row = $this->db->fetch_assoc($verify_result);
        $this->assertEquals(0, (int)$row['count']);

        // the auto-increment counter goes with the rows, so the next row is id 1
        $this->db->insert('test_users', ['name' => 'Reset User', 'email' => 'reset@example.com', 'age' => 25]);
        $this->assertEquals(1, $this->db->insert_id());
    }

    // ERROR HANDLING TESTS

    public function testInsertInvalidTable() {
        $result = $this->db->insert('nonexistent_table', [
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

        $this->assertFalse($result);
    }

    public function testUpdateInvalidTable() {
        $result = $this->db->update('nonexistent_table', [
            'name' => 'Updated User'
        ], 'id = ?', [1]);

        $this->assertFalse($result);
    }

    public function testDeleteInvalidTable() {
        $result = $this->db->delete('nonexistent_table', 'id = ?', [1]);

        $this->assertFalse($result);
    }

    public function testInsertInvalidColumn() {
        $result = $this->db->insert('test_users', [
            'nonexistent_column' => 'value',
            'name' => 'Test User'
        ]);

        $this->assertFalse($result);
    }

    public function testUpdateInvalidColumn() {
        $result = $this->db->update('test_users', [
            'nonexistent_column' => 'value'
        ], 'id = ?', [1]);

        $this->assertFalse($result);
    }
}
