## version 2.11.1 (January 28, 2024)

- version bump

## version 2.11.0 (January 27, 2024)

- the library can now log queries run via AJAX requests; see the newly added [debug_ajax](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#var$debug_ajax) property
- debug information is now also shown when running in CLI (when [debugging](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#var$debug) is enabled, of course)
- added a new [debug_show_database_manager](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#var$debug_show_database_manager) property for editing queries in your favorite database manager
- the "unsuccessful queries" tab is now open by default if there are any unsuccessful queries
- fixed [#79](https://github.com/stefangabos/Zebra_Database/issues/76) where the library would try to connect to the database even when using lazy connection because of the logic in the `free_result` method; thanks to [Brian Hare](https://github.com/BHare1985) for reporting!
- fixed an issue where having the `debug` property set to a `string` but debugging not being activated, would result in errors not being logged
- fixed bug where the library would try to `EXPLAIN` queries that could not be explained; like `SHOW TABLE` for example; see [#76](https://github.com/stefangabos/Zebra_Database/issues/76) - thank you [cosinus90](https://github.com/cosinus90)!
- fixed potential warnings being thrown in PHP 8; see [#74](https://github.com/stefangabos/Zebra_Database/pull/74) - thank you [Rémi](https://github.com/Revine-dev)!
- fixed a potential issue when encountering connection errors
- updated the CSS and the icons for the debug interface

## version 2.10.2 (May 13, 2022)

- fixed a deprecation warning shown in PHP 8.1+; see [#70](https://github.com/stefangabos/Zebra_Database/issues/70), thanks [Harry](https://github.com/Dibbyo456)
- fixed a potential bug with `INC` keyword being incorrectly detected in strings looking like `INC(foo)`
- fixed EXPLAIN not working in the debug console
- fixed debug console being always shown once enabled via string

## version 2.10.1 (January 07, 2021)

- fixed bug introduced in previous release, for the [implode](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#methodimplode) method; see [#65](https://github.com/stefangabos/Zebra_Database/issues/65), thanks [pbm845](https://github.com/pbm845)

## version 2.10.0 (December 23, 2020)

- added option for enabling debugging on the fly via a query string parameter - see [documentation](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#var$debug)
- added support for caching query results to a [redis](https://redis.io/) server
- the default value of [disable_warnings](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#var$disable_warnings) is now FALSE
- updated German language; thanks to [Bernhard Morgenstern](https://github.com/bmorg)!
- major documentation overhaul

## version 2.9.14 (April 30, 2020)

- fixed bug with XSS in the debug console; see [#62](https://github.com/stefangabos/Zebra_Database/issues/62)
- fixed incorrect handling of `NULL` values; see [#60](https://github.com/stefangabos/Zebra_Database/issues/60)
- the `global` section in the debugging console can now be disabled or configured to show only specific subsections via the newly added [debug_show_globals](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#var$debug_show_globals) property; see [#59](https://github.com/stefangabos/Zebra_Database/issues/59)
- fixed bug with setting the caching method to "memcache" but not having memcache properly set up, or setting up memcache but not having the caching method set to "memcache"
- minor layout updates for the debugging console

## version 2.9.13 (February 29, 2020)

- fixed a bug where the library would incorrectly handle MySQL functions in certain scenarios
- fixed [#57](https://github.com/stefangabos/Zebra_Database/issues/57) where in PHP 7.4.0 a warning was shown about `get_magic_quotes_gpc` function being deprecated; thanks [userc479](https://github.com/userc479) for reporting!
- added the `return_error_number` argument to the [error()](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#methoderror) method
- added property [auto_quote_replacements](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#var$auto_quote_replacements) allowing to disable the library's default behavior of automatically quoting escaped values

## version 2.9.12 (January 16, 2019)

- the [insert_bulk](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#methodinsert_bulk) method now supports INSERT IGNORE and INSERT...ON DUPLICATE KEY UPDATE; this fixes [#42](https://github.com/stefangabos/Zebra_Database/issues/42) and deprecates the `insert_update` method
- the [insert](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#methodinsert) method now also supports INSERT...ON DUPLICATE KEY UPDATE - this slightly changes the functionality of the method's 3rd agument but stays compatible with previous versions of the library
- fixed [#47](https://github.com/stefangabos/Zebra_Database/issues/47) where setting `log_path` property to a full path to a file with extension would not change the log file's name, as stated in the documentation
- fixed [#37](https://github.com/stefangabos/Zebra_Database/issues/37) where unsuccessful queries were not written to the log file
- fixed bug when the first argument for `fetch_assoc_all` and `fetch_obj_all` methods was skipped
- logs can now be handled via a custom callback function instead of being written to a log file, by setting the [$log_path](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#var$log_path) property; this answers [#48](https://github.com/stefangabos/Zebra_Database/issues/48)
- changed output written to the log files which is now less verbose, cleaner and taking up less space
- dates in log files are now in Y-m-d format instead of Y M d
- changed how entries are separated in the log file
- updated minimum required PHP version from 5.2.0 to 5.4.0. This fixes [#44](https://github.com/stefangabos/Zebra_Database/issues/44)

## version 2.9.11 (June 19, 2018)

- fixed issue [#43](https://github.com/stefangabos/Zebra_Database/issues/43) where some strings were incorrectly detected as MySQL functions
- fixed issue [#45](https://github.com/stefangabos/Zebra_Database/issues/45) where the [table_exists](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#methodtable_exists) method was always returning `true`
- fixed issue [#46](https://github.com/stefangabos/Zebra_Database/issues/46) where the [select_database](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#methodselect_database) was always returning `false`
- fixed issue [#49](https://github.com/stefangabos/Zebra_Database/issues/49)
- fixed issue [#50](https://github.com/stefangabos/Zebra_Database/issues/50) where MySQL functions were incorrectly recognized
- source code improvements

## version 2.9.10 (December 03, 2017)

- updated Russian translation; thanks [@rayzru](https://github.com/rayzru)!
- fixed bug with MySQL functions not being properly handled by the [select](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#methodselect) method when the `columns` argument was given as an array
- improved documentation for the [select](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#methodselect) method
- fixed an issue that would trigger an error if other PHP scripts were including the [SqlFormatter](http://github.com/jdorn/sql-formatter) library
- added support for using the `AS` keyword in the `columns` argument for the [select](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#methodselect) method. Fixes [#34](https://github.com/stefangabos/Zebra_Database/issues/34).

## version 2.9.9 (May 21, 2017)

- unnecessary files are no more included when downloading from GitHub or via Composer

## version 2.9.7 (May 10, 2017)

- fixed a bug introduced in the previous release where `*` character could not be used anymore in the [select()](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#methodselect) method
- documentation is now available in the repository and on GitHub
- the home of the library is now exclusively on GitHub

## version 2.9.6 (May 01, 2017)

- the debugging console is not shown when AJAX requests are detected
- fixed a bug where executing unbuffered queries was generating warnings
- improved the MySQL function recognition pattern and added all MySQL functions as per [MySQL's documentation](https://dev.mysql.com/doc/refman/5.7/en/func-op-summary-ref.html)
- source code tidying

## version 2.9.5 (April 11, 2017)

> This version somewhat break the compatibility with previous versions! To fix things, you will need to remove the call to the *show_debug_console* method as now the debugging console is automatically shown when script execution ends. If you were using the *write_log* method than you will need to remove the call to it and refer to the *debug* property for more information.

-  added support for [unbuffered queries](http://php.net/manual/en/mysqlinfo.concepts.buffering.php)
-  the debugging console is now automatically shown when script execution ends without the need to manually show it; as a consequence the *show_debug_console* and *halt* methods were removed
-  the [debug](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#var$debug) property can now be also an array (instead of just boolean) instructing the library to log debug information instead of showing it on the screen - as a consequence, the *write_log* method was removed
-  renamed the *console_show_records* to [debug_show_records](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#var$debug_show_records)
-  EXPLAIN and backtrace information can now be disabled from the debugging interface with the newly added [debug_show_explain](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#var$debug_show_explain) and [debug_backtrace](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#var$debug_show_backtrace) properties
-  added a new [option](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#methodoption) method for setting [connection options](http://php.net/manual/en/mysqli.options.php#refsect1-mysqli.options-parameters)
-  *database* argument is now optional in the [connect](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#methodconnect) method; additionally, an explicit selection of a database is not required anymore as now, in all the methods where required, you can prefix table names with database name, like *database.tablename*
-  the argument for the [free_result](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#methodfree_result) method is now optional and the last used resource will be used if not specified, just like for the rest of the methods requiring a result
-  fixed a bug where setting the *calc_rows* argument to *TRUE* for the [query](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#methodquery) method, and having a query starting with a comment would have no effect
-  fixed a bug with queries being always reported as being from cache in the log files written
-  backtrace information is not written to the log files by default anymore; in can be enabled by setting the [debug](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#var$debug) property to an array
-  query execution time is now shown in the debugging console in seconds rather than milliseconds
-  pressing *ESC* now closes the debugging console
-  changed the occurrences of PHP's [each()](http://php.net/manual/en/function.each.php) function which is being deprecated starting with PHP 7.2.0
-  **lots** of source code optimizations and documentation updates

## version 2.9.4 (April 01, 2017)

- fixed a bug where a new connection could not be made after using the [close](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#methodclose) method
- fixed an issue with the memcache warning message appearing even if no memcache extension was available; thanks **Jeff Buckles**
- the library now supports unbuffered queries using the newly added [query_unbuffered](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#methodquery_unbuffered) method
- added a new [select_database](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#methodselect_database) method (as a side-effect, the "database" argument is not mandatory anymore for the [connect](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#methodconnect) method)
- added *DEFAULT* to the list of known MySQL functions; thanks **Jeff Buckles**

## version 2.9.3 (February 19, 2016)

- fixed an issue that would trigger a warning if a replacement value was an array instead of a string
- fixed a bug where "fetch_obj_all" method would fail if the "index" argument was given; thanks **Milan Kvita**
- minimum required PHP version is now 5.2.0 instead of 5.0.0

## version 2.9.2 (January 07, 2016)

- the library now uses [SqlFormatter](https://github.com/jdorn/sql-formatter) PHP library by [Jeremy Dorn](https://github.com/jdorn) for better highlighting of SQL queries
- the debug console got a few minor visual tweaks

## version 2.9.1 (January 04, 2016)

- added 2 new methods: [error](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#methoderror) and [free_result](https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#methodfree_result)
- MySQL functions can now be used when setting values in "insert", "insert_update", "insert_bulk" and "update" methods
- fixed caching not working anymore since 2.9.0; thanks **Andrew Rumm**

## version 2.9.0 (December 07, 2015)

- fixed warnings that would be triggered on PHP 5.5+; thanks to **Andrew Rumm**

## version 2.8.8 (February 13, 2015)

- more fixes for warning when no queries are run

## version 2.8.7 (February 10, 2015)

- added a new "get_selected_database" method for getting the name of the currently selected database; thanks to **Stijn** for suggesting
- fixed a bug in the library's destructor method where mysqli_free_result would trigger a notice in your server's log files if the result was not a mysqli result set (as it could also be a boolean for queries that do not return anything or even unset when no queries were run); thanks to **Eike Broda**
- fixed a bug with the log files where the log files were recreated at each execution rather than being updated; thanks to **Eike Broda**
- added Russian language file; thanks to **Andrew Rumm**
- fixed an issue where the library would not check if an email address was set when sending out notifying emails for queries that take too long to execute; thanks to **Andrew Rumm**

## version 2.8.6 (November 25, 2014)

- simpler usage of WHERE-IN conditions; previously you had to use the implode method but now, when an array is given as a replacement item, this method will be automatically used for you; thanks to **primenic**
- updates to the German language file; thanks to **Eike Broda**

## version 2.8.5 (November 12, 2014)

- result sets can now also be cached in sessions
- users can now change resource paths, allowing users to move scripts and stylesheets to whatever locations; thanks to **Joseph Spurrier**
- column names for the select() method can now be given as an array (recommended) and will be automatically enclosed in grave accents; ; thanks to **Joseph Spurrier**
- fixed a bug with determining the path to the library's CSS and JavaScript when using symlinks; thanks to **primeinc**
- fixed a bug with backslashes in the replacement strings; thanks to **primeinc**
- fixed a bug in the "get_tables" method which would trigger warning messages; thanks to **Stefan L** for reporting
- fixed bug with dlookup method &amp; friends and caching
- fixed composer.json and the library is now correctly working with Composer's autoloader; thanks to **Joseph Spurrier**
- fixed a bug where setting the "disable_warnings" property to true would not actually disable warning; thanks to **Joseph Spurrier**
- fixed a bug where setting the "memcache_compressed" property to TRUE had no effect; thanks to **Andrew Rumm**
- fixed a bug with CSS/JS when port is not 80 where the library would not correctly look for the CSS and JS files (used by the debug console) when using a port other than 80; thanks to **Gabriel Moya**!
- fixed a bug where port and socket were not used even if set; thanks to **Nam Trung**
- fixed some typos in comments

## version 2.8.4 (December 22, 2013)

- added a new "memcache_key_prefix" property; this allows separate caching of the same queries by multiple instances of the libraries on the same memcache server, or the library handling multiple domains on the same memcache server; thanks to **primeinc**
- fixed a bug with the insert_bulk method; thanks to **Guillermo**

## version 2.8.3 (October 10, 2013)

- fixed a bug with the connection credentials which were stored in a public property rather than a private one; thanks **etb09**
- fixed a bug with the output generated by the "write_log" method; thanks **etb09**
- fixed a bug where the "insert_bulk" and "write_log" methods would trigger a "strict standards" warning in PHP 5.4+; thanks **etb09**
- fixed a minor issue that could cause the library to trigger a warning (only when debugging was on)
- log files can now be generated by date and hour; thanks **etb09**
- entries in log files now have correct indentation regardless of the used language file
- added German translation; thanks **etb09**

## version 2.8.2 (August 24, 2013)

- table names are now enclosed in grave accents for all the methods that take a table name as argument: dcount, delete, dlookup, dmax, dsum, get_table_columns, insert, insert_bulk, insert_update, select, truncate and update; this allows working with tables having special characters in their name
- minor performance optimizations in the debug console's JavaScript code

## version 2.8.1 (August 02, 2013)

- fixed an issue introduced in the previous release generated by the changing of the arguments order in the "query" method and which affected some of the library's methods; thanks **ziggy**
- inversed the order of last 2 arguments of the "select" method ("calc_rows" now comes before "highlight"); if you were using this argument make sure to addapt your code; thanks **Prathamesh Gharat**
- fixed a bug where when setting a query to be highlighted the debugging console would actually open and show the respective query *only* if the library's "minimize_console" attribute was set to FALSE; now it will work either way
- the "connect" method has 2 new arguments: "port" and "socket"; because of this, the "connect" argument is now the last argument of the method so make sure you update your code if you have to; thanks **Corey**
- proper declaration private and public variables
- some performance tweaks
- fixed some issues in the documentation; thanks **Mark**
- the project is now available on [GitHub](https://github.com/stefangabos/Zebra_Database) and also as a [package for Composer](https://packagist.org/packages/stefangabos/zebra_database)

## version 2.8 (March 15, 2013)

- dropped support for PHP 4; minimum required version is now PHP 5
- dropped support for PHP's *mysql* extension, which is [officially deprecated as of PHP v5.5.0](http://php.net/manual/en/changelog.mysql.php) and will be removed in the future; the extension was originally introduced in PHP v2.0 for MySQL v3.23, and no new features have been added since 2006; the library now relies on PHP's [mysqli](http://php.net/manual/en/book.mysqli.php) extension
- removed the "is_new" argument from the "connect" method as it's not needed by the mysqli extension; this means that "connect" is now the 5th argument and that this may break your code so be sure to update accordingly
- inversed the order of last 2 arguments of the "query" method ("calc_rows" now comes before "highlight"); <strong style="color: #c40000;">if you were using this argument make sure to addapt your code or risk opening a black-hole</strong>
- changed an instance of mktime() to time() as it was giving a "PHP Strict Standards" error since PHP 5.3.0
- fixed a bug when specifying custom paths for the "write_log" method ; thanks **Andrei Bodeanschi**
- fixed an issue where setting "cache_path" to a path without trailing slash would break the script
- fixed an issue where setting the caching time to 0 would still create (empty) cache files
- the JS and CSS files used by the debugger window are now loaded "protocol-less" in order to solve those mixed-content errors; thanks to **Mark Bjaergager**
- tweaked the CSS file a bit

## version 2.7.3 (July 16, 2012)

- the library now tries to write errors to the system log (if PHP is configured so; read more [here](http://www.php.net/manual/en/errorfunc.configuration.php#ini.log-errors)) when the "debug" property is set to FALSE (as when the "debug" property is set to TRUE the error messages are reported in the debug console)
- the library will now show a warning message in the debug console if the "memcache" extension is loaded but it is not used
- cached data is now gzcompress-ed and base64_encoded which means cached data is a bit more secure and a bit more faster to load; thanks to **PunKeel** for suggesting this a while ago
- changed (again!) the order of the arguments for the "select" method - "limit" now comes before "order"; <em style="color: #c40000;">note that this update made the select() method backward incompatible and that you will have to change the order of the arguments for this to work!</em>
- added a small example on how to use caching through memcache; see the documentation for the "caching_method" property

## version 2.7.2 (April 07, 2012)

- fixed a bug that most likely appeared since 2.7, where the "seek" method (and any method relying on it, like all the "fetch" methods) would produce a warning in the debug console if there were no records in the sought resource
- fixed a bug where NULL could not be used in the "replacements" array of a query; thanks to **בניית אתרים**

## version 2.7.1 (February 10, 2012)

- the select() method took arguments in a different order than specified in the documentation; thanks to **Andrei Bodeanschi**; <em style="color: #c40000;">note that this update made the select() method backward incompatible and that you will have to change the order of the arguments for this to work!</em>
- fixed a bug where the update() and insert_update() methods were not working if in the array with the columns to update, the INC() keyword was used with a replacement marker instead of a value, and the actual value was given in the replacements array; thanks to **Andrei Bodeanschi**
- fixed a bug where the insert_update() method was not working when the only update field used the INC() keyword; the generated query contained an invalid comma between UPDATE and the field name; thanks to **Allan**

## version 2.7 (January 07, 2012)

- added support for caching query results using memcache. thanks to **Balazs** for suggesting it a while ago, and to **Ovidiu Mihalcea** for introducing me to memcache
- fixed a bug where the script would crash if the object was instantiated more than once and the *language* method was being called for each of the instances; thanks to **Edy Galantzan**
- completely rewritten the dlookup method which was not working correctly if anything else than a comma separated list of column names was used (like an expression, for example); thanks to **Allan**
- the "connect" method can now take an additional argument instructing it to connect to the database right away rather than using a "lazy" connection
- fixed a bug where some of the elements in the debug console were incorrectly inheriting the page's body color

## version 2.6 (September 03, 2011)

- changed the name of "get_columns" method to "get_table_columns" as it returned the number of columns in a given table, and added a new "get_columns" method which takes as argument a resource and returns the number of columns in the given resource
- some documentation clarifications

## version 2.5 (July 02, 2011)

- a new method is now available: "get_link" which returns the MySQL link identifier associated with the current connection to the MySQL server. Why as a separate method? Because the library uses "lazy connection" (it is not actually connecting to the database until first query is executed) there's no link identifier available when calling the *connect* method.
- a new argument is now available for the *insert* and *insert_bulk* methods which allows the creation of INSERT IGNORE queries which will skip records that would cause a duplicate entry for a primary key.
- the default value of the "debug" property was set to FALSE

## version 2.4 (June 20, 2011)

- fixed a bug with the *insert_bulk* method (thanks to **Edy Galantzan** for reporting)
- added a new method: *table_exists* which checks to see if a table with the name given as argument exists in the database
- the *select* method now also accepts *limit* and *order* arguments; due to this change, this method is not compatible with previous versions (thanks to **Monil** for suggesting this)
- some documentation refinements

## version 2.3 (April 15, 2011)

- fixed a bug where the script would generate a warning if the "update" method was called with invalid arguments
- changed how the *insert_bulk* method needs to receive arguments, making it more simple to use

## version 2.2 (March 05, 2011)

- fixed a bug where the "select" method war returning a boolean value rather than a resource (thanks to **Monil**)
- the class now uses "lazy connection" meaning that it will not actually connect to the database until the first query is run
- the debug console now shows also session variables
- the "show_debug_console" method can now be instructed to return output rather than print it to the screen
- the highlighter now highlights more keywords
- improved documentation for the "connect" method

## version 2.1 (January 27, 2011)

- fixed a bug where the console inherited CSS properties from the parent application
- fixed some bugs in the JavaScript file that would break the code when parent application was running MooTools
- transactions are now supported
- added a new "insert_bulk" method which allows inserting multiple values into a table using a single query (thanks **Sebi P.** for the suggestion)
- added a new "insert_update" method which will create INSERT statements with ON DUPLICATE UPDATE (thank **Sebi P.** for the suggestion)
- enhanced the "update" method
- the debug console now shows a warning if no charset and collation was specified
- corrections to the documentation

## version 2.0 (January 21, 2011)

- the entire code was improved and some of the properties as well as method names were changed and, therefore, this version breaks compatibility with earlier versions
- fixed a bug where the script would try to also cache action queries; thanks to **Romulo Gomez**
- fixed a bug in the "seek" method
- fixed a bug where on some configurations of Apache/PHP the script would not work
- fixed a bug where if there was a connection error or MySQL generated an error and the debug console was minimized, it would not be shown automatically
- fixed a bug where the "dlookup" method would not return escaped column names (i.e. `order`)
- fixed a bug where the "found_rows" property was incorrect for cached queries
- fixed a bug where the debug console would improperly manage columns enclosed in ` (backtick)
- fixed a bug that caused improper display of some strings in the debug console
- added a new method "select" - a shorthand for selecting queries
- added a new method "get_columns" - returns information about a given table's columns
- added a new method "implode" - similar to PHP's own implode() function, with the difference that this method "escapes" imploded elements and also encloses them in grave accents
- added a new method "set_charset" - sets the characters set and the collation of the database
- improved functionality of fetch_assoc_all() and fetch_obj_all() methods
- the debug console shows more information and in a much better and organized way
- rewritten the method for logging queries to a txt file making the output very easy to read
- dropped the XTemplate templating engine in order to improve speed; every aspect of the debug console can still be changed through the CSS file

## version 1.1.4 (July 24, 2008)

- fixed a bug in the update() method when calling the method without the replacements argument

## version 1.1.3 (June 02, 2008)

- fixed a bug that was causing an E_WARNING error when the caching folder could not be found
- fixed a minor issue that would trigger an error message if replacements were specified even though there was nothing to replace
- documentation is now clearer on how to define the caching folder

## version 1.1.2b (May 08, 2008)

- fixed a huge bug in the highlighter when using $replacements
- fixed an issue where when calling a function/method that executes a method of the class by using of call_user_func_array() and friends, will produce a warning message due to the fact that, in such cases, the information returned by debug_backtrace() function is incomplete
- fixed a small issue in the template file that would produce an odd output when not having anything in the "messages" section

## version 1.1.2 (April 01, 2008)

- fixed a bug where the debug console's position could be influenced by the host application's stylesheet
- fixed a minor bug in the "log_debug_info" method
- fixed a few minor bugs
- added new methods - "fetch_assoc_all" and "fetch_obj_all" which will fetch all the rows in a record set as an associative array or an array of objects respectively; "get_tables" - returns all the tables in the currently used database; "get_table_status" - returns useful information on all or only on specific tables; "optimize" - automatically optimizes tables that have overhead (unused, lost space)
- full backtrace is now available in the debug console
- debug console is now a bit smarter and it does not highlight keywords in strings; also knows some more MySQL keywords
- more accurate reporting of duplicate queries
- better error reporting for when not being able to connect to the MySQL server or select a database

## version 1.1.1 (December 03, 2007)

- fixed a bug in the close() method; thanks **mokster**
- some documentation refinements (thanks to Vincent van Daal)

## version 1.1.0 (September 15, 2007)

- fixed a bug where calling on a fetch method and on a cached query would send the script into an infinite loop
- improved the speed of the "dlookup" method by adding LIMIT to it; thanks to **A.Leeming**
- added some new methods: "close" (alias of mysql_close()), "log_debug_info" which writes debug information to a log file, and "seek" - alias of mysql_data_seek()
- the "connect" method now returns the link identifier of the connection, which can be later used for closing the connection with the "close" method
- the "connect" method now returns the link identifier of the connection, which can be later used for closing the connection with the "close" method
- some documentation refinements; thanks **Vincent van Daal**
- completely rewritten debug console's template file and stylesheet

## version 1.0.9 (May 30, 2007)

- fixed a bug where the script would crash upon executing queries like SHOW TABLES, DESCRIBE, etc
- fixed a bug where due to a typo, no error message was shown if database could not be selected
- fixed a bug with $replacements containing apostrophes
- queries can now be cached
- new methods were added: "delete", "truncate", "insert" and "update" which are
- previously, all records returned by a SELECT query were shown in the debug console and that could crash the script if there were queries returning LOTS of rows; now there's a new property called "showMaxRows" instructing the script on how many rows returned by SELECT queries to be shown in the debug console (thanks **Dee S.**)
- the "debug" property is now TRUE by default
- lots of code cleanups and documentation refinements

## version 1.0.8 (January 28, 2007)

- THIS VERSION BREAKS COMPATIBILITY WITH PREVIOUS ONES!
- fixed a bug with $replacements containing question marks; thanks **Joeri**
- the debug console now shows backtracing information
- a new method was added: "fetch_obj" which is an alias of MySQL's mysql_fetch_object function
- warnings of duplicate queries were incorrectly being displayed in the console window

## version 1.0.7 (November 24, 2006)

- the debug console now also shows the result of EXPLAIN for SELECT queries
- queries can now be highlighted in debug console by setting the newly added $highlight argument for most of the methods
- the debug console now reports if two or more queries returned the same records (previously this feature was based on comparing the MySQL statements rahter than on the returned records)
- a new "halt" method was added; this stops the execution of the script at the line where is called and displays the debug console (if the "debug" property is set to TRUE and the viewer's IP address is in the allowed range set by the "debuggerIP" property)
- fixed some Java Script issues with the debug console
- some code cleanup and documentation refinements

## version 1.0.6 (October 21, 2006)

- fixed a bug where it was not possible to connect to two different databases by running two instances of this class in the same script
- fixed a bug where specifying two or more columns to be returned by the "dlookup" method would produce a warning if the column names were separated by a comma AND a space; there were no warnings if there was NO SPACE just one comma separating the column names
- fixed a bug where the "haltOnErrors" property was not implemented correctly and the script would actually be halted only on a few exceptional cases
- fixed a bug where the language could not be changed from the default one
- added a new method "setLanguage"
- the debug console can be instructed to appear only for a specific IP address
- the debug console now displays the actual returned rows for SELECT queries (thanks **Zed**)
- each entry of each tab in the debug console can now be collapsed/expanded

## version 1.0.5 (October 02, 2006)

- fixed a bug where the "insert_id()" method was not returning correct values (thanks arlc)
- introduced a new boolean property called "haltOnErrors"; when set to TRUE the execution of the script will be halted upon fatal errors and the debug console will be shown (if the "debug" property is set to TRUE)
- the debug console now highlights more keywords
- the debug console now shows MySQL keywords in upper case

## version 1.0.4 (September 03, 2006)

- fixed a bug where the "affectedRows" property always returned 1
- fixed a bug where query execution time was incorrect in PHP 4
- fixed a bug where execution time was also computed for unsuccessful queries
- symbols are now highlighted in the debug console
- added a new method: "insert_id", alias of mysql_insert_id() function
- the debug console now shows the number of returned rows for SELECT queries; this information is also available by reading the newly added "returnedRows" property
- the debug console can now be minimized both in real-time or by default by setting the newly added "minimizeDebugger" property
- the debug console shows queries in a more readable way
- the debug console now highlights more keywords
- tweaked various aesthetical aspects of the debug console
- debug information now can be logged to a file instead of being outputted to the screen

## version 1.0.3 (August 12, 2006)

- the $_FILES and $_SESSION superglobals are now also shown in the debug console
- properties now have default values in PHP 4

## version 1.0.2 (August 10, 2006)

- the debug console will now report if the same query was run more than once
- fixed a bug where the "_connected" method was reporting errors to the debug console; because it is a private method, it should report to the method that called it
- an example is now available in the downloadable package

## version 1.0.1 (August 05, 2006)

- the "escape_string" method now escapes any values - in previous version it didn't escape numbers

## version 1.0 (July 20, 2006)

- initial release
