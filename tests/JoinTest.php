<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Joins, and what becomes of a column name that two of the joined tables both have - which is a property
 * of PHP arrays rather than of the library, and is why aliasing is the answer to all of it.
 */
class JoinTest extends DatabaseTestCase
{
    protected function setUp(): void {
        parent::setUp();
        $this->connectToDatabase();
        $this->setupJoinTestData();
    }

    private function setupJoinTestData() {
        // two tables that deliberately share the names id, name and created_at
        $this->db->query("
            CREATE TABLE IF NOT EXISTS test_authors (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS test_books (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(200) NOT NULL,
                author_id INT,
                name VARCHAR(100) NOT NULL COMMENT 'Book series name - conflicts with author.name',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (author_id) REFERENCES test_authors(id)
            ) ENGINE=InnoDB
        ");

        $this->db->insert('test_authors', [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        $this->db->insert('test_authors', [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com'
        ]);

        $author_id_1 = $this->db->insert_id();

        $this->db->insert('test_books', [
            'title' => 'Test Book 1',
            'author_id' => 1,
            'name' => 'Mystery Series'
        ]);

        $this->db->insert('test_books', [
            'title' => 'Test Book 2',
            'author_id' => $author_id_1,
            'name' => 'Science Fiction Series'
        ]);
    }

    protected function tearDown(): void {
        if ($this->db) {
            $this->db->query("DROP TABLE IF EXISTS test_books");
            $this->db->query("DROP TABLE IF EXISTS test_authors");
        }
        parent::tearDown();
    }

    /**
     * When two joined tables have a column of the same name, an associative row can only hold one of them
     * and the one read last wins. That is how PHP arrays work rather than anything the library does, and
     * mysqli_fetch_assoc() behaves identically - so this pins a deliberate limitation rather than a bug,
     * and the answer to it is the aliasing the next test covers
     */
    public function testJoinWithCollidingColumnNames() {
        $result = $this->db->query("
            SELECT authors.*, books.*
            FROM test_authors authors
            INNER JOIN test_books books ON authors.id = books.author_id
        ");

        $this->assertNotFalse($result, "JOIN query should succeed");

        $row = $this->db->fetch_assoc($result);
        $this->assertNotEmpty($row, "Should return at least one row");

        $columns = array_keys($row);

        // there is exactly one "name" key, not two - an array cannot hold the same key twice
        $this->assertContains('name', $columns, "Should have a 'name' column");
        $this->assertCount(1, array_keys($columns, 'name'), "A row can only ever hold one 'name' key");

        // and the one that survives is the one selected last, which here is the book's
        $this->assertContains($row['name'], ['Mystery Series', 'Science Fiction Series'], "The last selected column wins");

        // the author's name is unreachable this way - aliasing is what makes both available
        $this->assertNotContains($row['name'], ['John Doe', 'Jane Smith'], "The earlier column is not reachable without an alias");
    }

    /**
     * Aliasing every column is what makes both sides of a collision reachable
     */
    public function testJoinWithColumnAliases() {
        $result = $this->db->query("
            SELECT
                authors.id as author_id,
                authors.name as author_name,
                authors.email as author_email,
                authors.created_at as author_created_at,
                books.id as book_id,
                books.title as book_title,
                books.name as book_series,
                books.created_at as book_created_at
            FROM test_authors authors
            INNER JOIN test_books books ON authors.id = books.author_id
            ORDER BY authors.id, books.id
        ");

        $this->assertNotFalse($result, "JOIN with aliases should succeed");

        $row = $this->db->fetch_assoc($result);
        $this->assertNotEmpty($row, "Should return results");

        $expected_columns = ['author_id', 'author_name', 'author_email', 'author_created_at',
                            'book_id', 'book_title', 'book_series', 'book_created_at'];

        foreach ($expected_columns as $column) {
            $this->assertArrayHasKey($column, $row, "Should have column: $column");
        }

        $this->assertNotEquals($row['author_name'],
            $row['book_series'],
            "Author name and book series should be different values"
        );
    }

    /**
     * Three tables, each contributing an id, a name and an email, and one of each surviving
     */
    public function testComplexJoinWithMultipleCollisions() {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS test_publishers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB
        ");

        $this->db->insert('test_publishers', [
            'name' => 'Publisher One',
            'email' => 'contact@publisherone.com'
        ]);

        $this->db->query("ALTER TABLE test_books ADD COLUMN publisher_id INT DEFAULT 1");

        $result = $this->db->query("
            SELECT *
            FROM test_authors authors
            INNER JOIN test_books books ON authors.id = books.author_id
            INNER JOIN test_publishers publishers ON books.publisher_id = publishers.id
            LIMIT 1
        ");

        $this->assertNotFalse($result, "Complex JOIN should succeed");

        $row = $this->db->fetch_assoc($result);
        $this->assertNotEmpty($row, "Should return results from complex JOIN");

        // what comes back holds one of each - the publisher's, that table being the one joined last
        $this->assertSame('Publisher One', $row['name'], "The last table joined is the one whose column survives");
        $this->assertSame('contact@publisherone.com', $row['email']);

        // and the columns the earlier tables contributed are unreachable rather than merged in
        $this->assertNotContains('authors.name', array_keys($row));
        $this->assertNotContains('test_authors.name', array_keys($row));

        $this->db->query("DROP TABLE IF EXISTS test_publishers");
    }

    /**
     * The unmatched side of a LEFT JOIN comes back as NULLs, aliases and all
     */
    public function testLeftJoinWithNullsAndCollisions() {
        // an author with no books, so that there is a row with nothing on the other side of the join
        $this->db->insert('test_authors', [
            'name' => 'Author Without Books',
            'email' => 'lonely@author.com'
        ]);

        $result = $this->db->query("
            SELECT authors.name as author_name, books.name as book_series, books.title
            FROM test_authors authors
            LEFT JOIN test_books books ON authors.id = books.author_id
            ORDER BY authors.id
        ");

        $this->assertNotFalse($result, "LEFT JOIN should succeed");

        $rows = $this->db->fetch_assoc_all($result);
        $this->assertNotEmpty($rows, "Should return results from LEFT JOIN");

        $lonely_author_found = false;
        foreach ($rows as $row) {
            if ($row['author_name'] === 'Author Without Books') {
                $lonely_author_found = true;
                $this->assertNull($row['book_series'], "Book series should be NULL for author without books");
                $this->assertNull($row['title'], "Book title should be NULL for author without books");
            }
        }

        $this->assertTrue($lonely_author_found, "Should find author without books in LEFT JOIN results");
    }

    /**
     * A UNION's rows take the shape of the first SELECT, whatever the tables underneath are called
     */
    public function testUnionWithCollidingColumns() {
        $result = $this->db->query("
            SELECT id, name, email FROM test_authors
            UNION
            SELECT id, name, 'no-email' as email FROM test_books
            ORDER BY id
        ");

        $this->assertNotFalse($result, "UNION query should succeed");

        $rows = $this->db->fetch_assoc_all($result);
        $this->assertNotEmpty($rows, "UNION should return results");

        foreach ($rows as $row) {
            $this->assertArrayHasKey('id', $row);
            $this->assertArrayHasKey('name', $row);
            $this->assertArrayHasKey('email', $row);
            $this->assertCount(3, $row, "Each UNION row should have exactly 3 columns");
        }
    }

    /**
     * A table joined to itself collides with itself in every column, so aliases are the only way to read it
     */
    public function testSelfJoinWithCollisions() {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS test_categories2 (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                parent_id INT NULL,
                level INT DEFAULT 1,
                FOREIGN KEY (parent_id) REFERENCES test_categories2(id)
            ) ENGINE=InnoDB
        ");

        $this->db->insert('test_categories2', ['name' => 'Root Category', 'parent_id' => null, 'level' => 1]);
        $root_id = $this->db->insert_id();

        $this->db->insert('test_categories2', ['name' => 'Child Category', 'parent_id' => $root_id, 'level' => 2]);

        $result = $this->db->query("
            SELECT
                parent.id as parent_id,
                parent.name as parent_name,
                child.id as child_id,
                child.name as child_name,
                child.level as child_level
            FROM test_categories2 parent
            INNER JOIN test_categories2 child ON parent.id = child.parent_id
        ");

        $this->assertNotFalse($result, "Self-JOIN should succeed");

        $row = $this->db->fetch_assoc($result);
        $this->assertNotEmpty($row, "Self-JOIN should return results");

        $this->assertEquals('Root Category', $row['parent_name']);
        $this->assertEquals('Child Category', $row['child_name']);
        $this->assertNotEquals($row['parent_id'], $row['child_id'], "Parent and child IDs should be different");

        $this->db->query("DROP TABLE IF EXISTS test_categories2");
    }

    /**
     * Column names that have to be quoted to be named at all - a reserved word, a hyphen, a leading digit -
     * come back under exactly those names
     */
    public function testJoinWithProblematicColumnNames() {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS test_special_columns (
                id INT AUTO_INCREMENT PRIMARY KEY,
                `order` INT NOT NULL COMMENT 'Reserved word',
                `user-name` VARCHAR(100) COMMENT 'Hyphenated name',
                `123numeric` VARCHAR(100) COMMENT 'Starts with number',
                `select` VARCHAR(100) COMMENT 'SQL keyword'
            ) ENGINE=InnoDB
        ");

        $this->db->insert('test_special_columns', [
            'order' => 1,
            'user-name' => 'test-user',
            '123numeric' => 'numeric-value',
            'select' => 'select-value'
        ]);

        $result = $this->db->query("
            SELECT
                authors.id as author_id,
                authors.name as author_name,
                special_columns.`order`,
                special_columns.`user-name`,
                special_columns.`123numeric`,
                special_columns.`select`
            FROM test_authors authors
            CROSS JOIN test_special_columns special_columns
            LIMIT 1
        ");

        $this->assertNotFalse($result, "JOIN with problematic column names should succeed");

        $row = $this->db->fetch_assoc($result);
        $this->assertNotEmpty($row, "Should return results");

        $this->assertArrayHasKey('order', $row);
        $this->assertArrayHasKey('user-name', $row);
        $this->assertArrayHasKey('123numeric', $row);
        $this->assertArrayHasKey('select', $row);

        $this->db->query("DROP TABLE IF EXISTS test_special_columns");
    }

    /**
     * Both fetchers lose the same half of a collision, since both are reading the same rows
     */
    public function testFetchAssocWithDuplicateJoinColumns() {
        $result = $this->db->query("
            SELECT authors.name, books.name, authors.id, books.id
            FROM test_authors authors
            INNER JOIN test_books books ON authors.id = books.author_id
            LIMIT 1
        ");

        $this->assertNotFalse($result, "Query with duplicate column names should succeed");

        $assoc_row = $this->db->fetch_assoc($result);
        $this->assertNotEmpty($assoc_row, "fetch_assoc should return data");

        // the same query again, the row pointer having moved past the only row there is
        $this->db->query("
            SELECT authors.name, books.name, authors.id, books.id
            FROM test_authors authors
            INNER JOIN test_books books ON authors.id = books.author_id
            LIMIT 1
        ");

        $all_rows = $this->db->fetch_assoc_all();
        $this->assertNotEmpty($all_rows, "fetch_assoc_all should return data");
        $this->assertCount(1, $all_rows, "Should return exactly 1 row");

        // four columns were selected - the name and the id of each table - and two keys come back, the
        // second of each pair having overwritten the first
        $this->assertSame(['name', 'id'], array_keys($assoc_row), "Four columns collapse into two keys");
        $this->assertSame($all_rows[0], $assoc_row, "Both fetch methods lose the same half");
    }
}
