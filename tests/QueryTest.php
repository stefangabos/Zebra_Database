<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * query() itself - the statement, the replacements it is given for its placeholders, and the arguments
 * that change what it does with the result: caching, highlighting and the found-rows count.
 */
class QueryTest extends DatabaseTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->connectToDatabase();
        $this->insertTestData();
    }

    public function testSimpleSelectQuery() {
        $result = $this->db->query("SELECT * FROM test_users");

        $this->assertNotFalse($result);
        $this->assertEquals(3, $this->db->returned_rows);

        $row = $this->db->fetch_assoc($result);
        $this->assertArrayHasKey('name', $row);
        $this->assertArrayHasKey('email', $row);
    }

    public function testQueryWithSingleParameterReplacement() {
        $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", ['John Doe']);

        $this->assertNotFalse($result);
        $this->assertEquals(1, $this->db->returned_rows);

        $row = $this->db->fetch_assoc($result);
        $this->assertEquals('John Doe', $row['name']);
        $this->assertEquals('john@example.com', $row['email']);
    }

    public function testQueryWithMultipleParameterReplacements() {
        $result = $this->db->query(
            "SELECT * FROM test_users WHERE age > ? AND is_active = ?",
            [25, 1]
        );

        $this->assertNotFalse($result);
        $this->assertEquals(1, $this->db->returned_rows); // John (30) and Bob (35), but Bob is inactive

        $names = [];
        while ($row = $this->db->fetch_assoc($result)) {
            $names[] = $row['name'];
        }

        $this->assertContains('John Doe', $names);
        $this->assertNotContains('Bob Johnson', $names); // Bob is inactive
    }

    public function testQueryWithNullParameter() {
        $this->db->insert('test_users', [
            'name' => 'Null Email User',
            'email' => null,
            'age' => 30
        ]);

        $result = $this->db->query("SELECT * FROM test_users WHERE email IS NULL");

        $this->assertNotFalse($result);
        $this->assertEquals(1, $this->db->returned_rows);

        $row = $this->db->fetch_assoc($result);
        $this->assertEquals('Null Email User', $row['name']);
        $this->assertNull($row['email']);
    }

    // array replacements, on their own and mixed with scalars, are ArrayParameterTest's subject

    public function testQueryWithIntegerTypes() {
        $result = $this->db->query("SELECT * FROM test_users WHERE age = ?", [30]);

        $this->assertNotFalse($result);
        $this->assertEquals(1, $this->db->returned_rows);

        $row = $this->db->fetch_assoc($result);
        $this->assertEquals('John Doe', $row['name']);
        $this->assertEquals(30, (int)$row['age']);
    }

    public function testQueryWithFloatTypes() {
        $result = $this->db->query("SELECT * FROM test_users WHERE score = ?", [85.50]);

        $this->assertNotFalse($result);
        $this->assertEquals(1, $this->db->returned_rows);

        $row = $this->db->fetch_assoc($result);
        $this->assertEquals('John Doe', $row['name']);
        $this->assertEquals(85.50, (float)$row['score']);
    }

    public function testQueryWithBooleanTypes() {
        $result = $this->db->query("SELECT * FROM test_users WHERE is_active = ?", [true]);

        $this->assertNotFalse($result);
        $this->assertEquals(2, $this->db->returned_rows); // John and Jane are active

        $result = $this->db->query("SELECT * FROM test_users WHERE is_active = ?", [false]);

        $this->assertNotFalse($result);
        $this->assertEquals(1, $this->db->returned_rows); // Bob is inactive

        $row = $this->db->fetch_assoc($result);
        $this->assertEquals('Bob Johnson', $row['name']);
    }

    public function testQueryWithSpecialCharacters() {
        $special_name = "O'Brien & Co. <script>alert('test')</script>";
        $this->db->insert('test_users', [
            'name' => $special_name,
            'email' => 'special@example.com',
            'age' => 40
        ]);

        $result = $this->db->query("SELECT * FROM test_users WHERE name = ?", [$special_name]);

        $this->assertNotFalse($result);
        $this->assertEquals(1, $this->db->returned_rows);

        $row = $this->db->fetch_assoc($result);
        $this->assertEquals($special_name, $row['name']);
    }

    // escaping of replacements, injection payloads included, is SecurityTest's subject

    public function testQueryWithoutParametersUsesRegularQuery() {
        // with nothing to substitute the statement is sent as it was written
        $result = $this->db->query("SELECT COUNT(*) as total FROM test_users");

        $this->assertNotFalse($result);
        $row = $this->db->fetch_assoc($result);
        $this->assertEquals(3, (int)$row['total']);
    }

    public function testQueryWithUnbufferedMode() {
        // an unbuffered query still has its placeholders replaced
        $reflection = new ReflectionClass($this->db);
        $unbufferedProperty = $reflection->getProperty('unbuffered');
        $unbufferedProperty->setAccessible(true);
        $unbufferedProperty->setValue($this->db, true);

        $result = $this->db->query("SELECT * FROM test_users WHERE age > ?", [25]);

        $this->assertNotFalse($result);

        // back the way it was found
        $unbufferedProperty->setValue($this->db, false);
    }

    public function testQueryWithInvalidSQL() {
        $result = $this->db->query("INVALID SQL QUERY");

        $this->assertFalse($result);

        $error = $this->db->error();
        $this->assertNotEmpty($error);
    }

    // a replacement count that does not match the placeholders, replacements given as something other than
    // an array, and INSERT, UPDATE and DELETE written with placeholders are ParameterizedQueryTest's subject

    public function testQueryWithCache() {
        $path = getTempPath('cache');

        foreach (glob($path . '/*') as $file) if (is_file($file)) unlink($file);

        // a probe, so that the cache can be asked whether it was used rather than only whether both calls
        // came back with something - and the suite's own scratch directory rather than a hard-coded /tmp
        $db = $this->probe(['caching_method' => 'disk', 'cache_path' => $path]);

        $this->assertNotFalse($db->query("SELECT * FROM test_users WHERE name = ?", ['John Doe'], 3600));
        $this->assertFalse($db->lastFromCache(), 'The first run is a cache miss');

        $result = $db->query("SELECT * FROM test_users WHERE name = ?", ['John Doe'], 3600);

        $this->assertNotFalse($result);
        $this->assertTrue($db->lastFromCache(), 'The second is a hit');
        $this->assertSame('John Doe', $db->fetch_assoc($result)['name'], 'And gives back the row that was cached');

    }

    public function testQueryWithHighlight() {
        // highlighting is a debugging concern and changes nothing about the result
        $result = $this->db->query(
            "SELECT * FROM test_users WHERE name = ?",
            ['John Doe'],
            false,
            false,
            true
        );

        $this->assertNotFalse($result);
        $this->assertEquals(1, $this->db->returned_rows);
    }

    /**
     * The calc_rows argument asks how many rows the query would have returned without its LIMIT, and puts
     * the answer in found_rows. insertTestData gives three users, so a LIMIT 1 over them returns one row
     * and finds three - which is the whole point of the argument and is what has to be asserted, rather
     * than "found_rows is at least returned_rows when it happens to be set"
     */
    public function testQueryWithCalcRows() {
        $result = $this->db->query(
            "SELECT * FROM test_users LIMIT 1",
            '',
            false,
            true
        );

        $this->assertNotFalse($result);
        $this->assertEquals(1, $this->db->returned_rows, "The LIMIT is still respected");
        $this->assertEquals(3, $this->db->found_rows, "And found_rows counts what the LIMIT left out");
    }
}
