<?php

/**
 * Common test queries and expected results
 * This file contains frequently used queries for testing various scenarios
 */

return [
    'basic_queries' => [
        'select_all_users' => 'SELECT * FROM test_users',
        'select_user_by_id' => 'SELECT * FROM test_users WHERE id = ?',
        'select_user_by_email' => 'SELECT * FROM test_users WHERE email = ?',
        'count_users' => 'SELECT COUNT(*) as count FROM test_users',
        'count_active_users' => 'SELECT COUNT(*) as count FROM test_users WHERE is_active = 1',
    ],

    'join_queries' => [
        'users_with_products' => '
            SELECT u.name as user_name, p.name as product_name, p.price 
            FROM test_users u 
            LEFT JOIN test_products p ON u.id = p.category_id',
        
        'products_with_categories' => '
            SELECT p.name as product_name, c.name as category_name, p.price
            FROM test_products p
            INNER JOIN test_categories c ON p.category_id = c.id',
        
        'complex_join' => '
            SELECT u.name as user_name, p.name as product_name, c.name as category_name
            FROM test_users u
            CROSS JOIN test_products p
            INNER JOIN test_categories c ON p.category_id = c.id
            LIMIT 5',
    ],

    'aggregate_queries' => [
        'avg_user_age' => 'SELECT AVG(age) as avg_age FROM test_users WHERE age IS NOT NULL',
        'max_product_price' => 'SELECT MAX(price) as max_price FROM test_products',
        'min_user_score' => 'SELECT MIN(score) as min_score FROM test_users WHERE score IS NOT NULL',
        'sum_product_prices' => 'SELECT SUM(price) as total_price FROM test_products',
        'count_by_category' => '
            SELECT c.name as category, COUNT(p.id) as product_count
            FROM test_categories c
            LEFT JOIN test_products p ON c.id = p.category_id
            GROUP BY c.id, c.name
            ORDER BY product_count DESC',
    ],

    'filtering_queries' => [
        'active_users_over_30' => 'SELECT * FROM test_users WHERE is_active = 1 AND age > 30',
        'products_price_range' => 'SELECT * FROM test_products WHERE price BETWEEN ? AND ?',
        'users_name_search' => 'SELECT * FROM test_users WHERE name LIKE ?',
        'recent_users' => "SELECT * FROM test_users WHERE created_at >= '2024-01-01'",
        'null_email_users' => 'SELECT * FROM test_users WHERE email IS NULL',
    ],

    'sorting_queries' => [
        'users_by_age_desc' => 'SELECT * FROM test_users ORDER BY age DESC',
        'products_by_price_asc' => 'SELECT * FROM test_products ORDER BY price ASC',
        'users_by_name' => 'SELECT * FROM test_users ORDER BY name',
        'complex_sort' => 'SELECT * FROM test_users ORDER BY is_active DESC, age ASC, name',
    ],

    'limit_queries' => [
        'first_5_users' => 'SELECT * FROM test_users ORDER BY id LIMIT 5',
        'users_page_2' => 'SELECT * FROM test_users ORDER BY id LIMIT 5 OFFSET 5',
        'top_3_expensive_products' => 'SELECT * FROM test_products ORDER BY price DESC LIMIT 3',
    ],

    'subqueries' => [
        'users_above_avg_age' => '
            SELECT * FROM test_users 
            WHERE age > (SELECT AVG(age) FROM test_users WHERE age IS NOT NULL)',
        
        'categories_with_products' => '
            SELECT * FROM test_categories 
            WHERE id IN (SELECT DISTINCT category_id FROM test_products)',
        
        'expensive_products' => '
            SELECT * FROM test_products 
            WHERE price > (SELECT AVG(price) FROM test_products)',
    ],

    'error_inducing_queries' => [
        'nonexistent_table' => 'SELECT * FROM nonexistent_table',
        'nonexistent_column' => 'SELECT nonexistent_column FROM test_users',
        'syntax_error' => 'SELCT * FROM test_users',
        'invalid_function' => 'SELECT INVALID_FUNCTION(name) FROM test_users',
        'division_by_zero' => 'SELECT 1/0 as result',
    ],

    'insert_queries' => [
        'basic_insert' => [
            'query' => 'INSERT INTO test_users (name, email, age) VALUES (?, ?, ?)',
            'params' => ['Test User', 'test@example.com', 25]
        ],
        'bulk_insert' => 'INSERT INTO test_users (name, email, age) VALUES (?, ?, ?), (?, ?, ?), (?, ?, ?)',
    ],

    'update_queries' => [
        'update_user_age' => [
            'query' => 'UPDATE test_users SET age = ? WHERE id = ?',
            'params' => [30, 1]
        ],
        'activate_all_users' => 'UPDATE test_users SET is_active = 1',
        'conditional_update' => [
            'query' => 'UPDATE test_users SET score = score + ? WHERE age > ?',
            'params' => [5, 30]
        ],
    ],

    'delete_queries' => [
        'delete_user_by_id' => [
            'query' => 'DELETE FROM test_users WHERE id = ?',
            'params' => [1]
        ],
        'delete_inactive_users' => 'DELETE FROM test_users WHERE is_active = 0',
        'delete_old_users' => "DELETE FROM test_users WHERE created_at < '2024-01-01'",
    ],

    'transaction_queries' => [
        'begin' => 'START TRANSACTION',
        'commit' => 'COMMIT',
        'rollback' => 'ROLLBACK',
        'savepoint' => 'SAVEPOINT test_point',
        'rollback_to_savepoint' => 'ROLLBACK TO SAVEPOINT test_point',
    ],

    'utility_queries' => [
        'show_tables' => 'SHOW TABLES',
        'describe_users' => 'DESCRIBE test_users',
        'show_create_table' => 'SHOW CREATE TABLE test_users',
        'check_connection' => 'SELECT 1 as connection_test',
        'get_version' => 'SELECT VERSION() as mysql_version',
        'get_current_database' => 'SELECT DATABASE() as current_database',
    ],

    'performance_test_queries' => [
        'heavy_computation' => '
            SELECT u1.name, u2.name, u1.age + u2.age as combined_age
            FROM test_users u1 
            CROSS JOIN test_users u2 
            WHERE u1.id != u2.id
            ORDER BY combined_age DESC',
        
        'recursive_like' => 'SELECT * FROM test_users WHERE name LIKE "%a%" AND name LIKE "%e%"',
        
        'complex_aggregation' => '
            SELECT 
                c.name,
                COUNT(p.id) as product_count,
                AVG(p.price) as avg_price,
                SUM(p.price) as total_price,
                MIN(p.price) as min_price,
                MAX(p.price) as max_price
            FROM test_categories c
            LEFT JOIN test_products p ON c.id = p.category_id
            GROUP BY c.id, c.name
            HAVING COUNT(p.id) > 0
            ORDER BY total_price DESC',
    ]
];