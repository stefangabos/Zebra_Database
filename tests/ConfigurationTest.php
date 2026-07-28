<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Test suite for the methods that configure the library rather than talk to the database -
 * option() and language()
 */
class ConfigurationTest extends DatabaseTestCase
{
    /**
     * A connection-less instance, since option() has to be called before the library connects
     */
    private function unconnectedInstance() {
        $db = new Zebra_Database();
        $db->debug = false;
        $db->halt_on_errors = false;
        $db->cache_path = getTempPath('cache');
        return $db;
    }

    // OPTION

    public function testOptionIsAppliedToTheConnection() {
        $db = $this->unconnectedInstance();

        // MYSQLI_INIT_COMMAND runs the given statement as soon as the connection is established, so if the
        // option really made it through to mysqli the session variable it sets will be readable afterwards
        $db->option(MYSQLI_INIT_COMMAND, 'SET @zebra_option_test = 1234');
        $db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT);

        $row = $db->fetch_assoc($db->query('SELECT @zebra_option_test AS value'));

        $this->assertEquals(1234, $row['value']);

        $db->close();
    }

    public function testOptionAcceptsAnArrayOfOptions() {
        $db = $this->unconnectedInstance();

        $db->option([
            MYSQLI_OPT_CONNECT_TIMEOUT  => 5,
            MYSQLI_INIT_COMMAND         => 'SET @zebra_option_test = 4321',
        ]);
        $db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT);

        $row = $db->fetch_assoc($db->query('SELECT @zebra_option_test AS value'));

        $this->assertEquals(4321, $row['value']);

        $db->close();
    }

    public function testOptionCanBeCalledSeveralTimes() {
        $db = $this->unconnectedInstance();

        $db->option(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
        $db->option(MYSQLI_INIT_COMMAND, 'SET @zebra_option_test = 7');
        $db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT);

        $row = $db->fetch_assoc($db->query('SELECT @zebra_option_test AS value'));

        $this->assertEquals(7, $row['value'], 'The second call must not have discarded the first');

        $db->close();
    }

    public function testOptionReturnsNothingWhenSetBeforeConnecting() {
        $db = $this->unconnectedInstance();

        $this->assertNull($db->option(MYSQLI_OPT_CONNECT_TIMEOUT, 5));
    }

    public function testOptionFailsOnceTheConnectionIsUp() {
        $db = $this->unconnectedInstance();
        $db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT);

        // the library connects lazily, so the connection only really exists after a query has been run
        $db->query('SELECT 1');

        $this->assertFalse($db->option(MYSQLI_OPT_CONNECT_TIMEOUT, 5));

        $db->close();
    }

    public function testOptionIsAcceptedAgainAfterCloseResetsTheOptions() {
        $db = $this->unconnectedInstance();
        $db->option(MYSQLI_INIT_COMMAND, 'SET @zebra_option_test = 1');
        $db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT);
        $db->query('SELECT 1');

        $db->close(true);

        // with the connection gone and the options reset, a fresh option is accepted and applied again
        $this->assertNull($db->option(MYSQLI_INIT_COMMAND, 'SET @zebra_option_test = 2'));

        $db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT);
        $row = $db->fetch_assoc($db->query('SELECT @zebra_option_test AS value'));

        $this->assertEquals(2, $row['value']);

        $db->close();
    }

    public function testInvalidOptionIsLoggedAsAnError() {
        $db = $this->unconnectedInstance();
        $db->debug = true;

        // 999999 is not a valid mysqli option constant
        $db->option(999999, 1);
        $db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT);
        $db->query('SELECT 1');

        $errors = $this->readErrors($db);

        $this->assertNotEmpty($errors, 'A bogus option has to be reported');
        $this->assertStringContainsString('999999', $errors[0]['message']);

        $db->debug = false;
        $db->close();
    }

    // LANGUAGE

    public function testDefaultLanguageIsEnglish() {
        $db = $this->unconnectedInstance();

        $this->assertSame('errors', $this->readLanguage($db, 'errors'));
    }

    public function testLanguageSwitchesTheMessages() {
        $db = $this->unconnectedInstance();

        $english = $this->readLanguage($db, 'errors');
        $db->language('german');
        $german = $this->readLanguage($db, 'errors');

        $this->assertNotSame($english, $german);
        $this->assertSame('Fehler', $german);
    }

    public function testLanguageCanBeSwitchedBackAndForth() {
        $db = $this->unconnectedInstance();

        $db->language('russian');
        $russian = $this->readLanguage($db, 'errors');
        $db->language('english');

        $this->assertNotSame($russian, $this->readLanguage($db, 'errors'));
        $this->assertSame('errors', $this->readLanguage($db, 'errors'));
    }

    public function testLanguageAffectsTheMessagesTheLibraryLogs() {
        $db = $this->unconnectedInstance();
        $db->debug = true;
        $db->language('german');
        $db->connect(TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_PORT);
        $db->query('SELECT 1');

        // options_before_connect is the message option() logs, and in german it is a different string
        $db->option(MYSQLI_OPT_CONNECT_TIMEOUT, 5);

        $errors = $this->readErrors($db);

        $this->assertNotEmpty($errors);
        $this->assertSame($this->readLanguage($db, 'options_before_connect'), $errors[0]['message']);

        $db->debug = false;
        $db->close();
    }

    /**
     * Every language file has to define exactly the same keys - a missing key would surface at runtime as
     * an "undefined array key" notice from wherever the library reads it
     */
    public function testAllLanguageFilesDefineTheSameKeys() {
        $reference = null;
        $reference_name = null;

        foreach (glob(__DIR__ . '/../languages/*.php') as $file) {

            $name = basename($file, '.php');

            $db = $this->unconnectedInstance();
            $db->language($name);
            $keys = array_keys($this->readLanguageArray($db));
            sort($keys);

            if ($reference === null) {
                $reference = $keys;
                $reference_name = $name;
                continue;
            }

            $this->assertSame(
                $reference,
                $keys,
                'The "' . $name . '" language file does not define the same keys as "' . $reference_name . '"'
            );

        }

        $this->assertNotNull($reference, 'There has to be at least one language file');
    }

    /**
     * The language and debug_info properties are protected, so read them the way a subclass would
     */
    private function readLanguage($db, $key) {
        $array = $this->readLanguageArray($db);
        return isset($array[$key]) ? $array[$key] : null;
    }

    private function readLanguageArray($db) {
        $property = new ReflectionProperty('Zebra_Database', 'language');
        $property->setAccessible(true);
        return $property->getValue($db);
    }

    private function readErrors($db) {
        $property = new ReflectionProperty('Zebra_Database', 'debug_info');
        $property->setAccessible(true);
        $debug_info = $property->getValue($db);
        return isset($debug_info['errors']) ? $debug_info['errors'] : [];
    }
}
