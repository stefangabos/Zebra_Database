<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Debugging written to a file - what the debug property being an array turns the console into, and the
 * three switches it carries: one file per day, one per hour, and a backtrace with every entry.
 */
class DebugTest extends DatabaseTestCase {

    private $test_log_dir;
    private $original_log_path;

    protected function setUp(): void {
        parent::setUp();
        $this->connectToDatabase();

        // store the original value - debug is not stored because tearDown always turns it off rather than
        // putting it back, so that a test leaving it on cannot spill a debugging console into the output
        $this->original_log_path = $this->db->log_path;

        // a directory of this test's own, under the suite's scratch directory rather than under the system
        // one - what the suite writes belongs where the suite can clean it up, and where it does not depend
        // on there being a /tmp to write to in the first place
        $this->test_log_dir = getTempPath('logs') . '/debug_test_' . uniqid();
        if (!is_dir($this->test_log_dir)) {
            mkdir($this->test_log_dir, 0777, true);
        }
    }

    protected function tearDown(): void {
        // debug goes off rather than back to what it was - see setUp
        if ($this->db) {
            $this->db->debug = false;
            $this->db->log_path = $this->original_log_path;
        }

        $this->cleanupLogFiles();

        parent::tearDown();
    }

    /**
     * The one log file the library wrote, whatever it decided to call it.
     *
     * The daily and hourly names are built from the clock, and a test that builds the same name after the
     * fact disagrees with the library whenever the two land either side of a midnight or an hour - rare,
     * real, and impossible to reproduce when it happens. Asking the directory what is in it sidesteps the
     * clock entirely, and the name is then asserted against a pattern rather than against a timestamp.
     *
     * @param   string  $pattern    a glob, relative to the test's log directory
     */
    private function theLogFile($pattern) {
        $files = glob($this->test_log_dir . '/' . $pattern);

        $this->assertCount(1, $files, 'Exactly one log file should have been written, matching ' . $pattern);

        return $files[0];
    }

    private function cleanupLogFiles() {
        if (is_dir($this->test_log_dir)) {
            $files = glob($this->test_log_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->test_log_dir);
        }
    }

    public function testDebugArrayBasicConfiguration() {
        // daily off, hourly off, backtrace off
        $debug_config = [false, false, false];
        $this->db->log_path = $this->test_log_dir . '/basic.log';
        $this->db->debug = $debug_config;

        $this->assertEquals($debug_config, $this->db->debug);

        $this->db->query("SELECT 1 as test_value");

        // what the shutdown function does at the end of a request
        $this->db->_show_debugging_console();

        $this->assertFileExists($this->test_log_dir . '/basic.log');

        $this->db->debug = false;
    }

    public function testDebugArrayDailyLogging() {
        // daily on
        $debug_config = [true, false, false];
        $this->db->log_path = $this->test_log_dir . '/daily.log';
        $this->db->debug = $debug_config;

        $this->db->query("SELECT 'daily test' as test_value");

        $this->db->_show_debugging_console();

        // the name carries the date the entry was written on
        $log_file = $this->theLogFile('daily-*.log');

        $this->assertMatchesRegularExpression('/daily-\d{8}\.log$/', basename($log_file), 'One file per day');
        $this->assertStringContainsString('daily test', file_get_contents($log_file));

        $this->db->debug = false;
    }

    public function testDebugArrayHourlyLogging() {
        // daily and hourly on
        $debug_config = [true, true, false];
        $this->db->log_path = $this->test_log_dir . '/hourly.log';
        $this->db->debug = $debug_config;

        $this->db->query("SELECT 'hourly test' as test_value");

        $this->db->_show_debugging_console();

        // the name carries the date and the hour the entry was written in
        $log_file = $this->theLogFile('hourly-*.log');

        $this->assertMatchesRegularExpression('/hourly-\d{8}-\d{2}\.log$/', basename($log_file), 'One file per hour');
        $this->assertStringContainsString('hourly test', file_get_contents($log_file));

        $this->db->debug = false;
    }

    public function testDebugArrayBacktraceLogging() {
        // backtrace on
        $debug_config = [false, false, true];
        $this->db->log_path = $this->test_log_dir . '/backtrace.log';
        $this->db->debug = $debug_config;

        $this->db->query("SELECT 'backtrace test' as test_value");

        $this->db->_show_debugging_console();

        $this->assertFileExists($this->test_log_dir . '/backtrace.log');

        $log_content = file_get_contents($this->test_log_dir . '/backtrace.log');
        $this->assertStringContainsString('backtrace test', $log_content);
        $this->assertStringContainsString('BACKTRACE', strtoupper($log_content));
        $this->assertStringContainsString('FILE', strtoupper($log_content));
        $this->assertStringContainsString('LINE', strtoupper($log_content));

        $this->db->debug = false;
    }

    public function testDebugArrayAllOptionsEnabled() {
        // all three on
        $debug_config = [true, true, true];
        $this->db->log_path = $this->test_log_dir . '/all-options.log';
        $this->db->debug = $debug_config;

        $this->db->query("SELECT 'all options test' as test_value");

        $this->db->_show_debugging_console();

        // hourly wins over daily when both are asked for, and the backtrace goes in the same file
        $log_file = $this->theLogFile('all-options-*.log');

        $this->assertMatchesRegularExpression('/all-options-\d{8}-\d{2}\.log$/', basename($log_file));

        $log_content = file_get_contents($log_file);
        $this->assertStringContainsString('all options test', $log_content);
        $this->assertStringContainsString('BACKTRACE', strtoupper($log_content));

        $this->db->debug = false;
    }

    public function testDebugArrayWithDirectoryLogPath() {
        $debug_config = [false, false, false];
        $this->db->log_path = $this->test_log_dir;
        $this->db->debug = $debug_config;

        $this->db->query("SELECT 'directory path test' as test_value");

        $this->db->_show_debugging_console();

        // a path that is a directory rather than a file gets a log.txt written into it
        $this->assertFileExists($this->test_log_dir . '/log.txt');

        $log_content = file_get_contents($this->test_log_dir . '/log.txt');
        $this->assertStringContainsString('directory path test', $log_content);

        $this->db->debug = false;
    }

    public function testDebugArrayLogsBothSuccessfulAndUnsuccessfulQueries() {
        $debug_config = [false, false, false];
        $this->db->log_path = $this->test_log_dir . '/both-queries.log';
        $this->db->debug = $debug_config;

        // so that the failing query below is reported rather than stopping the run
        $original_halt = $this->db->halt_on_errors;
        $this->db->halt_on_errors = false;

        $this->db->query("SELECT 'successful query' as test_value");
        $this->db->query("SELECT * FROM nonexistent_table");

        $this->db->_show_debugging_console();

        $this->db->halt_on_errors = $original_halt;

        $this->assertFileExists($this->test_log_dir . '/both-queries.log');

        $log_content = file_get_contents($this->test_log_dir . '/both-queries.log');
        $this->assertStringContainsString('successful query', $log_content);
        $this->assertStringContainsString('nonexistent_table', $log_content);

        $this->db->debug = false;
    }

    public function testDebugArrayLogContainsProperFormatting() {
        $debug_config = [false, false, true];
        $this->db->log_path = $this->test_log_dir . '/formatting.log';
        $this->db->debug = $debug_config;

        $this->db->query("SELECT 'formatting test' as test_value, 42 as number");

        $this->db->_show_debugging_console();

        $this->assertFileExists($this->test_log_dir . '/formatting.log');

        $log_content = file_get_contents($this->test_log_dir . '/formatting.log');

        // an entry says what ran, how long it took and where it was called from
        $this->assertStringContainsString('QUERY', strtoupper($log_content));
        $this->assertStringContainsString('DURATION', strtoupper($log_content));
        $this->assertStringContainsString('BACKTRACE', strtoupper($log_content));
        $this->assertStringContainsString('formatting test', $log_content);

        $this->assertStringContainsString('---', $log_content);

        $this->db->debug = false;
    }

    public function testDebugArrayDoesNotLogWhenDebugIsNotArray() {
        $this->db->log_path = $this->test_log_dir . '/should-not-exist.log';
        $this->db->debug = true;

        $this->db->query("SELECT 'should not be logged' as test_value");

        // a boolean asks for the console, so log_path is left alone however it is set
        ob_start();
        $this->db->_show_debugging_console();
        $output = ob_get_clean();

        $this->assertFileDoesNotExist($this->test_log_dir . '/should-not-exist.log');
        $this->assertStringContainsString('should not be logged', $output);

        $this->db->debug = false;
    }

    public function testDebugArrayWithInvalidLogPath() {
        $debug_config = [false, false, false];

        $this->db->log_path = '/invalid/path/that/does/not/exist/debug.log';
        $this->db->debug = $debug_config;

        $this->db->query("SELECT 'invalid path test' as test_value");

        // the library reports this with trigger_error(..., E_USER_ERROR), caught here with a handler of our
        // own rather than with expectError(), which PHPUnit 9.6 deprecates and PHPUnit 10 does not have.
        // Every message is collected, since opening the file raises a warning of its own before the library
        // reports the failure, and which of the two arrives last is not something to depend on
        $captured = [];

        set_error_handler(function($errno, $errstr) use (&$captured) {
            $captured[] = $errstr;
            return true; // stop PHP handling it further, so E_USER_ERROR does not halt the run
        });

        $this->db->_show_debugging_console();

        restore_error_handler();

        $this->assertNotEmpty($captured, 'An error should have been raised for the unwritable log path');

        $matched = false;
        foreach ($captured as $message) if (strpos($message, 'Could not write to log file') !== false) $matched = true;

        $this->assertTrue($matched, 'The library should report that it could not write to the log file, got: ' . implode(' | ', $captured));

        $this->db->debug = false;
    }

    public function testLogPathPropertyWorksCorrectlyWithDebugArray() {
        $custom_log_path = $this->test_log_dir . '/custom-path.log';

        $debug_config = [false, false, false];
        $this->db->log_path = $custom_log_path;
        $this->db->debug = $debug_config;

        $this->db->query("SELECT 'custom path test' as test_value");

        $this->db->_show_debugging_console();

        $this->assertFileExists($custom_log_path);

        $log_content = file_get_contents($custom_log_path);
        $this->assertStringContainsString('custom path test', $log_content);

        $this->db->debug = false;
    }

    public function testMultipleQueriesLogged() {
        $debug_config = [false, false, false];
        $this->db->log_path = $this->test_log_dir . '/multiple.log';
        $this->db->debug = $debug_config;

        $this->db->query("SELECT 'first query' as test");
        $this->db->query("SELECT 'second query' as test");
        $this->db->query("SELECT 'third query' as test");

        $this->db->_show_debugging_console();

        $this->assertFileExists($this->test_log_dir . '/multiple.log');

        $log_content = file_get_contents($this->test_log_dir . '/multiple.log');

        $this->assertStringContainsString('first query', $log_content);
        $this->assertStringContainsString('second query', $log_content);
        $this->assertStringContainsString('third query', $log_content);

        // one separator per query
        $separator_count = substr_count($log_content, '---');
        $this->assertGreaterThanOrEqual(3, $separator_count);

        $this->db->debug = false;
    }
}
