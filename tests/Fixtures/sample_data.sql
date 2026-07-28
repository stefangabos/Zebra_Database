-- Sample test data for Zebra_Database tests
-- This file contains common test data that can be used across multiple tests

-- Test users with various data types and edge cases
INSERT INTO test_users (name, email, age, score, is_active, created_at) VALUES 
('John Doe', 'john@example.com', 30, 85.50, 1, '2024-01-01 10:00:00'),
('Jane Smith', 'jane@example.com', 25, 92.75, 1, '2024-01-02 11:30:00'),
('Bob Johnson', 'bob@example.com', 35, 78.25, 0, '2024-01-03 09:15:00'),
('Alice Brown', 'alice@example.com', 28, 88.90, 1, '2024-01-04 14:20:00'),
('Charlie Wilson', 'charlie@example.com', 45, 76.40, 1, '2024-01-05 16:45:00'),
('Diana Davis', NULL, 32, 95.10, 1, '2024-01-06 08:30:00'), -- NULL email for testing
('Eve Miller', 'eve@example.com', NULL, 89.20, 0, '2024-01-07 12:00:00'), -- NULL age for testing
('Frank Garcia', 'frank@example.com', 29, NULL, 1, '2024-01-08 13:45:00'); -- NULL score for testing

-- Test categories
INSERT INTO test_categories (name) VALUES 
('Electronics'),
('Books'),
('Clothing'),
('Home & Garden'),
('Sports & Outdoors');

-- Test products with foreign key relationships
INSERT INTO test_products (name, price, category_id) VALUES 
('Laptop Computer', 999.99, 1),
('Programming Book', 49.99, 2),
('T-Shirt', 19.99, 3),
('Garden Hose', 29.99, 4),
('Tennis Racket', 79.99, 5),
('Smartphone', 699.99, 1),
('Cooking Book', 24.99, 2),
('Jeans', 59.99, 3),
('Plant Pot', 12.99, 4),
('Basketball', 25.99, 5);

-- Additional test data for edge cases
INSERT INTO test_users (name, email, age, score, is_active) VALUES 
('Test User with Very Long Name That Exceeds Normal Limits', 'longname@example.com', 25, 50.00, 1),
('', 'emptyname@example.com', 30, 75.00, 1), -- Empty name
('Special Chars !@#$%^&*()', 'special@example.com', 35, 80.00, 1),
('Unicode Test 你好世界', 'unicode@example.com', 40, 90.00, 1),
('Apostrophe O''Connor', 'apostrophe@example.com', 45, 85.00, 1);