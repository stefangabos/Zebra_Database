<?php

/**
 * Mutation check - reverts one fix at a time and reports whether the test that claims to guard it fails.
 *
 * A green suite proves nothing until you have watched it go red.
 *
 *     php tests/mutate.php
 *
 * Each entry reverts a fix by replacing "from" with "to", runs only the tests matching "filter", and puts
 * the library back afterwards. Every one of them should come out CAUGHT.
 *
 * Some tests legitimately pass in both directions and are deliberately absent - the one pinning that "%"
 * still works after "_" is escaped guards against over-correcting rather than against the bug. So are the
 * fixes nothing outside the library can observe; RegressionTest's docblocks name those.
 *
 * This file is a development tool and is kept out of the package by .gitattributes.
 */

$library = __DIR__ . '/../Zebra_Database.php';
$php     = getenv('PHP') ? getenv('PHP') : 'php';

$base = file_get_contents($library);

$mutations = [

    '81718e3 - close() without a connection' => [
        'filter' => 'testCloseWithoutAConnectionReturnsFalseAndWarnsAboutNothing',
        'from'   => 'return isset($result) ? $result : false;',
        'to'     => 'return $result;',
    ],

    'table_exists() escapes the "_" wildcard' => [
        'filter' => 'testTableExistsMatchesUnderscoresLiterally',
        'from'   => "addcslashes(\$this->escape(\$table), '_')",
        'to'     => '$this->escape($table)',
    ],

    'get_table_status() escapes the "_" wildcard' => [
        'filter' => 'testGetTableStatusDoesNotTreatUnderscoresAsWildcards',
        'from'   => "addcslashes(\$this->escape(\$table), '_')",
        'to'     => '$this->escape($table)',
    ],

    'an empty array replacement becomes a subquery' => [
        'filter' => 'testEmptyArrayInAnInClauseMatchesNothing',
        'from'   => "empty(\$replacement) ? '(SELECT 1 FROM DUAL WHERE FALSE)' : ",
        'to'     => '',
    ],

    'INC() has to be the whole of the value' => [
        'filter' => 'testUpdateOnlyTreatsIncAsTheKeywordWhenItIsTheWholeValue',
        'from'   => "'/^INC\\((\\-{1})?([0-9]+|\\?)\\)\$/i'",
        'to'     => "'/^INC\\((\\-{1})?(.*)\\)/i'",
    ],

    'an action query is not reported as coming from the cache' => [
        'filter' => 'testActionQueriesAreNotReportedAsComingFromCache',
        'from'   => "        if (!isset(\$this->last_result)) {\n\n            // reset the flag\n            \$from_cache = false;\n",
        'to'     => "        if (!isset(\$this->last_result)) {\n",
    ],

    '49b685f - freeing the same result twice' => [
        'filter' => 'testFreeingTheSameResultTwiceReportsFalseRatherThanDying',
        'from'   => "            try {\n\n                mysqli_free_result(\$resource);\n\n                return true;\n\n            // if it had already been freed by a previous call to this method\n            } catch (Throwable \$e) {\n            }\n",
        'to'     => "            mysqli_free_result(\$resource);\n\n            return true;\n",
    ],

    'the character set is set through the API, not with a query' => [
        'filter' => 'testMultiByteCharsetCannotBeUsedToBreakOutOfQuotes',
        'from'   => 'if (!mysqli_set_charset($this->connection, $charset)) return false;',
        'to'     => "if (!\$this->query('SET NAMES \\'' . \$this->escape(\$charset) . '\\'')) return false;",
    ],

    '102255e - a failing query returns FALSE with debugging off' => [
        'filter' => 'testAFailingQueryReturnsFalseWithDebuggingDisabled',
        'from'   => "\$backtraceInfo[1]['line'], true));\n\n            return false;\n",
        'to'     => "\$backtraceInfo[1]['line'], true));\n",
    ],

    'ba8d0f3 - errors reach the logger whenever debugging is off' => [
        'filter' => 'testErrorsReachTheSystemLoggerWhenDebugIsAStringButDebuggingIsOff',
        'from'   => "        } elseif (\$category === 'unsuccessful-queries' || \$category === 'errors') {",
        'to'     => "        } elseif (\$this->debug === false && (\$category === 'unsuccessful-queries' || \$category === 'errors')) {",
    ],

    '5b81986 - halt_on_errors "always" throws whatever debug says' => [
        'filter' => 'testHaltOnErrorsAlwaysThrowsEvenWithDebuggingDisabled',
        'from'   => "        if (\$this->_is_debugging_enabled() || (\$fatal && \$this->halt_on_errors === 'always')) {",
        'to'     => '        if ($this->_is_debugging_enabled()) {',
    ],

    'cf08bb2 - a query MySQL cannot EXPLAIN does not break debugging' => [
        'filter' => 'testAQueryThatCannotBeExplainedDoesNotBreakDebugging',
        'from'   => "                        try {\n\n                            // ask MySQL to EXPLAIN the query\n                            \$explain_resource = mysqli_query(\$this->connection, 'EXPLAIN ' . \$sql);",
        'to'     => "                        if (true) {\n\n                            // ask MySQL to EXPLAIN the query\n                            \$explain_resource = mysqli_query(\$this->connection, 'EXPLAIN ' . \$sql);",
    ],

    'a NULL among the columns becomes the SQL keyword' => [
        'filter' => 'testANullAmongTheColumnsBecomesTheSqlKeyword',
        'from'   => "            if (\$entry === null) {\n                \$result[] = 'NULL';\n                continue;\n            }\n",
        'to'     => '',
    ],

    'dsum() returns FALSE rather than NULL when nothing matches' => [
        'filter' => 'testAggregateMethodsReturnFalseNotNullWhenNothingMatches',
        'from'   => "return \$row['total'] === null ? false : \$row['total'];",
        'to'     => "return \$row['total'];",
    ],

    '485a681 - a cache path that is not a string' => [
        'filter' => 'testAnInvalidCachePathFailsCleanly',
        'from'   => 'if (is_string($this->cache_path) && file_exists($this->cache_path)',
        'to'     => 'if (file_exists($this->cache_path)',
    ],

    'get_tables() steps over the FALSE an unknown database gives' => [
        'filter' => 'testGetTablesReturnsAnEmptyArrayForAnUnknownDatabase',
        'from'   => 'if (is_array($result)) foreach ($result as $tableName) $tables[] = array_pop($tableName);',
        'to'     => 'foreach ($result as $tableName) $tables[] = array_pop($tableName);',
    ],

    'insert_bulk() looks its message up under the right key' => [
        'filter' => 'testInsertBulkReportsValuesThatAreNotAnArray',
        'from'   => "'message'   => \$this->language['data_not_an_array']",
        'to'     => "'message'   => \$this->language['`data_not_an_array']",
    ],

];

$failures = 0;

foreach ($mutations as $name => $mutation) {

    if (strpos($base, $mutation['from']) === false) {
        echo "SKIPPED  $name\n         (the code it reverts is not in the library any more)\n\n";
        $failures++;
        continue;
    }

    file_put_contents($library, str_replace($mutation['from'], $mutation['to'], $base));

    // every path is anchored to this file so the run does not depend on the directory it was started from
    exec(
        escapeshellarg($php) . ' ' . escapeshellarg(__DIR__ . '/../vendor/bin/phpunit') . ' -c ' . escapeshellarg(__DIR__) .
            ' --filter ' . escapeshellarg($mutation['filter']) . ' 2>&1',
        $output,
        $status
    );

    file_put_contents($library, $base);

    $ran = false;

    foreach ($output as $line) if (strpos($line, 'No tests executed') !== false) $ran = true;

    if ($ran) {
        echo "NO TESTS $name\n         (nothing matches the filter \"" . $mutation['filter'] . "\")\n\n";
        $failures++;
    } elseif ($status === 0) {
        echo "MISSED   $name\n         (the suite stays green with the fix reverted)\n\n";
        $failures++;
    } else {
        echo "CAUGHT   $name\n";
    }

    $output = [];

}

echo "\n" . ($failures === 0 ? 'Every fix is guarded.' : $failures . ' of ' . count($mutations) . ' are not.') . "\n";

exit($failures === 0 ? 0 : 1);
