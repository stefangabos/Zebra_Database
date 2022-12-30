<img src="https://github.com/stefangabos/zebrajs/blob/master/docs/images/logo.png" alt="zebrajs" align="right">

# Zebra Database &nbsp;[![Tweet](https://img.shields.io/twitter/url/http/shields.io.svg?style=social)](https://twitter.com/intent/tweet?text=A+compact,+lightweight+and+feature-rich+PHP+MySQLi+database+wrapper&url=https://github.com/stefangabos/Zebra_Database&via=stefangabos&hashtags=php,mysqli,mysql)

*A compact, lightweight yet feature-rich PHP MySQLi database wrapper providing methods for interacting with MySQL databases that are more secure, powerful and intuitive than PHP's default ones.*

[![Latest Stable Version](https://poser.pugx.org/stefangabos/zebra_database/v)](https://packagist.org/packages/stefangabos/zebra_database) [![Total Downloads](https://poser.pugx.org/stefangabos/zebra_database/downloads)](https://packagist.org/packages/stefangabos/zebra_database) [![Monthly Downloads](https://poser.pugx.org/stefangabos/zebra_database/d/monthly)](https://packagist.org/packages/stefangabos/zebra_database) [![Daily Downloads](https://poser.pugx.org/stefangabos/zebra_database/d/daily)](https://packagist.org/packages/stefangabos/zebra_database) [![License](https://poser.pugx.org/stefangabos/zebra_database/license)](https://packagist.org/packages/stefangabos/zebra_database)

Zebra_Database supports [transactions](https://dev.mysql.com/doc/refman/8.0/en/glossary.html#glos_transaction) and provides ways for caching query results either by saving cached data to the disk, in the session, by using [memcache](https://memcached.org/) or [redis](https://redis.io/).

The library provides a comprehensive debugging interface with detailed information about the executed queries: execution time, returned/affected rows, excerpts of the found rows, error messages, backtrace information, etc. It can also automatically [EXPLAIN](https://dev.mysql.com/doc/refman/8.0/en/explain.html) SELECT queries *(so you don't miss those keys again!)*.

Can provides debugging information when called from **CLI** (command-line interface) and supports logging queries done through **AJAX** requests.

It encourages developers to write maintainable code and provides a better default security layer by encouraging the use of *prepared statements*, where parameters are automatically [escaped](https://www.php.net/manual/en/mysqli.real-escape-string.php).

The code is heavily commented and generates no warnings/errors/notices when PHP's error reporting level is set to E_ALL.

## Features

- it uses the [mysqli extension](https://www.php.net/manual/en/book.mysqli.php) extension for communicating with the database instead of the old *mysql* extension, which is officially deprecated as of PHP v5.5.0 and will be removed in the future; **this is not a wrapper for the PDO extension which is already a wrapper in itself!**

- can provide debugging information even when called from **CLI** (command-line interface)

- logs all queries, including those run through **AJAX**

- offers [lots of powerful methods](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html) for easier interaction with MySQL

- supports [unbuffered queries](https://www.php.net/manual/en/mysqlinfo.concepts.buffering.php)

- provides a better security layer by encouraging the use of prepared statements, where parameters are automatically escaped

- provides a very detailed debugging interface with lots of useful information about executed queries; it also automatically [EXPLAIN](https://dev.mysql.com/doc/refman/8.0/en/explain.html)s each SELECT query

- supports caching of query results to the disk, in the session, or to a [memcache](https://memcached.org/) or to a [redis](https://redis.io/) server

- has [really good documentation](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html)

- code is heavily commented and generates no warnings/errors/notices when PHP's error reporting level is set to **E_ALL**

![Zebra_Database's debugging console](https://raw.githubusercontent.com/stefangabos/Zebra_Database/master/docs/media/zebra-database-debug-console.png)

## :notebook_with_decorative_cover: Documentation

Check out the [awesome documentation](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html)!

## 🎂 Support the development of this project

Your support is greatly appreciated and it keeps me motivated continue working on open source projects. If you enjoy this project please star it by clicking on the star button at the top of the page. If you're feeling generous, you can also buy me a coffee through PayPal or become a sponsor.
**Thank you for your support!** 🎉

[<img src="https://img.shields.io/github/stars/stefangabos/zebra_database?color=green&label=star%20it%20on%20GitHub" width="132" height="20" alt="Star it on GitHub">](https://github.com/stefangabos/Zebra_Database) [![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=3WTC6RTC7496Q) [<img src="https://img.shields.io/badge/-Sponsor-fafbfc?logo=GitHub%20Sponsors">](https://github.com/sponsors/stefangabos)

## Requirements

PHP 5.4.0+ with the **mysqli extension** activated, MySQL 4.1.22+

For using **memcache** as caching method, PHP must be compiled with the [memcache](https://pecl.php.net/package/memcache) extension and, if [memcache_compressed](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#var$memcache_compressed) property is set to TRUE, needs to be configured with `–with-zlib[=DIR]`

For using **redis** as caching method, PHP must be compiled with the [redis](https://pecl.php.net/package/redis) extension and, if [redis_compressed](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#var$redis_compressed) property is set to TRUE, needs to be configured with `–with-zlib[=DIR]`

## Installation

You can install Zebra Database via [Composer](https://packagist.org/packages/stefangabos/zebra_database)

```bash
# get the latest stable release
composer require stefangabos/zebra_database

# get the latest commit
composer require stefangabos/zebra_database:dev-master
```

Or you can install it manually by downloading the latest version, unpacking it, and then including it in your project

```php
require_once 'path/to/Zebra_Database.php';
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
    	column1
        , column2
        , column3
    FROM
    	tablename1
    	LEFT JOIN tablename2 ON tablename1.column1 = tablename2.column1
    WHERE
    	somecriteria = ?
        AND someothercriteria = ?
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
