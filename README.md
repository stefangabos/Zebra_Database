<img src="https://github.com/stefangabos/zebrajs/blob/master/docs/images/logo.png" alt="zebrajs" align="right">

# Zebra_Database

*A compact (one-file only), lightweight yet feature-rich PHP MySQLi database wrapper providing methods for interacting with MySQL databases that are more secure, powerful and intuitive than PHP's default ones.*

[![Latest Stable Version](https://poser.pugx.org/stefangabos/zebra_database/v/stable)](https://packagist.org/packages/stefangabos/zebra_database) [![Total Downloads](https://poser.pugx.org/stefangabos/zebra_database/downloads)](https://packagist.org/packages/stefangabos/zebra_database) [![Monthly Downloads](https://poser.pugx.org/stefangabos/zebra_database/d/monthly)](https://packagist.org/packages/stefangabos/zebra_database) [![Daily Downloads](https://poser.pugx.org/stefangabos/zebra_database/d/daily)](https://packagist.org/packages/stefangabos/zebra_database) [![License](https://poser.pugx.org/stefangabos/zebra_database/license)](https://packagist.org/packages/stefangabos/zebra_database)

Zebra_Database supports [transactions](https://dev.mysql.com/doc/refman/5.7/en/glossary.html#glos_transaction) and provides ways for caching query results either by saving cached data to the disk, in the session, or by using [memcache](http://memcached.org/).

The library provides a comprehensive debugging interface with detailed information about the executed queries: execution time, returned/affected rows, excerpts of the found rows, error messages, backtrace information, etc. It can also automatically [EXPLAIN](http://dev.mysql.com/doc/refman/5.0/en/explain.html) SELECT queries *(so you don't miss those keys again!)*.

It encourages developers to write maintainable code and provides a better default security layer by encouraging the use of *prepared statements*, where parameters are automatically [escaped](http://www.php.net/manual/en/mysqli.real-escape-string.php).

The code is heavily commented and generates no warnings/errors/notices when PHP's error reporting level is set to E_ALL.

:books: Check out the [awesome documentation](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html)!

## Support the development of this library

[![Donate](https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=3WTC6RTC7496Q)

## Features

- it uses the [mysqli extension](http://www.php.net/manual/en/book.mysqli.php) extension for communicating with the database instead of the old *mysql* extension, which is officially deprecated as of PHP v5.5.0 and will be removed in the future; **this is not a wrapper for the PDO extension which is already a wrapper in itself!**

- offers [lots of powerful methods](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html) for easier interaction with MySQL

- supports [unbuffered queries](http://php.net/manual/en/mysqlinfo.concepts.buffering.php)

- provides a better security layer by encouraging the use of prepared statements, where parameters are automatically escaped

- provides a very detailed debugging interface with lots of useful information about executed queries; it also automatically [EXPLAIN](http://dev.mysql.com/doc/refman/5.7/en/explain.html)s each SELECT query

- supports caching of query results to the disk, in the session, or to a [memcache](http://memcached.org/) server

- has [really good documentation](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html)

- code is heavily commented and generates no warnings/errors/notices when PHP's error reporting level is set to **E_ALL**

![Zebra_Database's debugging console](http://stefangabos.ro/zebra-database-debug-console.png)

## Requirements

PHP 5.4.0+ with the **mysqli extension** activated, MySQL 4.1.22+

For using **memcache** as caching method, PHP must be compiled with the [memcache](http://pecl.php.net/package/memcache) extension and, if [memcache_compressed](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#var$memcache_compressed) property is set to TRUE, needs to be configured with `–with-zlib[=DIR]`

## Installation

Download the latest version, unpack it, and load it in your project

```php
require_once 'Zebra_Database.php';
```

## Installation with Composer
You can install Zebra_Database via [Composer](https://packagist.org/packages/stefangabos/zebra_database)
```
composer require stefangabos/zebra_database
```

## How to use

##### Connecting to a database

```php
// instantiate the library
$db = new Zebra_Database();

// connect to a server and select a database
$db->connect('host', 'username', 'password', 'database');
```

##### Running queries

```php
// question marks will re replaced automatically with the escaped values from the array
// I ENCOURAGE YOU TO WRITE YOUR QUERIES IN A READABLE FORMAT, LIKE BELOW
$db->query('
    SELECT
    	column1,
        column2,
        column3
    FROM
    	tablename1
    	LEFT JOIN tablename2 ON tablename1.column1 = tablename2.column1
    WHERE
    	somecriteria = ? AND
        someothercriteria = ?
', array($somevalue, $someothervalue));

// any fetch method will work with the last result so
// there's no need to explicitly pass that around

// you could fetch all records to one associative array...
$records = $db->fetch_assoc_all();

// you could fetch all records to one associative array
// using the values in a specific column as keys
$records = $db->fetch_assoc_all('column1');

// or fetch records one by one, as associative arrays
while ($row = $db->fetch_assoc()) {
    // do stuff
}
```

##### An INSERT statement

```php
// notice that you can use MySQL functions in values
$db->insert(
    'tablename',
    array(
        'column1'      => $value1,
        'column2'      => $value2,
        'date_updated' => 'NOW()'
    )
);
```

##### An UPDATE statement

```php
// $criteria will be escaped and enclosed in grave accents, and will
// replace the corresponding ? (question mark) automatically
// also, notice that you can use MySQL functions in values
// when using MySQL functions, the value will be used as it is without being escaped!
// while this is ok when using a function without any arguments like NOW(), this may
// pose a security concern if the argument(s) come from user input.
// in this case we have to escape the value ourselves
$db->update(
    'tablename',
    array(
        'column1'      => $value1,
        'column2'      => 'TRIM(UCASE("value2"))',
        'column3'      => 'TRIM(UCASE("'' . $db->escape($value3) . "))',
        'date_updated' => 'NOW()'
    ),
    'criteria = ?',
    array($criteria)
);
```
> There are over **40 methods** and 20 properties that you can use and **lots** of things you can do with this library. I've prepared an [awesome documentation](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html) so that you can easily get an overview of what can be done. Go ahead, [check it out](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html)!
