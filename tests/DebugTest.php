<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Test suite for Zebra_Database debug functionality, specifically file logging
 * when debug property is set to an array
 */
class DebugTest extends DatabaseTestCase {

    private $test_log_dir;
    private $original_debug;
    private $original_log_path;

    protected function setUp(): void {
        parent::setUp();
        $this->connectToDatabase();
        
        // Store original values
        $this->original_debug = $this->db->debug;
        $this->original_log_path = $this->db->log_path;
        
        // Create temporary directory for log files
        $this->test_log_dir = sys_get_temp_dir() . '/zebra_debug_test_' . uniqid();
        if (!is_dir($this->test_log_dir)) {
            mkdir($this->test_log_dir, 0777, true);
        }
    }

    protected function tearDown(): void {
        // Always reset debug to false to prevent destructor errors
        if ($this->db) {
            $this->db->debug = false;
            $this->db->log_path = $this->original_log_path;
        }
        
        // Clean up test log files and directory
        $this->cleanupLogFiles();
        
        parent::tearDown();
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

    // Test basic debug array functionality
    
    public function testDebugArrayBasicConfiguration() {
        // Test basic debug array configuration - daily=false, hourly=false, backtrace=false
        $debug_config = [false, false, false];
        $this->db->log_path = $this->test_log_dir . '/basic.log';
        $this->db->debug = $debug_config;
        
        $this->assertEquals($debug_config, $this->db->debug);
        
        // Execute a query to generate debug info
        $this->db->query("SELECT 1 as test_value");
        
        // Force debug output by calling the shutdown function
        $this->db->_show_debugging_console();
        
        // Check that log file was created
        $this->assertFileExists($this->test_log_dir . '/basic.log');
        
        // Reset debug to prevent destructor issues
        $this->db->debug = false;
    }

    public function testDebugArrayDailyLogging() {
        // Test daily logging - daily=true, hourly=false, backtrace=false
        $debug_config = [true, false, false];
        $this->db->log_path = $this->test_log_dir . '/daily.log';
        $this->db->debug = $debug_config;
        
        // Execute a query
        $this->db->query("SELECT 'daily test' as test_value");
        
        // Force debug output
        $this->db->_show_debugging_console();
        
        // Check that daily log file was created (should have date suffix)
        $expected_file = $this->test_log_dir . '/daily-' . date('Ymd') . '.log';
        $this->assertFileExists($expected_file);
        
        // Check that log contains our query
        $log_content = file_get_contents($expected_file);
        $this->assertStringContainsString('daily test', $log_content);
        
        $this->db->debug = false;
    }

    public function testDebugArrayHourlyLogging() {
        // Test hourly logging - daily=true, hourly=true, backtrace=false
        $debug_config = [true, true, false];
        $this->db->log_path = $this->test_log_dir . '/hourly.log';
        $this->db->debug = $debug_config;
        
        // Execute a query
        $this->db->query("SELECT 'hourly test' as test_value");
        
        // Force debug output
        $this->db->_show_debugging_console();
        
        // Check that hourly log file was created (should have date and hour suffix)
        $expected_file = $this->test_log_dir . '/hourly-' . date('Ymd') . '-' . date('H') . '.log';
        $this->assertFileExists($expected_file);
        
        // Check that log contains our query
        $log_content = file_get_contents($expected_file);
        $this->assertStringContainsString('hourly test', $log_content);
        
        $this->db->debug = false;
    }

    public function testDebugArrayBacktraceLogging() {
        // Test backtrace logging - daily=false, hourly=false, backtrace=true
        $debug_config = [false, false, true];
        $this->db->log_path = $this->test_log_dir . '/backtrace.log';
        $this->db->debug = $debug_config;
        
        // Execute a query
        $this->db->query("SELECT 'backtrace test' as test_value");
        
        // Force debug output
        $this->db->_show_debugging_console();
        
        // Check that log file was created
        $this->assertFileExists($this->test_log_dir . '/backtrace.log');
        
        // Check that log contains backtrace information
        $log_content = file_get_contents($this->test_log_dir . '/backtrace.log');
        $this->assertStringContainsString('backtrace test', $log_content);
        $this->assertStringContainsString('BACKTRACE', strtoupper($log_content));
        $this->assertStringContainsString('FILE', strtoupper($log_content));
        $this->assertStringContainsString('LINE', strtoupper($log_content));
        
        $this->db->debug = false;
    }

    public function testDebugArrayAllOptionsEnabled() {
        // Test all options enabled - daily=true, hourly=true, backtrace=true
        $debug_config = [true, true, true];
        $this->db->log_path = $this->test_log_dir . '/all-options.log';
        $this->db->debug = $debug_config;
        
        // Execute a query
        $this->db->query("SELECT 'all options test' as test_value");
        
        // Force debug output
        $this->db->_show_debugging_console();
        
        // Check that hourly log file with backtrace was created
        $expected_file = $this->test_log_dir . '/all-options-' . date('Ymd') . '-' . date('H') . '.log';
        $this->assertFileExists($expected_file);
        
        // Check that log contains our query and backtrace
        $log_content = file_get_contents($expected_file);
        $this->assertStringContainsString('all options test', $log_content);
        $this->assertStringContainsString('BACKTRACE', strtoupper($log_content));
        
        $this->db->debug = false;
    }

    public function testDebugArrayWithDirectoryLogPath() {
        // Test with log_path set to a directory (should create log.txt)
        $debug_config = [false, false, false];
        $this->db->log_path = $this->test_log_dir; // Directory, not file
        $this->db->debug = $debug_config;
        
        // Execute a query
        $this->db->query("SELECT 'directory path test' as test_value");
        
        // Force debug output
        $this->db->_show_debugging_console();
        
        // Check that default log.txt file was created
        $this->assertFileExists($this->test_log_dir . '/log.txt');
        
        // Check content
        $log_content = file_get_contents($this->test_log_dir . '/log.txt');
        $this->assertStringContainsString('directory path test', $log_content);
        
        $this->db->debug = false;
    }

    public function testDebugArrayLogsBothSuccessfulAndUnsuccessfulQueries() {
        $debug_config = [false, false, false];
        $this->db->log_path = $this->test_log_dir . '/both-queries.log';
        $this->db->debug = $debug_config;
        
        // Disable halt_on_errors so unsuccessful queries don't stop execution
        $original_halt = $this->db->halt_on_errors;
        $this->db->halt_on_errors = false;
        
        // Execute a successful query
        $this->db->query("SELECT 'successful query' as test_value");
        
        // Execute an unsuccessful query
        $this->db->query("SELECT * FROM nonexistent_table");
        
        // Force debug output
        $this->db->_show_debugging_console();
        
        // Restore halt_on_errors
        $this->db->halt_on_errors = $original_halt;
        
        // Check that log file was created
        $this->assertFileExists($this->test_log_dir . '/both-queries.log');
        
        // Check that log contains both queries
        $log_content = file_get_contents($this->test_log_dir . '/both-queries.log');
        $this->assertStringContainsString('successful query', $log_content);
        $this->assertStringContainsString('nonexistent_table', $log_content);
        
        $this->db->debug = false;
    }

    public function testDebugArrayLogContainsProperFormatting() {
        $debug_config = [false, false, true]; // With backtrace
        $this->db->log_path = $this->test_log_dir . '/formatting.log';
        $this->db->debug = $debug_config;
        
        // Execute a query
        $this->db->query("SELECT 'formatting test' as test_value, 42 as number");
        
        // Force debug output
        $this->db->_show_debugging_console();
        
        // Check that log file was created
        $this->assertFileExists($this->test_log_dir . '/formatting.log');
        
        $log_content = file_get_contents($this->test_log_dir . '/formatting.log');
        
        // Check for proper log formatting elements
        $this->assertStringContainsString('QUERY', strtoupper($log_content));
        $this->assertStringContainsString('DURATION', strtoupper($log_content));
        $this->assertStringContainsString('BACKTRACE', strtoupper($log_content));
        $this->assertStringContainsString('formatting test', $log_content);
        
        // Check for separator lines
        $this->assertStringContainsString('---', $log_content);
        
        $this->db->debug = false;
    }

    public function testDebugArrayDoesNotLogWhenDebugIsNotArray() {
        // Set debug to boolean true (not array)
        $this->db->log_path = $this->test_log_dir . '/should-not-exist.log';
        $this->db->debug = true; // Boolean, not array
        
        // Execute a query
        $this->db->query("SELECT 'should not be logged' as test_value");
        
        // Force debug output (should show console, not write file)
        ob_start();
        $this->db->_show_debugging_console();
        $output = ob_get_clean();
        
        // Check that log file was NOT created (since debug is not an array)
        $this->assertFileDoesNotExist($this->test_log_dir . '/should-not-exist.log');
        
        // But console output should be generated
        $this->assertStringContainsString('should not be logged', $output);
        
        $this->db->debug = false;
    }

    public function testDebugArrayWithInvalidLogPath() {
        $debug_config = [false, false, false];
        
        // Set an invalid log path (unwritable)
        $this->db->log_path = '/invalid/path/that/does/not/exist/debug.log';
        $this->db->debug = $debug_config;
        
        // Execute a query
        $this->db->query("SELECT 'invalid path test' as test_value");
        
        // the library reports this with trigger_error(..., E_USER_ERROR), which we catch with our own
        // error handler rather than with expectError() - that is deprecated in PHPUnit 9.6 and gone in
        // PHPUnit 10, and it is also what made this test the only warning in an otherwise clean run
        // collect every error rather than just the last one - trying to open the file raises a warning
        // of its own before the library gets to report the failure, and which arrives last should not
        // be something this test depends on
        $captured = [];

        set_error_handler(function($errno, $errstr) use (&$captured) {
            $captured[] = $errstr;
            return true; // stop PHP handling it further, so E_USER_ERROR does not halt the run
        });

        // Force debug output (should trigger error)
        $this->db->_show_debugging_console();

        restore_error_handler();

        $this->assertNotEmpty($captured, 'An error should have been raised for the unwritable log path');

        $matched = false;
        foreach ($captured as $message) if (strpos($message, 'Could not write to log file') !== false) $matched = true;

        $this->assertTrue($matched, 'The library should report that it could not write to the log file, got: ' . implode(' | ', $captured));

        $this->db->debug = false;
    }

    public function testLogPathPropertyWorksCorrectlyWithDebugArray() {
        // Test that log_path property is properly used when debug is array
        $custom_log_path = $this->test_log_dir . '/custom-path.log';
        
        $debug_config = [false, false, false];
        $this->db->log_path = $custom_log_path;
        $this->db->debug = $debug_config;
        
        // Execute a query
        $this->db->query("SELECT 'custom path test' as test_value");
        
        // Force debug output
        $this->db->_show_debugging_console();
        
        // Check that the custom log file was created
        $this->assertFileExists($custom_log_path);
        
        // Check content
        $log_content = file_get_contents($custom_log_path);
        $this->assertStringContainsString('custom path test', $log_content);
        
        $this->db->debug = false;
    }

    public function testMultipleQueriesLogged() {
        $debug_config = [false, false, false];
        $this->db->log_path = $this->test_log_dir . '/multiple.log';
        $this->db->debug = $debug_config;
        
        // Execute multiple queries
        $this->db->query("SELECT 'first query' as test");
        $this->db->query("SELECT 'second query' as test");
        $this->db->query("SELECT 'third query' as test");
        
        // Force debug output
        $this->db->_show_debugging_console();
        
        // Check that log file was created
        $this->assertFileExists($this->test_log_dir . '/multiple.log');
        
        $log_content = file_get_contents($this->test_log_dir . '/multiple.log');
        
        // Check that all queries are logged
        $this->assertStringContainsString('first query', $log_content);
        $this->assertStringContainsString('second query', $log_content);
        $this->assertStringContainsString('third query', $log_content);
        
        // Check that there are multiple query sections (look for multiple separators)
        $separator_count = substr_count($log_content, '---');
        $this->assertGreaterThanOrEqual(3, $separator_count);
        
        $this->db->debug = false;
    }
}