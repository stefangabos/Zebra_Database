<?php

/**
 * Gives the tests a way of reading what the library recorded about the queries it ran - which
 * statements were sent, which errors were logged and whether a result came from the cache -
 * without reaching into the object with reflection.
 *
 * The debug_info property is only populated while debugging is enabled, so an instance of this
 * class has to be created with $debug = TRUE.
 */
class DatabaseProbe extends Zebra_Database
{
    /**
     * Whether the query that ran last was served from the cache
     */
    public function lastFromCache() {
        if (empty($this->debug_info['successful-queries'])) return null;
        $last = end($this->debug_info['successful-queries']);
        return $last['from_cache'];
    }

    /**
     * The statements the library actually sent to the server, in the order it sent them
     */
    public function queries() {
        if (empty($this->debug_info['successful-queries'])) return [];
        return array_map(function($entry) {
            return preg_replace('/\s+/', ' ', trim($entry['query']));
        }, $this->debug_info['successful-queries']);
    }

    /**
     * The errors the library logged
     */
    public function errors() {
        return isset($this->debug_info['errors']) ? $this->debug_info['errors'] : [];
    }

    /**
     * The rows MySQL returned when asked to EXPLAIN the query that ran last
     */
    public function explainOfLastQuery() {
        return $this->consoleEntryForLastQuery('explain');
    }

    /**
     * What the debugging console recorded about the query that ran last
     */
    public function consoleEntryForLastQuery($key = null) {
        if (empty($this->debug_info['successful-queries'])) return null;
        $last = end($this->debug_info['successful-queries']);
        if ($key === null) return $last;
        return isset($last[$key]) ? $last[$key] : null;
    }

    /**
     * The debugging console is printed by a shutdown function that reads the debug flag when it runs,
     * so a probe has to be shut down through this in order to keep the console out of the test output
     */
    public function shutdown() {
        $this->debug = false;
        $this->close();
    }
}
