<?php

/**
 * The smoke test that runs on the oldest PHP the library claims to support.
 *
 * Not a second test suite, and it must never grow into one - PHPUnit is not available down here and the
 * point is only to catch the obvious: the library parses, it loads, it connects, and a row still makes the
 * round trip. Anything finer belongs in the real suite, which runs from 7.4 upward.
 *
 * Written to 5.6 syntax - no "??", no return types, nothing PHPUnit. It has to run where it is aimed.
 *
 * Run through tests/run-legacy.sh rather than directly.
 */

$failures = 0;
$checks   = 0;

/**
 * @param   string  $what       what is being claimed
 * @param   bool    $condition  whether it holds
 *
 * @return  void
 */
function check($what, $condition) {

    global $failures, $checks;

    $checks++;

    if ($condition) {
        echo '  ok    ' . $what . "\n";
    } else {
        echo '  FAIL  ' . $what . "\n";
        $failures++;
    }

}

echo 'PHP ' . PHP_VERSION . "\n\n";

require_once dirname(dirname(__DIR__)) . '/Zebra_Database.php';

check('the library class exists', class_exists('Zebra_Database'));

$host = getenv('DB_HOST') !== false ? getenv('DB_HOST') : '127.0.0.1';
$port = getenv('DB_PORT') !== false ? getenv('DB_PORT') : '3306';
$user = getenv('DB_USER') !== false ? getenv('DB_USER') : 'root';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$name = getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'zebra_test';

$db = new Zebra_Database();

// the console is HTML written on shutdown, which is no use in a terminal, and halting would hide the
// report this script ends with
$db->debug = false;
$db->halt_on_errors = false;

$db->connect($host, $user, $pass, $name, $port);

if (!$db->query('SELECT 1')) {
    echo '  FAIL  could not reach MySQL at ' . $host . ':' . $port . ' - ' . $db->error() . "\n";
    exit(1);
}

check('a connection is made', $db->get_link() !== false);

// the server here is a throwaway started by run-legacy.sh, so the table is this script's to make
$db->query('DROP TABLE IF EXISTS `legacy_smoke`');
$db->query('
    CREATE TABLE `legacy_smoke` (
        `id`    int(11) NOT NULL AUTO_INCREMENT,
        `name`  varchar(100) NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
');

check('a table is created', $db->table_exists('legacy_smoke'));

// the value carries a quote and a backslash, which is what escaping is for
$payload = "written on " . PHP_VERSION . " - o'brien \\ co";

check('a row is written', $db->insert('legacy_smoke', array('name' => $payload)));
check('and read back as it went in', $db->dlookup('name', 'legacy_smoke', 'id = ?', array($db->insert_id())) === $payload);

// a replacement that would end the string it is put in, if it were not escaped
$db->query('SELECT * FROM legacy_smoke WHERE name = ?', array("' OR '1'='1"));

check('an injection payload matches nothing', $db->returned_rows === 0);
check('and leaves the table standing', $db->table_exists('legacy_smoke'));

$db->query('DROP TABLE `legacy_smoke`');

$db->close();

echo "\n" . ($failures === 0
    ? $checks . " checks, all fine\n"
    : $failures . ' of ' . $checks . " checks failed\n");

exit($failures === 0 ? 0 : 1);
