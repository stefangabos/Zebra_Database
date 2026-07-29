<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Tests for the two things the library does that end the script, and so cannot be watched from inside a
 * test: stopping on a fatal error, and printing the debugging console on shutdown.
 *
 * Every test here runs its code in a child PHP process through ChildProcess and then asserts on what the
 * child left behind - whether it reached the end of its script, and what it printed. A test that triggered
 * either of these in-process would take the test runner down with it, which is why the halting half of
 * halt_on_errors had no coverage at all until now.
 */
class HaltingTest extends DatabaseTestCase
{
    // HALTING

    /**
     * The default configuration - debugging on, halt_on_errors on - stops the script dead at the first
     * failing query. This is the library's single most visible behaviour and the reason halt_on_errors
     * exists, and it is what the in-process tests have to keep turning off in order to run at all
     */
    public function testAFailingQueryStopsTheScriptWhenDebuggingIsOn() {
        $child = ChildProcess::run('
            $db->debug = true;
            $db->halt_on_errors = true;
            $db->query("SELECT * FROM this_table_does_not_exist");
        ');

        $this->assertFalse($child['reached_the_end'], 'The script has to stop at the failing query');
        $this->assertStringContainsString('this_table_does_not_exist', $child['output'], 'And say what went wrong');
    }

    /**
     * And with halting turned off it carries on, which is the half the in-process tests can see
     */
    public function testAFailingQueryDoesNotStopTheScriptWhenHaltOnErrorsIsOff() {
        $child = ChildProcess::run('
            $db->debug = true;
            $db->halt_on_errors = false;
            $db->query("SELECT * FROM this_table_does_not_exist");
        ');

        $this->assertTrue($child['reached_the_end'], 'Execution has to continue past the failure');
    }

    /**
     * With debugging off there is nowhere to report the error to, so the library does not stop either -
     * TRUE is not the same as "always", and this is the difference between them
     */
    public function testAFailingQueryDoesNotStopTheScriptWhenDebuggingIsOff() {
        $child = ChildProcess::run('
            $db->debug = false;
            $db->halt_on_errors = true;
            $db->query("SELECT * FROM this_table_does_not_exist");
        ');

        $this->assertTrue($child['reached_the_end'], 'Only "always" halts when debugging is off');
    }

    /**
     * "always" halts by throwing, so the script ends with an uncaught exception rather than a plain stop -
     * an uncaught exception is a fatal error, which is a non-zero exit status
     */
    public function testHaltOnErrorsAlwaysEndsTheScriptWithAnException() {
        $child = ChildProcess::run('
            $db->debug = false;
            $db->halt_on_errors = "always";
            $db->query("SELECT * FROM this_table_does_not_exist");
        ');

        $this->assertFalse($child['reached_the_end']);
        $this->assertNotSame(0, $child['status'], 'An uncaught exception is a failed run');
        $this->assertStringContainsString('RuntimeException', $child['output']);
        $this->assertStringContainsString('this_table_does_not_exist', $child['output']);
    }

    /**
     * A request that announces itself as AJAX is let through instead of being stopped, since a half-written
     * response with a debugging console in the middle of it is no use to the caller. debug_ajax is what
     * turns that back off, and neither branch can be reached without a request to run in
     */
    public function testAnAjaxRequestIsNotStoppedUnlessAjaxDebuggingIsOn() {
        $let_through = ChildProcess::run('
            $_SERVER["HTTP_X_REQUESTED_WITH"] = "XMLHttpRequest";
            $db->debug = true;
            $db->halt_on_errors = true;
            $db->query("SELECT * FROM this_table_does_not_exist");
        ');

        $this->assertTrue($let_through['reached_the_end'], 'An AJAX request runs to the end of its response');

        $stopped = ChildProcess::run('
            $_SERVER["HTTP_X_REQUESTED_WITH"] = "XMLHttpRequest";
            $db->debug = true;
            $db->debug_ajax = true;
            $db->halt_on_errors = true;
            $db->query("SELECT * FROM this_table_does_not_exist");
        ');

        $this->assertFalse($stopped['reached_the_end'], 'With debug_ajax on it is stopped like anything else');
    }

    // THE DEBUGGING CONSOLE

    /**
     * The console is printed by a shutdown function, so it only ever appears once the script has ended -
     * which is why a test can assert that a query printed nothing, but not what the console holds.
     *
     * Run from the command line the console is written as text rather than as the HTML panel a browser
     * gets, which is a feature of its own: the library documents that it reports from CLI too, and this is
     * the only place that claim is checked
     */
    public function testTheDebuggingConsoleIsPrintedWhenTheScriptEnds() {
        $child = ChildProcess::run('
            $db->debug = true;
            $db->query("SELECT 42 AS the_answer");
        ');

        $this->assertTrue($child['reached_the_end'], 'A successful query stops nothing');
        $this->assertStringContainsString('the_answer', $child['output'], 'The console lists the query that ran');
        $this->assertStringContainsString('DURATION', $child['output'], 'With how long it took, as the CLI console does');
    }

    public function testNothingIsPrintedWhenDebuggingIsOff() {
        $child = ChildProcess::run('
            $db->debug = false;
            $db->query("SELECT 42 AS the_answer");
        ');

        $this->assertTrue($child['reached_the_end']);

        // what the library would have printed is the console, so that is what has to be absent - asserting
        // that the child printed nothing whatsoever also fails on whatever the PHP build itself decides to
        // say, which on some of them is a warning about JIT memory before a line of the script has run
        $this->assertStringNotContainsString('the_answer', $child['output'], 'The query must not be echoed');
        $this->assertStringNotContainsString('DURATION', $child['output'], 'Nor any part of the console');
    }

    /**
     * The console is a development tool and shows the queries it logged - but a query holding a value that
     * came from somewhere private is still no reason to print the connection password beside it
     */
    public function testTheConsoleDoesNotPrintTheConnectionPassword() {
        // read through the accessor rather than through the constant, which is folded to its default while
        // the code is being analysed - leaving everything below it looking like unreachable code
        $password = (string)test_env('DB_PASS', '');

        if ($password === '') {
            $this->markTestSkipped('There is no password to look for on this connection');
        }

        $child = ChildProcess::run('
            $db->debug = true;
            $db->query("SELECT 42 AS the_answer");
        ');

        $this->assertStringNotContainsString($password, $child['output'], 'The password must not reach the console');
    }
}
