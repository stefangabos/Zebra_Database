<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Test suite for JOIN operations, focusing on column name collisions
 * and edge cases discovered in GitHub issues
 */
class JoinTest extends DatabaseTestCase
{
    protected function setUp(): void {
        parent::setUp();
        $this->connectToDatabase();
        $this->setupJoinTestData();
    }

    private function setupJoinTestData() {
        // Create tables with intentionally colliding column names
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

        // Insert test data
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
     * Test JOIN with colliding column names (id, name, created_at)
     *
     * When two joined tables have a column of the same name, an associative row can only hold one of
     * them and the one read last wins. That is how PHP arrays work rather than anything the library
     * does, and mysqli_fetch_assoc() behaves identically, so there is nothing here to fix - the answer
     * is to alias the columns, which testJoinWithAliasedColumns covers.
     *
     * This test pins that behaviour down so that it is a documented, deliberate limitation rather than
     * a surprise.
     */
    public function testJoinWithCollidingColumnNames() {
        $result = $this->db->query("
            SELECT a.*, b.*
            FROM test_authors a
            INNER JOIN test_books b ON a.id = b.author_id
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
     * Test JOIN with explicit column aliasing to avoid collisions
     */
    public function testJoinWithColumnAliases() {
        $result = $this->db->query("
            SELECT
                a.id as author_id,
                a.name as author_name,
                a.email as author_email,
                a.created_at as author_created_at,
                b.id as book_id,
                b.title as book_title,
                b.name as book_series,
                b.created_at as book_created_at
            FROM test_authors a
            INNER JOIN test_books b ON a.id = b.author_id
            ORDER BY a.id, b.id
        ");

        $this->assertNotFalse($result, "JOIN with aliases should succeed");

        $row = $this->db->fetch_assoc($result);
        $this->assertNotEmpty($row, "Should return results");

        // All aliased columns should be present
        $expected_columns = ['author_id', 'author_name', 'author_email', 'author_created_at',
                            'book_id', 'book_title', 'book_series', 'book_created_at'];

        foreach ($expected_columns as $column) {
            $this->assertArrayHasKey($column, $row, "Should have column: $column");
        }

        // Verify data integrity
        $this->assertNotEquals($row['author_name'],
            $row['book_series'],
            "Author name and book series should be different values"
        );
    }

    /**
     * Test complex JOIN with multiple table and column collisions
     */
    public function testComplexJoinWithMultipleCollisions() {
        // Create another table with more colliding column names
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

        // Add publisher_id to books table
        $this->db->query("ALTER TABLE test_books ADD COLUMN publisher_id INT DEFAULT 1");

        $result = $this->db->query("
            SELECT *
            FROM test_authors a
            INNER JOIN test_books b ON a.id = b.author_id
            INNER JOIN test_publishers p ON b.publisher_id = p.id
            LIMIT 1
        ");

        $this->assertNotFalse($result, "Complex JOIN should succeed");

        $row = $this->db->fetch_assoc($result);
        $this->assertNotEmpty($row, "Should return results from complex JOIN");

        // three tables each contribute a "name", an "email" and an "id", and what comes back holds one of
        // each - the publisher's, since that table is joined last. Counting the keys was what this used to
        // do, which can only ever come out as one and so said nothing at all
        $this->assertSame('Publisher One', $row['name'], "The last table joined is the one whose column survives");
        $this->assertSame('contact@publisherone.com', $row['email']);

        // and the columns the earlier tables contributed are unreachable rather than merged in
        $this->assertNotContains('a.name', array_keys($row));
        $this->assertNotContains('test_authors.name', array_keys($row));

        // Clean up
        $this->db->query("DROP TABLE IF EXISTS test_publishers");
    }

    /**
     * Test LEFT JOIN behavior with NULL values and column collisions
     */
    public function testLeftJoinWithNullsAndCollisions() {
        // Insert an author without books
        $this->db->insert('test_authors', [
            'name' => 'Author Without Books',
            'email' => 'lonely@author.com'
        ]);

        $result = $this->db->query("
            SELECT a.name as author_name, b.name as book_series, b.title
            FROM test_authors a
            LEFT JOIN test_books b ON a.id = b.author_id
            ORDER BY a.id
        ");

        $this->assertNotFalse($result, "LEFT JOIN should succeed");

        $rows = $this->db->fetch_assoc_all($result);
        $this->assertNotEmpty($rows, "Should return results from LEFT JOIN");

        // Find the author without books
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
     * Test UNION operations with potentially colliding columns
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

        // Verify that UNION preserves column structure
        foreach ($rows as $row) {
            $this->assertArrayHasKey('id', $row);
            $this->assertArrayHasKey('name', $row);
            $this->assertArrayHasKey('email', $row);
            $this->assertCount(3, $row, "Each UNION row should have exactly 3 columns");
        }
    }

    /**
     * Test self-JOIN with column name collisions
     */
    public function testSelfJoinWithCollisions() {
        // Create a table with self-referencing relationships
        $this->db->query("
            CREATE TABLE IF NOT EXISTS test_categories2 (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                parent_id INT NULL,
                level INT DEFAULT 1,
                FOREIGN KEY (parent_id) REFERENCES test_categories2(id)
            ) ENGINE=InnoDB
        ");

        // Insert hierarchical data
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

        // Clean up
        $this->db->query("DROP TABLE IF EXISTS test_categories2");
    }

    /**
     * Test JOIN with potentially problematic column names (reserved words, special chars)
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
                a.id as author_id,
                a.name as author_name,
                s.`order`,
                s.`user-name`,
                s.`123numeric`,
                s.`select`
            FROM test_authors a
            CROSS JOIN test_special_columns s
            LIMIT 1
        ");

        $this->assertNotFalse($result, "JOIN with problematic column names should succeed");

        $row = $this->db->fetch_assoc($result);
        $this->assertNotEmpty($row, "Should return results");

        // Verify all problematic columns are accessible
        $this->assertArrayHasKey('order', $row);
        $this->assertArrayHasKey('user-name', $row);
        $this->assertArrayHasKey('123numeric', $row);
        $this->assertArrayHasKey('select', $row);

        // Clean up
        $this->db->query("DROP TABLE IF EXISTS test_special_columns");
    }

    /**
     * Test that fetch_assoc properly handles duplicate column names in JOINs
     */
    public function testFetchAssocWithDuplicateJoinColumns() {
        $result = $this->db->query("
            SELECT a.name, b.name, a.id, b.id
            FROM test_authors a
            INNER JOIN test_books b ON a.id = b.author_id
            LIMIT 1
        ");

        $this->assertNotFalse($result, "Query with duplicate column names should succeed");

        // Test both fetch methods
        $assoc_row = $this->db->fetch_assoc($result);
        $this->assertNotEmpty($assoc_row, "fetch_assoc should return data");

        // Reset result pointer to test fetch_assoc_all
        $this->db->query("
            SELECT a.name, b.name, a.id, b.id
            FROM test_authors a
            INNER JOIN test_books b ON a.id = b.author_id
            LIMIT 1
        ");

        $all_rows = $this->db->fetch_assoc_all();
        $this->assertNotEmpty($all_rows, "fetch_assoc_all should return data");
        $this->assertCount(1, $all_rows, "Should return exactly 1 row");

        // four columns were selected - a.name, b.name, a.id and b.id - and two keys come back, because the
        // second of each pair overwrites the first. Comparing the key count against the unique key count
        // was the old assertion here, and one array cannot have more keys than it has unique keys
        $this->assertSame(['name', 'id'], array_keys($assoc_row), "Four columns collapse into two keys");
        $this->assertSame($all_rows[0], $assoc_row, "Both fetch methods lose the same half");
    }
}
