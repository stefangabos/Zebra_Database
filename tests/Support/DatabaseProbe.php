<?php

/**
 * A subclass of the library that lets the tests read what it recorded about the queries it ran - which
 * statements were sent, which errors were logged and whether a result came from the cache.
 *
 * Reaching into an object with reflection from inside a test spreads knowledge of the library's internals
 * across the whole suite and breaks noisily the moment a property is renamed. Doing it here instead keeps
 * that in one place, and gives the reader a list of everything the tests rely on that is not public API.
 *
 * The debug_info property is only filled in while debugging is enabled, so a probe has to be created with
 * $debug = TRUE - which is what DatabaseTestCase::probe() does.
 */
class DatabaseProbe extends Zebra_Database
{
    /**
     * Whether the query that ran last was served from the cache
     *
     * @return  bool|null   null when no query has run yet
     */
    public function lastFromCache() {

        if (empty($this->debug_info['successful-queries'])) return null;

        $last = end($this->debug_info['successful-queries']);

        return $last['from_cache'];

    }

    /**
     * The statements the library actually sent to the server, in the order it sent them
     *
     * @return  array<string>
     */
    public function queries() {

        if (empty($this->debug_info['successful-queries'])) return [];

        return array_map(function($entry) {
            return preg_replace('/\s+/', ' ', trim($entry['query']));
        }, $this->debug_info['successful-queries']);

    }

    /**
     * The errors the library logged
     *
     * @return  array<mixed>
     */
    public function errors() {

        return isset($this->debug_info['errors']) ? $this->debug_info['errors'] : [];

    }

    /**
     * The rows MySQL returned when asked to EXPLAIN the query that ran last
     *
     * @return  mixed
     */
    public function explainOfLastQuery() {

        return $this->consoleEntryForLastQuery('explain');

    }

    /**
     * What the debugging console recorded about the query that ran last
     *
     * @param   string|null $key    one entry of the record, or the whole of it when nothing is asked for
     *
     * @return  mixed
     */
    public function consoleEntryForLastQuery($key = null) {

        if (empty($this->debug_info['successful-queries'])) return null;

        $last = end($this->debug_info['successful-queries']);

        if ($key === null) return $last;

        return isset($last[$key]) ? $last[$key] : null;

    }

    /**
     * Shuts the probe down.
     *
     * A probe runs with debugging on, and the console is printed by a shutdown function that reads that
     * flag when it runs - so a probe left behind by a failing assertion would spill its whole console into
     * the test output. Turning debugging off before closing avoids that.
     *
     * Safe to call more than once: tearDown() calls it for every probe a test made, whether or not the
     * test called it itself.
     *
     * @return  void
     */
    public function shutdown() {

        $this->debug = false;
        $this->close();

    }
}
