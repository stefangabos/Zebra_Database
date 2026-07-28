<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Test suite for Zebra_Database utility methods (dcount, dlookup, dmax, dsum)
 */
class UtilityMethodsTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->connectToDatabase();
        $this->insertTestData();
    }

    // HOW THE COLUMN AND TABLE ARGUMENTS ARE TREATED
    // (all four of these methods pass the column into the query as it is while enclosing the table in
    // grave accents - the docblocks say so, and these pin what that means either way)

    public function testDcountTakesAnExpressionAsTheColumn() {
        $this->assertEquals(3, $this->db->dcount('*', 'test_users'));
        $this->assertEquals(3, $this->db->dcount('DISTINCT age', 'test_users'));
    }

    public function testDmaxTakesAnExpressionAsTheColumn() {
        // John is 30, Jane 25, Bob 35
        $this->assertEquals(70, $this->db->dmax('age * 2', 'test_users'));
    }

    public function testDsumTakesAnExpressionAsTheColumn() {
        $this->assertEquals(180, $this->db->dsum('age * 2', 'test_users'));
    }

    public function testDlookupTakesAMysqlFunctionAsTheColumn() {
        $value = $this->db->dlookup('CONCAT(name, age)', 'test_users|age DESC');

        $this->assertSame('Bob Johnson35', $value);
    }

    /**
     * The other side of the same coin - the column is not enclosed in grave accents, so one that is a
     * reserved word only works when the caller encloses it
     */
    public function testAColumnThatIsAReservedWordHasToBeQuotedByTheCaller() {
        $this->db->halt_on_errors = false;
        $this->db->query('DROP TABLE IF EXISTS `order`');
        $this->db->query('CREATE TABLE `order` (`order` INT)');
        $this->db->query('INSERT INTO `order` (`order`) VALUES (5), (7)');

        $unquoted = $this->db->dmax('order', 'order');
        $quoted = $this->db->dmax('`order`', 'order');

        $this->db->query('DROP TABLE `order`');

        $this->assertFalse($unquoted, 'Without the grave accents this is a syntax error');
        $this->assertEquals(7, $quoted);
    }

    /**
     * The table, on the other hand, is enclosed for you - which is why "order" works above as a table name
     * without the caller doing anything
     */
    public function testTheTableNameIsEnclosedInGraveAccents() {
        $this->db->query('DROP TABLE IF EXISTS `order`');
        $this->db->query('CREATE TABLE `order` (id INT)');
        $this->db->query('INSERT INTO `order` (id) VALUES (1), (2)');

        $count = $this->db->dcount('*', 'order');

        $this->db->query('DROP TABLE `order`');

        $this->assertEquals(2, $count);
    }

    // DCOUNT TESTS

    public function testDcountAllRecords() {
        $count = $this->db->dcount('*', 'test_users');

        $this->assertEquals(3, $count); // We inserted 3 users in insertTestData
    }

    public function testDcountSpecificColumn() {
        $count = $this->db->dcount('id', 'test_users');

        $this->assertEquals(3, $count);
    }

    public function testDcountWithWhereClause() {
        $count = $this->db->dcount('*', 'test_users', 'is_active = ?', [1]);

        $this->assertEquals(2, $count); // John and Jane are active
    }

    public function testDcountWithComplexWhereClause() {
        $count = $this->db->dcount('*', 'test_users', 'age > ? AND is_active = ?', [25, 1]);

        $this->assertEquals(1, $count); // Only John (30, active)
    }

    public function testDcountWithNoMatches() {
        $count = $this->db->dcount('*', 'test_users', 'name = ?', ['Nonexistent User']);

        $this->assertEquals(0, $count);
    }

    public function testDcountWithNullParameter() {
        // Insert a user with null email
        $this->db->insert('test_users', [
            'name' => 'Null Email User',
            'email' => null,
            'age' => 40
        ]);

        $count = $this->db->dcount('*', 'test_users', 'email IS NULL');

        $this->assertEquals(1, $count);
    }

    public function testDcountWithArrayParameter() {
        $count = $this->db->dcount('*', 'test_users', 'name IN (?)', [['John Doe', 'Jane Smith']]);

        $this->assertEquals(2, $count);
    }

    public function testDcountWithCache() {
        // Create cache directory
        $cache_dir = '/tmp/zebra_test_cache';
        if (!is_dir($cache_dir)) {
            mkdir($cache_dir, 0777, true);
        }

        $this->db->cache_path = $cache_dir;
        $this->db->caching_method = 'disk';

        // First call - should cache result
        $count1 = $this->db->dcount('*', 'test_users', 'is_active = ?', [1], 3600);
        $this->assertEquals(2, $count1);

        // Second call - should come from cache
        $count2 = $this->db->dcount('*', 'test_users', 'is_active = ?', [1], 3600);
        $this->assertEquals(2, $count2);

        // Clean up
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '/*');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($cache_dir);
        }
    }

    public function testDcountInvalidTable() {
        $count = $this->db->dcount('*', 'nonexistent_table');

        $this->assertFalse($count);
    }

    // DLOOKUP TESTS

    public function testDlookupSingleValue() {
        $name = $this->db->dlookup('name', 'test_users', 'email = ?', ['john@example.com']);

        $this->assertEquals('John Doe', $name);
    }

    public function testDlookupMultipleColumns() {
        $result = $this->db->dlookup('name, age', 'test_users', 'email = ?', ['john@example.com']);

        $this->assertIsArray($result);
        $this->assertEquals('John Doe', $result['name']);
        $this->assertEquals(30, (int)$result['age']);
    }

    public function testDlookupWithComplexWhereClause() {
        $email = $this->db->dlookup('email', 'test_users', 'age > ? AND is_active = ?', [25, 1]);

        // Should return John's email (he's 30 and active)
        $this->assertEquals('john@example.com', $email);
    }

    public function testDlookupWithNoMatches() {
        $result = $this->db->dlookup('name', 'test_users', 'name = ?', ['Nonexistent User']);

        $this->assertFalse($result);
    }

    public function testDlookupWithNullValue() {
        // Insert user with null email
        $this->db->insert('test_users', [
            'name' => 'Null Email User',
            'email' => null,
            'age' => 40
        ]);

        $name = $this->db->dlookup('name', 'test_users', 'email IS NULL');

        $this->assertEquals('Null Email User', $name);
    }

    public function testDlookupWithArrayParameter() {
        $name = $this->db->dlookup('name', 'test_users|name', 'name IN (?)', [['John Doe', 'Jane Smith']]);

        // Should return the first one alphabetically
        $this->assertEquals('Jane Smith', $name);
    }

    public function testDlookupNumericValue() {
        $age = $this->db->dlookup('age', 'test_users', 'name = ?', ['John Doe']);

        $this->assertEquals(30, (int)$age);
    }

    public function testDlookupDecimalValue() {
        $score = $this->db->dlookup('score', 'test_users', 'name = ?', ['Jane Smith']);

        $this->assertEquals(92.75, (float)$score);
    }

    public function testDlookupWithCache() {
        $cache_dir = '/tmp/zebra_test_cache';
        if (!is_dir($cache_dir)) {
            mkdir($cache_dir, 0777, true);
        }

        $this->db->cache_path = $cache_dir;
        $this->db->caching_method = 'disk';

        $name1 = $this->db->dlookup('name', 'test_users', 'email = ?', ['john@example.com'], 3600);
        $name2 = $this->db->dlookup('name', 'test_users', 'email = ?', ['john@example.com'], 3600);

        $this->assertEquals($name1, $name2);
        $this->assertEquals('John Doe', $name1);

        // Clean up
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '/*');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($cache_dir);
        }
    }

    public function testDlookupInvalidTable() {
        $result = $this->db->dlookup('name', 'nonexistent_table', 'id = ?', [1]);

        $this->assertFalse($result);
    }

    // DMAX TESTS

    public function testDmaxAllRecords() {
        $max_age = $this->db->dmax('age', 'test_users');

        $this->assertEquals(35, (int)$max_age); // Bob's age
    }

    public function testDmaxWithWhereClause() {
        $max_age = $this->db->dmax('age', 'test_users', 'is_active = ?', [1]);

        $this->assertEquals(30, (int)$max_age); // John's age (highest among active users)
    }

    public function testDmaxDecimalColumn() {
        $max_score = $this->db->dmax('score', 'test_users');

        $this->assertEquals(92.75, (float)$max_score); // Jane's score
    }

    public function testDmaxWithComplexWhereClause() {
        $max_score = $this->db->dmax('score', 'test_users', 'age > ? AND is_active = ?', [25, 1]);

        $this->assertEquals(85.50, (float)$max_score); // John's score
    }

    public function testDmaxWithNoMatches() {
        $result = $this->db->dmax('age', 'test_users', 'name = ?', ['Nonexistent User']);

        $this->assertFalse($result);
    }

    public function testDmaxWithArrayParameter() {
        $max_age = $this->db->dmax('age', 'test_users', 'name IN (?)', [['John Doe', 'Jane Smith']]);

        $this->assertEquals(30, (int)$max_age); // John is older than Jane
    }

    public function testDmaxWithCache() {
        $cache_dir = '/tmp/zebra_test_cache';
        if (!is_dir($cache_dir)) {
            mkdir($cache_dir, 0777, true);
        }

        $this->db->cache_path = $cache_dir;
        $this->db->caching_method = 'disk';

        $max1 = $this->db->dmax('age', 'test_users', '', '', 3600);
        $max2 = $this->db->dmax('age', 'test_users', '', '', 3600);

        $this->assertEquals($max1, $max2);
        $this->assertEquals(35, (int)$max1);

        // Clean up
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '/*');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($cache_dir);
        }
    }

    public function testDmaxInvalidTable() {
        $result = $this->db->dmax('age', 'nonexistent_table');

        $this->assertFalse($result);
    }

    public function testDmaxInvalidColumn() {
        $result = $this->db->dmax('nonexistent_column', 'test_users');

        $this->assertFalse($result);
    }

    // DSUM TESTS

    public function testDsumAllRecords() {
        $sum_age = $this->db->dsum('age', 'test_users');

        $this->assertEquals(90, (int)$sum_age); // 30 + 25 + 35
    }

    public function testDsumWithWhereClause() {
        $sum_age = $this->db->dsum('age', 'test_users', 'is_active = ?', [1]);

        $this->assertEquals(55, (int)$sum_age); // 30 + 25 (John and Jane)
    }

    public function testDsumDecimalColumn() {
        $sum_score = $this->db->dsum('score', 'test_users');

        $this->assertEquals(256.50, (float)$sum_score); // 85.50 + 92.75 + 78.25
    }

    public function testDsumWithComplexWhereClause() {
        $sum_score = $this->db->dsum('score', 'test_users', 'age > ? AND is_active = ?', [25, 1]);

        $this->assertEquals(85.50, (float)$sum_score); // Only John's score
    }

    public function testDsumWithNoMatches() {
        $result = $this->db->dsum('age', 'test_users', 'name = ?', ['Nonexistent User']);

        $this->assertEquals(0, (int)$result); // SUM returns 0 for no matches
    }

    public function testDsumWithArrayParameter() {
        $sum_age = $this->db->dsum('age', 'test_users', 'name IN (?)', [['John Doe', 'Jane Smith']]);

        $this->assertEquals(55, (int)$sum_age); // 30 + 25
    }

    public function testDsumWithNullValues() {
        // Insert user with null score
        $this->db->insert('test_users', [
            'name' => 'Null Score User',
            'email' => 'nullscore@example.com',
            'age' => 40,
            'score' => null
        ]);

        $sum_score = $this->db->dsum('score', 'test_users');

        // SUM should ignore NULL values
        $this->assertEquals(256.50, (float)$sum_score); // Same as before
    }

    public function testDsumWithCache() {
        $cache_dir = '/tmp/zebra_test_cache';
        if (!is_dir($cache_dir)) {
            mkdir($cache_dir, 0777, true);
        }

        $this->db->cache_path = $cache_dir;
        $this->db->caching_method = 'disk';

        $sum1 = $this->db->dsum('age', 'test_users', '', '', 3600);
        $sum2 = $this->db->dsum('age', 'test_users', '', '', 3600);

        $this->assertEquals($sum1, $sum2);
        $this->assertEquals(90, (int)$sum1);

        // Clean up
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '/*');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($cache_dir);
        }
    }

    public function testDsumInvalidTable() {
        $result = $this->db->dsum('age', 'nonexistent_table');

        $this->assertFalse($result);
    }

    public function testDsumInvalidColumn() {
        $result = $this->db->dsum('nonexistent_column', 'test_users');

        $this->assertFalse($result);
    }

    // EDGE CASES AND INTEGRATION TESTS

    public function testUtilityMethodsWithSpecialCharacters() {
        // Insert user with special characters
        $special_name = "O'Brien & Co. <script>alert('test')</script>";
        $this->db->insert('test_users', [
            'name' => $special_name,
            'email' => 'special@example.com',
            'age' => 45,
            'score' => 95.0
        ]);

        $count = $this->db->dcount('*', 'test_users', 'name = ?', [$special_name]);
        $this->assertEquals(1, $count);

        $name = $this->db->dlookup('name', 'test_users', 'email = ?', ['special@example.com']);
        $this->assertEquals($special_name, $name);

        $max_score = $this->db->dmax('score', 'test_users');
        $this->assertEquals(95.0, (float)$max_score);

        $sum_with_special = $this->db->dsum('score', 'test_users', 'name = ?', [$special_name]);
        $this->assertEquals(95.0, (float)$sum_with_special);
    }

    public function testUtilityMethodsWithLargeNumbers() {
        // Insert user with large numbers
        $this->db->insert('test_users', [
            'name' => 'Large Number User',
            'email' => 'large@example.com',
            'age' => 99,
            'score' => 999999.99
        ]);

        $max_age = $this->db->dmax('age', 'test_users');
        $this->assertEquals(99, (int)$max_age);

        $max_score = $this->db->dmax('score', 'test_users');
        $this->assertEquals(999999.99, (float)$max_score);

        $sum_score = $this->db->dsum('score', 'test_users');
        $this->assertGreaterThan(1000000, (float)$sum_score);
    }

    public function testUtilityMethodsWithEmptyTable() {
        // Empty the table
        $this->db->delete('test_users');

        $count = $this->db->dcount('*', 'test_users');
        $this->assertEquals(0, $count);

        $result = $this->db->dlookup('name', 'test_users');
        $this->assertFalse($result);

        $max_result = $this->db->dmax('age', 'test_users');
        $this->assertFalse($max_result);

        // note the strict assertion - casting to int would turn both FALSE and NULL into 0, which is
        // exactly how a NULL return went unnoticed here. dmax and dsum both document that the return
        // value has to be tested with ===, so that is what is tested
        $sum_result = $this->db->dsum('age', 'test_users');
        $this->assertFalse($sum_result);
        $this->assertNotNull($sum_result, "dsum must return FALSE rather than NULL when there is nothing to sum");
    }

    public function testAggregateMethodsReturnFalseNotNullWhenNothingMatches() {
        // SUM() and MAX() over zero matching rows come back as a single NULL row rather than no rows at
        // all, so the "did we get any rows" guard inside these methods passes and the NULL reaches the
        // caller unless it is converted
        $this->assertSame(false, $this->db->dsum('age', 'test_users', 'id > ?', [999999]), "dsum");
        $this->assertSame(false, $this->db->dmax('age', 'test_users', 'id > ?', [999999]), "dmax");
        $this->assertSame(false, $this->db->dlookup('name', 'test_users', 'id > ?', [999999]), "dlookup");

        // dcount is different - COUNT() genuinely returns a row containing 0, so it is not affected
        $this->assertEquals(0, $this->db->dcount('id', 'test_users', 'id > ?', [999999]), "dcount");
    }
}