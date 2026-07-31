<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * parse_file(), which runs the statements it finds in a MySQL dump - what it recognises as a statement,
 * what it steps over, and the two limitations that follow from recognising them line by line.
 */
class ParseFileTest extends DatabaseTestCase
{
    private $files = [];

    protected function setUp(): void {
        parent::setUp();
        $this->connectToDatabase();
        $this->db->query('DROP TABLE IF EXISTS test_parse_file');
        $this->db->query('CREATE TABLE test_parse_file (id INT AUTO_INCREMENT PRIMARY KEY, label VARCHAR(100))');
    }

    protected function tearDown(): void {
        foreach ($this->files as $file) if (file_exists($file)) unlink($file);
        $this->files = [];
        if ($this->db) $this->db->query('DROP TABLE IF EXISTS test_parse_file');
        parent::tearDown();
    }

    /**
     * Writes the given SQL to a throw-away file and returns its path
     */
    private function dumpFile($sql) {
        $path = getTempPath('uploads') . '/parse_file_' . count($this->files) . '.sql';
        file_put_contents($path, $sql);
        $this->files[] = $path;
        return $path;
    }

    private function labels() {
        $this->db->query('SELECT label FROM test_parse_file ORDER BY id');
        return array_column($this->db->fetch_assoc_all(), 'label');
    }

    // WHAT GETS RUN

    public function testParseFileRunsASingleStatement() {
        $path = $this->dumpFile("INSERT INTO test_parse_file (label) VALUES ('one');\n");

        $this->assertTrue($this->db->parse_file($path));
        $this->assertSame(['one'], $this->labels());
    }

    public function testParseFileRunsSeveralStatements() {
        $path = $this->dumpFile(
            "INSERT INTO test_parse_file (label) VALUES ('one');\n" .
            "INSERT INTO test_parse_file (label) VALUES ('two');\n" .
            "INSERT INTO test_parse_file (label) VALUES ('three');\n"
        );

        $this->assertTrue($this->db->parse_file($path));
        $this->assertSame(['one', 'two', 'three'], $this->labels());
    }

    public function testParseFileRunsAStatementThatSpansSeveralLines() {
        $path = $this->dumpFile(
            "INSERT INTO test_parse_file\n" .
            "    (label)\n" .
            "VALUES\n" .
            "    ('multiline');\n"
        );

        $this->assertTrue($this->db->parse_file($path));
        $this->assertSame(['multiline'], $this->labels());
    }

    public function testParseFileHandlesAFileWithoutATrailingNewline() {
        $path = $this->dumpFile("INSERT INTO test_parse_file (label) VALUES ('no newline');");

        $this->assertTrue($this->db->parse_file($path));
        $this->assertSame(['no newline'], $this->labels());
    }

    public function testParseFileHandlesWindowsLineEndings() {
        $path = $this->dumpFile(
            "INSERT INTO test_parse_file (label) VALUES ('crlf one');\r\n" .
            "INSERT INTO test_parse_file (label) VALUES ('crlf two');\r\n"
        );

        $this->assertTrue($this->db->parse_file($path));
        $this->assertSame(['crlf one', 'crlf two'], $this->labels());
    }

    // WHAT GETS SKIPPED

    public function testParseFileSkipsDoubleDashComments() {
        $path = $this->dumpFile(
            "-- this is a comment and must not be run\n" .
            "INSERT INTO test_parse_file (label) VALUES ('kept');\n" .
            "-- and so is this one\n"
        );

        $this->assertTrue($this->db->parse_file($path));
        $this->assertSame(['kept'], $this->labels());
    }

    public function testParseFileSkipsHashComments() {
        $path = $this->dumpFile(
            "# a hash comment\n" .
            "INSERT INTO test_parse_file (label) VALUES ('kept');\n"
        );

        $this->assertTrue($this->db->parse_file($path));
        $this->assertSame(['kept'], $this->labels());
    }

    public function testParseFileSkipsBlankAndWhitespaceOnlyLines() {
        $path = $this->dumpFile(
            "\n" .
            "   \n" .
            "\t\n" .
            "INSERT INTO test_parse_file (label) VALUES ('kept');\n" .
            "\n"
        );

        $this->assertTrue($this->db->parse_file($path));
        $this->assertSame(['kept'], $this->labels());
    }

    public function testParseFileSkipsIndentedComments() {
        $path = $this->dumpFile(
            "    -- an indented comment\n" .
            "INSERT INTO test_parse_file (label) VALUES ('kept');\n"
        );

        $this->assertTrue($this->db->parse_file($path));
        $this->assertSame(['kept'], $this->labels());
    }

    public function testParseFileHandlesAFileThatIsNothingButComments() {
        $path = $this->dumpFile("-- nothing to see here\n# nor here\n");

        $this->assertTrue($this->db->parse_file($path));
        $this->assertSame([], $this->labels());
    }

    // DOCUMENTED LIMITATIONS
    // (these describe how the parser behaves today, so that a change in behaviour is noticed)

    /**
     * Statements are recognised by a line ending in a semicolon, so a final statement without one is
     * silently dropped - the method still reports success
     */
    public function testParseFileIgnoresATrailingStatementWithoutASemicolon() {
        $path = $this->dumpFile(
            "INSERT INTO test_parse_file (label) VALUES ('with semicolon');\n" .
            "INSERT INTO test_parse_file (label) VALUES ('without semicolon')\n"
        );

        $this->assertTrue($this->db->parse_file($path));
        $this->assertSame(['with semicolon'], $this->labels());
    }

    /**
     * The same rule means a semicolon that only happens to be at the end of a line inside a string
     * literal cuts the statement short
     */
    public function testParseFileCutsAStatementAtASemicolonInsideAStringLiteral() {
        $path = $this->dumpFile(
            "INSERT INTO test_parse_file (label) VALUES ('a;\n" .
            "b');\n"
        );

        $this->db->halt_on_errors = false;
        $this->db->parse_file($path);

        $this->assertSame([], $this->labels(), 'Neither half is a valid statement, so nothing is inserted');
    }

    /**
     * file() returns an empty array for an empty file, which the method cannot tell apart from a file
     * it could not open - so an empty dump is reported as a failure
     */
    public function testParseFileReportsAnEmptyFileAsAFailure() {
        $path = $this->dumpFile('');

        $this->assertFalse($this->db->parse_file($path));
    }

    // ERROR HANDLING

    public function testParseFileReturnsFalseForAFileThatDoesNotExist() {
        $this->db->halt_on_errors = false;

        $this->assertFalse(@$this->db->parse_file(getTempPath('uploads') . '/no_such_file.sql'));
    }

    public function testParseFileLogsAnErrorForAFileThatDoesNotExist() {
        $db = $this->probe();

        @$db->parse_file(getTempPath('uploads') . '/no_such_file.sql');

        $errors = $db->errors();

        $this->assertNotEmpty($errors, 'A file that cannot be opened has to be reported');

    }

    /**
     * A statement that fails does not stop the ones that follow it - parse_file() does not run the
     * statements in a transaction and does not check the result of each query
     */
    public function testParseFileKeepsGoingAfterAFailingStatement() {
        $this->db->halt_on_errors = false;

        $path = $this->dumpFile(
            "INSERT INTO test_parse_file (label) VALUES ('before');\n" .
            "INSERT INTO no_such_table (label) VALUES ('boom');\n" .
            "INSERT INTO test_parse_file (label) VALUES ('after');\n"
        );

        $this->assertTrue($this->db->parse_file($path));
        $this->assertSame(['before', 'after'], $this->labels());
    }
}
