<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * select(), the shorthand that writes the SELECT for you - what it does with the columns it is given,
 * what it quotes, and the arguments that follow the table name.
 */
class SelectMethodTest extends DatabaseTestCase
{
    protected function setUp(): void {
        parent::setUp();
        $this->connectToDatabase();
        $this->insertTestData();
    }

    // COLUMN HANDLING

    public function testSelectAllColumnsWithAsterisk() {
        $result = $this->db->select('*', 'test_users');

        $this->assertNotFalse($result);
        $rows = $this->db->fetch_assoc_all($result);
        $this->assertCount(3, $rows);
        $this->assertArrayHasKey('email', $rows[0]);
    }

    public function testSelectSingleColumn() {
        $this->db->select('name', 'test_users', 'email = ?', ['john@example.com']);
        $row = $this->db->fetch_assoc();

        $this->assertSame(['name' => 'John Doe'], $row);
    }

    public function testSelectColumnsAsCommaSeparatedString() {
        $this->db->select('name, age', 'test_users', 'email = ?', ['john@example.com']);
        $row = $this->db->fetch_assoc();

        $this->assertSame(['name' => 'John Doe', 'age' => '30'], $row);
    }

    public function testSelectColumnsAsCommaSeparatedStringWithExtraWhitespace() {
        $this->db->select('  name  ,   age  ', 'test_users', 'email = ?', ['john@example.com']);
        $row = $this->db->fetch_assoc();

        $this->assertSame(['name' => 'John Doe', 'age' => '30'], $row);
    }

    public function testSelectColumnsAsArray() {
        $this->db->select(['name', 'age'], 'test_users', 'email = ?', ['john@example.com']);
        $row = $this->db->fetch_assoc();

        $this->assertSame(['name' => 'John Doe', 'age' => '30'], $row);
    }

    public function testSelectColumnsAreEscapedSoReservedWordsWork() {
        // "order" and "table" are reserved words - without back-ticks this query would be a syntax error
        $this->db->query('CREATE TEMPORARY TABLE `select_reserved` (`order` INT, `table` INT)');
        $this->db->query('INSERT INTO `select_reserved` (`order`, `table`) VALUES (1, 2)');

        $result = $this->db->select('order, table', 'select_reserved');

        $this->assertNotFalse($result);
        $this->assertSame(['order' => '1', 'table' => '2'], $this->db->fetch_assoc($result));
    }

    public function testSelectTableNameIsEscaped() {
        // "order" is a reserved word as a table name too
        $this->db->query('CREATE TEMPORARY TABLE `order` (`id` INT)');
        $this->db->query('INSERT INTO `order` (`id`) VALUES (42)');

        $result = $this->db->select('id', 'order');

        $this->assertNotFalse($result);
        $this->assertSame(['id' => '42'], $this->db->fetch_assoc($result));
    }

    public function testSelectWithAlias() {
        $this->db->select('name AS user_name', 'test_users', 'email = ?', ['john@example.com']);
        $row = $this->db->fetch_assoc();

        $this->assertSame(['user_name' => 'John Doe'], $row);
    }

    public function testSelectWithMysqlFunctionIsNotEscaped() {
        $this->db->select('COUNT(*)', 'test_users');
        $row = $this->db->fetch_assoc();

        $this->assertEquals(3, $row['COUNT(*)']);
    }

    public function testSelectWithSeveralMysqlFunctions() {
        $this->db->select('MIN(age), MAX(age)', 'test_users');
        $row = $this->db->fetch_assoc();

        $this->assertEquals(25, $row['MIN(age)']);
        $this->assertEquals(35, $row['MAX(age)']);
    }

    /**
     * A MySQL function was only recognised as one when it was the whole of the column, so giving it an
     * alias made it get enclosed in grave accents - "COUNT(*) AS total" came out as "`COUNT(*)` AS total"
     * and the query failed with a syntax error
     *
     * @group regression
     */
    public function testSelectWithAnAliasedMysqlFunction() {
        $this->db->select('COUNT(*) AS total', 'test_users');
        $row = $this->db->fetch_assoc();

        $this->assertSame(['total' => '3'], $row);
    }

    public function testSelectWithSeveralAliasedMysqlFunctions() {
        $this->db->select('MIN(age) AS youngest, MAX(age) AS oldest', 'test_users');
        $row = $this->db->fetch_assoc();

        $this->assertEquals(25, $row['youngest']);
        $this->assertEquals(35, $row['oldest']);
    }

    public function testSelectMixesAliasedFunctionsAndPlainColumns() {
        $this->db->select('name, LENGTH(name) AS name_length', 'test_users', 'email = ?', ['john@example.com']);
        $row = $this->db->fetch_assoc();

        $this->assertSame('John Doe', $row['name']);
        $this->assertEquals(8, $row['name_length']);
    }

    /**
     * Aliases are only recognized when written with the AS keyword - the docblock says so, and this is
     * what happens when they are not
     */
    public function testSelectDoesNotSupportAnAliasWrittenWithoutTheAsKeyword() {
        $this->db->halt_on_errors = false;

        // the whole of "name user_name" is taken to be a single column name
        $this->assertFalse($this->db->select('name user_name', 'test_users'));
    }

    public function testSelectAcceptsTheAsKeywordInAnyCase() {
        foreach (['AS', 'as', 'As'] as $keyword) {
            $this->db->select('name ' . $keyword . ' user_name', 'test_users', 'email = ?', ['john@example.com']);
            $this->assertSame(['user_name' => 'John Doe'], $this->db->fetch_assoc(), 'Written as "' . $keyword . '"');
        }
    }

    /**
     * Only the functions the library knows about are left unescaped - anything else is taken to be a
     * column name, which is what the docblock warns about
     */
    public function testSelectEscapesAnUnknownFunctionAsAColumnName() {
        $this->db->halt_on_errors = false;

        $this->assertFalse($this->db->select('NO_SUCH_FUNCTION(1)', 'test_users'));
    }

    /**
     * The string and the array form behave identically, whatever the docblock for select() once claimed
     * about the array form being the way to keep a value from being escaped
     */
    public function testSelectTreatsTheStringAndTheArrayFormIdentically() {
        $columns = ['name', 'NOW()', 'age AS years', 'test_users.email'];

        $this->db->select(implode(', ', $columns), 'test_users');
        $from_string = array_keys($this->db->fetch_assoc());

        $this->db->select($columns, 'test_users');
        $from_array = array_keys($this->db->fetch_assoc());

        $this->assertSame($from_string, $from_array);
        $this->assertSame(['name', 'NOW()', 'years', 'email'], $from_array);
    }

    public function testSelectLeavesAQualifiedAsteriskAlone() {
        $result = $this->db->select('test_users.*', 'test_users');

        $this->assertNotFalse($result);
        $this->assertArrayHasKey('email', $this->db->fetch_assoc($result));
    }

    public function testSelectWithQualifiedColumnName() {
        $this->db->select('test_users.name', 'test_users', 'email = ?', ['john@example.com']);
        $row = $this->db->fetch_assoc();

        $this->assertSame(['name' => 'John Doe'], $row);
    }

    // WHERE / REPLACEMENTS

    public function testSelectWithWhereClause() {
        $this->db->select('name', 'test_users', 'is_active = ?', [1]);

        $this->assertEquals(2, $this->db->returned_rows);
    }

    public function testSelectWithMultipleReplacements() {
        $this->db->select('name', 'test_users', 'age > ? AND is_active = ?', [25, 1]);
        $rows = $this->db->fetch_assoc_all();

        $this->assertCount(1, $rows);
        $this->assertSame('John Doe', $rows[0]['name']);
    }

    public function testSelectWithArrayReplacementForIn() {
        $this->db->select('name', 'test_users', 'age IN (?)', [[25, 30]]);
        $rows = $this->db->fetch_assoc_all();

        $this->assertCount(2, $rows);
    }

    public function testSelectReplacementsAreEscaped() {
        $this->db->select('name', 'test_users', 'name = ?', ["' OR 1=1 -- "]);

        $this->assertEquals(0, $this->db->returned_rows);
    }

    public function testSelectWithoutWhereReturnsEverything() {
        $this->db->select('name', 'test_users');

        $this->assertEquals(3, $this->db->returned_rows);
    }

    // ORDER / LIMIT

    public function testSelectWithOrderBy() {
        $this->db->select('name', 'test_users', '', '', 'age ASC');
        $rows = $this->db->fetch_assoc_all();

        $this->assertSame('Jane Smith', $rows[0]['name']);
        $this->assertSame('Bob Johnson', $rows[2]['name']);
    }

    public function testSelectWithOrderByDescending() {
        $this->db->select('name', 'test_users', '', '', 'age DESC');
        $rows = $this->db->fetch_assoc_all();

        $this->assertSame('Bob Johnson', $rows[0]['name']);
    }

    public function testSelectWithLimit() {
        $this->db->select('name', 'test_users', '', '', 'age ASC', '2');

        $this->assertEquals(2, $this->db->returned_rows);
    }

    public function testSelectWithLimitAndOffset() {
        $this->db->select('name', 'test_users', '', '', 'age ASC', '1, 1');
        $rows = $this->db->fetch_assoc_all();

        $this->assertCount(1, $rows);
        $this->assertSame('John Doe', $rows[0]['name']);
    }

    public function testSelectWithEverythingCombined() {
        $this->db->select(
            ['name', 'age'],
            'test_users',
            'is_active = ?',
            [1],
            'age DESC',
            '1'
        );
        $rows = $this->db->fetch_assoc_all();

        $this->assertCount(1, $rows);
        $this->assertSame(['name' => 'John Doe', 'age' => '30'], $rows[0]);
    }

    // CACHING

    public function testSelectResultsCanBeCached() {
        $path = getTempPath('cache');
        array_map('unlink', glob($path . '/*'));

        // a probe exposes the from_cache flag the library records for each query
        $db = $this->probe(['caching_method' => 'disk', 'cache_path' => $path]);

        $db->select('name', 'test_users', 'is_active = ?', [1], '', '', 10);
        $this->assertFalse($db->lastFromCache(), 'The first run is a cache miss');

        // the very same query a second time, which the cache answers
        $result = $db->select('name', 'test_users', 'is_active = ?', [1], '', '', 10);
        $this->assertTrue($db->lastFromCache(), 'The second identical select is a cache hit');

        $this->assertCount(2, $db->fetch_assoc_all($result));

    }

    // ERROR HANDLING

    public function testSelectReturnsFalseForInvalidTable() {
        $result = $this->db->select('*', 'this_table_does_not_exist');

        $this->assertFalse($result);
    }

    public function testSelectReturnsFalseForInvalidColumn() {
        $result = $this->db->select('this_column_does_not_exist', 'test_users');

        $this->assertFalse($result);
    }
}
