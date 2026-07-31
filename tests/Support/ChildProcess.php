<?php

/**
 * Runs PHP in a process of its own and hands back what it did.
 *
 * Two kinds of test need this. Anything that ends the script - a deliberate die(), an uncaught exception, a
 * fatal error - would take the test runner with it in-process; halt_on_errors and the debugging console,
 * which is printed by a shutdown function, are both of that kind. And anything that has to be watched while
 * it is still running needs a second process to watch from.
 *
 *   ChildProcess::run($script, $env)     starts it, waits for it, returns what it did
 *   ChildProcess::start($script, $env)   starts it and hands back a handle to watch and kill
 *
 * Both take either the path of a PHP file or a snippet of code, plus the environment it runs with.
 */
class ChildProcess
{
    /**
     * The lines a child runs before a snippet it was given. A script given by path brings its own.
     *
     * A snippet therefore starts with $db connected to the same database the suite itself uses, and with
     * the settings a test would otherwise have to repeat every time.
     *
     * @return  string
     */
    private static function preamble() {

        return '<?php' . PHP_EOL
            . 'require_once ' . var_export(dirname(__DIR__, 2) . '/Zebra_Database.php', true) . ';' . PHP_EOL
            . '$db = new Zebra_Database();' . PHP_EOL
            . '$db->debug = false;' . PHP_EOL
            . '$db->cache_path = ' . var_export(getTempPath('cache'), true) . ';' . PHP_EOL
            . '$db->connect('
                . var_export(TEST_DB_HOST, true) . ', '
                . var_export(TEST_DB_USER, true) . ', '
                . var_export(TEST_DB_PASS, true) . ', '
                . var_export(TEST_DB_NAME, true) . ', '
                . var_export(TEST_DB_PORT, true) . ');' . PHP_EOL;

    }

    /**
     * The PHP interpreter running this suite, so that a child is always the same version as its parent
     *
     * @return  string
     */
    private static function interpreter() {

        return PHP_BINARY;

    }

    /**
     * Works out what to run, writing a temporary script when handed code rather than a path.
     *
     * @param   string  $script     the path of a PHP file, or the body of one
     *
     * @return  array<string, mixed>    "path" and whether it is "temporary"
     */
    private static function resolve($script) {

        if (strpos($script, PHP_EOL) === false && substr($script, -4) === '.php' && is_file($script)) {
            return ['path' => $script, 'temporary' => false];
        }

        $path = getTempPath('child') . '/child_' . md5($script) . '.php';

        // the marker at the end is how a test tells "ran to completion" from "was stopped part way"
        file_put_contents($path, self::preamble() . $script . PHP_EOL . 'echo "[REACHED THE END]";' . PHP_EOL);

        return ['path' => $path, 'temporary' => true];

    }

    /**
     * Starts a child and returns straight away.
     *
     * @param   string                  $script     the path of a PHP file, or the body of one
     * @param   array<string, string>   $env        added on top of the environment phpunit was started with
     *
     * @return  ChildProcessHandle
     */
    public static function start($script, $env = []) {

        $resolved = self::resolve($script);

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

        // "exec" so that the shell replaces itself with PHP - without it proc_open() runs the command through
        // "/bin/sh -c" and this handle holds the shell, so proc_terminate() signals that and leaves PHP running
        $process = proc_open(
            'exec ' . escapeshellarg(self::interpreter()) . ' ' . escapeshellarg($resolved['path']),
            $descriptors,
            $pipes,
            null,
            // proc_open() drops entries whose value is an empty string - the child sees those as never set
            array_merge(getenv(), $env)
        );

        if (!is_resource($process)) {
            if ($resolved['temporary']) unlink($resolved['path']);
            throw new RuntimeException('Could not start a child PHP process');
        }

        // reading either pipe blocks until the child closes it, which for a child still running is never
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        return new ChildProcessHandle($process, $pipes, $resolved['temporary'] ? $resolved['path'] : null);

    }

    /**
     * Runs a child to completion and returns what became of it.
     *
     * @param   string                  $script     the path of a PHP file, or the body of one
     * @param   array<string, string>   $env        added on top of the environment phpunit was started with
     *
     * @return  array<string, mixed>    "status", "output" and "reached_the_end"
     */
    public static function run($script, $env = []) {

        $handle = self::start($script, $env);

        $handle->wait();

        return $handle->status();

    }
}
