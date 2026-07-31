<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * The two insert variants - insert_bulk(), which writes many rows in one statement, and insert_update(),
 * which writes one row and updates it instead when it collides with a key that is already there.
 */
class BulkInsertTest extends DatabaseTestCase
{
    protected function setUp(): void {
        parent::setUp();
        $this->connectToDatabase();
    }

    private function names() {
        $this->db->query('SELECT name FROM test_users ORDER BY name');
        $rows = $this->db->fetch_assoc_all();
        return $rows === false ? [] : array_column($rows, 'name');
    }

    // INSERT_BULK

    public function testInsertBulkInsertsEveryRow() {
        $result = $this->db->insert_bulk('test_users', ['name', 'email'], [
            ['Ann', 'ann@example.com'],
            ['Bob', 'bob@example.com'],
            ['Cat', 'cat@example.com'],
        ]);

        $this->assertTrue($result);
        $this->assertSame(['Ann', 'Bob', 'Cat'], $this->names());
    }

    public function testInsertBulkInsertsASingleRow() {
        $this->assertTrue($this->db->insert_bulk('test_users', ['name', 'email'], [
            ['Only', 'only@example.com'],
        ]));

        $this->assertSame(['Only'], $this->names());
    }

    public function testInsertBulkEscapesTheValues() {
        $this->db->insert_bulk('test_users', ['name', 'email'], [
            ['quote " and \' inside', 'quotes@example.com'],
            ["'; DROP TABLE test_users; --", 'injection@example.com'],
        ]);

        $names = $this->names();

        $this->assertContains('quote " and \' inside', $names);
        $this->assertContains("'; DROP TABLE test_users; --", $names);
        $this->assertTrue($this->db->table_exists('test_users'), 'The table is still standing');
    }

    public function testInsertBulkUsesMysqlFunctionsUnquoted() {
        $this->db->insert_bulk('test_users', ['name', 'email', 'created_at'], [
            ['Timed', 'timed@example.com', 'NOW()'],
        ]);

        $created = $this->db->dlookup('created_at', 'test_users', 'email = ?', ['timed@example.com']);

        $this->assertNotSame('NOW()', $created, 'NOW() has to be run, not stored as a string');
        $this->assertNotEmpty($created);
    }

    public function testInsertBulkEscapesTheTableName() {
        $this->db->query('DROP TABLE IF EXISTS `order`');
        $this->db->query('CREATE TABLE `order` (id INT, label VARCHAR(20))');

        $result = $this->db->insert_bulk('order', ['id', 'label'], [[1, 'one'], [2, 'two']]);
        $count = $this->db->dcount('*', 'order');

        $this->db->query('DROP TABLE `order`');

        $this->assertTrue($result);
        $this->assertEquals(2, $count);
    }

    // INSERT_BULK AND DUPLICATES

    public function testInsertBulkUpdatesOnDuplicateKeyByDefault() {
        $this->db->insert('test_users', ['name' => 'Original', 'email' => 'dupe@example.com', 'age' => 20]);

        // email carries a unique key, so the second row collides with the one above
        $this->db->insert_bulk('test_users', ['name', 'email', 'age'], [
            ['Fresh', 'fresh@example.com', 30],
            ['Replaced', 'dupe@example.com', 40],
        ], ['name', 'age']);

        $this->assertEquals('Replaced', $this->db->dlookup('name', 'test_users', 'email = ?', ['dupe@example.com']));
        $this->assertEquals(40, $this->db->dlookup('age', 'test_users', 'email = ?', ['dupe@example.com']));
        $this->assertEquals(2, $this->db->dcount('*', 'test_users'), 'The collision must not have added a row');
    }

    public function testInsertBulkCanUpdateWithStaticValues() {
        $this->db->insert('test_users', ['name' => 'Original', 'email' => 'dupe@example.com', 'age' => 20]);

        $this->db->insert_bulk('test_users', ['name', 'email', 'age'], [
            ['Ignored', 'dupe@example.com', 99],
        ], ['name' => 'Static Name']);

        $this->assertEquals('Static Name', $this->db->dlookup('name', 'test_users', 'email = ?', ['dupe@example.com']));
        $this->assertEquals(20, $this->db->dlookup('age', 'test_users', 'email = ?', ['dupe@example.com']), 'age was not in the update list');
    }

    public function testInsertBulkWithUpdateFalseIgnoresDuplicates() {
        $this->db->insert('test_users', ['name' => 'Original', 'email' => 'dupe@example.com', 'age' => 20]);

        $result = $this->db->insert_bulk('test_users', ['name', 'email', 'age'], [
            ['Fresh', 'fresh@example.com', 30],
            ['Should be skipped', 'dupe@example.com', 40],
        ], false);

        $this->assertTrue($result, 'INSERT IGNORE does not fail on the collision');
        $this->assertEquals('Original', $this->db->dlookup('name', 'test_users', 'email = ?', ['dupe@example.com']), 'The existing row is left alone');
        $this->assertEquals(2, $this->db->dcount('*', 'test_users'));
    }

    // INSERT_BULK ERROR HANDLING

    /**
     * The message for this was looked up under a key with a stray grave accent in it, so instead of the
     * message the user got an "undefined array key" warning and an empty message
     *
     * @group regression
     */
    public function testInsertBulkReportsValuesThatAreNotAnArray() {
        $db = $this->probe();

        $raised = [];
        set_error_handler(function($number, $message) use (&$raised) {
            if (!(error_reporting() & $number)) return true;
            $raised[] = $message;
            return true;
        });

        $result = $db->insert_bulk('test_users', ['name'], 'this is not an array');

        restore_error_handler();

        $errors = $db->errors();

        $this->assertFalse($result);
        $this->assertSame([], $raised, 'Looking the message up must not warn');
        $this->assertNotEmpty($errors);
        $this->assertNotEmpty($errors[0]['message'], 'The user has to be told what is wrong');
        $this->assertStringContainsString('insert_bulk', $errors[0]['message']);

    }

    // INSERT_UPDATE

    public function testInsertUpdateInsertsWhenThereIsNoCollision() {
        $result = $this->db->insert_update('test_users', [
            'name'  => 'Brand New',
            'email' => 'new@example.com',
            'age'   => 33,
        ]);

        $this->assertTrue($result);
        $this->assertEquals(33, $this->db->dlookup('age', 'test_users', 'email = ?', ['new@example.com']));
    }

    public function testInsertUpdateUpdatesOnCollisionUsingTheSameColumns() {
        $this->db->insert('test_users', ['name' => 'Original', 'email' => 'dupe@example.com', 'age' => 20]);

        $result = $this->db->insert_update('test_users', [
            'name'  => 'Updated',
            'email' => 'dupe@example.com',
            'age'   => 44,
        ]);

        $this->assertTrue($result);
        $this->assertEquals('Updated', $this->db->dlookup('name', 'test_users', 'email = ?', ['dupe@example.com']));
        $this->assertEquals(44, $this->db->dlookup('age', 'test_users', 'email = ?', ['dupe@example.com']));
        $this->assertEquals(1, $this->db->dcount('*', 'test_users'));
    }

    public function testInsertUpdateCanUpdateDifferentColumnsThanItInserts() {
        $this->db->insert('test_users', ['name' => 'Original', 'email' => 'dupe@example.com', 'age' => 20]);

        $this->db->insert_update(
            'test_users',
            ['name' => 'Ignored', 'email' => 'dupe@example.com', 'age' => 99],
            ['name' => 'Only The Name']
        );

        $this->assertEquals('Only The Name', $this->db->dlookup('name', 'test_users', 'email = ?', ['dupe@example.com']));
        $this->assertEquals(20, $this->db->dlookup('age', 'test_users', 'email = ?', ['dupe@example.com']));
    }

    public function testInsertUpdateAcceptsTheIncKeywordInTheUpdate() {
        $this->db->insert('test_users', ['name' => 'Counter', 'email' => 'dupe@example.com', 'age' => 20]);

        $this->db->insert_update(
            'test_users',
            ['name' => 'Counter', 'email' => 'dupe@example.com', 'age' => 1],
            ['age' => 'INC(5)']
        );

        $this->assertEquals(25, $this->db->dlookup('age', 'test_users', 'email = ?', ['dupe@example.com']));
    }

    public function testInsertUpdateUsesMysqlFunctionsUnquoted() {
        $this->db->insert_update('test_users', [
            'name'       => 'Timed',
            'email'      => 'timed@example.com',
            'created_at' => 'NOW()',
        ]);

        $created = $this->db->dlookup('created_at', 'test_users', 'email = ?', ['timed@example.com']);

        $this->assertNotSame('NOW()', $created);
        $this->assertNotEmpty($created);
    }

    public function testInsertUpdateEscapesItsValues() {
        $this->db->insert_update('test_users', [
            'name'  => "'; DROP TABLE test_users; --",
            'email' => 'injection@example.com',
        ]);

        $this->assertTrue($this->db->table_exists('test_users'));
        $this->assertSame("'; DROP TABLE test_users; --", $this->db->dlookup('name', 'test_users', 'email = ?', ['injection@example.com']));
    }

    public function testInsertUpdateTreatsANonArrayUpdateAsEmpty() {
        $this->db->insert('test_users', ['name' => 'Original', 'email' => 'dupe@example.com', 'age' => 20]);

        // a non-array third argument is turned into an empty one, which means "update with what was inserted"
        $result = $this->db->insert_update(
            'test_users',
            ['name' => 'Updated', 'email' => 'dupe@example.com', 'age' => 55],
            'not an array'
        );

        $this->assertTrue($result);
        $this->assertEquals(55, $this->db->dlookup('age', 'test_users', 'email = ?', ['dupe@example.com']));
    }
}
