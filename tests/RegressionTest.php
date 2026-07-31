<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Tests for bugs that were fixed at some point in the library's history and must not come back.
 *
 * Each test names the commit that fixed the bug it guards, so that reverting that commit points straight
 * at the test that goes red. A regression that is also a plain feature test lives with the rest of its
 * feature instead - the multi-byte escaping fix in SecurityTest, the empty array replacement in
 * ArrayParameterTest, the table_exists wildcards in EdgeCasesTest, the INC() keyword in CrudTest and a
 * dozen more - and carries "@group regression" on the method, so that the set below is not mistaken for
 * the whole of it.
 *
 * The group is on the class because every test in this file is a regression - it is what makes the whole
 * set runnable on its own with "tests/run-tests.sh --group regression" before tagging a release, and
 * mutate.php in the project root is what proves the set can actually go red.
 *
 * @group regression
 */
class RegressionTest extends DatabaseTestCase
{
    protected function setUp(): void {
        parent::setUp();
        $this->connectToDatabase();
        $this->insertTestData();
    }

    /**
     * An instance that has not connected to anything yet
     */
    private function unconnectedInstance() {
        $db = new Zebra_Database();
        $db->debug = false;
        $db->halt_on_errors = false;
        $db->cache_path = getTempPath('cache');
        return $db;
    }

    /**
     * 81718e3 - "Fixed undefined variable if calling the close() method without an active connection"
     *
     * close() read a variable that was only set inside the branch that had something to close
     */
    public function testCloseWithoutAConnectionReturnsFalseAndWarnsAboutNothing() {
        $db = $this->unconnectedInstance();

        $result = null;
        $raised = $this->diagnosticsRaisedBy(function() use ($db, &$result) {
            $result = $db->close();
        });

        $this->assertFalse($result);
        $this->assertSame([], $raised);
    }

    /**
     * 102255e - "Fixed bug with the library not returning FALSE upon errors when debugging was disabled"
     *
     * With debugging off the error path fell through without returning, so a failing query reported
     * something other than FALSE
     */
    public function testAFailingQueryReturnsFalseWithDebuggingDisabled() {
        $db = $this->unconnectedInstance();
        $db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT);

        $this->assertFalse($db->debug, 'This test is only meaningful with debugging off');
        $this->assertFalse($db->query('SELECT * FROM this_table_does_not_exist'));

        $db->close();
    }

    /**
     * ba8d0f3 - "Fixed an issue where having the `debug` property set to a string but debugging not being
     * activated would result in errors not being logged"
     *
     * When debugging is off the library reports errors through the system logger. The check for that was
     * written as `debug === false`, which is not the same thing as debugging being off - setting debug to
     * a string arms the query string switch without turning debugging on, and in that state errors went
     * nowhere at all
     */
    public function testErrorsReachTheSystemLoggerWhenDebugIsAStringButDebuggingIsOff() {
        $log = getTempPath('logs') . '/error_log.txt';
        if (file_exists($log)) unlink($log);

        $previous = ini_get('error_log');
        ini_set('error_log', $log);

        $db = $this->unconnectedInstance();
        // a string arms the "?turn_debugging_on=1" switch - with the parameter absent, debugging is off
        $db->debug = 'turn_debugging_on';

        try {

            $db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT);
            $db->query('SELECT * FROM this_table_does_not_exist');
            $db->close();

        } finally {
            ini_set('error_log', $previous);
        }

        $this->assertFileExists($log, 'The error has to reach the system logger');
        $this->assertStringContainsString('this_table_does_not_exist', file_get_contents($log));
    }

    public function testErrorsReachTheSystemLoggerWhenDebugIsFalse() {
        $log = getTempPath('logs') . '/error_log.txt';
        if (file_exists($log)) unlink($log);

        $previous = ini_get('error_log');
        ini_set('error_log', $log);

        $db = $this->unconnectedInstance();

        try {

            $db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT);
            $db->query('SELECT * FROM this_table_does_not_exist');
            $db->close();

        } finally {
            ini_set('error_log', $previous);
        }

        $this->assertFileExists($log);
        $this->assertStringContainsString('this_table_does_not_exist', file_get_contents($log));
    }

    /**
     * 5b81986 - halt_on_errors set to "always" raises an exception regardless of the debug property
     */
    public function testHaltOnErrorsAlwaysThrowsEvenWithDebuggingDisabled() {
        $db = $this->unconnectedInstance();
        $db->halt_on_errors = 'always';
        $db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT);

        $this->expectException('RuntimeException');

        try {
            $db->query('SELECT * FROM this_table_does_not_exist');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('this_table_does_not_exist', $exception->getMessage());
            throw $exception;
        }
    }

    public function testHaltOnErrorsSetToTrueDoesNotThrowWithDebuggingDisabled() {
        $db = $this->unconnectedInstance();
        $db->halt_on_errors = true;
        $db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT);

        // only the "always" value throws when debugging is off - TRUE is the default and must not
        $this->assertFalse($db->query('SELECT * FROM this_table_does_not_exist'));

        $db->close();
    }

    /**
     * 485a681 - "Fixed potential errors when setting invalid values as the cache path"
     *
     * The path was handed to file_exists() and friends without checking it was a string first. The query
     * is still expected to fail - a cache folder that is not set up right has to be noticed - but it has
     * to fail cleanly rather than through a PHP error
     *
     * @dataProvider invalidCachePaths
     */
    public function testAnInvalidCachePathFailsCleanly($cache_path) {
        $db = $this->unconnectedInstance();
        $db->caching_method = 'disk';
        $db->cache_path = $cache_path;
        $db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT);

        $result = null;
        $raised = $this->diagnosticsRaisedBy(function() use ($db, &$result) {
            $result = $db->query('SELECT 1 AS one', '', 60);
        });

        $this->assertFalse($result, 'A cache path that is not usable has to be reported, not worked around');
        $this->assertSame([], $raised, 'But it must not raise PHP errors on the way');

        $db->close();
    }

    public function invalidCachePaths() {
        return [
            'boolean true'  => [true],
            'boolean false' => [false],
            'an integer'    => [1234],
            'an array'      => [['some/path']],
            'null'          => [null],
        ];
    }

    /**
     * 9ee4ba3 and 512d544 - asking for memcache or redis caching without the extension being loaded and
     * connected used to reach for an object that was never created
     *
     * @dataProvider unavailableCachingMethods
     */
    public function testACachingMethodWithoutItsServerFailsCleanly($caching_method) {
        $db = $this->unconnectedInstance();
        $db->caching_method = $caching_method;
        // deliberately not setting the host or port, so the library has nothing to connect to
        $db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT);

        $raised = $this->diagnosticsRaisedBy(function() use ($db) {
            $db->query('SELECT 1 AS one', '', 60);
        });

        $this->assertSame([], $raised);

        $db->close();
    }

    public function unavailableCachingMethods() {
        return [
            'memcache' => ['memcache'],
            'redis'    => ['redis'],
        ];
    }

    /**
     * d80d8eb - dlookup() takes an ORDER BY clause after a pipe in the table name, which is how the single
     * row it returns can be chosen (issue #20)
     */
    public function testDlookupOrdersByWhatFollowsThePipe() {
        // insertTestData gives John 30, Jane 25 and Bob 35
        $this->assertSame('Bob Johnson', $this->db->dlookup('name', 'test_users|age DESC'));
        $this->assertSame('Jane Smith', $this->db->dlookup('name', 'test_users|age ASC'));
    }

    public function testDlookupWithAPipeStillAppliesTheWhereClause() {
        $name = $this->db->dlookup('name', 'test_users|age DESC', 'is_active = ?', [1]);

        $this->assertSame('John Doe', $name, 'Bob is the oldest but is not active');
    }

    public function testDlookupWithAPipeCanReturnSeveralColumns() {
        $row = $this->db->dlookup('name, age', 'test_users|age DESC');

        $this->assertSame(['name' => 'Bob Johnson', 'age' => '35'], $row);
    }

    public function testDlookupWithoutAPipeIsUnordered() {
        // no ORDER BY is added at all, so the clause has to be absent rather than empty
        $this->assertNotFalse($this->db->dlookup('name', 'test_users'));
    }

    /**
     * a35735a - "Fixed deprecation warning in PHP 8.3+"
     *
     * Values on their way into a query were trimmed without checking they were strings first, so anything
     * that was not one raised a deprecation
     *
     * @dataProvider nonStringValues
     */
    public function testNonStringValuesDoNotRaiseDeprecations($column, $value) {
        $raised = $this->diagnosticsRaisedBy(function() use ($column, $value) {
            $this->db->update('test_users', [$column => $value], 'email = ?', ['john@example.com']);
        });

        $this->assertSame([], $raised);
    }

    public function nonStringValues() {
        return [
            'an integer'    => ['age', 42],
            'a float'       => ['score', 12.5],
            'a boolean'     => ['is_active', true],
            'null'          => ['email', null],
        ];
    }

    /**
     * cf08bb2 - only SELECT, DELETE, INSERT, REPLACE and UPDATE can be explained, and the library EXPLAINs
     * every query it thinks returns rows while debugging - a query MySQL refuses to explain used to take
     * the debugging code down with it (issue #76)
     *
     * @dataProvider queriesThatCannotBeExplained
     */
    public function testAQueryThatCannotBeExplainedDoesNotBreakDebugging($query) {
        $db = $this->probe();

        $result = null;
        $raised = $this->diagnosticsRaisedBy(function() use ($db, $query, &$result) {
            $result = $db->query($query);
        });

        $this->assertNotFalse($result, 'The query itself still has to run');
        $this->assertSame([], $raised);

    }

    public function queriesThatCannotBeExplained() {
        return [
            'SHOW TABLES'   => ['SHOW TABLES'],
            'SHOW COLUMNS'  => ['SHOW COLUMNS FROM test_users'],
            'SHOW STATUS'   => ['SHOW TABLE STATUS'],
            'DESCRIBE'      => ['DESCRIBE test_users'],
        ];
    }

    /**
     * The same thing for unbuffered queries, which EXPLAIN through a different code path - one that had
     * been left without the try/catch cf08bb2 added to the buffered one.
     *
     * Since PHP 8.1 mysqli throws instead of returning FALSE, so asking MySQL to EXPLAIN something it
     * cannot explain raised a mysqli_sql_exception that escaped the library altogether and took the
     * script with it - and it only took having debugging on to get there.
     *
     * @dataProvider queriesThatCannotBeExplained
     */
    public function testAnUnbufferedQueryThatCannotBeExplainedDoesNotBreakDebugging($query) {
        $db = $this->probe();

        $this->assertTrue($db->debug_show_explain, 'The path under test is only reached while explaining');

        $rows = 0;
        $result = $db->query_unbuffered($query);

        // the EXPLAIN happens once the last row has been fetched, so the whole set has to be walked
        while ($db->fetch_assoc($result)) $rows++;

        $this->assertGreaterThan(0, $rows, 'The query itself still has to return its rows');

    }

    /**
     * And the counterpart - a query that can be explained still gets explained
     */
    public function testAnUnbufferedQueryThatCanBeExplainedStillIs() {
        $db = $this->probe();

        $result = $db->query_unbuffered('SELECT * FROM test_users');
        while ($db->fetch_assoc($result)) {
        }

        $this->assertNotEmpty($db->explainOfLastQuery(), 'A SELECT can be explained, so it has to be');

    }

    /**
     * 4ba22a3 - table_exists() failed when it was given a database-qualified table name, the database part
     * being pasted into the query without being enclosed in grave accents
     *
     * A plain name like "zebra_test" happens to survive that, so the database this uses is deliberately
     * named with a hyphen - without the grave accents MySQL reads it as a subtraction and the query is a
     * syntax error
     */
    public function testTableExistsAcceptsADatabaseQualifiedName() {
        $database = $this->createDatabaseNeedingQuoting();

        $this->assertTrue($this->db->table_exists($database . '.some_table'));
        $this->assertFalse($this->db->table_exists($database . '.this_table_does_not_exist'));

        $this->dropDatabaseNeedingQuoting($database);
    }

    public function testTableExistsAcceptsAPlainDatabaseQualifiedName() {
        $this->assertTrue($this->db->table_exists(TEST_DB_NAME . '.test_users'));
        $this->assertFalse($this->db->table_exists(TEST_DB_NAME . '.this_table_does_not_exist'));
    }

    /**
     * 1c9b20c - get_tables() escapes the database name for the same reason
     */
    public function testGetTablesAcceptsADatabaseNameNeedingQuoting() {
        $database = $this->createDatabaseNeedingQuoting();

        $this->assertSame(['some_table'], $this->db->get_tables($database));

        $this->dropDatabaseNeedingQuoting($database);
    }

    /**
     * Creates a throw-away database whose name only works when enclosed in grave accents, and returns
     * its name. The test is skipped if the account cannot create databases.
     */
    private function createDatabaseNeedingQuoting() {
        $database = 'zebra-test-quoted';

        $this->db->halt_on_errors = false;

        if ($this->db->query('CREATE DATABASE IF NOT EXISTS ' . '`' . $database . '`') === false)
            $this->markTestSkipped('The test account cannot create databases');

        $this->db->query('CREATE TABLE IF NOT EXISTS `' . $database . '`.`some_table` (id INT)');

        return $database;
    }

    private function dropDatabaseNeedingQuoting($database) {
        $this->db->query('DROP DATABASE `' . $database . '`');

        // dropping the database we were pointed at leaves the connection without a default one
        $this->db->select_database(TEST_DB_NAME);
    }

    /**
     * The bookkeeping for an unbuffered query - the SQL, the console index, and whether the row count and
     * the EXPLAIN are wanted - was stashed in properties created on the mysqli_result object. PHP 8.2
     * deprecates creating those and PHP 9 makes it an error, so it belongs on the instance.
     */
    public function testIteratingAnUnbufferedQueryRaisesNoDeprecations() {
        $db = $this->unbufferedProbe();

        $raised = $this->diagnosticsRaisedBy(function() use ($db) {
            $result = $db->query_unbuffered('SELECT * FROM test_users');
            while ($db->fetch_assoc($result)) {
            }
        });

        $this->assertSame([], $raised);

    }

    /**
     * And the bookkeeping still has to reach the debugging console, which is the whole point of it
     */
    public function testAnUnbufferedQueryStillFillsTheDebuggingConsole() {
        $db = $this->unbufferedProbe();

        $rows = 0;
        $result = $db->query_unbuffered('SELECT * FROM test_users');
        while ($db->fetch_assoc($result)) $rows++;

        $entry = $db->consoleEntryForLastQuery();

        $this->assertSame(3, $rows, 'insertTestData inserts three users');
        $this->assertEquals(3, $db->returned_rows, 'The property is only set once the last row is read');
        $this->assertEquals(3, $entry['returned_rows'], 'And it has to reach the console too');
        $this->assertCount(3, $entry['records'], 'The rows themselves are shown in the console');
        $this->assertTrue($entry['unbuffered']);
        $this->assertIsArray($entry['explain']);

    }

    /**
     * That bookkeeping is kept in one place on the instance rather than on each result, so a query that
     * follows an unbuffered one must not inherit anything from it
     */
    public function testTheBookkeepingDoesNotLeakIntoTheNextQuery() {
        $db = $this->unbufferedProbe();

        $result = $db->query_unbuffered('SELECT * FROM test_users');
        while ($db->fetch_assoc($result)) {
        }

        // a plain buffered query afterwards - it is not unbuffered and must not be explained as one
        $db->query('SELECT name FROM test_users');
        $entry = $db->consoleEntryForLastQuery();

        $this->assertFalse($entry['unbuffered']);

        // and an unbuffered one that is not to be explained must not pick up the previous EXPLAIN
        $db->debug_show_explain = false;
        $result = $db->query_unbuffered('SELECT age FROM test_users');
        while ($db->fetch_assoc($result)) {
        }

        $this->assertEmpty($db->explainOfLastQuery(), 'This query was not to be explained');

    }

    private function unbufferedProbe() {
        return $this->probe();
    }

    /**
     * 371534f - debug_info holds the credentials among other things and must not be readable from outside
     */
    /**
     * A NULL among the columns is the SQL keyword, not the name of something to be enclosed in grave accents.
     * The NULL was handed to explode() first, which turned it into an empty string, so what came out was an
     * empty pair of grave accents - invalid SQL - and the code written to produce the keyword never ran.
     * explode() also raises a deprecation for it on PHP 8.1 and an error on PHP 9.
     */
    public function testANullAmongTheColumnsBecomesTheSqlKeyword() {
        $db = $this->probe(['debug' => false]);

        $escape = new ReflectionMethod('Zebra_Database', '_escape');
        $escape->setAccessible(true);

        $raised = $this->diagnosticsRaisedBy(function() use ($escape, $db) {
            $this->assertSame('`name`, NULL, `age`', $escape->invoke($db, ['name', null, 'age']));
        });

        $this->assertSame([], $raised, 'And it must not raise a deprecation on the way');

    }

    /**
     * The whole point of the above is that a query built from those columns is valid, which an empty pair of
     * grave accents would not be
     */
    public function testAQueryBuiltWithANullColumnRuns() {
        $result = $this->db->query('SELECT ' . implode(', ', ['name', 'NULL', 'age']) . ' FROM test_users LIMIT 1');

        $this->assertNotFalse($result);
        $this->assertArrayHasKey('NULL', $this->db->fetch_assoc($result));
    }

    public function testDebugInfoIsNotPubliclyReadable() {
        $property = new ReflectionProperty('Zebra_Database', 'debug_info');

        $this->assertFalse($property->isPublic());
    }

    /**
     * With debugging on, each successful query is sent back to MySQL as an EXPLAIN. Queries like SHOW TABLE
     * STATUS cannot be explained, and the error that comes back is caught - but it stays on the connection,
     * and on PHP 8.1 the next mysqli_fetch_* call throws it rather than the query it belongs to. So reading
     * the results of a query that ran perfectly well failed instead, taking get_table_status(), optimize()
     * and everything built on them with it. Only PHP 8.1 behaves that way, so this goes red on that version
     * alone - which is exactly why the test suite runs on every supported version
     */
    public function testResultsCanBeReadAfterAQueryThatCannotBeExplained() {
        $this->db->debug = true;

        $this->assertTrue($this->db->debug_show_explain, 'The EXPLAIN is only attempted when this is on');

        $this->db->query('SHOW TABLE STATUS');
        $tables = $this->db->fetch_assoc_all('Name');

        $this->db->debug = false;

        $this->assertArrayHasKey('test_users', $tables);

        // the same thing through the methods that run into it in normal use
        $this->db->debug = true;

        $status = $this->db->get_table_status('test_users');
        $this->db->optimize('test_users');

        $this->db->debug = false;

        $this->assertArrayHasKey('test_users', $status);
    }
}
