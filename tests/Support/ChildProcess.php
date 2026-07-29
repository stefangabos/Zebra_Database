<?php

/**
 * Runs a snippet of PHP in a process of its own and hands back what it did.
 *
 * Some of what the library does cannot be observed from inside a test: halt_on_errors stops the script,
 * and the debugging console is printed by a shutdown function that only runs when the script ends. Both
 * are the library's most visible behaviour, and testing them in-process is impossible - a test that
 * triggers them takes the test runner with it.
 *
 * So the snippet runs in a child, and what the child leaves behind - its exit status, its output, and
 * whether it reached the end - is what gets asserted.
 */
class ChildProcess
{
    /**
     * The PHP interpreter running this suite, so that a child is always the same version as its parent
     */
    private static function interpreter() {
        return defined('PHP_BINARY') && PHP_BINARY !== '' ? PHP_BINARY : 'php';
    }

    /**
     * Runs the given code and returns ['status' => int, 'output' => string].
     *
     * The snippet is written to a file under tmp/ rather than passed to "php -r", so that it can be
     * written the way any other PHP is - with the library included and the connection details filled in
     * from the same settings the suite itself uses.
     *
     * @param   string  $code   the body of the script, with $db already connected and $reached_the_end
     *                          available to write to
     *
     * @return  array<string, mixed>
     */
    public static function run($code) {

        $script = '<?php' . PHP_EOL
            . 'require_once ' . var_export(dirname(__DIR__, 2) . '/Zebra_Database.php', true) . ';' . PHP_EOL
            . '$db = new Zebra_Database();' . PHP_EOL
            . '$db->debug = false;' . PHP_EOL
            . '$db->cache_path = ' . var_export(getTempPath('cache'), true) . ';' . PHP_EOL
            . '$db->connect('
                . var_export(TEST_DB_HOST, true) . ', '
                . var_export(TEST_DB_USER, true) . ', '
                . var_export(TEST_DB_PASS, true) . ', '
                . var_export(TEST_DB_NAME, true) . ', '
                . var_export(TEST_DB_PORT, true) . ');' . PHP_EOL
            . $code . PHP_EOL
            // the marker is how a test tells "the script ran to the end" from "the script was stopped",
            // which is the whole point of running it out here
            . 'echo "[REACHED THE END]";' . PHP_EOL;

        $path = getTempPath('child') . '/child_' . md5($code) . '.php';
        file_put_contents($path, $script);

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open(escapeshellarg(self::interpreter()) . ' ' . escapeshellarg($path), $descriptors, $pipes);

        if (!is_resource($process)) {
            unlink($path);
            throw new RuntimeException('Could not start a child PHP process');
        }

        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $status = proc_close($process);

        unlink($path);

        return [
            'status'            => $status,
            'output'            => $output,
            'reached_the_end'   => strpos($output, '[REACHED THE END]') !== false,
        ];

    }
}
