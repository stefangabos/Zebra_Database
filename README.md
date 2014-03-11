##Zebra_Database

####An advanced, compact and lightweight MySQL database wrapper library, built around PHP's mysqli extension

It provides methods for interacting with MySQL databases that are more powerful and intuitive than PHP’s default ones.

It supports transactions and provides ways for caching query results either by saving cached data on the disk, or by using memcache.

The class provides a comprehensive debugging interface with detailed information about the executed queries: execution time, returned/affected rows, excerpts of the found rows, error messages, etc. It also automatically EXPLAIN‘s each SELECT query (so you don’t miss those keys again!).

It encourages developers to write maintainable code and provides a better default security layer by encouraging the use of prepared statements, where parameters are automatically escaped.

Zebra_Database‘s code is heavily commented and generates no warnings/errors/notices when PHP’s error reporting level is set to E_ALL.

##Features

- it uses the **mysqli** extension for communicating with the database instead of the old **mysql** extension, which is officially deprecated as of PHP v5.5.0 and will be removed in the future; again, this is not a wrapper for the PDO extension which is already a wrapper in itself

- offers lots of powerful methods for easier interaction with MySQL

- provides a better security layer by encouraging the use of prepared statements, where parameters are automatically escaped

- provides a very detailed debugging interface with lots of useful information about executed queries; it also automatically EXAPLAIN’s each SELECT query

- supports caching of query results to disk or to a **memcache** server

- has comprehensive documentation

- code is heavily commented and generates no warnings/errors/notices when PHP’s error reporting level is set to **E_ALL**

## Requirements

PHP 5+ with the **mysqli extension** activated, MySQL 4.1.22+

For using **memcache** as caching method, PHP must be compiled with the [memcache](http://pecl.php.net/package/memcache) extension and, if [memcache_compressed](http://stefangabos.ro/wp-content/docs/Zebra_Database/Zebra_Database/Zebra_Database.html#var$memcache_compressed) property is set to TRUE, needs to be configured with –with-zlib[=DIR]

## How to use

Connect to a database

```php

<?php

require 'path/to/Zebra_Database.php';

$db = new Zebra_Database();

// turn debugging on
$db->debug = true;

// set relative path to parent of public folder from $_SERVER['DOCUMENT_ROOT'] (optional)
// no leading slash
// ie: http://example.com/vendor/stefangabos/zebra_database/public/css/database.css
$db->resource_path = 'vendor/stefangabos/zebra_database';

$db->connect('host', 'username', 'password', 'database');

// code goes here

// this should always be present at the end of your scripts;
// whether it should output anything should be controlled by the $debug property
$db->show_debug_console();

?>
```

A SELECT statement

```php
<?php

// $criteria will be escaped and enclosed in grave accents, and will
// replace the corresponding ? (question mark) automatically
$db->select(
    'column1, column2',
    'table',
    'criteria = ?',
    array($criteria)
);

// after this, one of the "fetch" methods can be run:

// to fetch all records to one associative array
$records = $db->fetch_assoc_all();

// or fetch records one by one, as associative arrays
while ($row = $db->fetch_assoc()) {
    // do stuff
}
?>
```

An INSERT statement

```php
<?php

$db->insert(
    'table',
    array(
        'column1' => $value1,
        'column2' => $value2,
    )
);

?>
```

An UPDATE statement

```php
<?php

// $criteria will be escaped and enclosed in grave accents, and will
// replace the corresponding ? (question mark) automatically
$db->update(
    'table',
    array(
        'column1' => $value1,
        'column2' => $value2,
    ),
    'criteria = ?',
    array($criteria)
);

?>
```

Visit the **[project's homepage](http://stefangabos.ro/php-libraries/zebra-database/)** for more information.
