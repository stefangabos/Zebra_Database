<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Reading a result set - a row at a time or all at once, as an array or as an object, with or without
 * being handed the result to read from - and the methods that go with it: free_result, seek, get_columns.
 */
class FetchMethodsTest extends DatabaseTestCase
{
    protected function setUp(): void {
        parent::setUp();
        $this->connectToDatabase();
        $this->insertTestData();
    }

    // FETCH_ASSOC TESTS

    public function testFetchAssocSingleRow() {
        $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", ['John Doe']);

        $row = $this->db->fetch_assoc($result);

        $this->assertIsArray($row);
        $this->assertArrayHasKey('id', $row);
        $this->assertArrayHasKey('name', $row);
        $this->assertArrayHasKey('email', $row);
        $this->assertArrayHasKey('age', $row);

        $this->assertEquals('John Doe', $row['name']);
        $this->assertEquals('john@example.com', $row['email']);
        $this->assertEquals(30, (int)$row['age']);
    }

    public function testFetchAssocMultipleRows() {
        $result = $this->db->query("SELECT * FROM test_users ORDER BY name");

        $rows = [];
        while ($row = $this->db->fetch_assoc($result)) {
            $rows[] = $row;
        }

        $this->assertCount(3, $rows);

        $this->assertEquals('Bob Johnson', $rows[0]['name']);
        $this->assertEquals('Jane Smith', $rows[1]['name']);
        $this->assertEquals('John Doe', $rows[2]['name']);
    }

    public function testFetchAssocWithNullValues() {
        $this->db->insert('test_users', [
            'name' => 'Null Email User',
            'email' => null,
            'age' => 40
        ]);

        $result = $this->db->query("SELECT * FROM test_users WHERE email IS NULL");
        $row = $this->db->fetch_assoc($result);

        $this->assertIsArray($row);
        $this->assertEquals('Null Email User', $row['name']);
        $this->assertNull($row['email']);
    }

    public function testFetchAssocWithSpecialCharacters() {
        $special_name = "O'Brien & Co. <script>alert('test')</script>";
        $this->db->insert('test_users', [
            'name' => $special_name,
            'email' => 'special@example.com',
            'age' => 45
        ]);

        $result = $this->db->query("SELECT * FROM test_users WHERE email = ?", ['special@example.com']);
        $row = $this->db->fetch_assoc($result);

        $this->assertEquals($special_name, $row['name']);
    }

    public function testFetchAssocEmptyResult() {
        $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", ['Nonexistent User']);
        $row = $this->db->fetch_assoc($result);

        $this->assertFalse($row);
    }

    public function testFetchAssocWithoutResult() {
        // given nothing to read from, it reads from the query that ran last
        $this->db->query("SELECT * FROM test_users WHERE name = ?", ['John Doe']);
        $row = $this->db->fetch_assoc();

        $this->assertIsArray($row);
        $this->assertEquals('John Doe', $row['name']);
    }

    public function testFetchAssocAll() {
        $result = $this->db->query("SELECT * FROM test_users ORDER BY name");
        $all_rows = $this->db->fetch_assoc_all('', $result);

        $this->assertIsArray($all_rows);
        $this->assertCount(3, $all_rows);

        $this->assertEquals('Bob Johnson', $all_rows[0]['name']);
        $this->assertEquals('Jane Smith', $all_rows[1]['name']);
        $this->assertEquals('John Doe', $all_rows[2]['name']);
    }

    public function testFetchAssocAllWithIndex() {
        $result = $this->db->query("SELECT * FROM test_users ORDER BY name");
        $indexed_rows = $this->db->fetch_assoc_all('name', $result);

        $this->assertIsArray($indexed_rows);
        $this->assertArrayHasKey('Bob Johnson', $indexed_rows);
        $this->assertArrayHasKey('Jane Smith', $indexed_rows);
        $this->assertArrayHasKey('John Doe', $indexed_rows);

        $this->assertEquals('bob@example.com', $indexed_rows['Bob Johnson']['email']);
        $this->assertEquals('jane@example.com', $indexed_rows['Jane Smith']['email']);
        $this->assertEquals('john@example.com', $indexed_rows['John Doe']['email']);
    }

    public function testFetchAssocAllWithNumericIndex() {
        $result = $this->db->query("SELECT * FROM test_users ORDER BY id");
        $indexed_rows = $this->db->fetch_assoc_all('id', $result);

        $this->assertIsArray($indexed_rows);

        foreach ($indexed_rows as $id => $row) {
            $this->assertIsNumeric($id);
            $this->assertEquals($id, $row['id']);
        }
    }

    public function testFetchAssocAllEmptyResult() {
        $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", ['Nonexistent User']);
        $all_rows = $this->db->fetch_assoc_all('', $result);

        $this->assertIsArray($all_rows);
        $this->assertEmpty($all_rows);
    }

    // FETCH_OBJ TESTS

    public function testFetchObjSingleRow() {
        $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", ['John Doe']);

        $obj = $this->db->fetch_obj($result);

        $this->assertIsObject($obj);
        $this->assertObjectHasProperty('id', $obj);
        $this->assertObjectHasProperty('name', $obj);
        $this->assertObjectHasProperty('email', $obj);
        $this->assertObjectHasProperty('age', $obj);

        $this->assertEquals('John Doe', $obj->name);
        $this->assertEquals('john@example.com', $obj->email);
        $this->assertEquals(30, (int)$obj->age);
    }

    public function testFetchObjMultipleRows() {
        $result = $this->db->query("SELECT * FROM test_users ORDER BY name");

        $objects = [];
        while ($obj = $this->db->fetch_obj($result)) {
            $objects[] = $obj;
        }

        $this->assertCount(3, $objects);

        $this->assertEquals('Bob Johnson', $objects[0]->name);
        $this->assertEquals('Jane Smith', $objects[1]->name);
        $this->assertEquals('John Doe', $objects[2]->name);
    }

    public function testFetchObjWithNullValues() {
        $this->db->insert('test_users', [
            'name' => 'Null Email User',
            'email' => null,
            'age' => 40
        ]);

        $result = $this->db->query("SELECT * FROM test_users WHERE email IS NULL");
        $obj = $this->db->fetch_obj($result);

        $this->assertIsObject($obj);
        $this->assertEquals('Null Email User', $obj->name);
        $this->assertNull($obj->email);
    }

    public function testFetchObjWithSpecialCharacters() {
        $special_name = "O'Brien & Co. <script>alert('test')</script>";
        $this->db->insert('test_users', [
            'name' => $special_name,
            'email' => 'special@example.com',
            'age' => 45
        ]);

        $result = $this->db->query("SELECT * FROM test_users WHERE email = ?", ['special@example.com']);
        $obj = $this->db->fetch_obj($result);

        $this->assertEquals($special_name, $obj->name);
    }

    public function testFetchObjEmptyResult() {
        $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", ['Nonexistent User']);
        $obj = $this->db->fetch_obj($result);

        $this->assertFalse($obj);
    }

    public function testFetchObjWithoutResult() {
        // given nothing to read from, it reads from the query that ran last
        $this->db->query("SELECT * FROM test_users WHERE name = ?", ['John Doe']);
        $obj = $this->db->fetch_obj();

        $this->assertIsObject($obj);
        $this->assertEquals('John Doe', $obj->name);
    }

    public function testFetchObjAll() {
        $result = $this->db->query("SELECT * FROM test_users ORDER BY name");
        $all_objects = $this->db->fetch_obj_all('', $result);

        $this->assertIsArray($all_objects);
        $this->assertCount(3, $all_objects);

        $this->assertIsObject($all_objects[0]);
        $this->assertEquals('Bob Johnson', $all_objects[0]->name);
        $this->assertEquals('Jane Smith', $all_objects[1]->name);
        $this->assertEquals('John Doe', $all_objects[2]->name);
    }

    public function testFetchObjAllWithIndex() {
        $result = $this->db->query("SELECT * FROM test_users ORDER BY name");
        $indexed_objects = $this->db->fetch_obj_all('name', $result);

        $this->assertIsArray($indexed_objects);
        $this->assertArrayHasKey('Bob Johnson', $indexed_objects);
        $this->assertArrayHasKey('Jane Smith', $indexed_objects);
        $this->assertArrayHasKey('John Doe', $indexed_objects);

        $this->assertIsObject($indexed_objects['Bob Johnson']);
        $this->assertEquals('bob@example.com', $indexed_objects['Bob Johnson']->email);
        $this->assertEquals('jane@example.com', $indexed_objects['Jane Smith']->email);
        $this->assertEquals('john@example.com', $indexed_objects['John Doe']->email);
    }

    public function testFetchObjAllWithNumericIndex() {
        $result = $this->db->query("SELECT * FROM test_users ORDER BY id");
        $indexed_objects = $this->db->fetch_obj_all('id', $result);

        $this->assertIsArray($indexed_objects);

        foreach ($indexed_objects as $id => $obj) {
            $this->assertIsNumeric($id);
            $this->assertIsObject($obj);
            $this->assertEquals($id, $obj->id);
        }
    }

    public function testFetchObjAllEmptyResult() {
        $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", ['Nonexistent User']);
        $all_objects = $this->db->fetch_obj_all('', $result);

        $this->assertIsArray($all_objects);
        $this->assertEmpty($all_objects);
    }

    // MIXED FETCH TESTS

    public function testMixedFetchAssocAndFetchObj() {
        $result = $this->db->query("SELECT * FROM test_users ORDER BY name");

        // both fetchers move the same row pointer, so they take turns rather than each starting over
        $array_row = $this->db->fetch_assoc($result);
        $this->assertIsArray($array_row);
        $this->assertEquals('Bob Johnson', $array_row['name']);

        $object_row = $this->db->fetch_obj($result);
        $this->assertIsObject($object_row);
        $this->assertEquals('Jane Smith', $object_row->name);

        $array_row2 = $this->db->fetch_assoc($result);
        $this->assertIsArray($array_row2);
        $this->assertEquals('John Doe', $array_row2['name']);
    }

    // FREE_RESULT TESTS

    public function testFreeResult() {
        $result = $this->db->query("SELECT * FROM test_users LIMIT 1");

        $row = $this->db->fetch_assoc($result);
        $this->assertIsArray($row);

        $free_result = $this->db->free_result($result);
        $this->assertTrue($free_result);
    }

    /**
     * 49b685f - freeing the same result a second time was a fatal error on PHP 8.1 and newer, where mysqli
     * raises an Error rather than a warning. An already freed result is still an instance of mysqli_result,
     * so there is nothing to check beforehand - the attempt has to be made and the Error caught, which is
     * what turns the second call into a plain FALSE.
     *
     * @group regression
     */
    public function testFreeingTheSameResultTwiceReportsFalseRatherThanDying() {
        $result = $this->db->query('SELECT * FROM test_users LIMIT 1');

        $this->assertTrue($this->db->free_result($result), 'The first call has something to free');
        $this->assertFalse($this->db->free_result($result), 'The second has not, and must not be fatal');
    }

    public function testFreeResultWithoutParameter() {
        $this->db->query("SELECT * FROM test_users LIMIT 1");

        // given nothing, it frees the result of the query that ran last
        $free_result = $this->db->free_result();
        $this->assertTrue($free_result);
    }

    // SEEK TESTS

    public function testSeek() {
        $result = $this->db->query("SELECT * FROM test_users ORDER BY name");

        // the second row, the rows being counted from zero
        $seek_result = $this->db->seek(1, $result);
        $this->assertTrue($seek_result);

        $row = $this->db->fetch_assoc($result);
        $this->assertEquals('Jane Smith', $row['name']);
    }

    public function testSeekToFirstRow() {
        $result = $this->db->query("SELECT * FROM test_users ORDER BY name");

        $first_row = $this->db->fetch_assoc($result);
        $this->assertEquals('Bob Johnson', $first_row['name']);

        // back to where the pointer started, so the same row comes back a second time
        $seek_result = $this->db->seek(0, $result);
        $this->assertTrue($seek_result);

        $row_again = $this->db->fetch_assoc($result);
        $this->assertEquals('Bob Johnson', $row_again['name']);
    }

    public function testSeekOutOfBounds() {
        $result = $this->db->query("SELECT * FROM test_users ORDER BY name");

        $seek_result = $this->db->seek(10, $result);
        $this->assertFalse($seek_result);
    }

    public function testSeekWithoutParameter() {
        $this->db->query("SELECT * FROM test_users ORDER BY name");

        // given nothing, it seeks in the result of the query that ran last
        $seek_result = $this->db->seek(1);
        $this->assertTrue($seek_result);

        $row = $this->db->fetch_assoc();
        $this->assertEquals('Jane Smith', $row['name']);
    }

    // GET_COLUMNS TESTS

    public function testGetColumns() {
        $result = $this->db->query("SELECT name, email, age FROM test_users LIMIT 1");
        $columns = $this->db->get_columns($result);

        $this->assertIsArray($columns);
        $this->assertCount(3, $columns);

        $column_names = [];
        foreach ($columns as $column) {
            $this->assertIsArray($column);
            $this->assertArrayHasKey('name', $column);
            $column_names[] = $column['name'];
        }

        $this->assertContains('name', $column_names);
        $this->assertContains('email', $column_names);
        $this->assertContains('age', $column_names);
    }

    public function testGetColumnsWithoutParameter() {
        $this->db->query("SELECT name, email FROM test_users LIMIT 1");
        $columns = $this->db->get_columns();

        $this->assertIsArray($columns);
        $this->assertCount(2, $columns);

        $column_names = [];
        foreach ($columns as $column) {
            $this->assertIsArray($column);
            $this->assertArrayHasKey('name', $column);
            $column_names[] = $column['name'];
        }

        $this->assertContains('name', $column_names);
        $this->assertContains('email', $column_names);
    }

    public function testGetColumnsAllColumns() {
        $result = $this->db->query("SELECT * FROM test_users LIMIT 1");
        $columns = $this->db->get_columns($result);

        $this->assertIsArray($columns);

        $column_names = [];
        foreach ($columns as $column) {
            $this->assertIsArray($column);
            $this->assertArrayHasKey('name', $column);
            $column_names[] = $column['name'];
        }

        $this->assertContains('id', $column_names);
        $this->assertContains('name', $column_names);
        $this->assertContains('email', $column_names);
        $this->assertContains('age', $column_names);
        $this->assertContains('score', $column_names);
        $this->assertContains('is_active', $column_names);
        $this->assertContains('created_at', $column_names);
    }

    // DATA TYPE TESTS

    public function testFetchVariousDataTypes() {
        $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", ['John Doe']);
        $row = $this->db->fetch_assoc($result);

        // mysqli hands every column back as a string, so what is asserted is what the value reads as
        $this->assertIsNumeric($row['id']);
        $this->assertIsNumeric($row['age']);
        $this->assertIsString($row['name']);
        $this->assertIsString($row['email']);
        $this->assertIsNumeric($row['score']);
        $this->assertIsNumeric($row['is_active']);
        $this->assertNotEmpty($row['created_at']);
    }

    public function testFetchLargeDataSet() {
        for ($i = 1; $i <= 100; $i++) {
            $this->db->insert('test_users', [
                'name' => "User $i",
                'email' => "user$i@example.com",
                'age' => 20 + ($i % 50),
                'score' => 50.0 + ($i % 100)
            ]);
        }

        $result = $this->db->query("SELECT * FROM test_users WHERE name LIKE 'User%'");

        $count = 0;
        while ($row = $this->db->fetch_assoc($result)) {
            $count++;
            $this->assertIsArray($row);
            $this->assertArrayHasKey('name', $row);
        }

        $this->assertEquals(100, $count);
    }
}
