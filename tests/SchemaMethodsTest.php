<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Test suite for the methods that inspect or maintain the schema rather than the data -
 * get_tables(), get_table_columns(), get_table_status(), optimize() and truncate()
 */
class SchemaMethodsTest extends DatabaseTestCase
{
    protected function setUp(): void {
        parent::setUp();
        $this->connectToDatabase();
        $this->insertTestData();
    }

    /**
     * A debugging instance, so that the statements the library sends can be read back
     */
    private function probe() {
        $db = new DatabaseProbe();
        $db->debug = true;
        $db->halt_on_errors = false;
        $db->cache_path = getTempPath('cache');
        $db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT);
        return $db;
    }

    // GET_TABLES

    public function testGetTablesReturnsAFlatListOfTableNames() {
        $tables = $this->db->get_tables();

        $this->assertIsArray($tables);
        $this->assertContains('test_users', $tables);
        $this->assertContains('test_products', $tables);
        $this->assertContains('test_categories', $tables);

        // the raw result of SHOW TABLES is a list of single-element rows - the method has to flatten it
        foreach ($tables as $table) $this->assertIsString($table);
    }

    public function testGetTablesAcceptsAnExplicitDatabase() {
        $tables = $this->db->get_tables(TEST_DB_NAME);

        $this->assertContains('test_users', $tables);
    }

    /**
     * SHOW TABLES IN <unknown database> fails, so fetch_assoc_all() returns FALSE - the method used to
     * iterate over that FALSE and warn
     */
    public function testGetTablesReturnsAnEmptyArrayForAnUnknownDatabase() {
        $this->db->halt_on_errors = false;

        $tables = $this->db->get_tables('this_database_does_not_exist');

        $this->assertSame([], $tables);
    }

    // GET_TABLE_COLUMNS

    public function testGetTableColumnsIsKeyedByColumnName() {
        $columns = $this->db->get_table_columns('test_users');

        $this->assertIsArray($columns);
        $this->assertArrayHasKey('id', $columns);
        $this->assertArrayHasKey('name', $columns);
        $this->assertArrayHasKey('email', $columns);
        $this->assertArrayHasKey('age', $columns);
        $this->assertArrayHasKey('score', $columns);
        $this->assertArrayHasKey('is_active', $columns);
    }

    public function testGetTableColumnsDescribesEachColumn() {
        $columns = $this->db->get_table_columns('test_users');

        $this->assertArrayHasKey('Type', $columns['id']);
        $this->assertArrayHasKey('Null', $columns['id']);
        $this->assertArrayHasKey('Key', $columns['id']);
        $this->assertStringContainsString('int', strtolower($columns['id']['Type']));
        $this->assertSame('PRI', $columns['id']['Key']);
        $this->assertSame('NO', $columns['name']['Null']);
        $this->assertSame('YES', $columns['email']['Null']);
    }

    public function testGetTableColumnsWorksForADatabaseQualifiedTable() {
        $columns = $this->db->get_table_columns(TEST_DB_NAME . '.test_users');

        $this->assertArrayHasKey('id', $columns);
    }

    public function testGetTableColumnsReturnsFalseForAnUnknownTable() {
        $this->db->halt_on_errors = false;

        $this->assertFalse($this->db->get_table_columns('this_table_does_not_exist'));
    }

    // GET_TABLE_STATUS

    public function testGetTableStatusIsKeyedByTableName() {
        $status = $this->db->get_table_status();

        $this->assertIsArray($status);
        $this->assertArrayHasKey('test_users', $status);
        $this->assertArrayHasKey('Engine', $status['test_users']);
        $this->assertSame('InnoDB', $status['test_users']['Engine']);
    }

    public function testGetTableStatusCanBeNarrowedToASingleTable() {
        $status = $this->db->get_table_status('test_users');

        $this->assertSame(['test_users'], array_keys($status));
    }

    public function testGetTableStatusAcceptsAPercentWildcard() {
        $status = $this->db->get_table_status('test_%');

        $this->assertArrayHasKey('test_users', $status);
        $this->assertArrayHasKey('test_products', $status);
        $this->assertArrayHasKey('test_categories', $status);
    }

    public function testGetTableStatusAcceptsADatabaseQualifiedTable() {
        $status = $this->db->get_table_status(TEST_DB_NAME . '.test_users');

        $this->assertSame(['test_users'], array_keys($status));
    }

    /**
     * The table name ends up in a LIKE pattern, where "_" stands for any single character - so asking for
     * "test_users" used to return "testXusers" as well
     */
    public function testGetTableStatusDoesNotTreatUnderscoresAsWildcards() {
        $this->db->query('DROP TABLE IF EXISTS testXusers');
        $this->db->query('CREATE TABLE testXusers (id INT)');

        $status = $this->db->get_table_status('test_users');

        $this->db->query('DROP TABLE testXusers');

        $this->assertSame(['test_users'], array_keys($status));
    }

    public function testGetTableStatusStillTreatsPercentAsAWildcardAfterAnUnderscore() {
        $status = $this->db->get_table_status('test_c%');

        $this->assertSame(['test_categories'], array_keys($status));
    }

    /**
     * optimize() picks the tables to work on through get_table_status(), so it used to optimize
     * anything the underscore wildcard happened to match as well
     */
    public function testOptimizeDoesNotTreatUnderscoresAsWildcards() {
        $this->db->query('DROP TABLE IF EXISTS testXusers');
        $this->db->query('CREATE TABLE testXusers (id INT)');

        $db = $this->probe();
        $db->optimize('test_users');
        $optimized = $this->optimizedTables($db);
        $db->shutdown();

        $this->db->query('DROP TABLE testXusers');

        $this->assertSame(['test_users'], $optimized);
    }

    public function testGetTableStatusIsEmptyWhenNothingMatches() {
        $status = $this->db->get_table_status('no_such_table_anywhere');

        $this->assertSame([], $status);
    }

    // OPTIMIZE

    public function testOptimizeRunsOptimizeTableForEveryTable() {
        $db = $this->probe();

        $db->optimize();

        $optimized = $this->optimizedTables($db);

        $this->assertContains('test_users', $optimized);
        $this->assertContains('test_products', $optimized);
        $this->assertContains('test_categories', $optimized);

        $db->shutdown();
    }

    public function testOptimizeCanBeNarrowedToASingleTable() {
        $db = $this->probe();

        $db->optimize('test_users');

        $this->assertSame(['test_users'], $this->optimizedTables($db));

        $db->shutdown();
    }

    public function testOptimizeAcceptsAPercentWildcard() {
        $db = $this->probe();

        $db->optimize('test_categories%');

        $this->assertSame(['test_categories'], $this->optimizedTables($db));

        $db->shutdown();
    }

    public function testOptimizeRunsNothingWhenNoTableMatches() {
        $db = $this->probe();

        $db->optimize('no_such_table_anywhere');

        $this->assertSame([], $this->optimizedTables($db));
        $this->assertSame([], $db->errors(), 'Matching no table is not an error');

        $db->shutdown();
    }

    public function testOptimizeAcceptsADatabaseQualifiedTable() {
        $db = $this->probe();

        $db->optimize(TEST_DB_NAME . '.test_users');

        $optimized = $this->optimizedTables($db);

        $this->assertCount(1, $optimized);
        // qualified in, qualified out
        $this->assertSame(TEST_DB_NAME . '.test_users', $optimized[0]);

        $db->shutdown();
    }

    public function testOptimizeReturnsNothing() {
        $this->assertNull($this->db->optimize('test_users'));
    }

    /**
     * The tables named by the OPTIMIZE TABLE statements the library sent, with the back-ticks stripped
     */
    private function optimizedTables($db) {
        $tables = [];

        foreach ($db->queries() as $query)
            if (preg_match('/^OPTIMIZE TABLE (.*)$/i', $query, $matches))
                $tables[] = str_replace('`', '', $matches[1]);

        return $tables;
    }

    // TRUNCATE

    public function testTruncateEmptiesTheTable() {
        $this->assertGreaterThan(0, $this->db->dcount('*', 'test_users'));

        $this->assertTrue($this->db->truncate('test_users'));

        $this->assertEquals(0, $this->db->dcount('*', 'test_users'));
    }

    public function testTruncateResetsTheAutoIncrement() {
        $this->db->truncate('test_users');
        $this->db->insert('test_users', ['name' => 'First again', 'email' => 'first@example.com']);

        $this->assertEquals(1, $this->db->insert_id());
    }

    public function testTruncateEscapesTheTableName() {
        // "order" is a reserved word - without back-ticks TRUNCATE would be a syntax error
        $this->db->query('DROP TABLE IF EXISTS `order`');
        $this->db->query('CREATE TABLE `order` (`id` INT)');
        $this->db->query('INSERT INTO `order` (`id`) VALUES (1)');

        $this->assertTrue($this->db->truncate('order'));
        $this->assertEquals(0, $this->db->dcount('*', 'order'));

        $this->db->query('DROP TABLE `order`');
    }

    public function testTruncateReturnsFalseForAnUnknownTable() {
        $this->db->halt_on_errors = false;

        $this->assertFalse($this->db->truncate('this_table_does_not_exist'));
    }

    public function testTruncateLeavesOtherTablesAlone() {
        $this->db->truncate('test_users');

        $this->assertGreaterThan(0, $this->db->dcount('*', 'test_products'));
    }
}
