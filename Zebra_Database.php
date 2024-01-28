<?php

/**
 *  A compact, lightweight yet feature-rich PHP MySQLi database wrapper providing methods for interacting
 *  with MySQL databases that are more secure, powerful and intuitive than PHP's default ones.
 *
 *  Can provides debugging information when called from **CLI** (command-line interface) and supports logging queries
 *  done through **AJAX** requests.
 *
 *  Read more {@link https://github.com/stefangabos/Zebra_Database here}
 *
 *  @author     Stefan Gabos <contact@stefangabos.ro>
 *  @version    2.11.1 (last revision: January 28, 2024)
 *  @copyright  © 2006 - 2024 Stefan Gabos
 *  @license    https://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE
 *  @package    Zebra_Database
 */
class Zebra_Database {

    /**
     *  The number of rows affected after running an `INSERT`, `UPDATE`, `REPLACE` or `DELETE` query.
     *
     *  See the {@link returned_rows} property for getting the number of rows returned by `SELECT` queries.
     *
     *  <code>
     *  // update some columns in a table
     *  $db->update('table', array(
     *      'column_1'  =>  'value 1',
     *      'column_2'  =>  'value 2',
     *  ), 'id = ?', array($id));
     *
     *  // print the number of affected rows
     *  echo $db->affected_rows;
     *  </code>
     *
     *  @var integer
     */
    public $affected_rows;

    /**
     *  Should escaped variables be also enclosed in single quotes?
     *
     *  Default is `TRUE`
     *
     *  @since  2.9.13
     *
     *  @var boolean
     */
    public $auto_quote_replacements = true;

    /**
     *  Path where to store cached queries results.
     *
     *  *The path must be relative to your working path and not the path of this library!*
     *
     *  @var string
     */
    public $cache_path;

    /**
     *  The method to be used for caching query results.
     *
     *  Can be either:
     *
     *  - **disk**      -   query results are cached as files on the disk at the path specified by {@link cache_path}
     *
     *  - **session**   -   query results are cached in the session (use this only for smaller data sets)<br>
     *                      *When using this method, the library expects an active session and will trigger a fatal error
     *                      otherwise!*
     *
     *  - **memcache**  -   query results are cached using a {@link https://memcached.org/about memcache} server<br>
     *                      When using this method make sure to also set the appropriate values for {@link memcache_host},
     *                      {@link memcache_port} and optionally {@link memcache_compressed} **prior** to calling the
     *                      {@link connect()} method! Failing to do so will disable caching.
     *                      <br>
     *                      *When using this method, PHP must be compiled with the {@link https://pecl.php.net/package/memcache memcache}
     *                      extension and, if {@link memcache_compressed} property is set to `TRUE`, needs to be configured
     *                      with `--with-zlib[=DIR]`*
     *
     *  - **redis**     -   query results are cached using a {@link https://redis.io/ redis} server<br>
     *                      When using this method make sure to also set the appropriate values for {@link redis_host},
     *                      {@link redis_port} and optionally {@link redis_compressed} **prior** to calling the
     *                      {@link connect()} method! Failing to do so will disable caching.
     *                      <br>
     *                      *When using this method, PHP must be compiled with the {@link https://pecl.php.net/package/redis redis}
     *                      extension and, if {@link redis_compressed} property is set to `TRUE`, needs to be configured
     *                      with `--with-zlib[=DIR]`*
     *
     *  <code>
     *  // the host where memcache is listening for connections
     *  $db->memcache_host = 'localhost';
     *
     *  // the port on which memcache is listening for connections
     *  $db->memcache_port = 11211;
     *
     *  // for this to work, PHP needs to be configured with --with-zlib[=dir] !
     *  // set it to FALSE otherwise
     *  $db->memcache_compressed = true;
     *
     *  // cache queries using the memcache server
     *  $db->caching_method = 'memcache';
     *
     *  // only now it is the time to connect
     *  $db->connect(...)
     *  </code>
     *
     *  *Caching is done on a per-query basis by setting the `cache` argument when calling some of the library's methods
     *  like {@link query()}, {@link select()}, {@link dcount()}, {@link dlookup()}, {@link dmax()} and {@link dsum()}!*
     *
     *  >   Warnings related to the presence of memcache and redis can be disabled by setting the {@link disable_warnings} property.
     *
     *  Default is `disk`
     *
     *  @since  2.7
     *
     *  @var string
     */
    public $caching_method = 'disk';

    /**
     *  Turns debugging on or off.
     *
     *  See also {@link $debug_ajax}.
     *
     *  The property can take one of the following values:
     *
     *  -   **boolean TRUE**<br>
     *      Setting this property to a boolean `TRUE` will instruct the library to generate debugging information for each
     *      query it executes and show the information *on the screen* when script execution ends.
     *
     *      >   This is also works when called from CLI!
     *
     *  -   **a string**<br>
     *      Setting this property to a string will instruct the library to turn debugging on when the given string is
     *      present as argument in the query string (in the URL) and has the value of `1` (i.e `?show_debug=1`)
     *      <br><br>*Useful for turning debugging on on the fly. If you decide to use this in production, make
     *      sure to not use an easily guessable value!*<br><br>
     *      When debugging is turned on this way, a session cookie (a cookie that expires when the browser is closed) will
     *      also be set so that the query string argument doesn't need to be present for subsequent requests. Debugging
     *      can also be turned off by setting said query string argument to `0` (i.e `?show_debug=0`). The cookie's name
     *      can be set via the {@link debug_cookie_name} property.
     *
     *      >   This is also works when called from CLI!
     *
     *  -   **an array([bool]daily, [bool]hourly, [bool]backtrace)**<br>
     *      Setting this property to an array like above will instruct the library to generate debugging information for
     *      each query it executes and write the information to a log file when script execution ends.
     *
     *          -   the value of the first entry (daily) indicates whether the log files should be grouped by days or not;
     *              if set to `TRUE`, log files will have their name in the form of `log_ymd.txt`, where `y`, `m` and `d`
     *              represent the two digit values of year, month and day, respectively.
     *
     *          -   the value of the second entry (hourly) indicates whether the log files should be grouped by hours or not;
     *              if set to `TRUE`, log files will have their name in the form of `log_ymd_h.txt`, where `y`, `m` and `d`
     *              represent the two digit values of year, month and day, respectively, while `h` represents the two digit
     *              value of the hour.<br>
     *              *Note that if this argument is set to `TRUE`, the first argument will be automatically considered as `TRUE`*
     *
     *          -   the value of the third entry (backtrace) indicates whether backtrace information (where the query was
     *              called from) should also be written to the log file.<br><br>
     *              *The default values for all the entries is `FALSE` and all are optional, therefore setting the value of
     *              this property to an empty array is equivalent of setting it to `array(false, false, false)`*
     *
     *  -   **boolean FALSE**<br>
     *      Setting this property to `FALSE` will instruct the library to not generate debugging information for any of the
     *      queries it executes. Even so, if an error occurs the library will try to log the error to PHP's error log
     *      file, if your environment is {@link https://www.php.net/manual/en/errorfunc.configuration.php#ini.log-errors
     *      configured to do so} !
     *
     *  >   It is highly recommended to set the value of this property to `FALSE` on the production environment. Generating
     *  the debugging information may consume a lot of resources and is meant to be used **only** in the development process!
     *
     *  <code>
     *  // log debug information instead of showing it on screen
     *  // log everything in one single file (not by day/hour) and also show backtrace information
     *  $db->`debug = array(false,` false, true)
     *
     *  // disable the generation of debugging information
     *  $db->debug = false;
     *
     *  // turn debugging on when "debug_db" is found in the query string and has the value "1"
     *  // (meaning that you have to have ?debug_db=1 in your URL)
     *  $db->debug = 'debug_db';
     *  </code>
     *
     *  Default is `TRUE`
     *
     *  @since  turning debugging on/off via a query string is available since 2.10.0
     *
     *  @var boolean|string|array<boolean>
     */
    public $debug = true;

    /**
     *  Enables logging of queries done through AJAX requests.
     *
     *  When set to `TRUE` all AJAX requests sent by your page will be captured and checked for logs generated by
     *  `Zebra Database`.
     *
     *  >   Note that when this is enabled HTML code will be appended to the result of the AJAX requests!<br>Therefore,
     *      depending on your use-case, having this enabled could break some of your page's functionality.
     *
     *  Default is `FALSE`
     *
     *  @since 2.11.0
     *
     *  @var boolean
     */
    public $debug_ajax = false;

    /**
     *  An array of IP addresses for which to show the debugging console / write to log file, if the {@link debug} property
     *  is **not** set to `FALSE`.
     *
     *  <code>
     *  // show the debugging console only to specific IPs
     *  $db->debugger_ip = array('xxx.xxx.xxx.xxx', 'yyy.yyy.yyy.yyy');
     *  </code>
     *
     *  Default is an empty array
     *
     *  @since  1.0.6
     *
     *  @var array<string>
     */
    public $debugger_ip = array();

    /**
     *  If debugging is enabled on the fly via the presence of a query string argument (see the {@link debug} property),
     *  a cookie is set so that the query string is not required to be present in subsequent requests.
     *
     *  This property sets that cookie's name.
     *
     *  Default value is `zebra_db`
     *
     *  @since 2.10.0
     *
     *  @var string
     */
    public $debug_cookie_name = 'zebra_db';

    /**
     *  Indicates whether {@link https://php.net/manual/en/function.debug-backtrace.php backtrace} information should be
     *  shown in the debugging console.
     *
     *  Default is `TRUE`
     *
     *  @since  2.5.9
     *
     *  @var boolean
     */
    public $debug_show_backtrace = true;

    /**
     *  Show a link for editing the query in your favorite database manager like (like {@link https://www.phpmyadmin.net/ phpMyAdmin}
     *  or {@link https://www.adminer.org/ Adminer}).
     *
     *  It should be an HTML anchor (a link) to your favorite database manager where you build the query string according
     *  to the requirements of said database manager using the following placeholders:
     *
     *  - %host%
     *  - %user%
     *  - %password%
     *  - %database%
     *  - %port%
     *  - %socket%
     *  - %query%
     *
     *  ...were any of those placeholders will be replaced by the values you have passed on to {@link connect}, except
     *  for the *%query%* placeholder which will be replaced by the respective query.
     *
     *  Here's how to set up the value for opening the query in {@link https://www.adminer.org/ Adminer}:
     *
     *  <code>
     *  <a href="path/to/adminer.php?server=%host%:%port%&db=%database%&sql=%query%" target="adminer">edit in adminer</a>
     *  </code>
     *
     *  *I don't use {@link https://www.phpmyadmin.net/ phpMyAdmin} so if you manage to set it up, please share the result
     *  with me so I can add it to the docs. Thanks!*
     *
     *  >   Be **VERY CAREFUL** when using this feature and make sure you **do not** expose your credentials in the links
     *      you build.
     *
     *  Setting it to `false` will disable the feature.
     *
     *  Default is a link to the documentation on how to set the link up.
     *
     *  @since 2.11.0
     *
     *  @var boolean|string
     */
    public $debug_show_database_manager = false;

    /**
     *  Indicates whether queries should be {@link https://dev.mysql.com/doc/refman/8.0/en/explain.html EXPLAIN}ed in the
     *  debugging console.
     *
     *  Default is `TRUE`
     *
     *  @since  2.5.9
     *
     *  @var boolean
     */
    public $debug_show_explain = true;

    /**
     *  Indicates which of `$_REQUEST`, `$_POST`, `$_GET`, `$_SESSION`, `$_COOKIE`, `$_FILES` and `$_SERVER` superglobals
     *  should be available in the debugging console, under the *globals* section.
     *
     *  Can be set to either boolean `TRUE` or `FALSE` as a global setting, or as an associative array where each option's
     *  visibility can be individually be set, like in the example below:
     *
     *  <code>
     *  $db->debug_show_globals(array(
     *      'request'   =>  true,
     *      'post'      =>  true,
     *      'get'       =>  true,
     *      'session'   =>  true,
     *      'cookie'    =>  true,
     *      'files'     =>  true,
     *      'server'    =>  true,
     *  ));
     *  </code>
     *
     *  Default is `TRUE`
     *
     *  @since  2.9.14
     *
     *  @var boolean|array<string,boolean>
     */
    public $debug_show_globals = true;

    /**
     *  Sets the number of records returned by `SELECT` queries to be shown in the debugging console.
     *
     *  Setting this to `0` or `FALSE` will disable this feature.
     *
     *  <code>
     *  // show 50 records
     *  $db->debug_show_records(50);
     *  </code>
     *
     *  *Be aware that having this property set to a high number (hundreds) and having queries that di return that many
     *  rows can cause your script to crash due to memory limitations. In this case you should either lower the value
     *  of this property or try and set PHP's memory limit higher using:*
     *
     *  <code>
     *  // set PHP's memory limit to 20 MB
     *  ini_set('memory_limit','20M');
     *  </code>
     *
     *  Default is `20`
     *
     *  @since  1.0.9
     *
     *  @var integer
     */
    public $debug_show_records = 20;

    /**
     *  By default, if {@link set_charset()} method is not called, caching is used and {@link https://memcached.org/about memcache}
     *  or {@link https://redis.io/ redis} are available but none of them is used, a warning message will be displayed
     *  in the debugging console.
     *
     *  The ensure that data is both properly saved and retrieved to and from the database, this method should be called
     *  first thing after connecting to a database.
     *
     *  If you don't want to call this method nor do you want to see the warning, set this property to `TRUE`
     *
     *  Default is `FALSE`
     *
     *  @var boolean
     */
    public $disable_warnings = false;

    /**
     *  After running a `SELECT` query through {@link select()}, {@link query()} or {@link query_unbuffered()} methods and
     *  having the *calc_rows* argument set to `TRUE`, this property will contain the number of records that **would** have
     *  been returned **if** there was no `LIMIT` applied to the query.
     *
     *  If *calc_rows* is `FALSE`, or it is `TRUE` but there is no `LIMIT` applied to the query, this property's value
     *  will be the same as the value of the {@link returned_rows} property.
     *
     *  *For {@link query_unbuffered unbuffered queries} the value of this property will be available **only** after
     *  iterating over **all** the records with either {@link fetch_assoc()} or {@link fetch_obj()} methods. Until then,
     *  the value will be **0**!*
     *
     *  <code>
`     *  // let's assume` that "table" has 100 rows but we're only selecting the first 10 of those
     *  // the last argument of the method tells the library to get the total number of records in the table
     *  $db->query('
     *      SELECT
     *          *
     *      FROM
     *          table
     *      WHERE
     *          something = ?
     *      LIMIT
     *          10
     *  ', array($somevalue), false, true);
     *
     *  // prints "10"
     *  // as this is the number of records
     *  // returned by the query
     *  echo $db->returned_rows;
     *
     *  // prints "100"
     *  // because we set the "calc_rows" argument of the
     *  // "query" method to TRUE
     *  echo $db->found_rows;
     *  </code>
     *
     *  @var integer|string
     */
    public $found_rows;

    /**
     *  When the value of this property is set to `TRUE`, the execution of the script will be halted after the first
     *  unsuccessful query and the debugging console will be shown (or debug information will be written to the log file if configured
     *  so), **if** the value of the {@link debug} property is **not** `FALSE` and the viewer's IP address is in the
     *  {@link debugger_ip} array (or {@link debugger_ip} is an empty array).
     *
     *  <code>
     *  // don't stop execution for unsuccessful queries (if possible)
     *  $db->halt_on_errors = false;
     *  </code>
     *
     *  Default is `TRUE`
     *
     *  @since  1.0.5
     *
     *  @var  boolean
     */
    public $halt_on_errors = true;

    /**
     *  Path where to store the log files when the {@link debug} property is set to an array OR a callback function to
     *  pass the log information to instead of being written to a file.
     *
     *  **The path is relative to your working directory.**
     *
     *  *Use `.` (dot) for the current directory instead of an empty string or the log file will be written to the server's
     *  root.*
     *
     *  If a full path is specified (including an extension) the log file's name will be used from there. Otherwise, the
     *  log file's name will be `log.txt`
     *
     *  *At the given path the library will attempt to create a file named "log.txt" (or variations as described
     *  {@link debug here}) so the appropriate rights will need to be granted to the script!*
     *
     *  **IF YOU'RE LOGGING, MAKE SURE YOU HAVE A CRON JOB THAT DELETES THE LOG FILES FROM TIME TO TIME!**
     *
     *  Default is `""` (an empty string) - log files are created in the root of your server.
     *
     *  If you are using a callback function, the function receives the following arguments:
     *
     *  -   the debug information, as a string, just like it would go into the log file
     *  -   the backtrace information, as a string, just like it would go into the log file - if {@link debug_show_backtrace}
     *      is set to `FALSE`, this will be an empty string
     *
     *  @var string
     */
    public $log_path = '';

    /**
     *  Time (in seconds) after which a query will be considered as running for too long.
     *
     *  If a query's execution time exceeds this number a notification email will be automatically sent to the address
     *  defined by {@link notification_address}, having {@link notifier_domain} in subject.
     *
     *  <code>
     *  // consider queries running for more than 5 seconds as slow and send email
     *  $db->max_query_time = 5;
     *  </code>
     *
     *  Default is `10`
     *
     *  @var integer
     */
    public $max_query_time = 10;

    /**
     *  Setting this property to `TRUE` will instruct to library to compress the cached results (using `zlib`).
     *
     *  *For this to work, PHP needs to be configured with `--with-zlib[=DIR]`!*
     *
     *  *Set this property only if you are using `memcache` as {@link caching_method}.*
     *
     *  Default is `FALSE`
     *
     *  @since  2.7
     *
     *  @var boolean
     */
    public $memcache_compressed = false;

    /**
     *  The host where the memcache server is listening for connections.
     *
     *  *Set this property only if you are using `memcache` as {@link caching_method}.*
     *
     *  Default is `FALSE`
     *
     *  @since  2.7
     *
     *  @var boolean|string
     */
    public $memcache_host = false;

    /**
     *  The prefix for the keys used to identify cached queries in memcache. This allows separate caching of the same
     *  queries by multiple instances of the libraries, or the same instance handling multiple domains on the same
     *  memcache server.
     *
     *  *Set this property only if you are using `memcache` as {@link caching_method}.*
     *
     *  Default is `""` (an empty string)
     *
     *  @since  2.8.4
     *
     *  @var string
     */
    public $memcache_key_prefix = '';

    /**
     *  The port on which the memcache server is listening for connections.
     *
     *  *Set this property only if you are using `memcache` as {@link caching_method}.*
     *
     *  Default is `FALSE`
     *
     *  @since  2.7
     *
     *  @var integer|boolean
     */
    public $memcache_port = false;

    /**
     *  By setting this property to `TRUE` a minimized version of the debugging console will be shown by default instead
     *  of the full-sized one.
     *
     *  Clicking on it will show the full debugging console.
     *
     *  For quick and easy debugging, setting the `highlight` argument of a method that has it will result in the debugging
     *  console being shown at full size and with the respective query visible for inspecting.
     *
     *  Default is `TRUE`
     *
     *  @since  1.0.4
     *
     *  @var boolean
     */
    public $minimize_console = true;

    /**
     *  Email address to which notification emails to be sent when a query's execution time exceeds the number of seconds
     *  set by {@link max_query_time}. The notification email will be automatically sent to the address defined by
     *  {@link notification_address} and having {@link notifier_domain} in subject.
     *
     *  >   Mails are sent using PHP's {@link https://www.php.net/manual/en/function.mail.php mail} function.
     *
     *  <code>
     *  // the email address where to send an email when there are slow queries
     *  $db->notification_address = 'youremail@yourdomain.com';
     *  </code>
     *
     *  @var string
     */
    public $notification_address = '';

    /**
     *  Domain name to be used in the subject of notification emails sent when a query's execution time exceeds the number
     *  of seconds set by {@link max_query_time}.
     *
     *  If a query's execution time exceeds the number of seconds set by {@link max_query_time}, a notification email
     *  will be automatically sent to the address defined by {@link notification_address} and having {@link notifier_domain}
     *  in subject.
     *
     *  >   Mails are sent using PHP's {@link https://www.php.net/manual/en/function.mail.php mail} function.
     *
     *  <code>
     *  // set a domain name so that you'll know where the email comes from
     *  $db->notifier_domain = 'yourdomain.com';
     *  </code>
     *
     *  @var string
     */
    public $notifier_domain = '';

    /**
     *  Setting this property to `TRUE` will instruct to library to compress the cached results (using `zlib`).
     *
     *  *For this to work, PHP needs to be configured with `--with-zlib[=DIR]`*!
     *
     *  *Set this property only if you are using `redis` as {@link caching_method}.*
     *
     *  Default is `FALSE`
     *
     *  @since  2.10.0
     *
     *  @var boolean
     */
    public $redis_compressed = false;

    /**
     *  The host where the redis server is listening for connections.
     *
     *  *Set this property only if you are using `redis` as {@link caching_method}.*
     *
     *  Default is `FALSE`
     *
     *  @since  2.10.0
     *
     *  @var boolean|string
     */
    public $redis_host = false;

    /**
     *  The prefix for the keys used to identify cached queries in redis. This allows separate caching of the same
     *  queries by multiple instances of the libraries, or the same instance handling multiple domains on the same
     *  redis server.
     *
     *  *Set this property only if you are using `redis` as {@link caching_method}.*
     *
     *  Default is `""` (an empty string)
     *
     *  @since  2.10.0
     *
     *  @var string
     */
    public $redis_key_prefix = '';

    /**
     *  The port on which the redis server is listening for connections.
     *
     *  *Set this property only if you are using `redis` as {@link caching_method}.*
     *
     *  Default is `FALSE`
     *
     *  @since  2.10.0
     *
     *  @var integer|boolean
     */
    public $redis_port = false;

    /**
     *  Path to parent of public folder containing the `css` and `javascript` folders.
     *
     *  >   The path must be relative to your `$_SERVER['DOCUMENT_ROOT']`
     *
     *  @var string
     */
    public $resource_path;

    /**
     *  After running a `SELECT` query through {@link select()}, {@link query()} or {@link query_unbuffered()} methods,
     *  this property will contain the number of returned rows.
     *
     *  *For {@link query_unbuffered unbuffered queries} the value of this property will be available **only** after
     *  iterating over **all** the records with either {@link fetch_assoc()} or {@link fetch_obj()} methods. Until then,
     *  the value will be **0**!*
     *
     *  See {@link found_rows} also.
     *
     *  <code>
     *  $db->query('
     *      SELECT
     *          *
     *      FROM
     *          table
     *      WHERE
     *          something = ?
     *      LIMIT
     *          10
     *  ', array($somevalue));
     *
     *  // prints "10"
     *  // as this is the number of records
     *  // returned by the query
     *  echo $db->returned_rows;
     *  </code>
     *
     *  @since  1.0.4
     *
     *  @var integer
     */
    public $returned_rows;

    /**
     *  Array with cached results.
     *
     *  We will use this for fetching and seek
     *
     *  @var array<mixed>
     *  @access private
     */
    private $cached_results = array();

    /**
     *  MySQL link identifier.
     *
     *  @var mixed
     *  @access private
     */
    private $connection = false;

    /**
     *  Array that will store the database connection credentials
     *
     *  @var array<string>
     *  @access private
     */
    private $credentials;

    /**
     *  All debugging information is stored in this array.
     *
     *  @var array<mixed>
     *  @access private
     */
    private $debug_info = array();

    /**
     *  A flag telling the script whether it was called from CLI or in the browser
     *
     *  @var boolean
     *  @access private
     */
    private $is_cli_request = false;

    /**
     *  The language to be used in the debugging console.
     *
     *  Default is "english".
     *
     *  @var string|array<string>
     *  @access private
     */
    private $language = 'english';

    /**
     *  Stores information about the last executed query
     *
     *  @var boolean|integer|object|null
     */
    private $last_result;

    /**
     *  Instance of an opened memcache server connection.
     *
     *  @since 2.7
     *
     *  @var mixed
     *  @access private
     */
    private $memcache = false;

    /**
     *  Stores extra connect options that affect behavior for a connection.
     *
     *  @since 2.9.5
     *
     *  @var array<mixed>
     *  @access private
     */
    private $options = array();

    /**
     *  Absolute path to the library, used for includes
     *
     *  Value is set in the constructor!
     *
     *  @var string
     *  @access private
     */
    private $path;

    /**
     *  Instance of an opened redis server connection.
     *
     *  @since 2.10.0
     *
     *  @var mixed
     *  @access private
     */
    private $redis = false;

    /**
     *  Keeps track of the total time used to execute queries
     *
     *  @var float
     *  @access private
     */
    private $total_execution_time = 0;

    /**
     *  Tells whether a transaction is in progress or not.
     *
     *  Possible values are
     *  -   0, no transaction is in progress
     *  -   1, a transaction is in progress
     *  -   2, a transaction is in progress but an error occurred with one of the queries
     *  -   3, transaction is run in test mode and it will be rolled back upon completion
     *
     *  @var int
     *  @access private
     */
    private $transaction_status = 0;

    /**
     *  Flag telling the library whether to use unbuffered queries or not
     *
     *  @var boolean
     *  @access private
     */
    private $unbuffered = false;

    /**
     *  Array of warnings, generated by the script, to be shown to the user in the debugging console
     *
     *  Default value is set in the constructor!
     *
     *  @var array<string,true>
     *  @access private
     */
    private $warnings;

    /**
     *  All MySQL functions as per {@link https://dev.mysql.com/doc/refman/8.0/en/built-in-function-reference.html}
     *
     *  @var array<mixed>
     *  @access private
     */
    private $mysql_functions = array(

        // spell-checker: disable
        'ABS', 'ACOS', 'ADDDATE', 'ADDTIME', 'AES_DECRYPT', 'AES_ENCRYPT', 'ANY_VALUE', 'AREA', 'ASBINARY', 'ASWKB', 'ASCII',
        'ASIN', 'ASTEXT', 'ASWKT', 'ASYMMETRIC_DECRYPT', 'ASYMMETRIC_DERIVE', 'ASYMMETRIC_ENCRYPT', 'ASYMMETRIC_SIGN',
        'ASYMMETRIC_VERIFY', 'ATAN', 'ATAN2', 'ATAN', 'AVG', 'BENCHMARK', 'BIN', 'BIT_AND', 'BIT_COUNT', 'BIT_LENGTH',
        'BIT_OR', 'BIT_XOR', 'BUFFER', 'CAST', 'CEIL', 'CEILING', 'CENTROID', 'CHAR', 'CHAR_LENGTH', 'CHARACTER_LENGTH',
        'CHARSET', 'COALESCE', 'COERCIBILITY', 'COLLATION', 'COMPRESS', 'CONCAT', 'CONCAT_WS', 'CONNECTION_ID', 'CONTAINS',
        'CONV', 'CONVERT', 'CONVERT_TZ', 'CONVEXHULL', 'COS', 'COT', 'COUNT', 'CRC32', 'CREATE_ASYMMETRIC_PRIV_KEY',
        'CREATE_ASYMMETRIC_PUB_KEY', 'CREATE_DH_PARAMETERS', 'CREATE_DIGEST', 'CROSSES', 'CURDATE', 'CURRENT_DATE',
        'CURRENT_TIME', 'CURRENT_TIMESTAMP', 'CURRENT_USER', 'CURTIME', 'DATABASE', 'DATE', 'DATE_ADD', 'DATE_FORMAT',
        'DATE_SUB', 'DATEDIFF', 'DAY', 'DAYNAME', 'DAYOFMONTH', 'DAYOFWEEK', 'DAYOFYEAR', 'DECODE', 'DEFAULT', 'DEGREES',
        'DES_DECRYPT', 'DES_ENCRYPT', 'DIMENSION', 'DISJOINT', 'DISTANCE', 'ELT', 'ENCODE', 'ENCRYPT', 'ENDPOINT', 'ENVELOPE',
        'EQUALS', 'EXP', 'EXPORT_SET', 'EXTERIORRING', 'EXTRACT', 'EXTRACTVALUE', 'FIELD', 'FIND_IN_SET', 'FLOOR', 'FORMAT',
        'FOUND_ROWS', 'FROM_BASE64', 'FROM_DAYS', 'FROM_UNIXTIME', 'GEOMCOLLFROMTEXT', 'GEOMETRYCOLLECTIONFROMTEXT',
        'GEOMCOLLFROMWKB', 'GEOMETRYCOLLECTIONFROMWKB', 'GEOMETRYCOLLECTION', 'GEOMETRYN', 'GEOMETRYTYPE', 'GEOMFROMTEXT',
        'GEOMETRYFROMTEXT', 'GEOMFROMWKB', 'GEOMETRYFROMWKB', 'GET_FORMAT', 'GET_LOCK', 'GLENGTH', 'GREATEST', 'GROUP_CONCAT',
        'GTID_SUBSET', 'GTID_SUBTRACT', 'HEX', 'HOUR', 'IF', 'IFNULL', 'IN', 'INET_ATON', 'INET_NTOA', 'INET6_ATON',
        'INET6_NTOA', 'INSERT', 'INSTR', 'INTERIORRINGN', 'INTERSECTS', 'INTERVAL', 'IS_FREE_LOCK', 'IS_IPV4',
        'IS_IPV4_COMPAT', 'IS_IPV4_MAPPED', 'IS_IPV6', 'IS_USED_LOCK', 'ISCLOSED', 'ISEMPTY', 'ISNULL', 'ISSIMPLE',
        'JSON_APPEND', 'JSON_ARRAY', 'JSON_ARRAY_APPEND', 'JSON_ARRAY_INSERT', 'JSON_CONTAINS', 'JSON_CONTAINS_PATH',
        'JSON_DEPTH', 'JSON_EXTRACT', 'JSON_INSERT', 'JSON_KEYS', 'JSON_LENGTH', 'JSON_MERGE', 'JSON_OBJECT', 'JSON_QUOTE',
        'JSON_REMOVE', 'JSON_REPLACE', 'JSON_SEARCH', 'JSON_SET', 'JSON_TYPE', 'JSON_UNQUOTE', 'JSON_VALID', 'LAST_DAY',
        'LAST_INSERT_ID', 'LCASE', 'LEAST', 'LEFT', 'LENGTH', 'LINEFROMTEXT', 'LINESTRINGFROMTEXT', 'LINEFROMWKB',
        'LINESTRINGFROMWKB', 'LINESTRING', 'LN', 'LOAD_FILE', 'LOCALTIME', 'LOCALTIMESTAMP', 'LOCATE', 'LOG', 'LOG10', 'LOG2',
        'LOWER', 'LPAD', 'LTRIM', 'MAKE_SET', 'MAKEDATE', 'MAKETIME', 'MASTER_POS_WAIT', 'MAX', 'MBRCONTAINS', 'MBRCOVEREDBY',
        'MBRCOVERS', 'MBRDISJOINT', 'MBREQUAL', 'MBREQUALS', 'MBRINTERSECTS', 'MBROVERLAPS', 'MBRTOUCHES', 'MBRWITHIN', 'MD5',
        'MICROSECOND', 'MID', 'MIN', 'MINUTE', 'MLINEFROMTEXT', 'MULTILINESTRINGFROMTEXT', 'MLINEFROMWKB',
        'MULTILINESTRINGFROMWKB', 'MOD', 'MONTH', 'MONTHNAME', 'MPOINTFROMTEXT', 'MULTIPOINTFROMTEXT', 'MPOINTFROMWKB',
        'MULTIPOINTFROMWKB', 'MPOLYFROMTEXT', 'MULTIPOLYGONFROMTEXT', 'MPOLYFROMWKB', 'MULTIPOLYGONFROMWKB', 'MULTILINESTRING',
        'MULTIPOINT', 'MULTIPOLYGON', 'NAME_CONST', 'NOT IN', 'NOW', 'NULLIF', 'NUMGEOMETRIES', 'NUMINTERIORRINGS',
        'NUMPOINTS', 'OCT', 'OCTET_LENGTH', 'OLD_PASSWORD', 'ORD', 'OVERLAPS', 'PASSWORD', 'PERIOD_ADD', 'PERIOD_DIFF', 'PI',
        'POINT', 'POINTFROMTEXT', 'POINTFROMWKB', 'POINTN', 'POLYFROMTEXT', 'POLYGONFROMTEXT', 'POLYFROMWKB', 'POLYGONFROMWKB',
        'POLYGON', 'POSITION', 'POW', 'POWER', 'PROCEDURE ANALYSE', 'QUARTER', 'QUOTE', 'RADIANS', 'RAND', 'RANDOM_BYTES',
        'RELEASE_ALL_LOCKS', 'RELEASE_LOCK', 'REPEAT', 'REPLACE', 'REVERSE', 'RIGHT', 'ROUND', 'ROW_COUNT', 'RPAD', 'RTRIM',
        'SCHEMA', 'SEC_TO_TIME', 'SECOND', 'SESSION_USER', 'SHA1', 'SHA', 'SHA2', 'SIGN', 'SIN', 'SLEEP', 'SOUNDEX', 'SPACE',
        'SQRT', 'SRID', 'ST_AREA', 'ST_ASBINARY', 'ST_ASWKB', 'ST_ASGEOJSON', 'ST_ASTEXT', 'ST_ASWKT', 'ST_BUFFER',
        'ST_BUFFER_STRATEGY', 'ST_CENTROID', 'ST_CONTAINS', 'ST_CONVEXHULL', 'ST_CROSSES', 'ST_DIFFERENCE', 'ST_DIMENSION',
        'ST_DISJOINT', 'ST_DISTANCE', 'ST_DISTANCE_SPHERE', 'ST_ENDPOINT', 'ST_ENVELOPE', 'ST_EQUALS', 'ST_EXTERIORRING',
        'ST_GEOHASH', 'ST_GEOMCOLLFROMTEXT', 'ST_GEOMETRYCOLLECTIONFROMTEXT', 'ST_GEOMCOLLFROMTXT', 'ST_GEOMCOLLFROMWKB',
        'ST_GEOMETRYCOLLECTIONFROMWKB', 'ST_GEOMETRYN', 'ST_GEOMETRYTYPE', 'ST_GEOMFROMGEOJSON', 'ST_GEOMFROMTEXT',
        'ST_GEOMETRYFROMTEXT', 'ST_GEOMFROMWKB', 'ST_GEOMETRYFROMWKB', 'ST_INTERIORRINGN', 'ST_INTERSECTION', 'ST_INTERSECTS',
        'ST_ISCLOSED', 'ST_ISEMPTY', 'ST_ISSIMPLE', 'ST_ISVALID', 'ST_LATFROMGEOHASH', 'ST_LENGTH', 'ST_LINEFROMTEXT',
        'ST_LINESTRINGFROMTEXT', 'ST_LINEFROMWKB', 'ST_LINESTRINGFROMWKB', 'ST_LONGFROMGEOHASH', 'ST_MAKEENVELOPE',
        'ST_MLINEFROMTEXT', 'ST_MULTILINESTRINGFROMTEXT', 'ST_MLINEFROMWKB', 'ST_MULTILINESTRINGFROMWKB', 'ST_MPOINTFROMTEXT',
        'ST_MULTIPOINTFROMTEXT', 'ST_MPOINTFROMWKB', 'ST_MULTIPOINTFROMWKB', 'ST_MPOLYFROMTEXT', 'ST_MULTIPOLYGONFROMTEXT',
        'ST_MPOLYFROMWKB', 'ST_MULTIPOLYGONFROMWKB', 'ST_NUMGEOMETRIES', 'ST_NUMINTERIORRING', 'ST_NUMINTERIORRINGS',
        'ST_NUMPOINTS', 'ST_OVERLAPS', 'ST_POINTFROMGEOHASH', 'ST_POINTFROMTEXT', 'ST_POINTFROMWKB', 'ST_POINTN',
        'ST_POLYFROMTEXT', 'ST_POLYGONFROMTEXT', 'ST_POLYFROMWKB', 'ST_POLYGONFROMWKB', 'ST_SIMPLIFY', 'ST_SRID',
        'ST_STARTPOINT', 'ST_SYMDIFFERENCE', 'ST_TOUCHES', 'ST_UNION', 'ST_VALIDATE', 'ST_WITHIN', 'ST_X', 'ST_Y',
        'STARTPOINT', 'STD', 'STDDEV', 'STDDEV_POP', 'STDDEV_SAMP', 'STR_TO_DATE', 'STRCMP', 'SUBDATE', 'SUBSTR', 'SUBSTRING',
        'SUBSTRING_INDEX', 'SUBTIME', 'SUM', 'SYSDATE', 'SYSTEM_USER', 'TAN', 'TIME', 'TIME_FORMAT', 'TIME_TO_SEC', 'TIMEDIFF',
        'TIMESTAMP', 'TIMESTAMPADD', 'TIMESTAMPDIFF', 'TO_BASE64', 'TO_DAYS', 'TO_SECONDS', 'TOUCHES', 'TRIM', 'TRUNCATE',
        'UCASE', 'UNCOMPRESS', 'UNCOMPRESSED_LENGTH', 'UNHEX', 'UNIX_TIMESTAMP', 'UPDATEXML', 'UPPER', 'USER', 'UTC_DATE',
        'UTC_TIME', 'UTC_TIMESTAMP', 'UUID', 'UUID_SHORT', 'VALIDATE_PASSWORD_STRENGTH', 'VALUES', 'VAR_POP', 'VAR_SAMP',
        'VARIANCE', 'VERSION', 'WAIT_FOR_EXECUTED_GTID_SET', 'WAIT_UNTIL_SQL_THREAD_AFTER_GTIDS', 'WEEK', 'WEEKDAY',
        'WEEKOFYEAR', 'WEIGHT_STRING', 'WITHIN', 'X', 'Y', 'YEAR', 'YEARWEEK'
        // spell-checker: enable

    );

    /**
     *  A flag used for deprecated features (PHP < 5.4.0)
     *
     *  @var boolean
     *  @access private
     */
    private $use_deprecated;

    /**
     *  Constructor of the class
     *
     *  @return void
     */
    public function __construct() {

        // if the mysqli extension is not loaded, stop execution
        if (!extension_loaded('mysqli')) trigger_error('Zebra_Database: mysqli extension is not enabled', E_USER_ERROR);

        // get path of class and replace (on a windows machine) \ with /
        // this path is to be used for all includes as it is an absolute path
        $this->path = preg_replace('/\\\/', '/', dirname(__FILE__));

        // sets default values for the class' properties
        // public properties
        $this->cache_path = rtrim($this->path, '/') . '/cache/';

        // let developers know about this feature
        $this->debug_show_database_manager = '<a href="https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#var$debug_show_database_manager" style="color: #C40000" title="Open in favorite database manager">SET UP</a>';

        $this->language($this->language);

        // set default warnings:
        $this->warnings = array(
            'charset'   => true,   // set_charset not called
            'memcache'  => true,   // memcache is available but it is not used
            'redis'     => true,   // redis is available but it is not used
        );

        // this is used in the "escape" method
        $this->use_deprecated = version_compare(PHP_VERSION, '5.4.0', '<');

        // whether the call was made from CLI or from the browser
        $this->is_cli_request = strpos(php_sapi_name(), 'cli') === 0;

        // show the debug console when script execution ends
        register_shutdown_function(array($this, '_show_debugging_console'));

    }

    /**
     *  Closes the MySQL connection and optionally unsets the connection options previously set with the {@link option()}
     *  method
     *
     *  @param  boolean $reset_options  If set to `TRUE` the library will also unset the connection options previously
     *                                  set with the {@link option()} method.
     *
     *                                  Default is `FALSE`
     *
     *                                  *This option was added in 2.9.5*
     *
     *  @since  1.1.0
     *
     *  @return boolean     Returns `TRUE` on success or `FALSE` on failure
     */
    public function close($reset_options = false) {

        // close the last open connection, if any
        if (!is_bool($this->connection)) $result = mysqli_close($this->connection);

        // set this flag to FALSE so that other connection can be opened
        $this->connection = false;

        // unset previously set credentials
        // or otherwise running a query after close will simply reuse those credentials to connect again
        $this->credentials = array();

        // if options need to be unset, unset them now
        if ($reset_options) $this->options = array();

        // return the result
        return $result;

    }

    /**
     *  Opens a connection to a MySQL server and optionally selects a database
     *
     *  Since the library is using *lazy connection* (it is not actually connecting to the database until the first query
     *  is executed), the object representing the connection to the MySQL server is not available at this time. In case
     *  you need it before running any queries, use the {@link get_link()} method.
     *
     *  If you want the connection to the database to be made right away, set the `connect` argument to `TRUE`
     *
     *  <code>
     *  // create the database object
     *  $db = new Zebra_Database();
     *
     *  // notice that we're not doing any error checking. errors will be shown in the debugging console
     *  $db->connect('host', 'username', 'password', 'database');
     *  </code>
     *
     *  @param  string  $host       The address of the MySQL server to connect to (i.e. localhost)
     *
     *                              Prepending `p:` to the host opens a persistent connection
     *
     *  @param  string  $user       The user name used for authentication when connecting to the MySQL server
     *
     *  @param  string  $password   The password used for authentication when connecting to the MySQL server
     *
     *  @param  string  $database   (Optional) The database to be selected after the connection is established
     *
     *                              This *can also be set later* with the {@link select_database()} method
     *
     *                              Default is `""` (empty string)
     *
     *  @param  string  $port       (Optional) The port number to use when connecting to the MySQL server
     *
     *                              Default is `""` (empty string) which defaults to the value returned by `ini_get('mysqli.default_port')`
     *
     *  @param  string  $socket     (Optional) The socket or named pipe that should be used.
     *
     *                              Specifying the socket parameter will not explicitly determine the type of connection
     *                              to be used when connecting to the MySQL server. How the connection is made to the MySQL
     *                              database is determined by the *host* argument.
     *
     *                              Default is `""` (empty string) which defaults to the value returned by `ini_get('mysqli.default_socket')`
     *
     *  @param  boolean $connect    (Optional) Setting this argument to `TRUE` will force the library to connect to the
     *                              database right away instead of using a *lazy connection* and not connecting the
     *                              database until the first query is run.
     *
     *                              Default is `FALSE`
     *
     *  @return void
     */
    public function connect($host, $user, $password, $database = '', $port = '', $socket = '', $connect = false) {

        // if the "memcache" extension is loaded and the caching method is set to "memcache"
        if (!extension_loaded('memcache') || $this->caching_method === 'memcache')

            // suppress the warning telling the developer to use memcache for caching query results
            unset($this->warnings['memcache']);

        // if the "redis" extension is loaded and the caching method is set to "redis"
        if (!extension_loaded('redis') || $this->caching_method === 'redis')

            // suppress the warning telling the developer to use redis for caching query results
            unset($this->warnings['redis']);

        // we are using lazy-connection
        // that is, we are not going to actually connect to the database until we execute the first query
        // the actual connection is done by the _connected method
        $this->credentials = array(
            'host'      => $host,
            'user'      => $user,
            'password'  => $password,
            'database'  => $database,
            'port'      => $port === '' ? ini_get('mysqli.default_port') : $port,
            'socket'    => $socket === '' ? ini_get('mysqli.default_socket') : $socket,
        );

        // connect now, if we need to connect right away
        if ($connect) $this->_connected();

    }

    /**
     *  Counts the values in a column of a table
     *
     *  <code>
     *  // count male users
     *  $male = $db->dcount('id', 'users', 'gender = "M"');
     *
     *  // when working with variables you should use the following syntax
     *  // this way you will stay clear of SQL injections
     *  $users = $db->dcount('id', 'users', 'gender = ?', array($gender));
     *  </code>
     *
     *  @param  string  $column         Name of the column in which to do the counting
     *
     *  @param  string  $table          Name of the table containing the column
     *
     *                                  *May also be specified like `databasename.tablename` if a database was not explicitly
     *                                  selected with the {@link connect()} or {@link select_database()} methods prior to
     *                                  calling this method.*
     *
     *  @param  string  $where          (Optional) A MySQL `WHERE` clause (without the `WHERE` keyword)
     *
     *                                  Default is `""` (an empty string)
     *
     *  @param  mixed   $replacements   (Optional) An array with as many items as the total parameter markers (`?`, question
     *                                  marks) in *$where*. Each item will be automatically {@link escape escaped} and
     *                                  will replace the corresponding `?`.<br>
     *                                  An item may also be an array, case in which each value from the array will be
     *                                  automatically {@link escape escaped} and concatenated with the other elements from
     *                                  the array - useful when using *WHERE column IN (?)* conditions. See second example
     *                                  {@link query here}.
     *
     *                                  Default is `""` (an empty string)
     *
     *  @param  mixed   $cache          (Optional) Instructs the library on whether it should cache the query results or
     *                                  not. Can be either `FALSE` - no caching - or an integer representing the number
     *                                  of seconds after which the cache will be considered as expired and the query will
     *                                  be executed again.
     *
     *                                  The caching method is specified through the {@link caching_method} property.
     *
     *                                  Default is `FALSE`
     *
     *  @param  boolean $highlight      (Optional) If set to `TRUE` the debugging console will be opened automatically
     *                                  and the query will be shown - useful for quickly debugging specific queries.
     *
     *                                  Default is `FALSE`
     *
     *  @return mixed                   Returns the number of counted records or `FALSE` if no records matching the given
     *                                  criteria (if any) were found. It also returns `FALSE` if there are no records in
     *                                  the table, or if there was an error.
     *
     *                                  *This method may return boolean `FALSE` but may also return a non-boolean value
     *                                  which evaluates to `FALSE`, such as `0`. Use the `===` operator for testing the
     *                                  return value of this method.*
     */
    public function dcount($column, $table, $where = '', $replacements = '', $cache = false, $highlight = false) {

        // run the query
        $this->query('

            SELECT
                COUNT(' . $column . ') AS counted
            FROM
                ' . $this->_escape($table) .
            ($where !== '' ? ' WHERE ' . $where : '') . '

        ', $replacements, $cache, false, $highlight);

        // if query was executed successfully and one or more records were returned
        if (isset($this->last_result) && $this->last_result !== false && $this->returned_rows > 0) {

            // fetch the result
            $row = $this->fetch_assoc();

            // return the result
            return $row['counted'];

        }

        // if error or no records
        return false;

    }

    /**
     *  Deletes rows from a table
     *
     *  <code>
     *  // delete male users
     *  $db->delete('users', 'gender = "M"');
     *
     *  // when working with variables you should use the following syntax
     *  // this way you will stay clear of SQL injections
     *  $db->delete('users', 'gender = ?', array($gender));
     *  </code>
     *
     *  @param  string  $table          Table from which to delete
     *
     *                                  *May also be specified like `databasename.tablename` if a database was not explicitly
     *                                  selected with the {@link connect()} or {@link select_database()} methods prior to
     *                                  calling this method.*
     *
     *  @param  string  $where          (Optional) A MySQL `WHERE` clause (without the `WHERE` keyword)
     *
     *                                  Default is `""` (an empty string)
     *
     *  @param  mixed   $replacements   (Optional) An array with as many items as the total parameter markers (`?`, question
     *                                  marks) in *$where*. Each item will be automatically {@link escape escaped} and
     *                                  will replace the corresponding `?`.<br>
     *                                  An item may also be an array, case in which each value from the array will be
     *                                  automatically {@link escape escaped} and concatenated with the other elements from
     *                                  the array - useful when using *WHERE column IN (?)* conditions. See second example
     *                                  {@link query here}.
     *
     *                                  Default is `""` (an empty string)
     *
     *  @param  boolean $highlight      (Optional) If set to `TRUE` the debugging console will be opened automatically
     *                                  and the query will be shown - useful for quickly debugging specific queries.
     *
     *                                  Default is `FALSE`
     *
     *  @since  1.0.9
     *
     *  @return boolean                 Returns `TRUE` on success or `FALSE` on error
     */
    public function delete($table, $where = '', $replacements = '', $highlight = false) {

        // run the query
        $this->query('

            DELETE FROM
                ' . $this->_escape($table) .
            ($where !== '' ? ' WHERE ' . $where : '') . '

        ', $replacements, false, false, $highlight);

        // return TRUE if query was successful, or FALSE if it wasn't
        return isset($this->last_result) && $this->last_result !== false;

    }

    /**
     *  Returns one or more columns from **ONE** row of a table
     *
     *  <code>
     *  // get name, surname and age of all male users
     *  $result = $db->dlookup('name, surname, age', 'users', 'gender = "M"');
     *
     *  // when working with variables you should use the following syntax
     *  // this way you will stay clear of SQL injections
     *  $result = $db->dlookup('name, surname, age', 'users', 'gender = ?', array($gender));
     *  </code>
     *
     *  @param  string  $column         One or more columns to return data from
     *
     *                                  >   If only one column is specified, the returned result will be the specified
     *                                      column's value. If more columns are specified the returned result will be an
     *                                      associative array!
     *
     *                                  *You may use `*` (asterisk) to return all the columns from the row.*
     *
     *  @param  string  $table          Name of the table in which to search
     *
     *                                  *May also be specified like `databasename.tablename` if a database was not explicitly
     *                                  selected with the {@link connect()} or {@link select_database()} methods prior to
     *                                  calling this method.*
     *
     *  @param  string  $where          (Optional) A MySQL `WHERE` clause (without the `WHERE` keyword)
     *
     *                                  Default is `""` (an empty string)
     *
     *  @param  mixed   $replacements   (Optional) An array with as many items as the total parameter markers (`?`, question
     *                                  marks) in *$where*. Each item will be automatically {@link escape escaped} and
     *                                  will replace the corresponding `?`.<br>
     *                                  An item may also be an array, case in which each value from the array will be
     *                                  automatically {@link escape escaped} and concatenated with the other elements from
     *                                  the array - useful when using *WHERE column IN (?)* conditions. See second example
     *                                  {@link query here}.
     *
     *                                  Default is `""` (an empty string)
     *
     *  @param  mixed   $cache          (Optional) Instructs the library on whether it should cache the query results or
     *                                  not. Can be either `FALSE` - no caching - or an integer representing the number
     *                                  of seconds after which the cache will be considered as expired and the query will
     *                                  be executed again.
     *
     *                                  The caching method is specified through the {@link caching_method} property.
     *
     *                                  Default is `FALSE`
     *
     *  @param  boolean $highlight      (Optional) If set to `TRUE` the debugging console will be opened automatically
     *                                  and the query will be shown - useful for quickly debugging specific queries.
     *
     *                                  Default is `FALSE`
     *
     *  @return mixed                   Returns the found value/values or `FALSE` if no records matching the given criteria
     *                                  (if any) were found. It also returns `FALSE` if there are no records in the table,
     *                                  or if there was an error.
     */
    public function dlookup($column, $table, $where = '', $replacements = '', $cache = false, $highlight = false) {

        // run the query
        $this->query('
            SELECT
                ' . $column . '
            FROM
                ' . $this->_escape($table) .
                ($where !== '' ? ' WHERE ' . $where : '') . '
            LIMIT 1
        ', $replacements, $cache, false, $highlight);

        // if query was executed successfully and one or more records were returned
        if (isset($this->last_result) && $this->last_result !== false && $this->returned_rows > 0) {

            // fetch the result
            $row = $this->fetch_assoc();

            // if there is only one column in the returned set
            // return as a single value
            if (count($row) == 1) return array_pop($row);

            // if more than one columns, return as an array
            return $row;

        }

        // if error or no records
        return false;

    }

    /**
     *  Looks up the maximum value in a column of a table
     *
     *  <code>
     *  // get the maximum age of male users
     *  $result = $db->dmax('age', 'users', 'gender = "M"');
     *
     *  // when working with variables you should use the following syntax
     *  // this way you will stay clear of SQL injections
     *  $result = $db->dmax('age', 'users', 'gender = ?', array($gender));
     *  </code>
     *
     *  @param  string  $column         Name of the column in which to search
     *
     *  @param  string  $table          Name of table in which to search
     *
     *                                  *May also be specified like `databasename.tablename` if a database was not explicitly
     *                                  selected with the {@link connect()} or {@link select_database()} methods prior to
     *                                  calling this method.*
     *
     *  @param  string  $where          (Optional) A MySQL `WHERE` clause (without the `WHERE` keyword)
     *
     *                                  Default is `""` (an empty string)
     *
     *  @param  mixed   $replacements   (Optional) An array with as many items as the total parameter markers (`?`, question
     *                                  marks) in *$where*. Each item will be automatically {@link escape escaped} and
     *                                  will replace the corresponding `?`.<br>
     *                                  An item may also be an array, case in which each value from the array will be
     *                                  automatically {@link escape escaped} and concatenated with the other elements from
     *                                  the array - useful when using *WHERE column IN (?)* conditions. See second example
     *                                  {@link query here}.
     *
     *                                  Default is `""` (an empty string)
     *
     *  @param  mixed   $cache          (Optional) Instructs the library on whether it should cache the query results or
     *                                  not. Can be either `FALSE` - no caching - or an integer representing the number
     *                                  of seconds after which the cache will be considered as expired and the query will
     *                                  be executed again.
     *
     *                                  The caching method is specified through the {@link caching_method} property.
     *
     *                                  Default is `FALSE`
     *
     *  @param  boolean $highlight      (Optional) If set to `TRUE` the debugging console will be opened automatically
     *                                  and the query will be shown - useful for quickly debugging specific queries.
     *
     *                                  Default is `FALSE`
     *
     *  @return mixed                   Returns the maximum value in the column or `FALSE` if no records matching the
     *                                  given criteria (if any) were found. It also returns `FALSE` if there are no
     *                                  records in the table, or if there was an error.
     *
     *                                  *This method may return boolean `FALSE` but may also return a non-boolean value
     *                                  which evaluates to `FALSE`, such as `0`. Use the `===` operator for testing the return
     *                                  value of this method.*
     */
    public function dmax($column, $table, $where = '', $replacements = '', $cache = false, $highlight = false) {

        // run the query
        $this->query('

            SELECT
                MAX(' . $column . ') AS maximum
            FROM
                ' . $this->_escape($table) .
            ($where !== '' ? ' WHERE ' . $where : '') . '

        ', $replacements, $cache, false, $highlight);

        // if query was executed successfully and one or more records were returned
        if (isset($this->last_result) && $this->last_result !== false && $this->returned_rows > 0) {

            // fetch the result
            $row = $this->fetch_assoc();

            // return the result
            return $row['maximum'];

        }

        // if error or no records
        return false;

    }

    /**
     *  Sums the values in a column of a table
     *
     *  <code>
     *  // get the total logins of all male users
     *  $result = $db->dsum('login_count', 'users', 'gender = "M"');
     *
     *  // when working with variables you should use the following syntax
     *  // this way you will stay clear of SQL injections
     *  $result = $db->dsum('login_count', 'users', 'gender = ?', array($gender));
     *  </code>
     *
     *  @param  string  $column         Name of the column in which to sum values
     *
     *  @param  string  $table          Name of the table in which to search
     *
     *                                  *May also be specified like `databasename.tablename` if a database was not explicitly
     *                                  selected with the {@link connect()} or {@link select_database()} methods prior to
     *                                  calling this method.*
     *
     *  @param  string  $where          (Optional) A MySQL `WHERE` clause (without the `WHERE` keyword)
     *
     *                                  Default is `""` (an empty string)
     *
     *  @param  mixed   $replacements   (Optional) An array with as many items as the total parameter markers (`?`, question
     *                                  marks) in *$where*. Each item will be automatically {@link escape escaped} and
     *                                  will replace the corresponding `?`.<br>
     *                                  An item may also be an array, case in which each value from the array will be
     *                                  automatically {@link escape escaped} and concatenated with the other elements from
     *                                  the array - useful when using *WHERE column IN (?)* conditions. See second example
     *                                  {@link query here}.
     *
     *                                  Default is `""` (an empty string)
     *
     *  @param  mixed   $cache          (Optional) Instructs the library on whether it should cache the query results or
     *                                  not. Can be either `FALSE` - no caching - or an integer representing the number
     *                                  of seconds after which the cache will be considered as expired and the query will
     *                                  be executed again.
     *
     *                                  The caching method is specified through the {@link caching_method} property.
     *
     *                                  Default is `FALSE`
     *
     *  @param  boolean $highlight      (Optional) If set to `TRUE` the debugging console will be opened automatically
     *                                  and the query will be shown - useful for quickly debugging specific queries.
     *
     *                                  Default is `FALSE`
     *
     *  @return mixed                   Returns the summed values or `FALSE` if no records matching the given criteria
     *                                  (if any) were found. It also returns `FALSE` if there are no records in the table,
     *                                  or if there was an error.
     *
     *                                  *This method may return boolean `FALSE` but may also return a non-boolean value
     *                                  which evaluates to `FALSE`, such as `0`. Use the `===` operator for testing the return
     *                                  value of this method.*
     */
    public function dsum($column, $table, $where = '', $replacements = '', $cache = false, $highlight = false) {

        // run the query
        $this->query('

            SELECT
                SUM(' . $column . ') AS total
            FROM
                ' . $this->_escape($table) .
            ($where !== '' ? ' WHERE ' . $where : '') . '

        ', $replacements, $cache, false, $highlight);

        // if query was executed successfully and one or more records were returned
        if (isset($this->last_result) && $this->last_result !== false && $this->found_rows > 0) {

            // fetch the result
            $row = $this->fetch_assoc();

            // return the result
            return $row['total'];

        }

        // if error or no records
        return false;

    }

    /**
     *  Returns the description of the last error, or an empty string if no error occurred
     *
     *  In most cases you should not need this method as any errors are shown in the debugging console as long as the
     *  {@link debug debugging} is on, or available in PHP's error log file (if your environment is
     *  {@link https://www.php.net/manual/en/errorfunc.configuration.php#ini.log-errors configured to do so}) when set to
     *  `FALSE`.
     *
     *  If, for some reasons, none of the above is available, you can use this method to see errors.
     *
     *  <code>
     *  $db->query('
     *      SELECT
     *          *
     *      FROM
     *          users
     *      WHERE
     *          invalid_column = ?
     *  ', array($value)) or die($db->error());
     *  </code>
     *
     *  @param  boolean $return_error_number    Indicates whether the error number should also be returned.
     *
     *                                          If set to `TRUE`, the method returns an array in the form of
     *
     *                                          <code>
     *                                          Array(
     *                                              'number'    =>  '1234',
     *                                              'message'   =>  'The message',
     *                                          )
     *                                          </code>
     *
     *                                          ...or an empty string if no error occurred.
     *
     *                                          Default is `FALSE`
     *
     *  @since  2.9.1
     *
     *  @return mixed   Returns the description of the last error, or an empty string if no error occurred.
     *
     */
    public function error($return_error_number = false) {

        $error_message = $error_number = '';

        // try
        try {

            // get error message and number
            $error_message = @mysqli_error($this->connection);
            $error_number = @mysqli_errno($this->connection);

        // if the above fails, we might have a connection-level error and we got an "incomplete" mysqli object
        } catch (Error $e) {
        }

        // if PHP < 8, we're not going to catch the error above
        // therefore we do this which works for all versions
        if (!$error_message && $this->connection !== false && $this->connection instanceof mysqli) {

            // extract values from the object
            $error_message = $this->connection->connect_error;
            $error_number = $this->connection->connect_errno;

        }

        // if we also need to return the error number alongside the error message
        if ($return_error_number && $error_message !== '')

            // return as an array
            return array(
                'number'    => $error_number,
                'message'   => $error_message,
            );

        // a string description of the last error, or an empty string if no error occurred
        return is_null($error_message) ? '' : $error_message;

    }

    /**
     *  Escapes special characters in a string making it safe to be used in an MySQL statement, in order to prevent SQL
     *  injections
     *
     *  >   This method also encloses given string in single quotes!
     *
     *  *Works even if {@link https://www.php.net/manual/en/info.configuration.php#ini.magic-quotes-gpc magic_quotes}
     *  is ON.*
     *
     *  <code>
     *  // use the method in a query
     *  // THIS IS NOT THE RECOMMENDED METHOD!
     *  $db->query('
     *      SELECT
     *          *
     *      FROM
     *          users
     *      WHERE
     *          gender = "' . $db->escape($gender) . '"
     *  ');
     *
     *  // the recommended method
     *  // (variables are automatically escaped this way)
     *  $db->query('
     *      SELECT
     *          *
     *      FROM
     *          users
     *      WHERE
     *          gender = ?
     *  ', array($gender));
     *  </code>
     *
     *  @param  string  $string     String to be quoted and escaped
     *
     *  @return string|boolean      Returns the original string enclosed in single quotes and with special characters
     *                              escaped in order to prevent SQL injections, or `FALSE` if there's no database
     *                              connection.
     */
    public function escape($string) {

        // if no active connection exists, return false
        if (!$this->_connected()) return false;

        // if we are on PHP < 5.4.0 and "magic quotes" are on, strip slashes
        if ($this->use_deprecated && get_magic_quotes_gpc()) $string = stripslashes($string);

        // escape and return the string
        return mysqli_real_escape_string($this->connection, $string);

    }

    /**
     *  Returns an associative array that corresponds to the fetched row and moves the internal data pointer ahead. The
     *  data is taken from the resource created by the previous query or from the resource given as argument.
     *
     *  <code>
     *  // run a query
     *  $db->query('SELECT * FROM table WHERE criteria = ?', array($criteria));
     *
     *  // iterate through the found records
     *  while ($row = $db->fetch_assoc()) {
     *
     *      // code goes here
     *
     *  }
     *  </code>
     *
     *  @param  resource|string     $resource   (Optional) Resource to fetch
     *
     *                                          >   If not specified, the resource returned by the last run query is used
     *
     *  @return mixed               Returns an associative array that corresponds to the fetched row and moves the
     *                              internal data pointer ahead, or `FALSE` if there are no more rows.
     */
    public function fetch_assoc($resource = '') {

        // if no active connection exists, return false
        if (!$this->_connected()) return false;

        // if no resource was specified, and a query was run before, assign the last resource
        if ($resource === '' && isset($this->last_result) && $this->last_result !== false) $resource = & $this->last_result;

        // if $resource is a valid resource
        if ($this->_is_result($resource)) {

            // fetch next row from the result set
            $result = mysqli_fetch_assoc($resource);

            // if this was an unbuffered query manage setting the value for $returned_rows and some other things
            if ($resource->type == 1) $this->_manage_unbuffered_query_info($resource, $result);

            // return next row, or FALSE if no more rows available
            return $result;

        // if $resource is a pointer to an array taken from cache
        } elseif (is_integer($resource) && isset($this->cached_results[$resource])) {

            // get the current entry from the array
            $result = current($this->cached_results[$resource]);

            // advance the pointer
            next($this->cached_results[$resource]);

            // note that the above could've been done in a single step, using PHP's each() function
            // but it has been deprecated starting with PHP 7.2.0

            // return the value
            return $result;

        }

        // if $resource is invalid
        // save debug information
        return $this->_log('errors', array(

            'message'   => $this->language['not_a_valid_resource'],

        ));

    }

    /**
     *  Returns an associative array containing all the rows from the resource created by the previous query or from the
     *  resource given as argument and moves the internal pointer to the end
     *
     *  <code>
     *  // run a query
     *  $db->query('SELECT * FROM table WHERE criteria = ?', array($criteria));
     *
     *  // fetch all the rows as an associative array
     *  $records = $db->fetch_assoc_all();
     *  </code>
     *
     *  @param  int|string          $index      (Optional) Name of a column containing unique values
     *
     *                                          If specified, the returned associative array's keys will be the values
     *                                          from this column.
     *
     *                                          *If not specified, returned array will have numerical indexes, starting
     *                                          from 0.*
     *
     *  @param  resource|string     $resource   (Optional) Resource to fetch
     *
     *                                          >   If not specified, the resource returned by the last run query is used
     *
     *  @since  1.1.2
     *
     *  @return mixed               Returns an associative array containing all the rows from the resource created by the
     *                              previous query or from the resource given as argument and moves the internal pointer
     *                              to the end. Returns `FALSE` on error.
     */
    public function fetch_assoc_all($index = '', $resource = '') {

        // if no active connection exists, return false
        if (!$this->_connected()) return false;

        // if only one argument was given
        // meaning that the first argument is either a valid resource or a pointer to an array taken from cache
        if ($resource === '' && $this->_is_result($index) || (is_integer($index) && isset($this->cached_results[$index]))) {

            // use the first argument as the actual resource
            $resource = $index;

            // consider first argument as skipped
            $index = '';

        }

        // if no resource was specified, and a query was run before, assign the last resource
        if ($resource === '' && isset($this->last_result) && $this->last_result !== false) $resource = & $this->last_result;

        if (

            // if $resource is a valid resource
            $this->_is_result($resource)

            // OR $resource is a pointer to an array taken from cache
            || (is_integer($resource) && isset($this->cached_results[$resource]))

        ) {

            // this is the array that will contain the results
            $result = array();

            // move the pointer to the start of $resource
            // if there are any rows available (notice the @)
            if (@$this->seek(0, $resource)) {

                // iterate through the records
                while ($row = $this->fetch_assoc($resource)) {

                    // if $index was specified and exists in the returned row, add data to the result
                    if (trim($index) !== '' && isset($row[$index])) $result[$row[$index]] = $row;

                    // if $index was not specified or does not exists in the returned row, add data to the result
                    else $result[] = $row;

                }

            }

            // return the results
            return $result;

        }

        // if $resource is invalid
        // save debug information
        return $this->_log('errors', array(

            'message'   => $this->language['not_a_valid_resource'],

        ));

    }

    /**
     *  Returns an object with properties that correspond to the fetched row and moves the internal data pointer ahead.
     *  The data is taken from the resource created by the previous query or from the resource given as argument.
     *
     *  <code>
     *  // run a query
     *  $db->query('SELECT * FROM table WHERE criteria = ?', array($criteria));
     *
     *  // iterate through the found records
     *  while ($row = $db->fetch_obj()) {
     *
     *      // code goes here
     *
     *  }
     *  </code>
     *
     *  @param  resource|string     $resource   (Optional) Resource to fetch
     *
     *                                          >   If not specified, the resource returned by the last run query is used
     *
     *  @since  1.0.8
     *
     *  @return mixed               Returns an object with properties that correspond to the fetched row and moves
     *                              the internal data pointer ahead, or `FALSE` if there are no more rows.
     */
    public function fetch_obj($resource = '') {

        // if no active connection exists, return false
        if (!$this->_connected()) return false;

        // if no resource was specified, and a query was run before, assign the last resource
        if ($resource === '' && isset($this->last_result) && $this->last_result !== false) $resource = & $this->last_result;

        // if $resource is a valid resource, fetch and return next row from the result set
        if ($this->_is_result($resource)) {

            // fetch next row from the result set
            $result = mysqli_fetch_object($resource);

            // if this was an unbuffered query manage setting the value for $returned_rows and some other things
            if ($resource->type == 1) $this->_manage_unbuffered_query_info($resource, $result);

            // return next row, or FALSE if no more rows available
            return $result;

        // if $resource is a pointer to an array taken from cache
        } elseif (is_integer($resource) && isset($this->cached_results[$resource])) {

            // get the current entry from the array
            $result = current($this->cached_results[$resource]);

            // advance the pointer
            next($this->cached_results[$resource]);

            // note that the above could've been done in a single step, using PHP's each() function
            // but it has been deprecated starting with PHP 7.2.0

            // if we're not past the end of the array
            if ($result !== false)

                // cast the resulting array as an object
                $result = (object)$result;

            // return result
            return $result;

        }

        // if $resource is invalid
        // save debug information
        return $this->_log('errors', array(

            'message'   => $this->language['not_a_valid_resource'],

        ));

    }

    /**
     *  Returns an associative array containing all the rows (as objects) from the resource created by the previous query
     *  or from the resource given as argument and moves the internal pointer to the end
     *
     *  <code>
     *  // run a query
     *  $db->query('SELECT * FROM table WHERE criteria = ?', array($criteria));
     *
     *  // fetch all the rows as an associative array
     *  $records = $db->fetch_obj_all();
     *  </code>
     *
     *  @param  int|string          $index      (Optional) A column name from the records, containing unique values
     *
     *                                          If specified, the returned associative array's keys will be the values
     *                                          from this column.
     *
     *                                          *If not specified, returned array will have numerical indexes, starting
     *                                          from 0.*
     *
     *  @param  resource|string     $resource   (Optional) Resource to fetch
     *
     *                                          >   If not specified, the resource returned by the last run query is used
     *
     *  @since  1.1.2
     *
     *  @return mixed               Returns an associative array containing all the rows (as objects) from the resource
     *                              created by the previous query or from the resource given as argument and moves the
     *                              internal pointer to the end. Returns `FALSE` on error.
     */
    public function fetch_obj_all($index = '', $resource = '') {

        // if no active connection exists, return false
        if (!$this->_connected()) return false;

        // if only one argument was given
        // meaning that the first argument is either a valid resource or a pointer to an array taken from cache
        if ($resource === '' && $this->_is_result($index) || (is_integer($index) && isset($this->cached_results[$index]))) {

            // use the first argument as the actual resource
            $resource = $index;

            // consider first argument as skipped
            $index = '';

        }

        // if no resource was specified, and a query was run before, assign the last resource
        if ($resource === '' && isset($this->last_result) && $this->last_result !== false) $resource = & $this->last_result;

        if (

            // if $resource is a valid resource
            $this->_is_result($resource)

            // OR $resource is a pointer to an array taken from cache
            || (is_integer($resource) && isset($this->cached_results[$resource]))

        ) {

            // this is the array that will contain the results
            $result = array();

            // move the pointer to the start of $resource
            // if there are any rows available (notice the @)
            if (@$this->seek(0, $resource)) {

                // iterate through the resource data
                while ($row = $this->fetch_obj($resource)) {

                    // if $index was specified and exists in the returned row, add data to the result
                    if (trim($index) !== '' && property_exists($row, $index)) $result[$row->{$index}] = $row;

                    // if $index was not specified or does not exists in the returned row, add data to the result
                    else $result[] = $row;

                }

            }

            // return the results
            return $result;

        }

        // if $resource is invalid
        // save debug information
        return $this->_log('errors', array(

            'message'   => $this->language['not_a_valid_resource'],

        ));

    }

    /**
     *  Frees the memory associated with a result
     *
     *  >   You should always free your result with {@link free_result()}, when your result object is not needed anymore
     *
     *  @param  resource|string     $resource   (Optional) A valid resource
     *
     *                                          >   If not specified, the resource returned by the last run query is used
     *
     *  @since  2.9.1
     *
     *  @return false|void
     */
    public function free_result($resource = '') {

        // if no active connection exists, return false
        if (!$this->connection) return false;

        // if no resource was specified, and a query was run before, assign the last resource
        if ($resource === '' && isset($this->last_result) && $this->last_result !== false) $resource = & $this->last_result;

        // if argument is a valid resource, free the result
        // (we mute it as it might have already been freed by a previous call to this method)
        if ($this->_is_result($resource)) @mysqli_free_result($resource);

    }

    /**
     *  Returns an array of associative arrays with information about the columns in the MySQL result associated with
     *  the specified result identifier
     *
     *  Each entry will have the column's name as key and, associated, an array with the following keys:
     *
     *  - name
     *  - table
     *  - def
     *  - max_length
     *  - not_null
     *  - primary_key
     *  - multiple_key
     *  - unique_key
     *  - numeric
     *  - blob
     *  - type
     *  - unsigned
     *  - zerofill
     *
     *  <code>
     *  // run a query
     *  $db->query('SELECT * FROM table');
     *
     *  // print information about the columns
     *  print_r('<pre>');
     *  print_r($db->get_columns());
     *  </code>
     *
     *  @param  resource|string     $resource   (Optional) Resource to fetch columns information from.
     *
     *                                          >   If not specified, the resource returned by the last run query is used
     *
     *  @since  2.0
     *
     *  @return mixed               Returns an associative array with information about the columns in the MySQL result
     *                              associated with the specified result identifier, or `FALSE` on error.
     */
    public function get_columns($resource = '') {

        // if no active connection exists, return false
        if (!$this->_connected()) return false;

        // if no resource was specified, and a query was run before, assign the last resource
        if ($resource === '' && isset($this->last_result) && $this->last_result !== false) $resource = & $this->last_result;

        // if $resource is a valid resource
        if ($this->_is_result($resource)) {

            $result = array();

            // iterate through all the columns
            while ($column_info = mysqli_fetch_field($resource))

                // add information to the array of results
                // converting it first to an associative array
                $result[$column_info->name] = get_object_vars($column_info);

            // return information
            return $result;

        // if $resource is a pointer to an array taken from cache
        // return information that was stored in the cached file
        } elseif (is_integer($resource) && isset($this->cached_results[$resource])) return $this->column_info;

        // if $resource is invalid
        // save debug information
        return $this->_log('errors', array(

            'message'   => $this->language['not_a_valid_resource'],

        ));

    }

    /**
     *  Returns the MySQL link identifier associated with the current connection to the MySQL server
     *
     *  Why a separate method? Because the library uses *lazy connection* (it is not actually connecting to the database
     *  until the first query is executed) there's no link identifier available when calling the {@link connect()} method.
     *
     *  <code>
     *  // create the database object
     *  $db = new Zebra_Database();
     *
     *  // nothing is returned by this method!
     *  $db->connect('host', 'username', 'password', 'database');
     *
     *  // get the link identifier
     *  $link = $db->get_link();
     *  </code>
     *
     *  @since 2.5
     *
     *  @return object|boolean  Returns the MySQL link identifier associated with the current connection to the MySQL server.
     */
    public function get_link() {

        // if an active connection exists
        // return the MySQL link identifier associated with the current connection to the MySQL server
        if ($this->_connected()) return $this->connection;

        // if script gets this far, return false as something must've been wrong
        return false;

    }

    /**
    *   Returns the name of the currently selected database
    *
    *   @since 2.8.7
    *
    *   @return mixed   Returns the name of the currently selected database, or `FALSE` if there's no active connection.
    */
    public function get_selected_database() {

        // if an active connection exists
        if ($this->_connected()) return $this->credentials['database'];

        // return false if there's no connection
        return false;

    }

    /**
     *  Returns an array with all the tables in a database
     *
     *  <code>
     *  // get all tables from the currently selected database
     *  $tables = get_tables();
     *  </code>
     *
     *  @param  string  $database   (Optional) The name of the database from which to return the names of existing tables
     *
     *                              If not specified, the tables from the currently selected database will be returned.
     *
     *                              *This option was added in 2.9.5*
     *
     *  @since  1.1.2
     *
     *  @return array<string>       Returns an array with all the tables in the specified database
     */
    public function get_tables($database = '') {

        // fetch all the tables in the database
        $result = $this->fetch_assoc_all('', $this->query('
            SHOW TABLES' . ($database !== '' ? ' IN ' . $this->_escape($database) : '')));

        $tables = array();

        // as the results returned by default are quite odd
        // translate them to a more usable array
        foreach ($result as $tableName) $tables[] = array_pop($tableName);

        // return the array with the table names
        return $tables;

    }

    /**
     *  Returns information about the columns of a given table as an associative array
     *
     *  <code>
     *  // get column information for a table named "table_name"
     *  $db->get_table_columns('table_name');
     *  </code>
     *
     *  @param  string  $table  Name of table to return column information for
     *
     *                          *May also be specified like `databasename.tablename` if a database was not explicitly selected
     *                          with the {@link connect()} or {@link select_database()} methods prior to calling this
     *                          method.*
     *
     *  @since  2.6
     *
     *  @return array<string>   Returns information about the columns of a given table as an associative array.
     */
    public function get_table_columns($table) {

        // run the query
        $this->query('

            SHOW COLUMNS FROM ' . $this->_escape($table) . '

        ');

        // fetch and return data
        return $this->fetch_assoc_all('Field');

    }

    /**
     *  Returns an associative array with a lot of useful information on all or specific tables only
     *
     *  <code>
     *  // return status information on tables in the currently
     *  // selected database having their name starting with "users"
     *  $tables = get_table_status('users%');
     *  </code>
     *
     *  @param  string  $table      (Optional) Table for which to return information for
     *
     *                              *May also be specified like `databasename.tablename` if a database was not explicitly
     *                              selected with the {@link connect()} or {@link select_database()} methods prior to
     *                              calling this method.*
     *
     *                              `%` may be used as a wildcard in table's name to get information about all the tables
     *                              matching the pattern.
     *
     *                              If not specified, information will be returned for all the tables in the currently
     *                              selected database.
     *
     *  @since  1.1.2
     *
     *  @return array<mixed>        Returns an associative array with a lot of useful information on all or specific
     *                              tables only
     */
    public function get_table_status($table = '') {

        // if table argument contains the database name, extract it
        if (strpos($table, '.') !== false) list($database, $table) = explode('.', $table, 2);

        // run the query
        $this->query('
            SHOW
            TABLE
            STATUS
            ' . (isset($database) ? ' IN ' . $this->_escape($database) : '') . '
            ' . (trim($table) !== '' ? 'LIKE ?' : '') . '
        ', array($table));

        // fetch and return data
        return $this->fetch_assoc_all('Name');

    }

    /**
     *  Works similarly to PHP's `implode()` function with the difference that the *glue* is always the comma and that
     *  all the values are automatically {@link escape() escaped}
     *
     *  >   This was useful for escaping an array's values to be used in an SQL statements having the `IN` keyword,
     *      before adding arrays directly in the replacement array became possible in version 2.8.6
     *
     *  <code>
     *  $array = array(1,2,3,4);
     *
     *  //  this would work as the WHERE clause in the SQL statement would become
     *  //  WHERE column IN ('1','2','3','4')
     *  $db->query('
     *      SELECT
     *          column
     *      FROM
     *          table
     *      WHERE
     *          column IN (' . $db->implode($array) . ')
     *  ');
     *
     *  //  THE RECOMMENDED WAY OF DOING WHERE-IN CONDITIONS SINCE VERSION 2.8.6
     *
     *  $db->query('
     *      SELECT
     *          column
     *      FROM
     *          table
     *      WHERE
     *          column IN (?)
     *  ', array($array));
     *  </code>
     *
     *  @param  array<mixed>    $items  An array with items to be *glued* together
     *
     *  @since  2.0
     *
     *  @return string          Returns the string representation of all the array elements in the same order, escaped and
     *                          with commas between each element.
     */
    public function implode($items) {

        $result = '';

        // iterate through the array's items and "glue" items together
        foreach ($items as $item) $result .= ($result !== '' ? ',' : '') . '\'' . $this->escape($item) . '\'';

        return $result;

    }

    /**
     *  Shorthand for `INSERT` queries with additional `IGNORE / ON DUPLICATE KEY` support
     *
     *  >   This method inserts a single row of data. For inserting multiple rows of data see the {@link insert_bulk()}
     *      method
     *
     *  When using this method column names will be automatically {@link escape escaped} in order to prevent SQL injections and
     *  will be enclosed in ``` (grave accents), allowing the usage of reserved words.
     *
     *  <code>
     *  // simple insert
     *  $db->insert(
     *      'table',
     *      array(
     *          'a' => 1,
     *          'b' => 2,
     *          'c' => 3,
     *      )
     *  );
     *
     *  //  would result in
     *  //  INSERT INTO
     *  //      table (`a`, `b`, `c`)
     *  //  VALUES
     *  //      ("1", "2", "3")
     *
     *  // ==================================================
     *
     *  // ignore inserts if it would create duplicate keys
     *  $db->insert(
     *      'table',
     *      array(
     *          'a' => 1,
     *          'b' => 2,
     *          'c' => 3,
     *      ),
     *      false
     *  );
     *
     *  //  would result in
     *  //  INSERT IGNORE INTO
     *  //      table (`a`, `b`, `c`)
     *  //  VALUES
     *  //      ("1", "2", "3")
     *
     *  // ==================================================
     *
     *  // update values on duplicate keys
     *  // (let's assume `a` is the key)
     *  $db->insert(
     *      'table',
     *      array(
     *          'a' => 1,
     *          'b' => 2,
     *          'c' => 3,
     *      ),
     *      array('b', 'c')
     *  );
     *
     *  //  would result in
     *  //  INSERT INTO
     *  //      table (`a`, `b`, `c`)
     *  //  VALUES
     *  //      ("1", "2", "3"),
     *  //  ON DUPLICATE KEY UPDATE
     *  //      `b` = VALUES(`b`),
     *  //      `c` = VALUES(`c`)
     *
     *  // ==================================================
     *
     *  // when using MySQL functions, the value will be used as it is without being escaped!
     *  // while this is ok when using a function without any arguments like NOW(), this may
     *  // pose a security concern if the argument(s) come from user input.
     *  // in this case we have to escape the value ourselves
     *  $db->insert(
     *      'table',
     *      array(
     *          'column1'       =>  'value1',
     *          'column2'       =>  'TRIM(UCASE("' . $db->escape($value) . '"))',
     *          'date_updated'  =>  'NOW()',
     *  ));
     *  </code>
     *
     *  @param  string  $table          Table in which to insert
     *
     *                                  *May also be specified like `databasename.tablename` if a database was not explicitly
     *                                  selected with the {@link connect()} or {@link select_database()} methods prior to
     *                                  calling this method.*
     *
     *  @param  mixed   $columns        An associative array where the array's keys represent the columns names and the
     *                                  array's values represent the values to be inserted in each respective column.
     *
     *                                  Column names will be enclosed in grave accents ``` (thus allowing the usage of
     *                                  reserved words) and will be automatically {@link escape() escaped} in order to
     *                                  prevent SQL injections.
     *
     *                                  You may also use any of {@link https://dev.mysql.com/doc/refman/8.0/en/built-in-function-reference.html MySQL's functions}
     *                                  as *values*.
     *
     *                                  >   Be aware that when using MySQL functions, the value will be used as it is,
     *                                      without being escaped! While this is ok when using a function without any
     *                                      arguments like `NOW()`, this may pose a security concern if the argument(s)
     *                                      come from user input. In this case make sure you {@link escape} the values
     *                                      yourself!
     *
     *  @param  mixed   $update         (Optional) By default, calling this method with this argument set to boolean `TRUE`
     *                                  or to an empty array will result in a simple insert which will fail in case of
     *                                  duplicate keys.
     *
     *                                  Setting this argument to boolean `FALSE` will create an `INSERT IGNORE` query where
     *                                  when trying to insert a record that would cause a duplicate entry for a key,
     *                                  the row would be skipped instead.
     *
     *                                  Setting this argument to an array of column names will create a query where, on
     *                                  duplicate key, these given columns will be updated with their respective values
     *                                  from the *$values* argument.
     *
     *                                  Alternatively, this argument can also be an associative array where the array's
     *                                  keys represent column names and the array's values represent the values to update
     *                                  the columns' values to if the inserted row would cause a duplicate key.
     *
     *                                  Column names will be enclosed in grave accents ``` (thus allowing the usage of
     *                                  reserved words) and will be automatically {@link escape() escaped} in order to
     *                                  prevent SQL injections.
     *
     *                                  You may also use any of {@link https://dev.mysql.com/doc/refman/8.0/en/built-in-function-reference.html MySQL's functions}
     *                                  as *values*.
     *
     *                                  >   Be aware that when using MySQL functions, the value will be used as it is,
     *                                      without being escaped! While this is ok when using a function without any
     *                                      arguments like `NOW()`, this may pose a security concern if the argument(s)
     *                                      come from user input. In this case make sure you {@link escape} the values
     *                                      yourself!
     *
     *                                  For more information see {@link https://dev.mysql.com/doc/refman/8.0/en/insert-on-duplicate.html MySQL's INSERT ... ON DUPLICATE KEY syntax}.
     *
     *                                  Default is `TRUE`
     *
     *  @param  boolean $highlight      (Optional) If set to `TRUE` the debugging console will be opened automatically
     *                                  and the query will be shown - useful for quickly debugging specific queries.
     *
     *                                  Default is `FALSE`
     *
     *  @since  1.0.9
     *
     *  @return boolean                 Returns `TRUE` on success of `FALSE` on error.
     */
    public function insert($table, $columns, $update = true, $highlight = false) {

        return $this->insert_bulk($table, array_keys($columns), array(array_values($columns)), $update, $highlight);

    }

    /**
     *  Shorthand for inserting multiple rows in a single query with additional `IGNORE / ON DUPLICATE KEY` support
     *
     *  When using this method column names will be enclosed in grave accents ``` (thus allowing the usage of reserved
     *  words) and will be automatically {@link escape() escaped} in order to prevent SQL injections.
     *
     *  <code>
     *
     *  // simple, multi-row insert
     *  $db->insert_bulk(
     *      'table',
     *      array('a', 'b', 'c'),
     *      array(
     *          array(1, 2, 3),
     *          array(4, 5, 6),
     *          array(7, 8, 9),
     *      )
     *  );
     *
     *  //  would result in
     *  //  INSERT INTO
     *  //      table (`a`, `b`, `c`)
     *  //  VALUES
     *  //      ("1", "2", "3"),
     *  //      ("4", "5", "6"),
     *  //      ("7", "8", "9")
     *
     *  // ==================================================
     *
     *  // ignore inserts if it would create duplicate keys
     *  $db->insert_bulk(
     *      'table',
     *      array('a', 'b', 'c'),
     *      array(
     *          array(1, 2, 3),
     *          array(4, 5, 6),
     *          array(7, 8, 9),
     *      ),
     *      false
     *  );
     *
     *  //  would result in
     *  //  INSERT IGNORE INTO
     *  //      table (`a`, `b`, `c`)
     *  //  VALUES
     *  //      ("1", "2", "3"),
     *  //      ("4", "5", "6"),
     *  //      ("7", "8", "9")
     *
     *  // ==================================================
     *
     *  // update values on duplicate keys
     *  // (let's assume `a` is the key)
     *  $db->insert_bulk(
     *      'table',
     *      array('a', 'b', 'c'),
     *      array(
     *          array(1, 2, 3),
     *          array(4, 5, 6),
     *          array(7, 8, 9),
     *      ),
     *      array('b', 'c')
     *  );
     *
     *  //  would result in
     *  //  INSERT INTO
     *  //      table (`a`, `b`, `c`)
     *  //  VALUES
     *  //      ("1", "2", "3"),
     *  //      ("4", "5", "6"),
     *  //      ("7", "8", "9")
     *  //  ON DUPLICATE KEY UPDATE
     *  //      `b` = VALUES(`b`),
     *  //      `c` = VALUES(`c`)
     *
     *  // ==================================================
     *
     *  // update values on duplicate keys, but this time use static values
     *  // (let's assume `a` is the key)
     *  $db->insert_bulk(
     *      'table',
     *      array('a', 'b', 'c'),
     *      array(
     *          array(1, 2, 3),
     *          array(4, 5, 6),
     *          array(7, 8, 9),
     *      ),
     *      array('b' => 'foo', 'c' => 'bar')
     *  );
     *
     *  //  would result in
     *  //  INSERT INTO
     *  //      table (`a`, `b`, `c`)
     *  //  VALUES
     *  //      ("1", "2", "3"),
     *  //      ("4", "5", "6"),
     *  //      ("7", "8", "9")
     *  //  ON DUPLICATE KEY UPDATE
     *  //      `b` = "foo",
     *  //      `c` = "bar"
     *
     *  // ==================================================
     *
     *  // when using MySQL functions, the value will be used as it is without being escaped!
     *  // while this is ok when using a function without any arguments like NOW(), this may
     *  // pose a security concern if the argument(s) come from user input.
     *  // in this case we have to escape the value ourselves
     *  $db->insert_bulk(
     *      'table',
     *      array('a', 'b', 'c'),
     *      array(
     *          array('1', 'TRIM(UCASE("' . $db->escape($foo) . '"))', 'NOW()'),
     *          array('2', 'TRIM(UCASE("' . $db->escape($bar) . '"))', 'NOW()'),
     *      )
     *  );
     *
     *  </code>
     *
     *  @param  string  $table          Table in which to insert
     *
     *                                  *May also be specified like `databasename.tablename` if a database was not explicitly
     *                                  selected with the {@link connect()} or {@link select_database()} methods prior to
     *                                  calling this method.*
     *
     *  @param  mixed   $columns        An array with columns to insert values into
     *
     *                                  Column names will be enclosed in grave accents ``` (thus allowing the usage of
     *                                  reserved words) and will be automatically {@link escape() escaped} in order to
     *                                  prevent SQL injections.
     *
     *  @param  mixed   $values         An array of an unlimited number of arrays with values to be inserted. The arrays
     *                                  must have the same number of items as you in the *$columns* argument.
     *
     *                                  Values will be automatically {@link escape() escaped} in order to prevent SQL injections.
     *
     *                                  You may also use any of {@link https://dev.mysql.com/doc/refman/8.0/en/built-in-function-reference.html MySQL's functions}
     *                                  as *values*.
     *
     *                                  >   Be aware that when using MySQL functions, the value will be used as it is,
     *                                      without being escaped! While this is ok when using a function without any
     *                                      arguments like `NOW()`, this may pose a security concern if the argument(s)
     *                                      come from user input. In this case make sure you {@link escape} the values
     *                                      yourself!
     *
     *  @param  mixed   $update         (Optional) By default, calling this method with this argument set to boolean `TRUE`
     *                                  or to an empty array will result in a simple multi-row insert which will fail in
     *                                  case of duplicate keys.
     *
     *                                  Setting this argument to boolean `FALSE` will create an `INSERT IGNORE` query where
     *                                  when trying to insert a record that would cause a duplicate entry for a key,
     *                                  the row would be skipped instead.
     *
     *                                  Setting this argument to an array of column names will create a query where, on
     *                                  duplicate key, these given columns will be updated with their respective values
     *                                  from the *$values* argument.
     *
     *                                  Alternatively, this argument can also be an associative array where the array's
     *                                  keys represent column names and the array's values represent the values to update
     *                                  the columns' values to if the inserted row would cause a duplicate key.
     *
     *                                  Column names will be enclosed in grave accents ``` (thus allowing the usage of
     *                                  reserved words) and will be automatically {@link escape() escaped} in order to
     *                                  prevent SQL injections.
     *
     *                                  You may also use any of {@link https://dev.mysql.com/doc/refman/8.0/en/built-in-function-reference.html MySQL's functions}
     *                                  as *values*.
     *
     *                                  >   Be aware that when using MySQL functions, the value will be used as it is,
     *                                      without being escaped! While this is ok when using a function without any
     *                                      arguments like `NOW()`, this may pose a security concern if the argument(s)
     *                                      come from user input. In this case make sure you {@link escape} the values
     *                                      yourself!
     *
     *                                  For more information see {@link https://dev.mysql.com/doc/refman/8.0/en/insert-on-duplicate.html MySQL's INSERT ... ON DUPLICATE KEY syntax}.
     *
     *                                  Default is `TRUE`
     *
     *  @param  boolean $highlight      (Optional) If set to `TRUE` the debugging console will be opened automatically
     *                                  and the query will be shown - useful for quickly debugging specific queries.
     *
     *                                  Default is `FALSE`
     *
     *  @since  2.1
     *
     *  @return boolean                 Returns `TRUE` on success of `FALSE` on error
     */
    public function insert_bulk($table, $columns, $values, $update = true, $highlight = false) {

        // if $values is not an array of arrays
        if (!is_array($values))

            // save debug information
            return $this->_log('errors', array(

                'message'   => $this->language['data`_not_an_array']

            ));

        // start preparing the INSERT statement
        $sql = '
            INSERT' . ($update === false ? ' IGNORE' : '') . ' INTO
                ' . $this->_escape($table) . '
                (' . '`' . implode('`,`', $columns) . '`)
            VALUES
        ';

        // prepare the values
        $sql .= implode(', ', array_map(function($row) {
            return '(' . implode(', ', array_map(function($value) { return $this->_is_mysql_function($value) ? (is_null($value) ? 'NULL' : $value) : '"' . $this->escape($value) . '"'; }, $row)) . ')' . "\n";
        }, $values));

        // if $update is an array and is not empty
        if (is_array($update) && !empty($update))

            // prepare the ON DUPLICATE KEY statement
            $sql .= 'ON DUPLICATE KEY UPDATE' . "\n" .

            // add required values
            implode(', ', array_map(function($column, $value) {

                // if $update is not an associative array it means the columns are also the values
                if (is_numeric($column) && is_int($column))

                    // and return column = VALUES(column)
                    return '`' . $value . '` = VALUES(`' . $this->escape($value) . '`)';

                // if $update is an associative array
                else

                    // it means we have probably given a static value
                    return '`' . $column . '` = ' . ($this->_is_mysql_function($value) ? (is_null($value) ? 'NULL' : $value) : '"' . $this->escape($value) . '"');

            }, array_keys($update), array_values($update)));

        // run the query
        $this->query($sql, '', false, false, $highlight);

        // return true if query was executed successfully
        return isset($this->last_result) && $this->last_result !== false;

    }

    /**
     *  Retrieves the ID generated for an `AUTO_INCREMENT` column by the previous `INSERT` query
     *
     *  @since  1.0.4
     *
     *  @return mixed   Returns the ID generated for an `AUTO_INCREMENT` column by the previous `INSERT` query on success,
     *                  `0` if the previous query did not generate an `AUTO_INCREMENT` value, or `FALSE` if there was
     *                  no MySQL connection.
     */
    public function insert_id() {

        // if no active connection exists, return false
        if (!$this->_connected()) return false;

        // if a query was run before, return the AUTO_INCREMENT value
        if (isset($this->last_result) && $this->last_result !== false) return mysqli_insert_id($this->connection);

        // if no query was run before
        // save debug information
        return $this->_log('errors', array(

            'message'   => $this->language['not_a_valid_resource'],

        ));

    }

    /**
     *  >   This method is deprecated since 2.9.12 and will be removed in 3.0. Please use the {@link insert_bulk()} method
     *
     *  When using this method, if an inserted row would cause a duplicate value in a `UNIQUE` index or `PRIMARY KEY`,
     *  an `UPDATE` of the existing row is performed.
     *
     *  Read more {@link https://dev.mysql.com/doc/refman/8.0/en/insert-on-duplicate.html here}.
     *
     *  When using this method column names will be enclosed in grave accents ``` (thus allowing the usage of reserved
     *  words) and will be automatically {@link escape() escaped} in order to prevent SQL injections.
     *
     *  <code>
     *  // presuming article_id is a UNIQUE index or PRIMARY KEY, the statement below will
     *  // insert a new row for given $article_id and set the "votes" to 0. But, if $article_id
     *  // is already in the database, increment the votes' numbers.
     *  // also notice that we're using a MySQL function as a value
     *  $db->insert_update(
     *      'table',
     *      array(
     *          'article_id'    =>  $article_id,
     *          'votes'         =>  0,
     *          'date_updated'  =>  'NOW()',
     *      ),
     *      array(
     *          'votes'         =>  'INC(1)',
     *      )
     *  );
     *
     *  // when using MySQL functions, the value will be used as it is without being escaped!
     *  // while this is ok when using a function without any arguments like NOW(), this may
     *  // pose a security concern if the argument(s) come from user input.
     *  // in this case we have to escape the value ourselves
     *  $db->insert_update(
     *      'table',
     *      array(
     *          'article_id'    =>  'CEIL("' . $db->escape($article_id) . '")',
     *          'votes'         =>  0,
     *          'date_updated'  =>  'NOW()',
     *      ),
     *      array(
     *          'votes'         =>  'INC(1)',
     *      )
     *  );
     *  </code>
     *
     *  @param  string  $table          Table in which to insert/update
     *
     *                                  *May also be specified like `databasename.tablename` if a database was not explicitly
     *                                  selected with the {@link connect()} or {@link select_database()} methods prior to
     *                                  calling this method.*
     *
     *  @param  mixed   $columns        An associative array where the array's keys represent the columns names and the
     *                                  array's values represent the values to be inserted in each respective column.
     *
     *                                  Column names will be enclosed in grave accents ``` (thus allowing the usage of
     *                                  reserved words) and will be automatically {@link escape() escaped} in order to
     *                                  prevent SQL injections.
     *
     *                                  You may also use any of {@link https://dev.mysql.com/doc/refman/8.0/en/built-in-function-reference.html MySQL's functions}
     *                                  as *values*.
     *
     *                                  >   Be aware that when using MySQL functions, the value will be used as it is,
     *                                      without being escaped! While this is ok when using a function without any
     *                                      arguments like `NOW()`, this may pose a security concern if the argument(s)
     *                                      come from user input. In this case make sure you {@link escape} the values
     *                                      yourself!
     *
     *  @param  mixed   $update         (Optional) An associative array where the array's keys represent the columns names
     *                                  and the array's values represent the values to update the columns' values to.
     *
     *                                  This array represents the columns/values to be updated if the inserted row would
     *                                  cause a duplicate value in a `UNIQUE` index or `PRIMARY KEY`.
     *
     *                                  If an empty array is given, the values in *$columns* will be used.
     *
     *                                  Column names will be enclosed in grave accents ``` (thus allowing the usage of
     *                                  reserved words) and will be automatically {@link escape() escaped} in order to
     *                                  prevent SQL injections.
     *
     *                                  A special value may also be used for when a column's value needs to be incremented
     *                                  or decremented. In this case, use `INC(value)` where *value* is the value to
     *                                  increase the column's value with. Use `INC(-value)` to decrease the column's value.
     *                                  See {@link update()} for an example.
     *
     *                                  You may also use any of {@link https://dev.mysql.com/doc/refman/8.0/en/built-in-function-reference.html MySQL's functions}
     *                                  as *values*.
     *
     *                                  >   Be aware that when using MySQL functions, the value will be used as it is,
     *                                      without being escaped! While this is ok when using a function without any
     *                                      arguments like `NOW()`, this may pose a security concern if the argument(s)
     *                                      come from user input. In this case make sure you {@link escape} the values
     *                                      yourself!
     *
     *                                  Default is an empty array
     *
     *  @param  boolean $highlight      (Optional) If set to `TRUE` the debugging console will be opened automatically
     *                                  and the query will be shown - useful for quickly debugging specific queries.
     *
     *                                  Default is `FALSE`
     *
     *  @since  2.1
     *  @deprecated                     Deprecated starting with 2.9.12, will be removed in 3.0
     *
     *  @return boolean                 Returns `TRUE` on success of `FALSE` on error
     */
    public function insert_update($table, $columns, $update = array(), $highlight = false) {

        // if $update is not given as an array, make it an empty array
        if (!is_array($update)) $update = array();

        // get string of comma separated column names, enclosed in grave accents
        $cols = $this->_escape(array_keys($columns));

        $values = '';

        // iterate through the given columns
        foreach ($columns as $column_name => $value) {

            // separate values by comma
            $values .= ($values !== '' ? ', ' : '');

            // if value is a MySQL function
            if ($this->_is_mysql_function($value)) {

                // use it as it is
                $values .= (is_null($value) ? 'NULL' : $value);

                // we don't need this value in the replacements array
                unset($columns[$column_name]);

            // if not a MySQL function, use a marker
            // that we'll replace with the value from the replacements array
            } else $values .= '?';

        }

        // if no $update specified
        if (empty($update)) {

            // use the columns specified in $columns
            $update_cols = $this->_build_sql($columns);

            // use the same column for update as for insert
            $update = $columns;

        // if $update is specified
        // generate the SQL from the $update array
        } else $update_cols = $this->_build_sql($update);

        // run the query
        $this->query('

            INSERT INTO
                ' . $this->_escape($table) . '
                (' . $cols . ')
            VALUES
                (' . $values . ')
            ON DUPLICATE KEY UPDATE
                ' . $update_cols . '

        ', array_merge(array_values($columns), array_values($update)), false, false, $highlight);

        // return TRUE if query was successful, or FALSE if it wasn't
        return isset($this->last_result) && $this->last_result !== false;

    }

    /**
     *  Sets the language to be used for the messages in the debugging console and in the log files
     *
     *  <code>
     *  // show messages in German
     *  $db->language('german');
     *  </code>
     *
     *  @param  string  $language   The name of the language file from the `languages` subdirectory
     *
     *                              Must be specified without the extension!
     *                              (i.e. `german` for the german language not `german.php`)
     *
     *                              Default is "english"
     *
     *  @since  1.0.6
     *
     *  @return void
     */
    public function language($language) {

        // include the language file
        require $this->path . '/languages/' . $language . '.php';

    }

    /**
     *  Optimizes all tables that have overhead (unused, lost space) in a database
     *
     *  <code>
     *  // optimize all tables in the currently selected database
     *  $db->optimize();
     *  </code>
     *
     *  @param  string  $table      (Optional) Table to optimize
     *
     *                              *May also be specified like `databasename.tablename` if a database was not explicitly
     *                              selected with the {@link connect()} or {@link select_database()} methods prior to
     *                              calling this method.*
     *
     *                              `%` may be used as a wildcard in table's name to optimize only the tables matching the
     *                              pattern.
     *
     *                              If not specified, all the tables in the currently selected database will be optimized.
     *
     *                              *This option was added in 2.9.5*
     *
     *  @since  1.1.2
     *
     *  @return void
     */
    public function optimize($table = '') {

        // if table argument contains the database name, extract it
        if (strpos($table, '.') !== false) $database = substr($table, 0, strpos($table, '.'));

        // fetch information on all the tables in the database
        $tables = $this->get_table_status($table);

        // iterate through the database's tables, and if it has overhead (unused, lost space), optimize it
        foreach ($tables as $table) $this->query('OPTIMIZE TABLE ' . (isset($database) ? $this->_escape($database) . '.' : '') . $this->_escape($table['Name']));

    }

    /**
     *  Sets one or more options that affect the behavior of a connection
     *
     *  See {@link https://www.php.net/manual/en/mysqli.options.php the options} that can be set.
     *
     *  >   Usually, this method must to be called before connecting to a MySQL server because the library uses "lazy
     *      connection" and it will not actually connect to the MySQL server until the first query is run, unless the
     *      {@link connect()} method is called with the `connect` argument set to `TRUE`. As such, you may also call
     *      this method after the {@link connect()} method but **only** if no queries were run before calling this
     *      method.
     *
     *  *This method may be called multiple times to set several options.*
     *
     *  <code>
     *  // instantiate the library
     *  $db = new Zebra_Database();
     *
     *  // set a single option
     *  $db->option(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
     *
     *  // set an array of options
     *  $db->option(array(
     *      MYSQLI_OPT_CONNECT_TIMEOUT  =>  5,
     *      MYSQLI_INIT_COMMAND         =>  'SET AUTOCOMMIT = 0',
     *  ));
     *
     *  // connect to a MySQL server using the options set above
     *  $db->`connect(...)
     *
     *  </`code
     *
     *  @param  mixed   $option     One of the valid values described {@link https://www.php.net/manual/en/mysqli.options.php here},
     *                              or an array of key/value pairs where the keys are valid values described in the previous
     *                              link.
     *
     *  @param  mixed   $value      (Optional) When setting a single option this is the value to be associated with that
     *                              option. When setting an array of options this argument is ignored.
     *
     *  @since  2.9.5
     *
     *  @return boolean|void        Will return `FALSE` if there is no connection and nothing otherwise.
     */
    public function option($option, $value = '') {

        // if a connection was already made
        if ($this->connection)

            // inform the user that options can only be set before connecting
            return $this->_log('errors', array(

                'message'   => $this->language['options_before_connect'],

            ));

        // if option is given as an array
        if (is_array($option))

            // iterate over the options
            foreach ($option as $property => $value)

                // save them to a private property
                $this->options[$property] = $value;

        // if option is not given as an array
        else $this->options[$option] = $value;

    }

    /**
     *  Parses a MySQL dump file (like an export from phpMyAdmin)
     *
     *  *If you must parse a very large file and your script crashed due to timeout or because of memory limitations,
     *  try the following:*
     *
     *  <code>
     *  // prevent script timeout
     *  set_time_limit(0);
     *
     *  // allow for more memory to be used by the script
     *  ini_set('memory_limit','128M');
     *  </code>
     *
     *  @param  string  $path   Path to the file to be parsed
     *
     *  @return boolean         Returns `TRUE` on success or `FALSE` on failure
     */
    public function parse_file($path) {

        // read file into an array
        $file_content = file($path);

        // if file was successfully opened
        if ($file_content) {

            $query = '';

            // iterates through every line of the file
            foreach ($file_content as $sql_line) {

                // trims whitespace from both beginning and end of line
                $tsql = trim($sql_line);

                // if line content is not empty and is the line does not represent a comment
                if ($tsql !== '' && substr($tsql, 0, 2) !== '--' && substr($tsql, 0, 1) !== '#') {

                    // add to query string
                    $query .= $sql_line;

                    // if line ends with ';'
                    if (preg_match('/;\s*$/', $sql_line)) {

                        // run the query
                        $this->query($query);

                        // empties the query string
                        $query = '';

                    }

                }

            }

            return true;

        }

        // if file could not be opened
        // save debug info
        return $this->_log('errors', array(

            'message'   => $this->language['file_could_not_be_opened'],

        ));


    }

    /**
     *  Runs a MySQL query
     *
     *  After a `SELECT` query you can get the number of returned rows by reading the {@link returned_rows} property.
     *
     *  After an `UPDATE`, `INSERT` or `DELETE` query you can get the number of affected rows by reading the
     *  {@link affected_rows} property.
     *
     *  >   Note that you don't necessarily need to store the result of this method in a variable for using it with a fetch
     *      method like {@link fetch_assoc()} or {@link fetch_obj()}, as all these methods, when called without the
     *      `resource` argument, work on the LAST returned result resource!
     *
     *  <code>
     *  // run a query
     *  $db->query('
     *      SELECT
     *          *
     *      FROM
     *          users
     *      WHERE
     *          gender = ?
     *  ', array($gender));
     *
     *  // array as replacement, for use with WHERE-IN conditions
     *  $db->query('
     *      SELECT
     *          *
     *      FROM
     *          users
     *      WHERE
     *          gender = ? AND
     *          id IN (?)
     *  ', array('f', array(1, 2, 3)));
     *  </code>
     *
     *  @param  string  $sql            MySQL statement to execute
     *
     *  @param  mixed   $replacements   (Optional) An array with as many items as the total parameter markers (`?`, question
     *                                  marks) in *$where*. Each item will be automatically {@link escape escaped} and
     *                                  will replace the corresponding `?`.<br>
     *                                  An item may also be an array, case in which each value from the array will be
     *                                  automatically {@link escape escaped} and concatenated with the other elements from
     *                                  the array - useful when using *WHERE column IN (?)* conditions. See second example
     *                                  {@link query here}.
     *
     *                                  Default is `""` (an empty string)
     *
     *  @param  mixed   $cache          (Optional) Instructs the library on whether it should cache the query results or
     *                                  not. Can be either `FALSE` - no caching - or an integer representing the number
     *                                  of seconds after which the cache will be considered as expired and the query will
     *                                  be executed again.
     *
     *                                  The caching method is specified through the {@link caching_method} property.
     *
     *                                  *For {@link query_unbuffered unbuffered queries} this argument is always `FALSE`!*
     *
     *                                  Default is `FALSE`
     *
     *  @param  boolean $calc_rows      (Optional) If query is a `SELECT` query, this argument is set to `TRUE`, and
     *                                  there is a `LIMIT` applied to the query, the value of the {@link found_rows}
     *                                  property (after the query was run) will represent the number of records that
     *                                  *would* have been returned if there was no `LIMIT` applied to the query.
     *
     *                                  This is very useful for creating pagination or computing averages. Also, note
     *                                  that this information will be available without running an extra query.
     *                                  {@link https://dev.mysql.com/doc/refman/8.0/en/information-functions.html#function_found-rows Here's how}
     *
     *                                  *For {@link query_unbuffered unbuffered queries} the value of this property will
     *                                  be available **only** after iterating over **all** the records with either
     *                                  {@link fetch_assoc()} or {@link fetch_obj()} methods. Until then, the value will
     *                                  be **0**!*
     *
     *                                  Default is `FALSE`
     *
     *                                  >   This argument is deprecated since 2.10.0 and will be removed in 3.0 because
     *                                      the technique used for it was {@link https://dev.mysql.com/doc/refman/8.0/en/information-functions.html#function_found-rows deprecated in MySQL 8.0.17}
     *                                      and is pending removal in a future version. Please use the solution provided
     *                                      in the MySQL documentation instead.
     *
     *  @param  boolean $highlight      (Optional) If set to `TRUE` the debugging console will be opened automatically
     *                                  and the query will be shown - useful for quickly debugging specific queries.
     *
     *                                  Default is `FALSE`
     *
     *  @return mixed                   Returns a resource or an array (if results are taken from the cache) upon success,
     *                                  or `FALSE` on error.
     *
     *                                  *If query results are taken from cache, the returned result will be a `pointer` to
     *                                  the actual results of the query!*
     */
    public function query($sql, $replacements = '', $cache = false, $calc_rows = false, $highlight = false) {

        // if no active connection exists, return false
        if (!$this->_connected()) return false;

        unset($this->affected_rows);

        // if $replacements is specified but it's not an array
        if ($replacements !== '' && !is_array($replacements))

            // save debug information
            return $this->_log('unsuccessful-queries', array(

                'query' => $sql,
                'error' => $this->language['warning_replacements_not_array']

            ));

        // if $replacements is specified and is an array
        if ($replacements !== '' && is_array($replacements) && !empty($replacements)) {

            // found how many items to replace are there in the query string
            preg_match_all('/\?/', $sql, $matches, PREG_OFFSET_CAPTURE);

            // if the number of items to replace is different than the number of items specified in $replacements
            if (!empty($matches[0]) && count($matches[0]) != count($replacements))

                // save debug information
                return $this->_log('unsuccessful-queries', array(

                    'query' => $sql,
                    'error' => $this->language['warning_replacements_wrong_number']

                ));

            // if the number of items to replace is the same as the number of items specified in $replacements
            // make preparations for the replacement
            $pattern1 = $pattern2 = $replacements1 = $replacements2 = array();

            // prepare parameter markers for replacement
            foreach ($matches[0] as $match) $pattern1[] = '/\\' . $match[0] . '/';

            foreach ($replacements as $key => $replacement) {

                // generate a string
                $randomstr = md5(microtime()) . $key;

                // prepare the replacements for the parameter markers
                $replacements1[] = $randomstr;

                // if the replacement is NULL, leave it like it is
                if ($replacement === null) $replacements2[$key] = 'NULL';

                // if the replacement is an array, implode and escape it for use in WHERE ? IN ? statement
                elseif (is_array($replacement)) $replacements2[$key] = preg_replace(array('/\\\\/', '/\$([0-9]*)/'), array('\\\\\\\\', '\\\$$1'), $this->implode($replacement));

                // otherwise, mysqli_real_escape_string the items in replacements
                // also, replace anything that looks like $45 to \$45 or else the next preg_replace-s will treat
                // it as reference
                else $replacements2[$key] = ($this->auto_quote_replacements ? '\'' : '') . preg_replace(array('/\\\\/', '/\$([0-9]*)/'), array('\\\\\\\\', '\\\$$1'), $this->escape($replacement)) . ($this->auto_quote_replacements ? '\'' : '');

                // and also, prepare the new pattern to be replaced afterwards
                $pattern2[$key] = '/' . $randomstr . '/';

            }

            // replace each question mark with something new
            // (we do this intermediary step so that we can actually have question marks in the replacements)
            $sql = preg_replace($pattern1, $replacements1, $sql, 1);

            // perform the actual replacement
            $sql = preg_replace($pattern2, $replacements2, $sql, 1);

        }

        // unbuffered queries cannot be cached
        if ($this->unbuffered && $cache) {

            // save debug information
            $this->_log('errors', array(

                'query'     => $sql,
                'message'   => $this->language['unbuffered_queries_cannot_be_cached'],

            ), false);

            // set this flag to false
            $cache = false;

        }

        // $calc_rows is TRUE, we have a SELECT query and the SQL_CALC_FOUND_ROWS string is not in it
        // (we do this trick to get the numbers of records that would've been returned if there was no LIMIT applied)
        if ($calc_rows && strpos($sql, 'SQL_CALC_FOUND_ROWS') === false)

            // add the 'SQL_CALC_FOUND_ROWS' parameter to the query
            $sql = preg_replace('/^(.*?)SELECT/is', '$1SELECT SQL_CALC_FOUND_ROWS', $sql, 1);

        if (isset($this->last_result)) unset($this->last_result);

        // starts a timer
        list($usec, $sec) = explode(' ', microtime());

        $start_timer = (float)$usec + (float)$sec;

        $refreshed_cache = 'nocache';

        // if we need to look for a cached version of the query's results
        if ($cache !== false && (int)$cache > 0) {

            // by default, we assume that the cache exists and is not expired
            $refreshed_cache = false;

            // if caching method is "memcache" and memcache is enabled
            if ($this->caching_method === 'memcache' && $this->memcache) {

                // the key to identify this particular information (prefix it if required)
                $memcache_key = md5($this->memcache_key_prefix . $sql);

                // if there is a cached version of what we're looking for, and data is valid
                if (($result = $this->memcache->get($memcache_key)) && $cached_result = @unserialize(gzuncompress(base64_decode($result)))) {

                    // put results in the right place
                    // (we couldn't do this above because $this->cached_result[] = @unserialize... would've triggered a warning)
                    $this->cached_results[] = $cached_result;

                    // assign to the last_result property the pointer to the position where the array was added
                    $this->last_result = count($this->cached_results) - 1;

                    // reset the pointer of the array
                    reset($this->cached_results[$this->last_result]);

                }

            // if caching method is "redis"
            } elseif ($this->caching_method === 'redis') {

                // the key to identify this particular information (prefix it if required)
                $redis_key = md5($this->redis_key_prefix . $sql);

                // if there is a cached version of what we're looking for, and data is valid
                if (($result = $this->redis->get($redis_key)) && $cached_result = @unserialize(gzuncompress(base64_decode($result)))) {

                    // put results in the right place
                    // (we couldn't do this above because $this->cached_result[] = @unserialize... would've triggered a warning)
                    $this->cached_results[] = $cached_result;

                    // assign to the last_result property the pointer to the position where the array was added
                    $this->last_result = count($this->cached_results) - 1;

                    // reset the pointer of the array
                    reset($this->cached_results[$this->last_result]);

                }

            // if caching method is "session"
            } elseif ($this->caching_method === 'session') {

                // unique identifier of the current query
                $key = md5($sql);

                // if a cached version of this query's result already exists and it is not expired
                if (isset($_SESSION[$key]) && isset($_SESSION[$key . '_timestamp']) && $_SESSION[$key . '_timestamp'] + $cache > time() && $cached_result = @unserialize(gzuncompress(base64_decode($_SESSION[$key])))) {

                    // put results in the right place
                    // (we couldn't do this above because $this->cached_result[] = @unserialize... would've triggered a warning)
                    $this->cached_results[] = $cached_result;

                    // assign to the last_result property the pointer to the position where the array was added
                    $this->last_result = count($this->cached_results) - 1;

                    // reset the pointer of the array
                    reset($this->cached_results[$this->last_result]);

                }

            // if caching method is "disk"
            } else {

                // if cache folder exists and is writable
                if (file_exists($this->cache_path) && is_dir($this->cache_path) && is_writable($this->cache_path)) {

                    // the cache file's name
                    $file_name = rtrim($this->cache_path, '/') . '/' . md5($sql);

                    // if a cached version of this query's result already exists, it is not expired and is valid
                    if (
                        file_exists($file_name)
                        && filemtime($file_name) + $cache > time()
                        && ($cached_result = @unserialize(gzuncompress(base64_decode(file_get_contents($file_name)))))
                    ) {

                        // put results in the right place
                        // (we couldn't do this above because $this->cached_result[] = @unserialize... would've triggered a warning)
                        $this->cached_results[] = $cached_result;

                        // assign to the last_result property the pointer to the position where the array was added
                        $this->last_result = count($this->cached_results) - 1;

                        // reset the pointer of the array
                        reset($this->cached_results[$this->last_result]);

                    }

                // if folder doesn't exist
                } else

                    // save debug information
                    return $this->_log('errors', array(

                        'message'   => $this->language['cache_path_not_writable'],

                    ), false);

            }

        }

        // if query was not read from the cache
        if (!isset($this->last_result)) {

            try {

                // run the query
                $this->last_result = mysqli_query($this->connection, $sql, $this->unbuffered ? MYSQLI_USE_RESULT : MYSQLI_STORE_RESULT);

            // if there was an error
            } catch (Exception $e) {

                $this->last_result = false;

            }

            // if no test transaction, query was unsuccessful and a transaction is in progress
            if ($this->transaction_status !== 3 && !$this->last_result && $this->transaction_status !== 0)

                // set transaction_status to 2 so that the transaction_commit know that it has to rollback
                $this->transaction_status = 2;

        }

        // stops timer
        list($usec, $sec) = explode(' ', microtime());

        $stop_timer = (float)$usec + (float)$sec;

        // add the execution time to the total execution time
        // (we will use this in the debugging console)
        $this->total_execution_time += $stop_timer - $start_timer;

        // if
        if (

            // notification address and notifier domain are set
            !empty($this->notification_address)

            && !empty($this->notifier_domain)

            // and execution time exceeds max_query_time
            && ($stop_timer - $start_timer > $this->max_query_time)

        )

            // then send a notification mail
            @mail(
                $this->notification_address,
                sprintf($this->language['email_subject'], $this->notifier_domain),
                sprintf($this->language['email_content'], $this->max_query_time, $stop_timer - $start_timer, $sql),
                'From: ' . $this->notifier_domain
            );

        // if the query was successfully executed
        if ($this->last_result !== false) {

            // if query's result was not read from cache (meaning $this->last_result is a result resource or boolean
            // TRUE - as queries like UPDATE, DELETE, DROP return boolean TRUE on success rather than a result resource)
            if ($this->_is_result($this->last_result) || $this->last_result === true) {

                // if returned resource is a valid resource, consider query to be a SELECT query
                $is_select = $this->_is_result($this->last_result);

                // reset these values for each query
                $this->returned_rows = $this->found_rows = 0;

                // if query was a SELECT query
                if ($is_select) {

                    // for buffered queries
                    if (!$this->unbuffered)

                        // the returned_rows property holds the number of records returned by a SELECT query
                        $this->returned_rows = $this->found_rows = @mysqli_num_rows($this->last_result);

                    // for unbuffered queries set this property of the result set so that once all the rows have iterated
                    // over, we can get some extra information (see the _manage_unbuffered_query_info method for more info)
                    else $this->last_result->query = $sql;

                    // if we need the number of rows that would have been returned if there was no LIMIT
                    // and the query was not an unbuffered one
                    if ($calc_rows && !$this->unbuffered) {

                        // get the number of records that would've been returned if there was no LIMIT
                        $found_rows = mysqli_fetch_assoc(mysqli_query($this->connection, 'SELECT FOUND_ROWS()'));

                        $this->found_rows = $found_rows['FOUND_ROWS()'];

                    }

                // if query was an action query, the affected_rows property holds the number of affected rows by
                // action queries (DELETE, INSERT, UPDATE)
                } else $this->affected_rows = @mysqli_affected_rows($this->connection);

                // if query's results need to be cached
                if ($is_select && $cache !== false && (int)$cache > 0) {

                    // flag that we have refreshed the cache
                    $refreshed_cache = true;

                    $cache_data = array();

                    // iterate though the query's records and save the results in a temporary variable
                    while ($row = mysqli_fetch_assoc($this->last_result)) $cache_data[] = $row;

                    // if there were any records fetched, resets the internal pointer of the result resource
                    if (!empty($cache_data)) $this->seek(0, $this->last_result);

                    // we'll also be saving the found_rows, returned_rows and columns information
                    array_push($cache_data, array(

                        'returned_rows' => $this->returned_rows,
                        'found_rows'    => $this->found_rows,
                        'column_info'   => $this->get_columns(),

                    ));

                    // the content to be cached
                    $content = base64_encode(gzcompress(serialize($cache_data)));

                    // if caching method is "memcache" and memcache is enabled
                    if ($this->caching_method === 'memcache' && $this->memcache)

                        // cache query data
                        $this->memcache->set($memcache_key, $content, ($this->memcache_compressed ? MEMCACHE_COMPRESSED : false), $cache);

                    // if caching method is "redis" and redis is enabled
                    elseif ($this->caching_method === 'redis' && $this->redis)

                        // cache query data
                        $this->redis->setEx($redis_key, $cache, $content);

                    // if caching method is "session"
                    elseif ($this->caching_method === 'session') {

                        // if there seems to be no active session
                        if (!isset($_SESSION))

                            // save debug information
                            return $this->_log('errors', array(

                                'message'   => $this->language['no_active_session'],

                            ));

                        // the unique identifier for the current query
                        $key = md5($sql);

                        // cache query data in current session
                        $_SESSION[$key] = $content;

                        // save also the current timestamp
                        $_SESSION[$key . '_timestamp'] = time();

                    // if caching method is "disk" and cached folder was found and is writable
                    } elseif (isset($file_name)) {

                        // deletes (if exists) the previous cache file
                        @unlink($file_name);

                        // creates the new cache file
                        $handle = fopen($file_name, 'wb');

                        // saves the query's result in it
                        fwrite($handle, $content);

                        // and close the file
                        fclose($handle);

                    }

                }

            // if query was read from cache
            } else {

                // if read from cache this must be a SELECT query
                $is_select = true;

                // the last entry in the cache file contains the returned_rows, found_rows and column_info properties
                // we need to take them off the array
                $counts = array_pop($this->cached_results[$this->last_result]);

                // set extract these properties from the values in the cached file
                $this->returned_rows    = $counts['returned_rows'];
                $this->found_rows       = $counts['found_rows'];
                $this->column_info      = $counts['column_info'];

            }

            // if we need the number of rows that would have been returned if there was no LIMIT, the query was an
            // unbuffered one and it was a successful query
            if ($calc_rows && $this->unbuffered && $this->_is_result($this->last_result))

                // set a flag telling the script to do this once all the rows are fetched
                $this->last_result->calc_rows = true;

            // if debugging is on
            if ($this->_is_debugging_enabled()) {

                $warning = '';

                $result = array();

                // if rows were returned
                if ($is_select) {

                    $row_counter = 0;

                    // if there are any number of rows to be shown
                    if ($this->debug_show_records) {

                        // if query was not read from cache
                        // put the first rows, as defined by debug_show_records, in an array to show them in the
                        // debugging console
                        if ($this->_is_result($this->last_result)) {

                            // if there are any rows
                            if (!$this->unbuffered && mysqli_num_rows($this->last_result)) {

                                // iterate through the records until we displayed enough records
                                while ($row_counter++ < $this->debug_show_records && $row = mysqli_fetch_assoc($this->last_result))

                                    $result[] = $row;

                                // reset the pointer in the result afterwards
                                // we have to mute error reporting because if the result set is empty (mysqli_num_rows() == 0),
                                // a seek to 0 will fail with a E_WARNING!
                                mysqli_data_seek($this->last_result, 0);

                            }

                        // if query was read from the cache and there are any records
                        // put the first rows, as defined by debug_show_records, in an array to show them in the
                        // debugging console
                        } elseif (!empty($this->cached_results[$this->last_result])) $result = array_slice($this->cached_results[$this->last_result], 0, $this->debug_show_records);

                    }

                    // if there were queries run already
                    if (isset($this->debug_info['successful-queries'])) {

                        $keys = array();

                        // iterate through the run queries
                        // to find out if this query was already run
                        foreach ($this->debug_info['successful-queries'] as $key => $query_data)

                            // if query was run before
                            if (

                                isset($query_data['records'])
                                && !empty($query_data['records'])
                                && $query_data['records'] == $result

                            // save the pointer to the query in an array
                            ) $keys[] = $key;

                        // if the query was run before
                        if (!empty($keys)) {

                            // issue a warning for all the queries that were found to be the same as the current one
                            // iterate through the queries that are the same
                            foreach ($keys as $key) {

                                // we create the variable as we will also use it later when adding the
                                // debug information for this query
                                $warning = sprintf($this->language['optimization_needed'], count($keys));

                                // add the warning to the query's debug information
                                $this->debug_info['successful-queries'][$key]['warning'] = $warning;

                            }

                        }

                    }

                    // if
                    if (

                        // if we need to EXPLAIN the last executed query
                        $this->debug_show_explain

                        // it was not an unbuffered one
                        && !$this->unbuffered

                        // query was successful
                        && $this->_is_result($this->last_result)

                    ) {

                        // since only SELECT, DELETE, INSERT, REPLACE, and UPDATE queries can be explained
                        // put the following code in a try/catch
                        try {

                            // ask MySQL to EXPLAIN the query
                            $explain_resource = mysqli_query($this->connection, 'EXPLAIN ' . $sql);

                            // if MYSQL could explain the query
                            if (!is_bool($explain_resource))

                                // put all the records returned by the explain query in an array
                                while ($row = mysqli_fetch_assoc($explain_resource)) $explain[] = $row;

                        // if EXPLAIN is not available
                        // (for something line SHOW TABLE for example) do nothing
                        } catch (Exception $e) {
                        }

                    }

                    // if
                    if (

                        // we need to EXPLAIN the last executed query
                        $this->debug_show_explain

                        // it was an unbuffered one
                        && $this->unbuffered

                        // query was successful
                        && $this->_is_result($this->last_result)

                    ) {

                        // set a flag telling the script to EXPLAIN the query once all the rows are fetched
                        $this->last_result->explain = true;

                        // the SQL to explain
                        $this->last_result->query = $sql;

                    }

                }

                // save debug information
                $this->_log('successful-queries', array(

                    'query'             => $sql,
                    'records'           => $result,
                    'returned_rows'     => $this->returned_rows,
                    'explain'           => (isset($explain) ? $explain : ''),
                    'affected_rows'     => (isset($this->affected_rows) ? $this->affected_rows : false),
                    'execution_time'    => $stop_timer - $start_timer,
                    'warning'           => $warning,
                    'highlight'         => $highlight,
                    'from_cache'        => $refreshed_cache,
                    'unbuffered'        => $this->unbuffered,
                    'transaction'       => ($this->transaction_status !== 0 ? true : false),

                ), false);

                // if this was an unbuffered query and a valid select query
                if ($this->unbuffered && $is_select && $this->_is_result($this->last_result))

                    // save the index of the entry in the debug_info array
                    $this->last_result->log_index = count($this->debug_info['successful-queries']) - 1;

                // if at least one query is to be highlighted, set the "minimize_console" property to FALSE
                if ($highlight) $this->minimize_console = false;

            }

            // return result resource
            return $this->last_result;

        }

        // in case of error
        // save debug information
        return $this->_log('unsuccessful-queries', array(

            'query' => $sql,
            'error' => mysqli_error($this->connection)

        ));

    }

    /**
     *  Runs a MySQL {@link https://www.php.net/manual/en/mysqlinfo.concepts.buffering.php unbuffered query}.
     *
     *  The method's arguments are the same as for the {@link query()} method.
     *
     *  *For unbuffered queries the values returned by {@link returned_rows} and {@link found_rows} properties will be
     *  available **only** after iterating over **all** the records with either {@link fetch_assoc()} or {@link fetch_obj()}
     *  methods, and will be **0** until then!*
     *
     *  >   Until you iterate over the entire resultset, all subsequent queries will return a `Commands out of sync` error
     *      unless the {@link free_result()} method is called
     *
     *  @since 2.9.4
     *
     *  @return mixed       Returns a resource or an array (if results are taken from the cache) upon success, or `FALSE`
     *                      on error.
     *
     *                      *If query results are taken from cache, the returned result will be a `pointer` to the actual
     *                      results of the query!*
     */
    public function query_unbuffered() {

        $this->unbuffered = true;

        $result = call_user_func_array(array($this, 'query'), func_get_args());

        $this->unbuffered = false;

        return $result;

    }

    /**
     *  Moves the internal row pointer of the MySQL result associated with the specified result identifier to the
     *  specified row number
     *
     *  The next call to a fetch method like {@link fetch_assoc()} or {@link fetch_obj()} would return that row.
     *
     *  @param  integer             $row        The row you want to move the pointer to
     *
     *                                          *$row* starts at `0`.
     *
     *                                          *$row* should be a value in the range from `0` to {@link returned_rows}
     *
     *  @param  resource|string     $resource   (Optional) Resource to fetch
     *
     *                                          >   If not specified, the resource returned by the last run query is used
     *
     *  @since  1.1.0
     *
     *  @return boolean             Returns `TRUE` on success or `FALSE` on failure
     */
    public function seek($row, $resource = '') {

        // if no active connection exists, return false
        if (!$this->_connected()) return false;

        // if no resource was specified, and there was a previous call to the "query" method, assign the last resource
        if ($resource === '' && isset($this->last_result) && $this->last_result !== false) $resource = & $this->last_result;

        // check if given resource is valid
        if ($this->_is_result($resource)) {

            // if this is an unbuffered query
            if ($resource->type == 1) {

                // get backtrace information
                $debug = debug_backtrace();

                // if method was called by another internal method (like fetch_assoc_all, for example) report that method
                if (isset($debug[1]) && isset($debug[1]['function']) && $debug[1]['class'] === 'Zebra_Database') $method = $debug[1]['function'];

                // if this (seek) method was called, report this method
                else $method = $debug[0]['function'];

                // save debug information
                return $this->_log('errors', array(

                    'message'   => sprintf($this->language['unusable_method_unbuffered_queries'], $method),

                ));

            }

            // return the fetched row
            if (mysqli_num_rows($resource) == 0 || mysqli_data_seek($resource, $row)) return true;

            // if error reporting was not suppressed with @
            elseif (error_reporting() != 0)

                // save debug information
                return $this->_log('errors', array(

                    'message'   => $this->language['could_not_seek'],

                ));

        // if $resource is actually a pointer to an array taken from cache
        } elseif (is_integer($resource) && isset($this->cached_results[$resource])) {

            // move the pointer to the start of the array
            reset($this->cached_results[$resource]);

            // if the pointer needs to be moved to the very first records then we don't need to do anything
            // as by resetting the array we already have that
            // simply return true
            if ($row == 0) return true;

            // if $row > 0
            elseif ($row > 0) {

                // get the current info from the array and advance the pointer
                while (next($this->cached_results[$resource]))

                    // if we've found what we were looking for
                    if ($row == key($this->cached_results[$resource])) return true;

                // save debug information
                return $this->_log('errors', array(

                    'message'   => $this->language['could_not_seek'],

                ));

            }

        }

        // if not a valid resource
        // save debug information
        return $this->_log('errors', array(

            'message'   => $this->language['not_a_valid_resource'],

        ));

    }

    /**
     *  Shorthand for simple `SELECT` queries
     *
     *  For complex queries (using `UNION`, `JOIN`, etc) use the {@link query()} method.
     *
     *  When using this method column names will be enclosed in grave accents ``` (thus allowing the usage of reserved
     *  words) and will be automatically {@link escape() escaped} in order to prevent SQL injections.
     *
     *  <code>
     *  $db->select(
     *      'column1, column2',
     *      'table',
     *      'criteria = ?',
     *      array($criteria)
     *  );
     *
     *  // or
     *
     *  $db->select(
     *      array('column1', 'column2'),
     *      'table',
     *      'criteria = ?',
     *      array($criteria)
     *  );
     *
     *  // or
     *
     *  $db->select(
     *      '*',
     *      'table',
     *      'criteria = ?',
     *      array($criteria)
     *  );
     *  </code>
     *
     *  @param  mixed  $columns         A string with comma separated values or an array representing valid column names
     *                                  as used in a `SELECT` statement.
     *
     *                                  >   If given as a string it will be enclosed in grave accents, so make sure you
     *                                      are only using column names and not things like "tablename.*" or MySQL
     *                                      functions!<br>
     *                                      Use this argument as an array if you want values (like MySQL functions) to
     *                                      be skipped from this process.
     *
     *                                  You may also use `*` (asterisk) to select all the columns of a table.
     *
     *  @param  string  $table          Table in which to search
     *
     *                                  *May also be specified like `databasename.tablename` if a database was not explicitly
     *                                  selected with the {@link connect()} or {@link select_database()} methods prior to
     *                                  calling this method.*
     *
     *                                  *Note that table name (and database name, if provided) will be enclosed in grave
     *                                  accents ``` and thus only one table name must be used!*
     *
     *  @param  string  $where          (Optional) A MySQL `WHERE` clause (without the `WHERE` keyword)
     *
     *                                  Default is `""` (an empty string)
     *
     *  @param  mixed   $replacements   (Optional) An array with as many items as the total parameter markers (`?`, question
     *                                  marks) in *$where*. Each item will be automatically {@link escape escaped} and
     *                                  will replace the corresponding `?`.<br>
     *                                  An item may also be an array, case in which each value from the array will be
     *                                  automatically {@link escape escaped} and concatenated with the other elements from
     *                                  the array - useful when using *WHERE column IN (?)* conditions. See second example
     *                                  {@link query here}.
     *
     *                                  Default is `""` (an empty string)
     *
     *  @param  string  $order          (Optional) A MySQL `ORDER BY` clause (without the `ORDER BY` keyword)
     *
     *                                  Default is `""` (an empty string)
     *
     *  @param  mixed   $limit          (Optional) A MySQL `LIMIT` clause (without the `LIMIT` keyword)
     *
     *                                  Default is `""` (an empty string)
     *
     *  @param  mixed   $cache          (Optional) Instructs the library on whether it should cache the query results or
     *                                  not. Can be either `FALSE` - no caching - or an integer representing the number
     *                                  of seconds after which the cache will be considered as expired and the query will
     *                                  be executed again.
     *
     *                                  The caching method is specified through the {@link caching_method} property.
     *
     *                                  Default is `FALSE`
     *
     *  @param  boolean $calc_rows      (Optional) If this argument is set to `TRUE`, and there is a LIMIT applied to the
     *                                  query, the value of the {@link found_rows} property (after the query was run)
     *                                  will represent the number of records that would have been returned if there was
     *                                  no LIMIT applied to the query.
     *
     *                                  This is very useful for creating pagination or computing averages. Also, note
     *                                  that this information will be available without running an extra query.
     *                                  {@link https://dev.mysql.com/doc/refman/8.0/en/information-functions.html#function_found-rows Here's how}
     *
     *                                  Default is `FALSE`
     *
     *                                  >   This argument is deprecated since 2.10.0 and will be removed in 3.0 because
     *                                      the technique used for it was {@link https://dev.mysql.com/doc/refman/8.0/en/information-functions.html#function_found-rows deprecated in MySQL 8.0.17}
     *                                      and is pending removal in a future version. Please use the solution provided
     *                                      in the MySQL documentation instead.
     *
     *  @param  boolean $highlight      (Optional) If set to `TRUE` the debugging console will be opened automatically
     *                                  and the query will be shown - useful for quickly debugging specific queries.
     *
     *                                  Default is `FALSE`
     *
     *  @since  2.0
     *
     *  @return mixed                   Returns a resource or an array (if results are taken from the cache) upon success,
     *                                  or `FALSE` on error.
     *
     *                                  *If query results are taken from cache, the returned result will be a `pointer`
     *                                  to the actual results of the query!*
     */
    public function select($columns, $table, $where = '', $replacements = '', $order = '', $limit = '', $cache = false, $calc_rows = false, $highlight = false) {

        // if $columns is not a string, split by comma and trim whitespace
        if (!is_array($columns)) $columns = array_map('trim', explode(',', $columns));

        // iterate over the given columns
        foreach ($columns as $key => $value)

            // and escape everyone except the * character
            if (null !== $value && trim($value) !== '*') $columns[$key] = $this->_escape($value);

        // run the query
        return $this->query('

            SELECT
                ' . implode(', ', $columns) . '
            FROM
                ' . $this->_escape($table) .

            ($where !== '' ? ' WHERE ' . $where : '') .

            ($order !== '' ? ' ORDER BY ' . $order : '') .

            ($limit !== '' ? ' LIMIT ' . $limit : '') . '

        ', $replacements, $cache, $calc_rows, $highlight);

    }

    /**
     *  Selects the default database for queries
     *
     *  <code>
     *  // set the default database for queries
     *  $db->select_database('database_name');
     *  </code>
     *
     *  @param  string  $database   Name of the database to select as the default database for queries
     *
     *  @since 2.9.4
     *
     *  @return boolean     Returns `TRUE` on success or `FALSE` on failure
     */
    public function select_database($database) {

        // if no active connection exists, return false
        if (!$this->_connected()) return false;

        // update the value in the credentials
        $this->credentials['database'] = $database;

        // select the database
        return mysqli_select_db($this->connection, $database);

    }

    /**
     *  Sets MySQL character set and collation
     *
     *  The ensure that data is both properly saved and retrieved from the database you should call this method first
     *  thing after connecting to the database.
     *
     *  If this method is not called a warning message will be displayed in the debugging console.
     *
     *  >   Warnings can be disabled by setting the {@link disable_warnings} property.
     *
     *  @param  string  $charset    (Optional) The character set to be used by the database
     *
     *                              Default is `utf8`
     *
     *                              See the {@link https://dev.mysql.com/doc/refman/8.0/en/charset-charsets.html list of possible values}
     *
     *  @param  string  $collation  (Optional) The collation to be used by the database
     *
     *                              Default is `utf8_general_ci`
     *
     *                              See the {@link https://dev.mysql.com/doc/refman/8.0/en/charset-charsets.html list of possible values}
     *
     *  @since  2.0
     *
     *  @return void
     */
    public function set_charset($charset = 'utf8', $collation = 'utf8_general_ci') {

        // do not show the warning that this method has not been called
        unset($this->warnings['charset']);

        // set MySQL character set
        $this->query('SET NAMES "' . $this->escape($charset) . '" COLLATE "' . $this->escape($collation) . '"');

    }

    /**
     *  Checks whether a table exists in a database
     *
     *  <code>
     *  // checks whether table "users" exists
     *  table_exists('users');
     *  </code>
     *
     *  @param  string  $table      The name of the table to check if it exists in the database.
     *
     *                              *May also be specified like `databasename.tablename` if a database was not explicitly
     *                              selected with the {@link connect()} or {@link select_database()} methods prior to
     *                              calling this method.*
     *
     *  @since  2.3
     *
     *  @return boolean             Returns `TRUE` if table given as argument exists in the database, or `FALSE` if it
     *                              does not
     */
    public function table_exists($table) {

        // if table argument contains the database name, extract it
        if (strpos($table, '.') !== false) list($database, $table) = explode('.', $table);

        // check if table exists in the database
        return is_array($this->fetch_assoc($this->query('SHOW TABLES' . (isset($database) ? ' IN ' . $database : '') . ' LIKE ?', array($table))));

    }

    /**
     *  Ends a transaction
     *
     *  This means that if all the queries since {@link transaction_start()} are valid, it updates the data in the
     *  database, but if any of the queries failed, it ignores all queries and treats them as if they never happened.
     *
     *  <code>
     *  // start transactions
     *  $db->transaction_start();
     *
     *  // run queries
     *
     *  // if all the queries since "transaction_start" are valid, write data to the database;
     *  // if any of the queries had an error, ignore all queries and treat them as if they never happened
     *  $db->transaction_complete();
     *  </code>
     *
     *  @since  2.1
     *
     *  @return boolean     Returns `TRUE` on success or `FALSE` on error
     */
    public function transaction_complete() {

        $sql = 'COMMIT';

        // if a transaction is in progress
        if ($this->transaction_status !== 0) {

            // if this was a test transaction or there was an error with one of the queries in the transaction
            if ($this->transaction_status === 3 || $this->transaction_status === 2) {

                // rollback changes
                $this->query('ROLLBACK');

                // set flag so that the query method will know that no transaction is in progress
                $this->transaction_status = 0;

                // if it was a test transaction return TRUE or FALSE otherwise
                return ($this->transaction_status === 3 ? true : false);

            }

            // if all queries in the transaction were executed successfully and this was not a test transaction

            // commit transaction
            $this->query($sql);

            // set flag so that the query method will know that no transaction is in progress
            $this->transaction_status = 0;

            // return TRUE if query was successful, or FALSE if it wasn't
            return isset($this->last_result) && $this->last_result !== false;

        }

        // if no transaction was in progress
        // save debug information
        return $this->_log('unsuccessful-queries', array(

            'query' => $sql,
            'error' => $this->language['no_transaction_in_progress'],

        ), false);

    }

    /**
     *  Starts the transaction system.
     *
     *  Transactions work only with databases that support transaction-safe table types. In MySQL, these are `InnoDB` or
     *  `BDB` table types. Working with `MyISAM` tables will not raise any errors but statements will be executed
     *  automatically as soon as they are called (just like if there was no transaction).
     *
     *  If you are not familiar with transactions, have a look {@link https://dev.mysql.com/doc/refman/8.0/en/commit.html here}
     *  and try to find a good online resource for more specific information.
     *
     *  <code>
     *  // start transactions
     *  $db->transaction_start();
     *
     *  // run queries
     *
     *  // if all the queries since "transaction_start" are valid, write data to database;
     *  // if any of the queries had an error, ignore all queries and treat them as if they never happened
     *  $db->transaction_complete();
     *  </code>
     *
     *  @param  boolean     $test_only      (Optional) Starts the transaction system in "test mode" causing the queries
     *                                      to be rolled back (when {@link transaction_complete()} is called) - even if
     *                                      all queries are valid
     *
     *                                      Default is `FALSE`
     *
     *  @since  2.1
     *
     *  @return boolean                     Returns `TRUE` on success or `FALSE` on error
     */
    public function transaction_start($test_only = false) {

        $sql = 'START TRANSACTION';

        // if a transaction is not in progress
        if ($this->transaction_status === 0) {

            // set flag so that the query method will know that a transaction is in progress
            $this->transaction_status = ($test_only ? 3 : 1);

            // try to start transaction
            $this->query($sql);

            // return TRUE if query was successful, or FALSE if it wasn't
            return isset($this->last_result) && $this->last_result !== false;

        }

        // save debug information
        return $this->_log('unsuccessful-queries', array(

            'query' => $sql,
            'error' => $this->language['transaction_in_progress'],

        ), false);

    }

    /**
     *  Shorthand for `TRUNCATE` queries
     *
     *  *Truncating a table is quicker then deleting all rows, as stated in the
     *  {@link https://dev.mysql.com/doc/refman/8.0/en/truncate-table.html MySQL documentation}. Truncating a table also
     *  resets the value of the AUTO INCREMENT column.*
     *
     *  <code>
     *  $db->truncate('table');
     *  </code>
     *
     *  @param  string  $table          Table to truncate
     *
     *                                  *May also be specified like `databasename.tablename` if a database was not explicitly
     *                                  selected with the {@link connect()} or {@link select_database()} methods prior to
     *                                  calling this method.*
     *
     *  @param  boolean $highlight      (Optional) If set to `TRUE` the debugging console will be opened automatically
     *                                  and the query will be shown - useful for quickly debugging specific queries.
     *
     *                                  Default is `FALSE`
     *
     *  @since  1.0.9
     *
     *  @return boolean                 Returns `TRUE` on success of `FALSE` on error
     */
    public function truncate($table, $highlight = false) {

        // run the query
        $this->query('

            TRUNCATE
                ' . $this->_escape($table) . '

        ', '', false, false, $highlight);

        // return TRUE if query was successful, or FALSE if it wasn't
        return isset($this->last_result) && $this->last_result !== false;

    }

    /**
     *  Shorthand for `UPDATE` queries
     *
     *  When using this method column names will be enclosed in grave accents ``` (thus allowing the usage of reserved
     *  words) and will be automatically {@link escape() escaped} in order to prevent SQL injections.
     *
     *  After an update check {@link affected_rows} to find out how many rows were affected.
     *
     *  <code>
     *  // notice that we're using a MySQL function as a value
     *  $db->update(
     *      'table',
     *      array(
     *          'column1'       =>  'value1',
     *          'column2'       =>  'value2',
     *          'date_updated'  =>  'NOW()',
     *      ),
     *      'criteria = ?',
     *      array($criteria)
     *  );
     *
     *  // when using MySQL functions, the value will be used as it is without being escaped!
     *  // while this is ok when using a function without any arguments like NOW(), this may
     *  // pose a security concern if the argument(s) come from user input.
     *  // in this case we have to escape the value ourselves
     *  $db->update(
     *      'table',
     *      array(
     *          'column1'       =>  'TRIM(UCASE("' . $db->escape($value1) . '"))',
     *          'column2'       =>  'value2',
     *          'date_updated'  =>  'NOW()',
     *      ),
     *      'criteria = ?',
     *      array($criteria)
     *  );
     *  </code>
     *
     *  @param  string  $table          Table in which to update
     *
     *                                  *May also be specified like `databasename.tablename` if a database was not explicitly
     *                                  selected with the {@link connect()} or {@link select_database()} methods prior to
     *                                  calling this method.*
     *
     *  @param  mixed   $columns        An associative array where the array's keys represent the columns names and the
     *                                  array's values represent the values to be inserted in each respective column.
     *
     *                                  Column names will be enclosed in grave accents ``` (thus allowing the usage of
     *                                  reserved words) and will be automatically {@link escape() escaped} in order to
     *                                  prevent SQL injections.
     *
     *                                  A special value may also be used for when a column's value needs to be
     *                                  incremented or decremented. In this case, use `INC(value)` where *value*
     *                                  is the value to increase the column's value with. Use `INC(-value)` to decrease
     *                                  the column's value:
     *
     *                                  <code>
     *                                  $db->update(
     *                                      'table',
     *                                      array(
     *                                          'column'    =>  'INC(?)',
     *                                      ),
     *                                      'criteria = ?',
     *                                      array(
     *                                          $value,
     *                                          $criteria
     *                                      )
     *                                  );
     *                                  </code>
     *
     *                                  ...is equivalent to
     *
     *                                  <code>
     *                                  $db->query('
     *                                      UPDATE
     *                                          table
     *                                      SET
     *                                          column = column + ?
     *                                      WHERE
     *                                          criteria = ?
     *                                  ', array($value, $criteria));
     *                                  </code>
     *
     *                                  You may also use any of {@link https://dev.mysql.com/doc/refman/8.0/en/built-in-function-reference.html MySQL's functions}
     *                                  as *values*.
     *
     *                                  >   Be aware that when using MySQL functions, the value will be used as it is,
     *                                      without being escaped! While this is ok when using a function without any
     *                                      arguments like `NOW()`, this may pose a security concern if the argument(s)
     *                                      come from user input. In this case make sure you {@link escape} the values
     *                                      yourself!
     *
     *  @param  string  $where          (Optional) A MySQL `WHERE` clause (without the `WHERE` keyword)
     *
     *                                  Default is `""` (an empty string)
     *
     *  @param  mixed   $replacements   (Optional) An array with as many items as the total parameter markers (`?`, question
     *                                  marks) in *$where*. Each item will be automatically {@link escape escaped} and
     *                                  will replace the corresponding `?`.<br>
     *                                  An item may also be an array, case in which each value from the array will be
     *                                  automatically {@link escape escaped} and concatenated with the other elements from
     *                                  the array - useful when using *WHERE column IN (?)* conditions. See second example
     *                                  {@link query here}.
     *
     *                                  Default is `""` (an empty string)
     *
     *  @param  boolean $highlight      (Optional) If set to `TRUE` the debugging console will be opened automatically
     *                                  and the query will be shown - useful for quickly debugging specific queries.
     *
     *                                  Default is `FALSE`
     *
     *  @since  1.0.9
     *
     *  @return boolean                 Returns `TRUE` on success of `FALSE` on error
     */
    public function update($table, $columns, $where = '', $replacements = '', $highlight = false) {

        // if $replacements is specified but it's not an array
        if ($replacements !== '' && !is_array($replacements))

            // save debug information
            return $this->_log('unsuccessful-queries', array(

                'query' => '',
                'error' => $this->language['warning_replacements_not_array']

            ));

        // generate the SQL from the $columns array
        $cols = $this->_build_sql($columns);

        // run the query
        $this->query('

            UPDATE
                ' . $this->_escape($table) . '
            SET
                ' . $cols .
            ($where !== '' ? ' WHERE ' . $where : '') . '

        ', array_merge(array_values($columns), $replacements === '' ? array() : $replacements), false, false, $highlight);

        // return TRUE if query was successful, or FALSE if it wasn't
        return isset($this->last_result) && $this->last_result !== false;

    }

    /**
     *  Given an associative array where the array's keys represent column names and the array's values represent the
     *  values to be associated with each respective column, this method will enclose column names in grave accents ```
     *  (thus, allowing the usage of reserved words) and will automatically {@link escape escape} values.
     *
     *  It will also take care of particular cases where the INC keyword is used in the values, where the INC keyword is
     *  used with a parameter marker ("?", question mark) or where a value is a single question mark - which throws an
     *  error message.
     *
     *  This method may also alter the original variable given as argument, as it is passed by reference!
     *
     *  @param  mixed   $columns    An associative array where the array's keys represent column names and the array's
     *                              values represent the values to be associated with each respective column
     *
     *  @access private
     *
     *  @return string  Returns the built SQL string
     */
    private function _build_sql(&$columns) {

        $sql = '';

        // start creating the SQL string and enclose field names in `
        foreach ($columns as $column_name => $value) {

            // separate values by comma
            $sql .= ($sql !== '' ? ', ' : '');

            // if value is just a parameter marker ("?", question mark)
            if (null !== $value && trim($value) === '?')

                // throw an error
                return $this->_log('unsuccessful-queries', array(

                    'error' => sprintf($this->language['cannot_use_parameter_marker'], print_r($columns, true)),

                ));

            // if special INC() keyword is used
            if (null !== $value && preg_match('/^INC\((\-{1})?([0-9]+)\)/i', $value, $matches) > 0) {

                // translate to SQL
                $sql .= '`' . $column_name . '` = `' . $column_name . '` ' . ($matches[1] === '-' ? '-' : '+') . ' ?';

                // if INC() contains an actual value and not a parameter marker ("?", question mark)
                // add the actual value to the array with the replacement values
                if ($matches[2] !== '?') $columns[$column_name] = $matches[2];

                // if we have a parameter marker ("?", question mark) instead of a value, it means the replacement value
                // is already in the array with the replacement values, and that we don't need it here anymore
                else unset($columns[$column_name]);

            // if value looks like one or more nested functions
            } elseif ($this->_is_mysql_function($value)) {

                // build the string without enclosing this value in quotes
                $sql .= '`' . $column_name . '` = ' . (is_null($value) ? 'NULL' : $value);

                // we don't need this anymore
                unset($columns[$column_name]);

            // the usual way
            } else $sql .= '`' . $column_name . '` = ?';

        }

        // return the built sql
        return $sql;

    }

    /**
     *  Checks if the connection to the MySQL server has been previously established by the connect() method.
     *
     *  @access private
     */
    private function _connected() {

        // if there's no connection to a MySQL database
        if (!$this->connection || $this->connection->connect_errno != 0) {

            // we need this because it is the only way we can set the connection options (if any)
            $this->connection = mysqli_init();

            // if we have any options set
            if (!empty($this->options))

                // iterate over the set options
                foreach ($this->options as $option => $value)

                    // set each option
                    $this->connection->options($option, $value) ||

                        // log if there's a bogus option/value
                        $this->_log('errors', array(

                            'message'   => sprintf($this->language['invalid_option'], $option),

                        ));

            // try to connect to the MySQL server
            try {

                @mysqli_real_connect(
                    $this->connection,
                    $this->credentials['host'],
                    $this->credentials['user'],
                    $this->credentials['password'],
                    $this->credentials['database'],
                    $this->credentials['port'],
                    $this->credentials['socket']
                );

            } catch (Exception $e) {
            }

            // tries to connect to the MySQL database
            if (mysqli_connect_errno())

                // if connection could not be established
                // save debug information
                return $this->_log('errors', array(

                    'message'   => $this->language['could_not_connect_to_database'],
                    'error'     => mysqli_connect_error(),

                ));

            // if caching is to be done to a memcache server and we don't yet have a connection
            if ($this->caching_method === 'memcache' && !$this->memcache && $this->memcache_host !== false && $this->memcache_port !== false) {

                // if memcache extension is installed
                if (class_exists('Memcache')) {

                    // instance to the memcache object
                    $memcache = new Memcache();

                    // try to connect to the memcache server
                    if (!$memcache->connect($this->memcache_host, $this->memcache_port))

                        // if connection could not be established, save debug information
                        $this->_log('errors', array(

                            'message'   => $this->language['could_not_connect_to_memcache_server']

                        ));

                    else $this->memcache = $memcache;

                // if memcache extension is not installed
                } else

                    // if connection could not be established, save debug information
                    $this->_log('errors', array(

                        'message'   => $this->language['memcache_extension_not_installed']

                    ));

            // if caching is to be done to a redis server and we don't yet have a connection
            } elseif ($this->caching_method === 'redis' && !$this->redis && $this->redis_host !== false && $this->redis_port !== false) {

                // if redis extension is installed
                if (class_exists('Redis')) {

                    // instance to the redis object
                    $redis = new Redis();

                    // try to connect to the redis server
                    if (!$redis->connect($this->redis_host, $this->redis_port))

                        // if connection could not be established, save debug information
                        $this->_log('errors', array(

                            'message'   => $this->language['could_not_connect_to_redis_server']

                        ));

                    else $this->redis = $redis;

                // if redis extension is not installed
                } else

                    // if connection could not be established, save debug information
                    $this->_log('errors', array(

                        'message'   => $this->language['redis_extension_not_installed']

                    ));

            }

        }

        // return TRUE if there is no error
        return true;

    }

    /**
     *  Shows the debugging console (or writes debugging information to the log file) when the script ends, if
     *  necessary conditions are met.
     *
     *  *This is a public method because it's used with register_shutdown_function.*
     *
     *  @access private
     *
     *  @return mixed
     */
    function _show_debugging_console() {

        // if debugging is on
        if ($this->_is_debugging_enabled()) {

            // include the SqlFormatter library, if available
            @include_once 'includes/SqlFormatter.php';

            // if SqlFormatter is available
            if (class_exists('SqlFormatter')) {

                // set some properties for the formatter
                SqlFormatter::$number_attributes = SqlFormatter::$boundary_attributes = 'class="symbol"';
                SqlFormatter::$quote_attributes = 'class="string"';
                SqlFormatter::$reserved_attributes = 'class="keyword"';
                SqlFormatter::$comment_attributes = 'class="comment"';
                SqlFormatter::$tab = '    ';

            }

            // if data is to be written to a log file instead of being shown on the screen
            if (is_array($this->debug)) return call_user_func_array(array($this, '_write_log'), $this->debug);

            // if request was made from the CLI defer to _write_log
            elseif ($this->is_cli_request) return $this->_write_log(false, false, false, true);

            // is this an AJAX request?
            $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']);

            // if warnings are not disabled
            if (!$this->disable_warnings)

                // if there are any warning messages iterate through them
                foreach (array_keys($this->warnings) as $warning)

                    // add them to the debugging console
                    $this->_log('warnings', array(

                        'message'   => $this->language['warning_' . $warning],

                    ), false);

            // blocks to be shown in the debugging console
            $blocks = array(
                'errors'                => array(
                    'counter'       => 0,
                    'identifier'    => 'e',
                    'generated'     => '',
                ),
                'unsuccessful-queries'  => array(
                    'counter'       => 0,
                    'identifier'    => 'uq',
                    'generated'     => '',
                ),
                'successful-queries'    => array(
                    'counter'       => 0,
                    'identifier'    => 'sq',
                    'generated'     => '',
                ),
                'warnings'              => array(
                    'counter'       => 0,
                    'identifier'    => 'w',
                    'generated'     => '',
                ),
                'globals'               => array(
                    'generated'     => '',
                ),
            );

            // if we don't have to show globals
            // (value is boolean FALSE or is an array but all entries are set to FALSE)
            if ($this->debug_show_globals === false || (is_array($this->debug_show_globals) && empty(array_filter($this->debug_show_globals, function($value) { return $value !== false; })))) unset($blocks['globals']);

            // there are no warnings
            $warnings = false;

            // prepare output for each block
            foreach (array_keys($blocks) as $block) {

                $output = '';

                // if there is any information for the current block
                // (we need to render the HTML for errors/successful/unsuccessful tabs either way because these may be populated from AJAX requests)
                if (isset($this->debug_info[$block]) || in_array($block, array('errors', 'successful-queries', 'unsuccessful-queries'))) {

                    // because we may be here for non-existing unsuccessful queries
                    if (isset($this->debug_info[$block]))

                        // iterate through the error message
                        foreach ($this->debug_info[$block] as $debug_info) {

                            // increment the messages counter
                            $counter = ++$blocks[$block]['counter'];

                            $identifier = $blocks[$block]['identifier'];

                            // if there are any queries, pretty-print them
                            if (isset($debug_info['query']))

                                // format and highlight query
                                $debug_info['query'] = class_exists('SqlFormatter') ? SqlFormatter::format($debug_info['query']) : $debug_info['query'];

                            // all blocks are enclosed in tables
                            $output .= '
                                <table cellspacing="0" cellpadding="0" border="0" class="zdc-entry' .

                                    // should this query be highlighted or is a transaction
                                    ($block === 'unsuccessful-queries' || $block === 'errors' && isset($debug_info['error']) ? ' zdc-visible' : (isset($debug_info['highlight']) && $debug_info['highlight'] == 1 ? ' zdc-highlight zdc-visible' : (isset($debug_info['transaction']) && $debug_info['transaction'] ? ' zdc-transaction' : ''))) .

                                '">
                                    <tr>
                                        <td class="zdc-counter' . ($is_ajax ? ' zdc-counter-ajax' : '') . '">' . str_pad($counter, 3, '0', STR_PAD_LEFT) . '</td>
                                        <td class="zdc-data">
                            ';

                            // are there any error messages issued by the script?
                            if (isset($debug_info['message']) && trim($debug_info['message']) !== '')

                                $output .= '
                                    <div class="zdc-box zdc-error">
                                        ' . $debug_info['message'] . '
                                    </div>
                                ';

                            // are there any error messages issued by MySQL?
                            if (isset($debug_info['error']) && trim($debug_info['error']) !== '')

                                $output .= '
                                    <div class="zdc-box zdc-error">
                                        ' . $debug_info['error'] . '
                                    </div>
                                ';

                            // are there any warning messages issued by the script?
                            if (isset($debug_info['warning']) && trim($debug_info['warning']) !== '') {

                                $output .= '
                                    <div class="zdc-box zdc-error">' .
                                        $debug_info['warning'] . '
                                    </div>
                                ';

                                // set a flag so that we show in the minimized debugging console that there are warnings
                                $warnings = true;

                            }

                            // is there a query to be displayed?
                            if (isset($debug_info['query']))

                                $output .= '
                                    <div class="zdc-box zdc-syntax-highlight">' .
                                        preg_replace('/^\<br\>/', '', html_entity_decode($debug_info['query'])) . '
                                    </div>
                                ';

                            // start generating the actions box
                            $output .= '
                                <div class="zdc-box zdc-actions">
                                    <ul>
                            ';

                            // highlight AJAX requests
                            if ($is_ajax) $output .= '<li class="zdc-ajax">AJAX</li>';

                            // actions specific to successful queries
                            if ($block === 'successful-queries') {

                                // info about whether the query results were taken from cache or not
                                if ($debug_info['from_cache'] !== 'nocache')

                                    $output .= '
                                        <li class="zdc-cache">
                                            <strong>' . $this->language['from_cache'] . ' (' . $this->caching_method . ')</strong>
                                        </li>
                                    ';

                                // info about whether the query results were taken from cache or not
                                elseif ($debug_info['unbuffered'])

                                    $output .= '
                                        <li class="zdc-unbuffered">
                                            <strong>' . $this->language['unbuffered'] . '</strong>
                                        </li>
                                    ';

                                // info about execution time
                                $output .= '
                                    <li class="zdc-time">' .
                                        $this->language['execution_time'] . ': ' .
                                        number_format($debug_info['execution_time'], 5) . ' ' .
                                        $this->language['seconds'] . ' (<strong>' .
                                        number_format(
                                            ($this->total_execution_time != 0 ? $debug_info['execution_time'] * 100 / $this->total_execution_time : 0),
                                            2, '.', ','
                                        ) . '</strong>%)
                                    </li>
                                ';

                                // if not an action query
                                if ($debug_info['affected_rows'] === false)

                                    // button for reviewing returned rows
                                    $output .= '
                                        <li class="zdc-records">
                                            ' . (!empty($debug_info['records']) ? '<a href="javascript: void(0)" class="zdc-toggle zdc-records-table">' : '') .
                                                $this->language['returned_rows'] . ': <strong>' . $debug_info['returned_rows'] . '</strong>
                                            ' . (!empty($debug_info['records']) ? '</a>' : '') . '
                                        </li>
                                    ';

                                // if action query
                                else

                                    // info about affected rows
                                    $output .= '
                                        <li class="zdc-affected">' .
                                            $this->language['affected_rows'] . ': <strong>' . $debug_info['affected_rows'] . '</strong>
                                        </li>
                                    ';

                                // if EXPLAIN is available (only for SELECT queries)
                                if (is_array($debug_info['explain']))

                                    // button for reviewing EXPLAIN results
                                    $output .= '
                                        <li class="zdc-explain">
                                            <a href="javascript: void(0)" class="zdc-toggle zdc-explain-table">' .
                                                $this->language['explain'] . '
                                            </a>
                                        </li>
                                    ';

                            }

                            // if backtrace information is available
                            if (isset($debug_info['backtrace']))

                                $output .= '
                                    <li class="zdc-backtrace">
                                        <a href="javascript: void(0)" class="zdc-toggle zdc-backtrace-table">' .
                                            $this->language['backtrace'] . '
                                        </a>
                                    </li>
                                ';

                            // if we are need to show a link to a database manager to open the query in
                            if (($block === 'successful-queries' || $block === 'unsuccessful-queries') && $this->debug_show_database_manager) {

                                $output .= '
                                    <li class="zdc-database-manager">
                                        ' . str_replace(
                                                array(
                                                    '%host%',
                                                    '%user%',
                                                    '%password%',
                                                    '%database%',
                                                    '%port%',
                                                    '%socket%',
                                                    '%query%',
                                                ),
                                                array(
                                                    $this->credentials !== null ? urlencode($this->credentials['host']) : '',
                                                    $this->credentials !== null ? urlencode($this->credentials['user']) : '',
                                                    $this->credentials !== null ? urlencode($this->credentials['password']) : '',
                                                    $this->credentials !== null ? urlencode($this->credentials['database']) : '',
                                                    $this->credentials !== null ? urlencode($this->credentials['port']) : '',
                                                    $this->credentials !== null ? urlencode($this->credentials['socket']) : '',
                                                    $this->credentials !== null ? urlencode(html_entity_decode(strip_tags($debug_info['query']))) : '',
                                                ),
                                                $this->debug_show_database_manager
                                            ) . '
                                    </li>
                                ';

                            }

                            // common actions (to top, close all)
                            $output .= '
                                <li class="zdc-top">
                                    <a href="' . preg_replace('/\#zdc\-top$/i', '', $_SERVER['REQUEST_URI']) . '#zdc-top">' .
                                        $this->language['to_top'] . '
                                    </a>
                                </li>
                                <li class="zdc-close">
                                    <a href="javascript: void(0)">' .
                                        $this->language['close_all'] . '
                                    </a>
                                </li>
                            ';

                            // wrap up actions bar
                            $output .= '
                                    </ul>
                                </div>
                            ';

                            // data tables (backtrace, returned rows, explain)
                            // let's see what tables do we need to display
                            $tables = array();

                            // if query did return records
                            if (!empty($debug_info['records'])) $tables[] = 'records';

                            // if explain is available
                            if (isset($debug_info['explain']) && is_array($debug_info['explain'])) $tables[] = 'explain';

                            // if backtrace is available
                            if (isset($debug_info['backtrace'])) $tables[] = 'backtrace';

                            // let's display data
                            foreach ($tables as $table) {

                                // start generating output
                                $output .= '
                                    <div class="zdc-box zdc-data-table zdc-' . $table . '-table">
                                        <table cellspacing="0" cellpadding="0" border="0">
                                            <thead><tr>
                                ';

                                // print table headers
                                foreach (array_keys($debug_info[$table][0]) as $header) $output .= '<th>' . $header . '</th>';

                                $output .= '</tr></thead><tbody>';

                                // print table rows and columns
                                foreach ($debug_info[$table] as $row) {

                                    $output .= '<tr>';

                                    foreach (array_values($row) as $column) $output .= '<td>' . (null !== $column ? htmlspecialchars($column, ENT_QUOTES, 'UTF-8') : '') . '</td>';

                                    $output .= '</tr>';

                                }

                                // wrap up data tables
                                $output .= '</tbody></table></div>';

                            }

                            // finish block
                            $output .= '</td></tr></table>';

                        }

                    // if anything was generated for the current block
                    // enclose generated output in a special div (unless this is an AJAX request case in which we leave it as it is)
                    // (we need to render the HTML for errors/successful/unsuccessful tabs either way because these may be populated from AJAX requests)
                    if ((isset($counter) && $counter > 0) || in_array($block, array('errors', 'successful-queries', 'unsuccessful-queries'))) $blocks[$block]['generated'] = $is_ajax ? $output : '<div id="zdc-' . $block . '">' . $output . '</div>';

                } elseif ($block === 'globals') {

                    // globals to show
                    $globals =  $this->debug_show_globals === true ?
                                array('REQUEST', 'POST', 'GET', 'SESSION', 'COOKIE', 'FILES', 'SERVER') :
                                array_map('strtoupper', array_keys(array_filter($this->debug_show_globals, function($value) { return $value !== false; })));

                    // start building output
                    $output = '
                        <div id="zdc-globals-submenu">
                            <ul>
                    ';

                    // iterate through the superglobals to show
                    foreach ($globals as $global)

                        // add button to submenu
                        $output .=
                            '<li>
                                <a href="javascript: void(0)" class="zdc-toggle zdc-toggle-id zdc-globals-' . strtolower($global) . '">$_' .
                                    $global . '
                                </a>
                            </li>
                        ';

                    // finish building the submenu
                    $output .= '
                            </ul>
                        </div>
                    ';

                    // iterate thought the superglobals to show
                    foreach ($globals as $global) {

                        // make the superglobal available
                        global ${'_' . $global};

                        // add to the generated output
                        $output .= '
                            <table cellspacing="0" cellpadding="0" border="0" id="zdc-globals-' . strtolower($global) . '" class="zdc-entry">
                                <tr>
                                    <td class="zdc-counter">001</td>
                                    <td class="zdc-data">
                                        <div class="zdc-box">
                                            <strong>$_' . $global . '</strong>
                                            <pre>' . htmlentities(var_export(${'_' . $global}, true)) . '</pre>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        ';

                    }

                    // enclose generated output in a special div
                    $output = '<div id="zdc-globals">' . $output . '</div>';

                    $blocks[$block]['generated'] = $output;

                }

            }

            // if this was an AJAX request add results to the debugging console
            if ($is_ajax) {

                foreach ($blocks as $k => $v)
                    if (in_array($k, array('errors', 'successful-queries', 'unsuccessful-queries')) && $v['generated'] !== '')
                        echo '<div class="zdc-' . $k . '-ajax" style="display:none">' . $v['generated'] . '</div class="zdc-end">';

            // if *not* AJAX request
            } else {

                // if there's an error, show the console
                if ($blocks['unsuccessful-queries']['counter'] > 0 || $blocks['errors']['counter'] > 0) $this->minimize_console = false;

                // finalize output by enclosing the debugging console's menu and generated blocks in a container
                $output = '
                    <div id="zdc">
                        <a name="zdc-top"></a>
                        <ul id="zdc-main" class="' . ($this->minimize_console ? '' : 'zdc-visible') . '">
                ';

                // we're always rendering the HTML for errors
                // in case it is populated from AJAX requests at run-time
                // but we keep it hidden if we don't have any errors at this point
                $output .= '
                    <li' . ($blocks['errors']['counter'] == 0 ? ' style="display: none"' : '') . '>
                        <a href="javascript: void(0)" class="zdc-toggle zdc-errors">' .
                            $this->language['errors'] . ': <span>' . $blocks['errors']['counter'] . '</span>
                        </a>
                    </li>
                ';

                // common buttons
                $output .= '
                    <li>
                        <a href="javascript: void(0)" class="zdc-toggle zdc-successful-queries">' .
                            $this->language['successful_queries'] . ': <span>' . $blocks['successful-queries']['counter'] . '</span>&nbsp;(' .
                            '<em class="zdc-total-duration" style="font-style: normal">' . number_format($this->total_execution_time, 5) . '</em> ' . $this->language['seconds'] . ')
                        </a>
                    </li>
                ';

                // we're always rendering the HTML for unsuccessful queries
                // in case it is populated from AJAX requests at run-time
                // but we keep it hidden if we don't have any unsuccessful queries at this point
                $output .= '
                    <li' . ($blocks['unsuccessful-queries']['counter'] == 0 ? ' style="display: none"' : '') . '>
                        <a href="javascript: void(0)" class="zdc-toggle zdc-unsuccessful-queries">' .
                            $this->language['unsuccessful_queries'] . ': <span>' . $blocks['unsuccessful-queries']['counter'] . '</span>
                        </a>
                    </li>
                ';

                // if there are any warnings
                if (isset($this->debug_info['warnings']))

                    $output .= '
                        <li>
                            <a href="javascript: void(0)" class="zdc-toggle zdc-warnings">' .
                                $this->language['warnings'] . ': <span>' . $blocks['warnings']['counter'] . '</span>
                            </a>
                        </li>
                    ';

                // if globals are to be shown
                // (value is boolean TRUE or is an array with at least one entry set to TRUE)
                if ($this->debug_show_globals === true || (is_array($this->debug_show_globals) && !empty(array_filter($this->debug_show_globals, function($value) { return $value !== false; }))))

                    $output .= '
                        <li>
                            <a href="javascript: void(0)" class="zdc-toggle zdc-toggle-id zdc-globals-submenu">' .
                                $this->language['globals'] . '
                            </a>
                        </li>
                    ';

                // wrap up debugging console's menu
                $output .= '
                    </ul>
                ';

                foreach (array_keys($blocks) as $block) $output .= $blocks[$block]['generated'];

                // wrap up
                $output .= '</div>';

                // add the minified version of the debugging console
                $output .= '
                    <div id="zdc-mini">
                        <a href="javascript: void(0)" class="zdc-toggle zdc-toggle-id zdc-main">' .
                            $blocks['successful-queries']['counter'] . ($warnings ? '<span>!</span>' : '') . ' / ' . $blocks['unsuccessful-queries']['counter'] . '
                        </a>
                    </div>
                ';

                // use the provided resource path for stylesheets and javascript (if any)
                if (!is_null($this->resource_path))

                    $path = rtrim(preg_replace('/\\\/', '/', '//' . $_SERVER['HTTP_HOST'] . trim($this->resource_path, '/')), '/');

                // if path not provided, determine the path automatically
                else

                    // this is the url that will be used for automatically including
                    // the CSS and the JavaScript files
                    $path = rtrim(preg_replace('/\\\/', '/', '//' . $_SERVER['HTTP_HOST'] . substr(dirname(__FILE__), strlen(realpath($_SERVER['DOCUMENT_ROOT'])))), '/');

                // link the required javascript
                $output = '<script type="text/javascript" src="' . $path . '/public/javascript/zebra_database.min.js"></script>' . $output;

                // link the required css file
                $output = '<link rel="stylesheet" href="' . $path . '/public/css/default/zebra_database.min.css" type="text/css">' . $output;

                // show generated output
                echo $output;

            }

        }

    }

    /**
     *  Encloses segments of a database.table.column construction in grave accents.
     *
     *  @param  mixed   $entries    A string or an array to escape.
     *
     *  @return string              Returns a string with the segments of a database.table.column construction enclosed
     *                              in grave accents.
     *
     *  @access private
     */
    private function _escape($entries) {

        // treat argument as an array
        $entries = (array)$entries;

        $result = array();

        // iterate over the entries given as argument
        foreach ($entries as $entry) {

            // explode string by dots
            $entry = explode('.', $entry);

            // iterate over the segments
            $entry = array_map(function($value) {

                // trim ticks and whitespace
                $value = null !== $value ? trim(trim($value, '`')) : $value;

                // if not * or a MySQL function
                if ($value !== '*' && !$this->_is_mysql_function($value)) {

                    // if alias is used
                    if (stripos($value, ' AS ') !== false) list($value, $alias) = array_map('trim', preg_split('/ AS /i', $value));

                    // enclose value in grave accents
                    return '`' . $value . '`' . (isset($alias) ? ' AS ' . $alias : '');

                }

                // return the value as it is otherwise
                return (is_null($value) ? 'NULL' : $value);

            }, $entry);

            // concatenate the string back and add it to the result
            $result[] = implode('.', $entry);

        }

        // recompose the string and return it
        return implode(', ', $result);

    }

    /**
     *  Checks if a string is in fact a MySQL function call (or a bunch of nested MySQL functions)
     *
     *  @param  mixed   $value  Checks whether the given value is a MySQL function or not.
     *
     *  @return boolean
     *  @access private
     */
    private function _is_mysql_function($value) {

        // returns TRUE if
        return (

            // treat NULL as a function
            is_null($value) || (

                // is not an array
                !is_array($value) &&

                // has opening and closing parenthesis
                strpos($value, '(') !== false && strpos($value, ')') !== false &&

                // there is no white spaces from the beginning until the opening parenthesis
                preg_match('/^([^\s]+?)\(/i', $value, $matches) &&

                // and match is not a MySQL function
                in_array(strtoupper($matches[1]), $this->mysql_functions)

            )

        );

    }

    /**
     *  Checks is a value is a valid result set obtained from a query against the database
     *
     *  @param  mixed       $value  The value to check
     *
     *  @access private
     *
     *  @return boolean     Returns whether the value given as argument is a valid result set obtained from a query
     *                      against the database
     */
    private function _is_result($value) {

        // check whether a value is a valid result set obtained from a query against the database
        return $value instanceof mysqli_result;

    }

    /**
     *  Checks whether debugging is enabled
     *
     *  @return boolean
     *  @access private
     */
    private function _is_debugging_enabled() {

        // assume this is false
        $debugger_enabled_via_query_string = false;

        // if coming from a CLI request and debugging is enabled via string in the "URL" and the correct value is passed as argument
        if ($this->is_cli_request && is_string($this->debug) && isset($_SERVER['argv']) && !empty(array_filter($_SERVER['argv'], function($value) { return strpos($value, $this->debug . '=1') !== false; })))

            // set flag manually
            $_GET[$this->debug] = 1;

        // if debugging is handled via a parameter in the query string
        // and argument is present in the query string
        if (is_string($this->debug) && isset($_GET[$this->debug])) {

            // if debugging needs to be turned on
            if ($_GET[$this->debug] == 1) {

                // set flag
                $debugger_enabled_via_query_string = true;

                // set cookie which expires when the browser is closed
                @setcookie($this->debug_cookie_name, '1', 0, '/', '', false, true);

            // consider turning debugging off
            } else

                // remove cookie
                @setcookie($this->debug_cookie_name, '', time() - 3600, '/', '', false, true);

        // if debugging is handled via a parameter in the query string and the cookie is already set
        } elseif (is_string($this->debug) && isset($_COOKIE[$this->debug_cookie_name]))

            // set flag
            $debugger_enabled_via_query_string = true;

        // debugging is on if
        return

            (

                // debug is boolean TRUE
                $this->debug === true ||

                // debugging is enabled on the fly via the presence of the required value in the query string (in the URL)
                $debugger_enabled_via_query_string ||

                // debugging is enabled but needs to be logged instead of being shown on the screen
                (is_array($this->debug) && empty(array_filter($this->debug, function($value) { return !(is_bool($value) || $value === 0 || $value === 1); })))

            // AND
            ) && (

                // not an AJAX request, or an AJAX request and debugging AJAX requests is ON
                !isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $this->debug_ajax

            // AND
            ) && (

                // debugger_ip is not an array
                !is_array($this->debugger_ip) ||

                // debugger_ip is an array and is empty
                empty($this->debugger_ip) ||

                // debugger_ip is an array and the viewer's IP is whitelisted
                (isset($_SERVER['REMOTE_ADDR']) && in_array($_SERVER['REMOTE_ADDR'], $this->debugger_ip))

            );

    }

    /**
     *  Handles saving of debug information and halts the execution of the script on fatal error or if the
     *  {@link halt_on_errors} property is set to `TRUE`
     *
     *  @param  string  $category   The name of the category to log the data into
     *
     *  @param  mixed   $data       The data to be logged
     *
     *  @param  boolean $fatal      (Optional) Flag indicating whether the logged message is fatal and execution should stop.
     *
     *                              Default is `FALSE`
     *
     *  @access private
     *  @return boolean
     */
    private function _log($category, $data, $fatal = true) {

        // if debugging is on
        if ($this->_is_debugging_enabled()) {

            // if category is different than "warnings"
            // (warnings are generated internally)
            if ($category !== 'warnings' && $this->debug_show_backtrace) {

                // get backtrace information
                $backtrace_data = debug_backtrace();

                // unset first entry as it refers to the call to this particular method
                unset($backtrace_data[0]);

                $data['backtrace'] = array();

                // iterate through the backtrace information
                foreach ($backtrace_data as $backtrace)

                    // extract needed information
                    $data['backtrace'][] = array(

                        $this->language['file']     => (isset($backtrace['file']) ? $backtrace['file'] : ''),
                        $this->language['function'] => $backtrace['function'] . '()',
                        $this->language['line']     => (isset($backtrace['line']) ? $backtrace['line'] : ''),

                    );

            }

            // saves debug information
            $this->debug_info[$category][] = $data;

            // if the saved debug info is about a fatal error and execution is to be stopped on fatal errors
            // (if this is an AJAX request and AJAX debugging is enabled, we let it through though)
            if ($fatal && $this->halt_on_errors && ($this->debug_ajax || !isset($_SERVER['HTTP_X_REQUESTED_WITH']))) die();

            return false;

        // if there are any unsuccessful queries or other errors and debugging is OFF
        } elseif (($category === 'unsuccessful-queries' || $category === 'errors') && !$this->_is_debugging_enabled()) {

            // get backtrace information
            $backtraceInfo = debug_backtrace();

            // log error to the system logger
            error_log('Zebra_Database (MySQL): ' . (isset($data['error']) ? $data['error'] : $data['message']) . print_r(' in ' . $backtraceInfo[1]['file'] . ' on line ' . $backtraceInfo[1]['line'], true));

        }

    }

    /**
     *  For unbuffered queries, this method manages setting the value of the {@link returned_rows} property once all the
     *  records have been iterating over with either {@link fetch_assoc()} or {@link fetch_obj()} methods.
     *
     *  If requested so, this method will also set the value for the {@link found_rows} property, will EXPLAIN the query
     *  and will populate the debugging console with the first few records, as set by the {@link debug_show_records}
     *  property.
     *
     *  @param  mixed   $resource   The resource as returned by the query
     *
     *  @param  mixed   $result     The results returned by the query
     *
     *  @access private
     *
     *  @return void
     */
    private function _manage_unbuffered_query_info(&$resource, &$result) {

        // if it was the last row
        if (!$result) {

            // set the number of returned rows
            $this->found_rows = $this->returned_rows = $resource->num_rows;

            // if debugging to the console is turned on
            if ($this->_is_debugging_enabled()) {

                // update the number of returned rows in the debugging console
                $this->debug_info['successful-queries'][$resource->log_index]['returned_rows'] = $resource->num_rows;

                // if we need to get the total number of rows in the table
                if (isset($resource->calc_rows) && $resource->calc_rows) {

                    // run that query now
                    $found_rows = mysqli_fetch_assoc(mysqli_query($this->connection, 'SELECT FOUND_ROWS()'));

                    // update the found_rows property
                    $this->found_rows = $found_rows['FOUND_ROWS()'];

                }

                // if we need to EXPLAIN the query
                if (isset($resource->explain) && $resource->explain) {

                    // do that now
                    $explain_resource = mysqli_query($this->connection, 'EXPLAIN ' . $resource->query);

                    // update information in the debugging console
                    while ($row = mysqli_fetch_assoc($explain_resource)) {
                        if (!is_array($this->debug_info['successful-queries'][$resource->log_index]['explain'])) $this->debug_info['successful-queries'][$resource->log_index]['explain'] = array();
                        $this->debug_info['successful-queries'][$resource->log_index]['explain'][] = $row;
                    }

                }

            }

        // if it was not the last row, debugging to the console is turned on and we've not yet reached the limit imposed by debug_show_records
        } elseif ($this->_is_debugging_enabled() && count($this->debug_info['successful-queries'][$resource->log_index]['records']) < $this->debug_show_records)

            // add row data to the debugging console
            $this->debug_info['successful-queries'][$resource->log_index]['records'][] = $result;

    }

    /**
     *  See the {@link debug} property for more information.
     *
     *  @access private
     *
     *  @return void
     */
    private function _write_log($daily = false, $hourly = false, $backtrace = false) {

        // did the request come from CLI?
        $is_cli_request = func_num_args() === 4;

        // if we are using a callback function to handle logs
        if (is_callable($this->log_path) && !isset($this->log_path_is_function))

            // set flag
            $this->log_path_is_function = true;

        // if we are writing logs to a file
        // (and request was *not* made from CLI)
        elseif (!$is_cli_request) {

            // set flag
            $this->log_path_is_function = false;

            $pathinfo = pathinfo($this->log_path);

            // if log_path is given as full path to a file, together with extension
            if (isset($pathinfo['filename']) && isset($pathinfo['extension'])) {

                // use those values
                $file_name = $pathinfo['dirname'] . '/' . $pathinfo['filename'];
                $extension = '.' . $pathinfo['extension'];

            // otherwise
            } else {

                // the file name is "log" and the extension is ".txt"
                $file_name = rtrim($this->log_path, '/\\') . '/log';
                $extension = '.txt';

            }

            // if $hourly is set to TRUE, $daily *must* be true
            if ($hourly) $daily = true;

            // are we writing daily logs?
            // (suppress "strict standards" warning for PHP 5.4+)
            $file_name .= ($daily ? '-' . @date('Ymd') : '');

            // are we writing hourly logs?
            // (suppress "strict standards" warning for PHP 5.4+)
            $file_name .= ($hourly ? '-' . @date('H') : '');

            // log file's extension
            $file_name .= $extension;

        // for CLI requests, set flag
        } else $this->log_path_is_function = false;

        // all the labels that may be used in a log entry
        $labels = array(
            strtoupper($this->language['date']),
            strtoupper('query'),
            strtoupper($this->language['execution_time']),
            strtoupper($this->language['warning']),
            strtoupper($this->language['error']),
            strtoupper($this->language['from_cache']),
            strtoupper($this->language['yes']),
            strtoupper($this->language['no']),
            strtoupper($this->language['backtrace']),
            strtoupper($this->language['file']),
            strtoupper($this->language['line']),
            strtoupper($this->language['function']),
            strtoupper($this->language['unbuffered']),
        );

        // determine the longest label (for proper indenting)
        $longest_label_length = 0;

        // iterate through the labels
        foreach ($labels as $label)

            // if the label is longer than the longest label so far
            if (strlen($label) > $longest_label_length)

                // this is the longest label, so far
                // we use utf8_decode so that strlen counts correctly with accented chars
                $longest_label_length = strlen(utf8_decode($label));

        $longest_label_length++;

        // if we are using a callback function for logs, we need to show debug information when request came from CLI,
        // or we are writing the logs to a file and we can create/write to the log file
        if ($this->log_path_is_function || $is_cli_request || $handle = @fopen($file_name, 'a+')) {

            // we need to show both successful and unsuccessful queries
            $sections = array('successful-queries', 'unsuccessful-queries');

            // iterate over the sections we need to show
            foreach ($sections as $section) {

                // if there are any queries in the section
                if (isset($this->debug_info[$section])) {

                    // iterate through the debug information
                    foreach ($this->debug_info[$section] as $debug_info) {

                        // the output
                        $output =

                            // query
                            "\n" . html_entity_decode(strip_tags(SqlFormatter::format($debug_info['query']))) . "\n" .

                            // date
                            (!$is_cli_request ? $labels[0] . str_pad('', $longest_label_length - strlen(utf8_decode($labels[0])), ' ', STR_PAD_RIGHT) . ': ' . @date('Y-m-d H:i:s') . "\n" : '') .

                            // if execution time is available
                            // (is not available for unsuccessful queries)
                            (isset($debug_info['execution_time']) ? $labels[2] . str_pad('', $longest_label_length - strlen(utf8_decode($labels[2])), ' ', STR_PAD_RIGHT) . ': ' . number_format($debug_info['execution_time'], 5) . ' ' . $this->language['seconds'] . "\n" : '') .

                            // if there is a warning message
                            (isset($debug_info['warning']) && $debug_info['warning'] !== '' ? $labels[3] . str_pad('', $longest_label_length - strlen(utf8_decode($labels[3])), ' ', STR_PAD_RIGHT) . ': ' . strip_tags($debug_info['warning']) . "\n" : '') .

                            // if there is an error message
                            (isset($debug_info['error']) && $debug_info['error'] !== '' ? $labels[4] . str_pad('', $longest_label_length - strlen(utf8_decode($labels[4])), ' ', STR_PAD_RIGHT) . ': ' . $debug_info['error'] . "\n" : '') .

                            // if not an action query, show whether the query was returned from the cache or was executed
                            (isset($debug_info['affected_rows']) && $debug_info['affected_rows'] === false && isset($debug_info['from_cache']) && $debug_info['from_cache'] !== 'nocache' ? $labels[5] . str_pad('', $longest_label_length - strlen(utf8_decode($labels[5])), ' ', STR_PAD_RIGHT) . ': ' . $labels[6] . "\n" : '') .

                            // if query was an unbuffered one
                            (isset($debug_info['unbuffered']) && $debug_info['unbuffered'] ? $labels[12] . str_pad('', $longest_label_length - strlen(utf8_decode($labels[12])), ' ', STR_PAD_RIGHT) . ': ' . $labels[6] . "\n" : '');

                        // if backtrace information should be written to the log file
                        if ($backtrace) {

                            $output .= $labels[8] . str_pad('', $longest_label_length - strlen(utf8_decode($labels[8])), ' ', STR_PAD_RIGHT) . ':' . "\n";

                            // handle full backtrace info
                            foreach ($debug_info['backtrace'] as $backtrace) {

                                // output
                                $output .=
                                    "\n" .
                                    $labels[9] . str_pad('', $longest_label_length - strlen(utf8_decode($labels[9])), ' ', STR_PAD_RIGHT) . ': ' . $backtrace[$this->language['file']] . "\n" .
                                    $labels[10] . str_pad('', $longest_label_length - strlen(utf8_decode($labels[10])), ' ', STR_PAD_RIGHT) . ': ' . $backtrace[$this->language['line']] . "\n" .
                                    $labels[11] . str_pad('', $longest_label_length - strlen(utf8_decode($labels[11])), ' ', STR_PAD_RIGHT) . ': ' . $backtrace[$this->language['function']] . "\n";

                            }

                        }

                        $output .= "\n<" . str_pad('', $longest_label_length - 2, '-', STR_PAD_RIGHT) . ">\n";

                        // if request came from CLI, output
                        if ($is_cli_request) echo $output;

                        // if we are writing the logs to a file
                        elseif (!$this->log_path_is_function) fwrite($handle, $output);

                        // if we are using a callback to manage logs, pass output to the function
                        else call_user_func_array($this->log_path, array($output));


                    }

                }

            }

            // if we are writing the logs to a file, close the log file
            if (!$this->log_path_is_function && !$is_cli_request) fclose($handle);

        // if log file could not be created/opened
        } else

            trigger_error($this->language['could_not_write_to_log'], E_USER_ERROR);

    }

    /**
     *  Frees the memory associated with the last result
     *
     *  @since 2.8
     *
     *  @access private
     */
    public function __destruct() {

        // frees the memory associated with the last result
        $this->free_result();

    }

}
