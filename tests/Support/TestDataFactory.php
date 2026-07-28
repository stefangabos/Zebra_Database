<?php

/**
 * Test Data Factory
 * Provides methods to generate test data for various scenarios
 */
class TestDataFactory
{
    /**
     * Create a test user with optional overrides
     */
    public static function createUser($overrides = []) {
        $defaults = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'age' => 25,
            'score' => 75.50,
            'is_active' => 1
        ];
        
        return array_merge($defaults, $overrides);
    }

    /**
     * Create multiple test users
     */
    public static function createUsers($count = 3, $baseOverrides = []) {
        $users = [];
        for ($i = 1; $i <= $count; $i++) {
            $users[] = self::createUser(array_merge($baseOverrides, [
                'name' => "Test User $i",
                'email' => "test$i@example.com"
            ]));
        }
        return $users;
    }

    /**
     * Create a test product with optional overrides
     */
    public static function createProduct($overrides = []) {
        $defaults = [
            'name' => 'Test Product',
            'price' => 99.99,
            'category_id' => 1
        ];
        
        return array_merge($defaults, $overrides);
    }

    /**
     * Create multiple test products
     */
    public static function createProducts($count = 3, $baseOverrides = []) {
        $products = [];
        for ($i = 1; $i <= $count; $i++) {
            $products[] = self::createProduct(array_merge($baseOverrides, [
                'name' => "Test Product $i",
                'price' => 10.00 * $i
            ]));
        }
        return $products;
    }

    /**
     * Create a test category with optional overrides
     */
    public static function createCategory($overrides = []) {
        $defaults = [
            'name' => 'Test Category'
        ];
        
        return array_merge($defaults, $overrides);
    }

    /**
     * Get malicious SQL injection inputs
     */
    public static function getMaliciousInputs() {
        return [
            "'; DROP TABLE test_users; --",
            "' OR '1'='1",
            "' OR 1=1 --",
            "' UNION SELECT password FROM admin_users --",
            "'; DELETE FROM test_users; --",
            "' OR 1=1 /*",
            "admin'--",
            "' OR 'x'='x",
            "') OR ('1'='1",
            "1' AND (SELECT COUNT(*) FROM test_users) > 0 --"
        ];
    }

    /**
     * Get extreme and edge case values
     */
    public static function getExtremeValues() {
        return [
            'strings' => [
                '',                                    // Empty string
                ' ',                                   // Space
                'a',                                   // Single character
                str_repeat('A', 1000),                 // Very long string
                "Line1\nLine2\rLine3",                 // Newlines
                "Tab\tSeparated\tValues",              // Tabs
                'Special !@#$%^&*()_+-={}[]|\\:;"\'<>?,./', // Special chars
                '你好世界',                              // Unicode
                'café naïve résumé',                   // Accented characters
            ],
            'numbers' => [
                0,
                -1,
                1,
                PHP_INT_MAX,
                PHP_INT_MIN,
                1.5,
                -1.5,
                PHP_FLOAT_MAX,
                -PHP_FLOAT_MAX,
            ],
            'booleans' => [
                true,
                false,
                1,
                0,
                'true',
                'false',
                'yes',
                'no'
            ],
            'nulls_and_empty' => [
                null,
                '',
                '0',
                0,
                false,
                [],
            ]
        ];
    }

    /**
     * Get binary and problematic data
     */
    public static function getBinaryData() {
        return [
            "\x00\x01\x02\x03",           // Binary data with null byte
            "test\x00injection",          // Null byte injection
            chr(0) . "' OR '1'='1",      // Null byte with SQL injection
            "\xFF\xFE\x00\x00",         // Binary data
            pack("H*", "deadbeef"),      // Hex packed binary
            "\xC0\x80",                  // Invalid UTF-8 overlong encoding
            "\xE0\x80\x80",             // Invalid UTF-8 overlong encoding
            "\xF0\x80\x80\x80",         // Invalid UTF-8 overlong encoding
        ];
    }

    /**
     * Get path traversal inputs
     */
    public static function getPathTraversalInputs() {
        return [
            "../../../etc/passwd",
            "..\\..\\..\\windows\\system32\\config\\sam",
            "%2e%2e%2f%2e%2e%2f%2e%2e%2fetc%2fpasswd",
            "....//....//....//etc//passwd",
            "..%252f..%252f..%252fetc%252fpasswd",
        ];
    }

    /**
     * Create test data for stress testing
     */
    public static function createStressTestData($size = 'medium') {
        $sizes = [
            'small' => ['users' => 10, 'products' => 20, 'text_length' => 100],
            'medium' => ['users' => 100, 'products' => 200, 'text_length' => 1000],
            'large' => ['users' => 1000, 'products' => 2000, 'text_length' => 10000],
        ];
        
        $config = $sizes[$size] ?? $sizes['medium'];
        
        return [
            'users' => self::createUsers($config['users']),
            'products' => self::createProducts($config['products']),
            'large_text' => str_repeat('Stress test data ', $config['text_length'])
        ];
    }

    /**
     * Create data with various NULL scenarios
     */
    public static function createNullTestData() {
        return [
            'all_null' => [
                'name' => null,
                'email' => null,
                'age' => null,
                'score' => null,
                'is_active' => null
            ],
            'partial_null' => [
                'name' => 'Test User',
                'email' => null,
                'age' => 25,
                'score' => null,
                'is_active' => 1
            ],
            'empty_vs_null' => [
                'name' => '',
                'email' => null,
                'age' => 0,
                'score' => null,
                'is_active' => false
            ]
        ];
    }

    /**
     * Create realistic test data with relationships
     */
    public static function createRealisticDataSet() {
        return [
            'categories' => [
                ['name' => 'Electronics'],
                ['name' => 'Books'],
                ['name' => 'Clothing'],
                ['name' => 'Home & Garden'],
                ['name' => 'Sports']
            ],
            'users' => [
                ['name' => 'John Smith', 'email' => 'john.smith@email.com', 'age' => 35, 'score' => 85.5],
                ['name' => 'Sarah Johnson', 'email' => 'sarah.johnson@email.com', 'age' => 28, 'score' => 92.0],
                ['name' => 'Mike Wilson', 'email' => 'mike.wilson@email.com', 'age' => 42, 'score' => 78.5],
                ['name' => 'Emily Brown', 'email' => 'emily.brown@email.com', 'age' => 31, 'score' => 88.0],
                ['name' => 'David Lee', 'email' => 'david.lee@email.com', 'age' => 26, 'score' => 95.5],
            ],
            'products' => [
                ['name' => 'iPhone 15', 'price' => 999.99, 'category_id' => 1],
                ['name' => 'MacBook Pro', 'price' => 2499.99, 'category_id' => 1],
                ['name' => 'The Great Gatsby', 'price' => 12.99, 'category_id' => 2],
                ['name' => 'Programming Pearls', 'price' => 39.99, 'category_id' => 2],
                ['name' => 'Blue Jeans', 'price' => 79.99, 'category_id' => 3],
                ['name' => 'Cotton T-Shirt', 'price' => 19.99, 'category_id' => 3],
                ['name' => 'Garden Hose', 'price' => 49.99, 'category_id' => 4],
                ['name' => 'Flower Pot', 'price' => 24.99, 'category_id' => 4],
                ['name' => 'Tennis Racket', 'price' => 129.99, 'category_id' => 5],
                ['name' => 'Basketball', 'price' => 29.99, 'category_id' => 5]
            ]
        ];
    }

    /**
     * Create data for JOIN testing scenarios
     */
    public static function createJoinTestData() {
        return [
            'authors' => [
                ['name' => 'J.K. Rowling', 'email' => 'jk.rowling@publisher.com'],
                ['name' => 'Stephen King', 'email' => 'stephen.king@publisher.com'],
                ['name' => 'Agatha Christie', 'email' => 'agatha.christie@publisher.com']
            ],
            'books' => [
                ['title' => 'Harry Potter', 'author_id' => 1, 'name' => 'Fantasy Series'],
                ['title' => 'The Shining', 'author_id' => 2, 'name' => 'Horror Series'],
                ['title' => 'Murder on Orient Express', 'author_id' => 3, 'name' => 'Mystery Series']
            ],
            'publishers' => [
                ['name' => 'Penguin Random House', 'email' => 'contact@penguinrandomhouse.com'],
                ['name' => 'HarperCollins', 'email' => 'info@harpercollins.com']
            ]
        ];
    }

    /**
     * Load malicious inputs from JSON fixture
     */
    public static function loadMaliciousInputsFromFile() {
        $file = __DIR__ . '/../Fixtures/malicious_inputs.json';
        if (file_exists($file)) {
            return json_decode(file_get_contents($file), true);
        }
        return self::getMaliciousInputs();
    }

    /**
     * Generate random test data
     */
    public static function generateRandomUser() {
        $firstNames = ['John', 'Jane', 'Mike', 'Sarah', 'David', 'Emily', 'Chris', 'Lisa'];
        $lastNames = ['Smith', 'Johnson', 'Brown', 'Davis', 'Miller', 'Wilson', 'Moore', 'Taylor'];
        $domains = ['email.com', 'test.org', 'example.net', 'sample.co'];
        
        $firstName = $firstNames[array_rand($firstNames)];
        $lastName = $lastNames[array_rand($lastNames)];
        $domain = $domains[array_rand($domains)];
        
        return [
            'name' => $firstName . ' ' . $lastName,
            'email' => strtolower($firstName . '.' . $lastName . '@' . $domain),
            'age' => rand(18, 80),
            'score' => round(rand(0, 10000) / 100, 2),
            'is_active' => rand(0, 1)
        ];
    }

    /**
     * Create data specifically for performance testing
     */
    public static function createPerformanceTestData($recordCount = 1000) {
        $data = [];
        for ($i = 1; $i <= $recordCount; $i++) {
            $data[] = [
                'name' => "Performance Test User $i",
                'email' => "perf$i@test.com",
                'age' => rand(18, 80),
                'score' => rand(0, 10000) / 100,
                'is_active' => $i % 2, // Alternate between 0 and 1
                'created_at' => date('Y-m-d H:i:s', strtotime("-$i days"))
            ];
        }
        return $data;
    }
}