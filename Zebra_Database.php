<?php

/**
 *  An advanced, compact and lightweight MySQL database wrapper library, built around PHP's
 *  {@link http://www.php.net/manual/en/book.mysqli.php MySQLi extension}. It provides methods for interacting with MySQL
 *  databases that are more secure, powerful and intuitive than PHP's default ones.
 *
 *  It supports {@link http://dev.mysql.com/doc/refman/5.0/en/commit.html transactions} and provides ways for caching
 *  query results either by saving cached data on the disk, or by using {@link http://memcached.org/about memcache}.
 *
 *  Provides a comprehensive debugging interface with detailed information about the executed queries: execution time,
 *  returned/affected rows, excerpts of the found rows, error messages, etc. It also automatically
 *  {@link http://dev.mysql.com/doc/refman/5.0/en/explain.html EXPLAIN}'s each SELECT query (so you don't miss those keys
 *  again!).
 *
 *  It encourages developers to write maintainable code and provides a better default security layer by encouraging the
 *  use of prepared statements, where arguments are escaped automatically.
 *
 *  The code is heavily commented and generates no warnings/errors/notices when PHP's error reporting level is set to
 *  E_ALL.
 *
 *  Visit {@link http://stefangabos.ro/php-libraries/zebra-database/} for more information.
 *
 *  For more resources visit {@link http://stefangabos.ro/}
 *
 *  @author     Stefan Gabos <contact@stefangabos.ro>
 *  @version    2.8.4 (last revision: July 18, 2014)
 *  @copyright  (c) 2006 - 2014 Stefan Gabos
 *  @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE
 *  @package    Zebra_Database
 */

class Zebra_Database
{

    /**
     *  After an INSERT, UPDATE, REPLACE or DELETE query this property will hold the number of rows that were affected by
     *  its execution.     .
     *
     *  For the number of rows returned by SELECT queries see the {@link $returned_rows} property.
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
     *  Path (with trailing slash) where to cache queries results.
     *
     *  <i>The path must be relative to your working path and not the class' path!</i>
     *
     *  @var string
     */
    public $cache_path;

    /**
     *  The method to be used for caching query results.
     *
     *  Can be either:
     *
     *  - <b>disk</b>     - query results are cached as files on the disk at the path specified by {@link cache_path}.
     *  - <b>session</b>  - query results are cached in the session (use this only for small data sets). Note that when
     *                      using this method for caching results, the library expects an active session, or will trigger
     *                      a fatal error otherwise!
     *  - <b>memcache</b> - query results are cached using a {@link http://memcached.org/about memcache} server; when
     *                      using this method make sure to also set the appropriate values for {@link memcache_host},
     *                       {@link memcache_port} and optionally {@link memcache_compressed}.
     *                      <br>
     *                      <i>For using memcache as caching method, PHP must be compiled with the
     *                      {@link http://pecl.php.net/package/memcache memcache} extension and, if {@link memcache_compressed}
     *                      property is set to TRUE, needs to be configured with </i><b>--with-zlib[=DIR]</b><i>.</i>
     *
     *  If caching method is set to "memcache", {@link memcache_host}, {@link memcache_port} and optionally
     *  {@link memcache_compressed} must be set <b>prior</b> to calling the {@link connect()} method! Failing to do so
     *  will disable caching.
     *
     *  <code>
     *  // the host where memcache is listening for connections
     *  $db->memcache_host = 'localhost';
     *
     *  // the port where memcache is listening for connections
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
     *  <i>Caching is done on a per-query basis by setting the "cache" argument when calling some of the library's
     *  methods like {@link query()}, {@link select()}, {@link dcount()}, {@link dlookup()}, {@link dmax()} and {@link dsum()}!</i>
     *
     *  Default is "disk".
     *
     *  @since  2.7
     *
     *  @var string
     */
    public $caching_method;

    /**
     *  Sets the number records returned by SELECT queries to be shown in the debugging console.
     *
     *  <code>
     *  // show 50 records
     *  $db->console_show_records(50);
     *  </code>
     *
     *  <i>Be aware that having this property set to a high number (hundreds), and having queries that returnthat many
     *  rows, can cause your script to crash due to memory limitations. In this case you should either lower the value
     *  of this property or try and set PHP's memory limit higher using:</i>
     *
     *  <code>
     *  // set PHP's memory limit to 20 MB
     *  ini_set('memory_limit','20M');
     *  </code>
     *
     *  Default is 20.
     *
     *  @since  1.0.9
     *
     *  @var integer
     */
    public $console_show_records;

    /**
     *  Setting this property to TRUE will instruct the library to generate debugging information for each query it executes.
     *
     *  Debugging information can later be reviewed by calling the {@link show_debug_console()} method.
     *
     *  <b>Don't forget to set this to FALSE on the production environment. Generating the debugging information consumes
     *  a lot of resources and is meant to be used *only* in the development process!</b>.
     *
     *  I recommend always calling the {@link show_debug_console()} method at the end of your scripts, and simply changing
     *  the value of the <i>debug</i> property to suit your needs, as {@link show_debug_console()} will not display
     *  anything if <i>debug</i> is FALSE.
     *
     *  Remember that on a production server you will not be left in the dark by setting this property to FALSE, as the
     *  library will try to write any errors to the system log, if PHP is
     *  {@link http://www.php.net/manual/en/errorfunc.configuration.php#ini.log-errors configured so}!
     *
     *  <code>
     *  // disable the generation of debugging information
     *  $db->debug = false;
     *  </code>
     *
     *  Default is TRUE.
     *
     *  @var boolean
     */
    public $debug;

    /**
     *  An array of IP addresses for which, if the {@link debug} property is set to TRUE, the {@link show_debug_console()}
     *  method should produce output.
     *
     *  An empty array would display the debugging console for everybody.
     *
     *  <code>
     *  // show the debugging console only to specific IPs
     *  $db->debugger_ip = array('xxx.xxx.xxx.xxx', 'yyy.yyy.yyy.yyy');
     *  </code>
     *
     *  Default is an empty array.
     *
     *  @since  1.0.6
     *
     *  @var array
     */
    public $debugger_ip;

    /**
     *  By default, if {@link set_charset()} method is not called, a warning message will be displayed in the debugging
     *  console.
     *
     *  The ensure that data is both properly saved and retrieved to and from the database, this method should be called
     *  first thing after connecting to the database.
     *
     *  If you don't want to call this method nor do you want to see the warning, set this property to FALSE.
     *
     *  Default is TRUE.
     *
     *  @var boolean
     */
    public $disable_warnings;

    /**
     *  After running a SELECT query through either {@link select()} or {@link query()} methods, and having set the
     *  <i>calc_rows</i> argument to TRUE, this property would contain the number of records that <b>would</b> have been
     *  returned <b>if</b> there was no LIMIT applied to the query.
     *
     *  If <i>calc_rows</i> is FALSE or is TRUE but there is no LIMIT applied to the query, this property's value will
     *  be the same as the value of the {@link returned_rows} property.
     *
     *  <code>
     *  // let's assume that "table" has 100 rows
     *  // but we're only selecting the first 10 of those
     *  // the last argument of the method tells the library
     *  // to get the total number of records in the table
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
     *  @var integer
     */
    public $found_rows;

    /**
     *  When the value of this property is set to TRUE, the execution of the script will be halted for any unsuccessful
     *  query and the debugging console will be shown, <b>if</b> the value of the {@link debug} property is TRUE and the
     *  viewer's IP address is in the {@link debugger_ip} array (or {@link debugger_ip} is an empty array).
     *
     *  <code>
     *  // don't stop execution for unsuccessful queries (if possible)
     *  $db->halt_on_errors = false;
     *  </code>
     *
     *  Default is TRUE.
     *
     *  @since  1.0.5
     *
     *  @var  boolean
     */
    public $halt_on_errors;

    /**
     *  Path (with trailing slash) where to store the log file.
     *
     *  <b>The path is relative to your working directory.</b>
     *
     *  Data is written to the log file when calling the {@link write_log()} method.
     *
     *  <i>At the given path the library will attempt to create a file named "log.txt". Remember to grant the appropriate
     *  rights to the script!</i>
     *
     *  <b>IF YOU'RE LOGGING, MAKE SURE YOU HAVE A CRON JOB OR SOMETHING THAT DELETES THE LOG FILE FROM TIME TO TIME!</b>
     *
     *  Remember that the library will try to write errors to the system log (if PHP is {@link http://www.php.net/manual/en/errorfunc.configuration.php#ini.log-errors configured so})
     *  <b>only</b> when the {@link $debug debug} property is set to FALSE (as when the <i>debug</i> property is set to
     *  TRUE the error messages are reported in the debugging console);
     *
     *  @var string
     */
    public $log_path;

    /**
     *  Time (in seconds) after which a query will be considered as running for too long.
     *
     *  If a query's execution time exceeds this number, a notification email will be automatically sent to the address
     *  defined by {@link notification_address}, having {@link notifier_domain} in subject.
     *
     *  <code>
     *  // consider queries running for more than 5 seconds as slow and send email
     *  $db->max_query_time = 5;
     *  </code>
     *
     *  Default is 10.
     *
     *  @var integer
     */
    public $max_query_time;

    /**
     *  Setting this property to TRUE will instruct to library to compress (using zlib) the cached results.
     *
     *  <i>For this to work, PHP needs to be configured with </i> <b>--with-zlib[=DIR]</b> <i>!</i>
     *
     *  <i>Set this property only if you are using "memcache" as {@link caching_method}.</i>
     *
     *  Default is FALSE.
     *
     *  @since  2.7
     *
     *  @var boolean
     */
    public $memcache_compressed;

    /**
     *  The host where memcache is listening for connections.
     *
     *  <i>Set this property only if you are using "memcache" as {@link caching_method}.</i>
     *
     *  Default is FALSE.
     *
     *  @since  2.7
     *
     *  @var mixed
     */
    public $memcache_host;

    /**
     *  The port where memcache is listening for connections.
     *
     *  <i>Set this property only if you are using "memcache" as {@link caching_method}.</i>
     *
     *  Default is FALSE.
     *
     *  @since  2.7
     *
     *  @var mixed
     */
    public $memcache_port;

    /**
     *  The prefix for the keys used to identify cached queries in memcache. This allows sepparate caching of the same
     *  queries by multiple instances of the libraries, or the same instance handling multiple domains on the same
     *  memcache server.
     *
     *  <i>Set this property only if you are using "memcache" as {@link caching_method}.</i>
     *
     *  Default is "" (an empty string).
     *
     *  @since  2.8.4
     *
     *  @var string
     */
    public $memcache_key_prefix;

    /**
     *  By setting this property to TRUE a minimized version of the debugging console will be shown by default, instead
     *  of the full-sized one.
     *
     *  Clicking on it will show the full debugging console.
     *
     *  For quick and easy debugging, setting the <i>highlight</i> argument of a method that has it will result in the
     *  debugging console being shown at full size and with the respective query visible for inspecting.
     *
     *  Default is TRUE
     *
     *  @since  1.0.4
     *
     *  @var boolean
     */
    public $minimize_console;

    /**
     *  Email address to which notification emails to be sent when a query's execution time exceeds the number of
     *  seconds set by {@link max_query_time}. The notification email will be automatically sent to the address defined
     *  by {@link notification_address} and having {@link notifier_domain} in subject.
     *
     *  <code>
     *  // the email address where to send an email when there are slow queries
     *  $db->notification_address = 'youremail@yourdomain.com';
     *  </code>
     *
     *  @var string
     */
    public $notification_address;

    /**
     *  Domain name to be used in the subject of notification emails sent when a query's execution time exceeds the number
     *  of seconds set by {@link max_query_time}.
     *
     *  If a query's execution time exceeds the number of seconds set by {@link max_query_time}, a notification email
     *  will be automatically sent to the address defined by {@link notification_address} and having {@link notifier_domain}
     *  in subject.
     *
     *  <code>
     *  // set a domain name so that you'll know where the email comes from
     *  $db->notifier_domain = 'yourdomain.com';
     *  </code>
     *
     *  @var string
     */
    public $notifier_domain;

    /**
     *  After running a SELECT query through either {@link select()} or {@link query()} methods this property would
     *  contain the number of returned rows.
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
     *  Path (without leading slash) to parent of public folder containing the css and javascript folders.
     *
     *  <i>The path must be relative to your $_SERVER['DOCUMENT_ROOT'] and not the class' path!</i>
     *
     *  @var string
     */
    public $resource_path;
    
    /**
     *  Array with cached results.
     *
     *  We will use this for fetching and seek
     *
     *  @access private
     */
    private $cached_results;

    /**
     *  Array that will store the database connection credentials
     *
     *  @access private
     */
    private $credentials;

    /**
     *  All debugging information is stored in this array.
     *
     *  @access private
     */
    private $debug_info;

    /**
     *  The language to be used in the debugging console.
     *
     *  Default is "english".
     *
     *  @access private
     */
    private $language;

    /**
     *  MySQL link identifier.
     *
     *  @access private
     */
    private $connection;

    /**
     *  Instance of an opened memcache server connection.
     *
     *  @since 2.7
     *
     *  @access private
     */
    private $memcache;

    /**
     *  Tells whether a transaction is in progress or not.
     *
     *  Possible values are
     *  -   0, no transaction is in progress
     *  -   1, a transaction is in progress
     *  -   2, a transaction is in progress but an error occurred with one of the queries
     *  -   3, transaction is run in test mode and it will be rolled back upon completion
     *
     *  @access private
     */
    private $transaction_status;

    /**
     *  Array of warnings, generated by the script, to be shown to the user in the debugging console
     *
     *  @access private
     */
    private $warnings;
    
    /**
     *  Constructor of the class
     *
     *  @return void
     */
    function __construct()
    {

        // if the mysqli extension is not loded, stop execution
        if (!extension_loaded('mysqli')) trigger_error('Zebra_Database: mysqli extension is not enabled!', E_USER_ERROR);

        // get path of class and replace (on a windows machine) \ with /
        // this path is to be used for all includes as it is an absolute path
        $this->path = preg_replace('/\\\/', '/', dirname(__FILE__));

        // sets default values for the class' properties
        // public properties
        $this->cache_path = $this->path . '/cache/';

        $this->console_show_records = 20;

        $this->debug = $this->halt_on_errors = $this->minimize_console = true;

        $this->language('english');

        $this->max_query_time = 10;

        $this->log_path = $this->notification_address = $this->notifier_domain = $this->memcache_key_prefix = '';

        $this->total_execution_time = $this->transaction_status = 0;

        $this->caching_method = 'disk';

        $this->cached_results = $this->debug_info = $this->debugger_ip = array();

        $this->connection = $this->memcache = $this->memcache_host = $this->memcache_port = $this->memcache_compressed = false;

        // set default warnings:
        $this->warnings = array(
            'charset'       =>  true,   // set_charset not called
            'memcache'      =>  true,   // memcache is available but it is not used
        );

    }

    /**
     *  Closes the MySQL connection.
     *
     *  @since  1.1.0
     *
     *  @return boolean     Returns TRUE on success or FALSE on failure.
     */
    function close()
    {

        // close the last one open
        return @mysqli_close($this->connection);

    }

    /**
     *  Opens a connection to a MySQL Server and selects a database.
     *
     *  Since the library is using <i>lazy connection</i>    (it is not actually connecting to the database until the first
     *  query is executed), the object representing the connection to the MySQL server is not available at this time. If
     *  you need it, use the {@link get_link()} method.
     *
     *  If you need the connection to the database to be made right away, set the <i>connect</i> argument to TRUE.
     *
     *  <code>
     *  // create the database object
     *  $db = new Zebra_Database();
     *
     *  // notice that we're not doing any error checking. errors will be shown in the debugging console
     *  $db->connect('host', 'username', 'password', 'database');
     *
     *  //  code goes here
     *
     *  // show the debugging console (if enabled)
     *  $db->show_debug_console();
     *  </code>
     *
     *  @param  string  $host       The address of the MySQL server to connect to (i.e. localhost).
     *
     *                              Prepending host by <b>p:</b> opens a persistent connection.
     *
     *  @param  string  $user       The user name used for authentication when connecting to the MySQL server.
     *
     *  @param  string  $password   The password used for authentication when connecting to the MySQL server.
     *
     *  @param  string  $database   The database to be selected after the connection is established.
     *
     *  @param  string  $port       (Optional) The port number to attempt to connect to the MySQL server.
     *
     *                              Leave as empty string to use the default as returned by ini_get("mysqli.default_port").
     *
     *  @param  string  $socket     (Optional) The socket or named pipe that should be used.
     *
     *                              Leave as empty string to use the default as returned by ini_get("mysqli.default_socket").
     *
     *                              Specifying the socket parameter will not explicitly determine the type of connection
     *                              to be used when connecting to the MySQL server. How the connection is made to the MySQL
     *                              database is determined by the <i>host</i> argument.
     *
     *  @param  boolean $connect    (Optional) Setting this argument to TRUE will force the library to connect to the
     *                              database right away instead of using a "lazy connection" where the actual connection
     *                              to the database will be made when the first query is run.
     *
     *                              Default is FALSE.
     *
     *  @return void
     */
    function connect($host, $user, $password, $database, $port = '', $socket = '', $connect = false)
    {

        // if the "memcache" extension is loaded and the caching method is set to "memcache"
        if (extension_loaded('memcache') && $this->caching_method == 'memcache')

            // suppress the warning telling the developer to use memcache for caching query results
            unset($this->warnings['memcache']);

        // we are using lazy-connection
        // that is, we are not going to actually connect to the database until we execute the first query
        // the actual connection is done by the _connected method
        $this->credentials = array(
            'host'      =>  $host,
            'user'      =>  $user,
            'password'  =>  $password,
            'database'  =>  $database,
        );

        // connect now, if we need to connect right away
        if ($connect) $this->_connected();

    }

    /**
     *  Counts the values in a column of a table.
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
     *  @param  string  $column         Name of the column in which to do the counting.
     *
     *  @param  string  $table          Name of the table containing the column.
     *
     *  @param  string  $where          (Optional) A MySQL WHERE clause (without the WHERE keyword).
     *
     *                                  Default is "" (an empty string).
     *
     *  @param  array   $replacements   (Optional) An array with as many items as the total parameter markers ("?", question
     *                                  marks) in <i>$column</i>, <i>$table</i> and <i>$where</i>. Each item will be
     *                                  automatically {@link escape()}-ed and will replace the corresponding "?".
     *
     *                                  Default is "" (an empty string).
     *
     *  @param  mixed   $cache          (Optional) Instructs the library on whether it should cache the query's results
     *                                  or not. Can be either FALSE - meaning no caching - or an integer representing the
     *                                  number of seconds after which the cache will be considered expired and the query
     *                                  executed again.
     *
     *                                  The caching method is specified by the value of the {@link caching_method} property.
     *
     *                                  Default is FALSE.
     *
     *  @param  boolean $highlight      (Optional) If set to TRUE the debugging console will be opened automatically
     *                                  and the query will be shown - really useful for quick and easy debugging.
     *
     *                                  Default is FALSE.
     *
     *  @return mixed                   Returns the number of counted records or FALSE if no records matching the given
     *                                  criteria (if any) were found. It also returns FALSE if there are no records in
     *                                  the table or if there was an error.
     *
     *                                  <i>This method may return boolean FALSE but may also return a non-boolean value
     *                                  which evaluates to FALSE, such as 0. Use the === operator for testing the return
     *                                  value of this method.</i>
     */
    function dcount($column, $table, $where = '', $replacements = '', $cache = false, $highlight = false)
    {

        // run the query
        $this->query('

            SELECT
                COUNT(' . $column . ') AS counted
            FROM
                `'. $table . '`' .
            ($where != '' ? ' WHERE ' . $where : '')

        , $replacements, $cache, false, $highlight);

        // if query was executed successfully and one or more records were returned
        if ($this->last_result !== false && $this->returned_rows > 0) {

            // fetch the result
            $row = $this->fetch_assoc();

            // return the result
            return $row['counted'];

        }

        // if error or no records
        return false;

    }

    /**
     *  Deletes rows from a table.
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
     *  @param  string  $table          Table from which to delete.
     *
     *  @param  string  $where          (Optional) A MySQL WHERE clause (without the WHERE keyword).
     *
     *                                  Default is "" (an empty string).
     *
     *  @param  array   $replacements   (Optional) An array with as many items as the total parameter markers ("?", question
     *                                  marks) in <i>$table</i> and <i>$where</i>. Each item will be automatically
     *                                  {@link escape()}-ed and will replace the corresponding "?".
     *
     *                                  Default is "" (an empty string).
     *
     *  @param  boolean $highlight      (Optional) If set to TRUE the debugging console will be opened automatically
     *                                  and the query will be shown - really useful for quick and easy debugging.
     *
     *                                  Default is FALSE.
     *
     *  @since  1.0.9
     *
     *  @return boolean                 Returns TRUE on success or FALSE on error.
     */
    function delete($table, $where = '', $replacements = '', $highlight = false)
    {

        // run the query
        $this->query('

            DELETE FROM
                `'. $table . '`' .
            ($where != '' ? ' WHERE ' . $where : '')

        , $replacements, false, false, $highlight);

        // if query was successful
        if ($this->last_result) return true;

        // if query was unsuccessful
        return false;

    }

    /**
     *  Returns one or more columns from ONE row of a table.
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
     *  @param  string  $column         One or more columns to return data from.
     *
     *                                  <i>If only one column is specified the returned result will be the specified
     *                                  column's value. If more columns are specified the returned result will be an
     *                                  associative array!</i>
     *
     *                                  <i>You may use "*" (without the quotes) to return all the columns from the
     *                                  row.</i>
     *
     *  @param  string  $table          Name of the table in which to search.
     *
     *  @param  string  $where          (Optional) A MySQL WHERE clause (without the WHERE keyword).
     *
     *                                  Default is "" (an empty string).
     *
     *  @param  array   $replacements   (Optional) An array with as many items as the total parameter markers ("?", question
     *                                  marks) in <i>$column</i>, <i>$table</i> and <i>$where</i>. Each item will be
     *                                  automatically {@link escape()}-ed and will replace the corresponding "?".
     *
     *                                  Default is "" (an empty string).
     *
     *  @param  mixed   $cache          (Optional) Instructs the library on whether it should cache the query's results
     *                                  or not. Can be either FALSE - meaning no caching - or an integer representing the
     *                                  number of seconds after which the cache will be considered expired and the query
     *                                  executed again.
     *
     *                                  The caching method is specified by the value of the {@link caching_method} property.
     *
     *                                  Default is FALSE.
     *
     *  @param  boolean $highlight      (Optional) If set to TRUE the debugging console will be opened automatically
     *                                  and the query will be shown - really useful for quick and easy debugging.
     *
     *                                  Default is FALSE.
     *
     *  @return mixed                   Found value/values or FALSE if no records matching the given criteria (if any)
     *                                  were found. It also returns FALSE if there are no records in the table or if there
     *                                  was an error.
     */
    function dlookup($column, $table, $where = '', $replacements = '', $cache = false, $highlight = false)
    {

        // run the query
        $this->query('

            SELECT
                ' . $column . '
            FROM
                `'. $table . '`' .
            ($where != '' ? ' WHERE ' . $where : '') . '
            LIMIT 1

        ', $replacements, $cache, false, $highlight);

        // if query was executed successfully and one or more records were returned
        if ($this->last_result !== false && $this->returned_rows > 0) {

            // fetch the result
            $row = $this->fetch_assoc();

            // if there is only one column in the returned set
            // return as a single value
            if (count($row) == 1) return array_pop($row);

            // if more than one columns, return as an array
            else return $row;

        }

        // if error or no records
        return false;

    }

    /**
     *  Looks up the maximum value in a column of a table.
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
     *  @param  string  $column         Name of the column in which to search.
     *
     *  @param  string  $table          Name of table in which to search.
     *
     *  @param  string  $where          (Optional) A MySQL WHERE clause (without the WHERE keyword).
     *
     *                                  Default is "" (an empty string).
     *
     *  @param  array   $replacements   (Optional) An array with as many items as the total parameter markers ("?", question
     *                                  marks) in <i>$column</i>, <i>$table</i> and <i>$where</i>. Each item will be
     *                                  automatically {@link escape()}-ed and will replace the corresponding "?".
     *
     *                                  Default is "" (an empty string).
     *
     *  @param  mixed   $cache          (Optional) Instructs the library on whether it should cache the query's results
     *                                  or not. Can be either FALSE - meaning no caching - or an integer representing the
     *                                  number of seconds after which the cache will be considered expired and the query
     *                                  executed again.
     *
     *                                  The caching method is specified by the value of the {@link caching_method} property.
     *
     *                                  Default is FALSE.
     *
     *  @param  boolean $highlight      (Optional) If set to TRUE the debugging console will be opened automatically
     *                                  and the query will be shown - really useful for quick and easy debugging.
     *
     *                                  Default is FALSE.
     *
     *  @return mixed                   The maximum value in the column or FALSE if no records matching the given criteria
     *                                  (if any) were found. It also returns FALSE if there are no records in the table
     *                                  or if there was an error.
     *
     *                                  <i>This method may return boolean FALSE but may also return a non-boolean value
     *                                  which evaluates to FALSE, such as 0. Use the === operator for testing the return
     *                                  value of this method.</i>
     */
    function dmax($column, $table, $where = '', $replacements = '', $cache = false, $highlight = false)
    {

        // run the query
         $this->query('

            SELECT
                MAX(' . $column . ') AS maximum
            FROM
                `'. $table . '`' .
            ($where != '' ? ' WHERE ' . $where : '')

        , $replacements, $cache, false, $highlight);

        // if query was executed successfully and one or more records were returned
        if ($this->last_result !== false && $this->returned_rows > 0) {

            // fetch the result
            $row = $this->fetch_assoc();

            // return the result
            return $row['maximum'];

        }

        // if error or no records
        return false;

    }

    /**
     *  Sums the values in a column of a table.
     *
     *  Example:
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
     *  @param  string  $column         Name of the column in which to sum values.
     *
     *  @param  string  $table          Name of the table in which to search.
     *
     *  @param  string  $where          (Optional) A MySQL WHERE clause (without the WHERE keyword).
     *
     *                                  Default is "" (an empty string).
     *
     *  @param  array   $replacements   (Optional) An array with as many items as the total parameter markers ("?", question
     *                                  marks) in <i>$column</i>, <i>$table</i> and <i>$where</i>. Each item will be
     *                                  automatically {@link escape()}-ed and will replace the corresponding "?".
     *
     *                                  Default is "" (an empty string).
     *
     *  @param  mixed   $cache          (Optional) Instructs the library on whether it should cache the query's results
     *                                  or not. Can be either FALSE - meaning no caching - or an integer representing the
     *                                  number of seconds after which the cache will be considered expired and the query
     *                                  executed again.
     *
     *                                  The caching method is specified by the value of the {@link caching_method} property.
     *
     *                                  Default is FALSE.
     *
     *  @param  boolean $highlight      (Optional) If set to TRUE the debugging console will be opened automatically
     *                                  and the query will be shown - really useful for quick and easy debugging.
     *
     *                                  Default is FALSE.
     *
     *  @return mixed                   Returns the sum, or FALSE if no records matching the given criteria (if any) were
     *                                  found. It also returns FALSE if there are no records in the table or on error.
     *
     *                                  <i>This method may return boolean FALSE but may also return a non-boolean value
     *                                  which evaluates to FALSE, such as 0. Use the === operator for testing the return
     *                                  value of this method.</i>
     */
    function dsum($column, $table, $where = '', $replacements = '', $cache = false, $highlight = false)
    {

        // run the query
        $this->query('

            SELECT
                SUM(' . $column . ') AS total
            FROM
                `'. $table . '`' .
            ($where != '' ? ' WHERE ' . $where : '')

        , $replacements, $cache, false, $highlight);

        // if query was executed successfully and one or more records were returned
        if ($this->last_result !== false && $this->found_rows > 0) {

            // fetch the result
            $row = $this->fetch_assoc();

            // return the result
            return $row['total'];

        }

        // if error or no records
        return false;

    }

    /**
     *  Escapes special characters in a string that's to be used in an SQL statement in order to prevent SQL injections.
     *
     *  <i>This method also encloses given string in single quotes!</i>
     *
     *  <i>Works even if {@link http://www.php.net/manual/en/info.configuration.php#ini.magic-quotes-gpc magic_quotes}
     *  is ON.</i>
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
     *  // (variable are automatically escaped this way)
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
     *  @param  string  $string     String to be quoted and escaped.
     *
     *  @return string              Returns the quoted string with special characters escaped in order to prevent SQL
     *                              injections.     .
     */
    function escape($string)
    {

        // if an active connection exists
        if ($this->_connected()) {

            // if "magic quotes" are on, strip slashes
            if (get_magic_quotes_gpc()) $string = stripslashes($string);

            // escape and return the string
            return mysqli_real_escape_string($this->connection, $string);

        }

        // upon error, we don't have to report anything as _connected() method already did
        // just return FALSE
        return false;

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
     *  @param  resource    $resource   (Optional) Resource to fetch.
     *
     *                                  <i>If not specified, the resource returned by the last run query is used.</i>
     *
     *  @return mixed                   Returns an associative array that corresponds to the fetched row and moves the
     *                                  internal data pointer ahead, or FALSE if there are no more rows.
     */
    function fetch_assoc($resource = '')
    {

        // if an active connection exists
        if ($this->_connected()) {

            // if no resource was specified, and a query was run before, assign the last resource
            if ($resource == '' && isset($this->last_result)) $resource = & $this->last_result;

            // if $resource is a valid resource, fetch and return next row from the result set
            if ($this->_is_result($resource)) return mysqli_fetch_assoc($resource);

            // if $resource is a pointer to an array taken from cache
            elseif (is_integer($resource) && isset($this->cached_results[$resource])) {

                // get the current entry from the array and advance the pointer
                $result = each($this->cached_results[$resource]);

                // return as an associative array
                return @$result[1];

            // if $resource is invalid
            } else

                // save debug information
                $this->_log('errors', array(

                    'message'   =>  $this->language['not_a_valid_resource'],

                ));

        }

        // we don't have to report any error as either the _connected() method already did
        // or did so the checking for valid resource
        return false;

    }

    /**
     *  Returns an associative array containing all the rows from the resource created by the previous query or from the
     *  resource given as argument and moves the internal pointer to the end.
     *
     *  <code>
     *  // run a query
     *  $db->query('SELECT * FROM table WHERE criteria = ?', array($criteria));
     *
     *  // fetch all the rows as an associative array
     *  $records = $db->fetch_assoc_all();
     *  </code>
     *
     *  @param  string      $index      (Optional) Name of a column containing unique values.
     *
     *                                  If specified, the returned associative array's keys will be the values from this
     *                                  column.
     *
     *                                  <i>If not specified, returned array will have numerical indexes, starting from 0.</i>
     *
     *  @param  resource    $resource   (Optional) Resource to fetch.
     *
     *                                  <i>If not specified, the resource returned by the last run query is used.</i>
     *
     *  @since  1.1.2
     *
     *  @return mixed                   Returns an associative array containing all the rows from the resource created
     *                                  by the previous query or from the resource given as argument and moves the
     *                                  internal pointer to the end. Returns FALSE on error.
     */
    function fetch_assoc_all($index = '', $resource = '')
    {

        // if an active connection exists
        if ($this->_connected()) {

            // if no resource was specified, and a query was run before, assign the last resource
            if ($resource == '' && isset($this->last_result)) $resource = & $this->last_result;

            if (

                // if $resource is a valid resource OR
                $this->_is_result($resource) ||

                // $resource is a pointer to an array taken from cache
                (is_integer($resource) && isset($this->cached_results[$resource]))

            ) {

                // this is the array that will contain the results
                $result = array();

                // move the pointer to the start of $resource
                // if there are any rows available (notice the @)
                if (@$this->seek(0, $resource))

                    // iterate through the records
                    while ($row = $this->fetch_assoc($resource))

                        // if $index was specified and exists in the returned row, add data to the result
                        if (trim($index) != '' && isset($row[$index])) $result[$row[$index]] = $row;

                        // if $index was not specified or does not exists in the returned row, add data to the result
                        else $result[] = $row;

                // return the results
                return $result;

            // if $resource is invalid
            } else

                // save debug information
                $this->_log('errors', array(

                    'message'   =>  $this->language['not_a_valid_resource'],

                ));

        }

        // we don't have to report any error as either the _connected() method already did
        // or did so the checking for valid resource
        return false;

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
     *  @param  resource    $resource   (Optional) Resource to fetch.
     *
     *                                  <i>If not specified, the resource returned by the last run query is used.</i>
     *
     *  @since  1.0.8
     *
     *  @return mixed                   Returns an object with properties that correspond to the fetched row and moves
     *                                  the internal data pointer ahead, or FALSE if there are no more rows.
     */
    function fetch_obj($resource = '')
    {

        // if an active connection exists
        if ($this->_connected()) {

            // if no resource was specified, and a query was run before, assign the last resource
            if ($resource == '' && isset($this->last_result)) $resource = & $this->last_result;

            // if $resource is a valid resource, fetch and return next row from the result set
            if ($this->_is_result($resource)) return mysqli_fetch_object($resource);

            // if $resource is a pointer to an array taken from cache
            elseif (is_integer($resource) && isset($this->cached_results[$resource])) {

                // get the current entry from the array and advance the pointer
                $result = each($this->cached_results[$resource]);

                // if we're not past the end of the array
                if ($result !== false) {

                    // create a new generic object -> similar with $obj = new stdClass() but i like this one better ;)
                    $obj = (object) NULL;

                    // populate the object's properties
                    foreach ($result[1] as $key=>$value) $obj->$key = $value;

                // if we're past the end of the array
                // make sure we return FALSE
                } else $obj = false;

                // return as object
                return $obj;

            // if $resource is invalid
            } else

                // save debug information
                $this->_log('errors', array(

                    'message'   =>  $this->language['not_a_valid_resource'],

                ));

        }

        // we don't have to report any error as either the _connected() method already did
        // or did so the checking for valid resource
        return false;

    }

    /**
     *  Returns an associative array containing all the rows (as objects) from the resource created by the previous query
     *  or from the resource given as argument and moves the internal pointer to the end.
     *
     *  <code>
     *  // run a query
     *  $db->query('SELECT * FROM table WHERE criteria = ?', array($criteria));
     *
     *  // fetch all the rows as an associative array
     *  $records = $db->fetch_obj_all();
     *  </code>
     *
     *  @param  string      $index      (Optional) A column name from the records, containing unique values.
     *
     *                                  If specified, the returned associative array's keys will be the values from this
     *                                  column.
     *
     *                                  <i>If not specified, returned array will have numerical indexes, starting from 0.</i>
     *
     *  @param  resource    $resource   (Optional) Resource to fetch.
     *
     *                                  <i>If not specified, the resource returned by the last run query is used.</i>
     *
     *  @since  1.1.2
     *
     *  @return mixed                   Returns an associative array containing all the rows (as objects) from the resource
     *                                  created by the previous query or from the resource given as argument and moves
     *                                  the internal pointer to the end. Returns FALSE on error.
     */
    function fetch_obj_all($index = '', $resource = '')
    {

        // if an active connection exists
        if ($this->_connected()) {

            // if no resource was specified, and a query was run before, assign the last resource
            if ($resource == '' && isset($this->last_result)) $resource = & $this->last_result;

            if (

                // if $resource is a valid resource OR
                $this->_is_result($resource) ||

                // $resource is a pointer to an array taken from cache
                (is_integer($resource) && isset($this->cached_results[$resource]))

            ) {

                // this is the array that will contain the results
                $result = array();

                // move the pointer to the start of $resource
                // if there are any rows available (notice the @)
                if (@$this->seek(0, $resource))

                    // iterate through the resource data
                    while ($row = $this->fetch_obj($resource))

                        // if $index was specified and exists in the returned row, add data to the result
                        if (trim($index) != '' && isset($row[$index])) $result[$row[$index]] = $row;

                        // if $index was not specified or does not exists in the returned row, add data to the result
                        else $result[] = $row;

                // return the results
                return $result;

            // if $resource is invalid
            } else

                // save debug information
                $this->_log('errors', array(

                    'message'   =>  $this->language['not_a_valid_resource'],

                ));

        }

        // we don't have to report any error as either the _connected() method already did
        // or did so the checking for valid resource
        return false;

    }

    /**
     *  Returns an array of associative arrays with information about the columns in the MySQL result associated with
     *  the specified result identifier.
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
     *  @param  resource    $resource   (Optional) Resource to fetch columns information from.
     *
     *                                  <i>If not specified, the resource returned by the last run query is used.</i>
     *
     *  @since  2.0
     *
     *  @return mixed                   Returns an associative array with information about the columns in the MySQL
     *                                  result associated with the specified result identifier, or FALSE on error.
     */
    function get_columns($resource = '')
    {

        // if an active connection exists
        if ($this->_connected()) {

            // if no resource was specified, and a query was run before, assign the last resource
            if ($resource == '' && isset($this->last_result)) $resource = & $this->last_result;

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
            else

                // save debug information
                $this->_log('errors', array(

                    'message'   =>  $this->language['not_a_valid_resource'],

                ));

        }

        // we don't have to report any error as either the _connected() method already did
        // or did so the checking for valid resource
        return false;

    }

    /**
     *  Returns the MySQL link identifier associated with the current connection to the MySQL server.
     *
     *  Why a separate method? Because the library uses <i>lazy connection</i> (it is not actually connecting to the database
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
     *  @return identifier  Returns the MySQL link identifier associated with the current connection to the MySQL server.
     */
    function get_link()
    {

        // if an active connection exists
        // return the MySQL link identifier associated with the current connection to the MySQL server
        if ($this->_connected()) return $this->connection;

        // if script gets this far, return false as something must've been wrong
        return false;

    }

    /**
     *  Returns information about the columns of a given table, as an associative array.
     *
     *  <code>
     *  // get column information for a table named "table_name"
     *  $db->get_table_columns('table_name');
     *  </code>
     *
     *  @param  string  $table  Name of table to return column information for.
     *
     *  @since  2.6
     *
     *  @return array           Returns information about the columns of a given table as an associative array.
     */
    function get_table_columns($table)
    {

        // run the query
        $this->query('

            SHOW COLUMNS FROM `' . $this->escape($table) . '`

        ');

        // fetch and return data
        return $this->fetch_assoc_all('Field');

    }

    /**
     *  Returns an associative array with a lot of useful information on all or specific tables only.
     *
     *  <code>
     *  // return status information on tables having their name starting with "users"
     *  $tables = get_table_status('users%');
     *  </code>
     *
     *  @param  string  $pattern    (Optional) Instructs the method to return information only on tables whose name matches
     *                              the given pattern.
     *
     *                              Can be a table name or a pattern with "%" as wildcard.
     *
     *  @since  1.1.2
     *
     *  @return array               Returns an associative array with a lot of useful information on all or specific
     *                              tables only.
     */
    function get_table_status($pattern = '')
    {

        // run the query
        $this->query('
            SHOW
            TABLE
            STATUS
            ' . (trim($pattern) != '' ? 'LIKE ?' : '') . '
        ', array($pattern));

        // fetch and return data
        return $this->fetch_assoc_all('Name');

    }

    /**
     *  Returns an array with all the tables in the current database.
     *
     *  <code>
     *  // get all tables from database
     *  $tables = get_tables();
     *  </code>
     *
     *  @since  1.1.2
     *
     *  @return array   An array with all the tables in the current database.
     */
    function get_tables()
    {

        // fetch all the tables in the database
        $result = $this->fetch_assoc_all('', $this->query('
            SHOW TABLES
        '));

        $tables = array();

        // as the results returned by default are quite odd
        // translate them to a more usable array
        foreach ($result as $tableName) $tables[] = array_pop($tableName);

        // return the array with the table names
        return $tables;

    }

    /**
     *  Stops the execution of the script at the line where this method is called and, if {@link debug} is set to TRUE and
     *  the viewer's IP address is in the {@link debugger_ip} array (or <i>debugger_ip</i> is an empty array), shows the
     *  debugging console.
     *
     *  @since  1.0.7
     *
     *  @return void
     */
    function halt()
    {

        // show the debugging console
        $this->show_debug_console();

        // stop further execution of the script
        die();

    }

    /**
     *  Works similarly to PHP's implode() function with the difference that the "glue" is always the comma, and that
     *  this method {@link escape()}'s arguments.
     *
     *  <i>Useful for escaping an array's values used in SQL statements with the "IN" keyword.</i>
     *
     *  <code>
     *  $array = array(1,2,3,4);
     *
     *  //  INCORRECT
     *
     *  //  this would not work as the WHERE clause in the SQL statement would become
     *  //  WHERE column IN ('1,2,3,4')
     *  $db->query('
     *      SELECT
     *          column
     *      FROM
     *          table
     *      WHERE
     *          column IN (?)
     *  ', array($array));
     *
     *  //  CORRECT
     *
     *  //  this would work as the WHERE clause in the SQL statement would become
     *  //  WHERE column IN ('1','2','3','4') which is what we actually need
     *  $db->query('
     *      SELECT
     *          column
     *      FROM
     *          table
     *      WHERE
     *          column IN (' . $db->implode($array) . ')
     *  ');
     *  </code>
     *
     *
     *  @param  array   $pieces     An array with items to be "glued" together
     *
     *  @since  2.0
     *
     *  @return string              Returns the string representation of all the array elements in the same order,
     *                              escaped and with commas between each element.
     */
    function implode($pieces)
    {

        $result = '';

        // iterate through the array's items and "glue" items together
        foreach ($pieces as $piece) $result .= ($result != '' ? ',' : '') . '\'' . $this->escape($piece) . '\'';

        return $result;

    }

    /**
     *  Shorthand for INSERT queries.
     *
     *  When using this method column names will be enclosed in grave accents " ` " (thus, allowing seamless usage of
     *  reserved words as column names) and values will be automatically {@link escape()}d in order to prevent SQL injections.
     *
     *  <code>
     *  $db->insert(
     *      'table',
     *      array(
     *          'column1'   =>  'value1',
     *          'column2'   =>  'value2',
     *  ));
     *  </code>
     *
     *  @param  string  $table          Table in which to insert.
     *
     *  @param  array   $columns        An associative array where the array's keys represent the columns names and the
     *                                  array's values represent the values to be inserted in each respective column.
     *
     *                                  Column names will be enclosed in grave accents " ` " (thus, allowing seamless
     *                                  usage of reserved words as column names) and values will be automatically
     *                                  {@link escape()}d in order to prevent SQL injections.
     *
     *  @param  boolean $ignore         (Optional) By default trying to insert a record that would cause a duplicate
     *                                  entry for a primary key would result in an error. If you want these errors to be
     *                                  skipped set this argument to TRUE.
     *
     *                                  For more information see {@link http://dev.mysql.com/doc/refman/5.5/en/insert.html MySQL's INSERT IGNORE syntax}.
     *
     *                                  Default is FALSE.
     *
     *  @param  boolean $highlight      (Optional) If set to TRUE the debugging console will be opened automatically
     *                                  and the query will be shown - really useful for quick and easy debugging.
     *
     *                                  Default is FALSE.
     *
     *  @since  1.0.9
     *
     *  @return boolean                 Returns TRUE on success of FALSE on error.
     */
    function insert($table, $columns, $ignore = false, $highlight = false)
    {

        // enclose the column names in grave accents
        $cols = '`' . implode('`,`', array_keys($columns)) . '`';

        // parameter markers for escaping values later on
        $values = rtrim(str_repeat('?,', count($columns)), ',');

        // run the query
        $this->query('

            INSERT' . ($ignore ? ' IGNORE' : '') . ' INTO
                `' . $table . '`
                (' . $cols . ')
            VALUES
                (' . $values . ')'

        , array_values($columns), false, false, $highlight);

        // return true if query was executed successfully
        if ($this->last_result) return true;

        return false;

    }

    /**
     *  Shorthand for inserting multiple rows in a single query.
     *
     *  When using this method column names will be enclosed in grave accents " ` " (thus, allowing seamless usage of
     *  reserved words as column names) and values will be automatically {@link escape()}d in order to prevent SQL injections.
     *
     *  <code>
     *  $db->insert_bulk(
     *      'table',
     *      array('column1', 'column2'),
     *      array(
     *          array('value1', 'value2'),
     *          array('value3', 'value4'),
     *          array('value5', 'value6'),
     *          array('value7', 'value8'),
     *          array('value9', 'value10')
     *      )
     *  ));
     *  </code>
     *
     *  @param  string  $table          Table in which to insert.
     *
     *  @param  array   $columns        An array with columns to insert values into.
     *
     *                                  Column names will be enclosed in grave accents " ` " (thus, allowing seamless
     *                                  usage of reserved words as column names).
     *
     *  @param  arrays  $data           An array of an unlimited number of arrays containing values to be inserted.
     *
     *                                  Values will be automatically {@link escape()}d in order to prevent SQL injections.
     *
     *  @param  boolean $ignore         (Optional) By default, trying to insert a record that would cause a duplicate
     *                                  entry for a primary key would result in an error. If you want these errors to be
     *                                  skipped set this argument to TRUE.
     *
     *                                  For more information see {@link http://dev.mysql.com/doc/refman/5.5/en/insert.html MySQL's INSERT IGNORE syntax}.
     *
     *                                  Default is FALSE.
     *
     *  @since  2.1
     *
     *  @return boolean                 Returns TRUE on success of FALSE on error.
     */
    function insert_bulk($table, $columns, $data, $ignore = false)
    {

        // we can't do array_values(array_pop()) since PHP 5.3+ as will trigger a "strict standards" error
        $values = array_values($data);

        // if $data is not an array of arrays
        if (!is_array(array_pop($values)))

            // save debug information
            $this->_log('errors', array(

                'message'   =>  $this->language['data_not_an_array'],

            ));

        // if arguments are ok
        else {

            // start preparing the INSERT statement
            $sql = '
                INSERT' . ($ignore ? ' IGNORE' : '') . ' INTO
                    `' . $table . '`
                    (' . '`' . implode('`,`', $columns) . '`' . ')
                VALUES
            ';

            // iterate through the arrays and escape values
            foreach ($data as $values) $sql .= '(' . $this->implode($values) . '),';

            // run the query
            $this->query(rtrim($sql, ','));

            // return true if query was executed successfully
            if ($this->last_result) return true;

        }

        // if script gets this far, return false as something must've been wrong
        return false;

    }

    /**
     *  Retrieves the ID generated for an AUTO_INCREMENT column by the previous INSERT query.
     *
     *  @since  1.0.4
     *
     *  @return mixed   The ID generated for an AUTO_INCREMENT column by the previous INSERT query on success,
     *                  '0' if the previous query does not generate an AUTO_INCREMENT value, or FALSE if there was
     *                  no MySQL connection.
     */
    function insert_id()
    {

        // if an active connection exists
        if ($this->_connected())

            // if a query was run before, return the AUTO_INCREMENT value
            if (isset($this->last_result)) return mysqli_insert_id($this->connection);

            // if no query was run before
            else

                // save debug information
                $this->_log('errors', array(

                    'message'   =>  $this->language['not_a_valid_resource'],

                ));

        // upon error, we don't have to report anything as _connected() method already did
        // just return FALSE
        return false;

    }

    /**
     *  When using this method, if a row is inserted that would cause a duplicate value in a UNIQUE index or PRIMARY KEY,
     *  an UPDATE of the old row is performed.
     *
     *  Read more at {@link http://dev.mysql.com/doc/refman/5.0/en/insert-on-duplicate.html}.
     *
     *  When using this method, column names will be enclosed in grave accents " ` " (thus, allowing seamless usage of
     *  reserved words as column names) and values will be automatically {@link escape()}d in order to prevent SQL injections.
     *
     *  <code>
     *  // presuming article_id is a UNIQUE index or PRIMARY KEY, the statement below will insert a new row for given
     *  // $article_id and set the "votes" to 0. But, if $article_id is already in the database, increment the votes'
     *  // numbers.
     *  $db->insert_update(
     *      'table',
     *      array(
     *          'article_id'    =>  $article_id,
     *          'votes'         =>  0,
     *      ),
     *      array(
     *          'votes'         =>  'INC(1)',
     *      )
     *  );
     *  </code>
     *
     *  @param  string  $table          Table in which to insert/update.
     *
     *  @param  array   $columns        An associative array where the array's keys represent the columns names and the
     *                                  array's values represent the values to be inserted in each respective column.
     *
     *                                  Column names will be enclosed in grave accents " ` " (thus, allowing seamless
     *                                  usage of reserved words as column names) and values will be automatically
     *                                  {@link escape()}d.
     *
     *  @param  array   $update         (Optional) An associative array where the array's keys represent the columns names
     *                                  and the array's values represent the values to update the columns' values to.
     *
     *                                  This array represents the columns/values to be updated if the inserted row would
     *                                  cause a duplicate value in a UNIQUE index or PRIMARY KEY.
     *
     *                                  If an empty array is given, the values in <i>$columns</i> will be used.
     *
     *                                  Column names will be enclosed in grave accents " ` " (thus, allowing seamless
     *                                  usage of reserved words as column names) and values will be automatically
     *                                  {@link escape()}d.
     *
     *                                  A special value may also be used for when a column's value needs to be
     *                                  incremented or decremented. In this case, use <i>INC(value)</i> where <i>value</i>
     *                                  is the value to increase the column's value with. Use <i>INC(-value)</i> to decrease
     *                                  the column's value. See {@link update()} for an example.
     *
     *                                  Default is an empty array.
     *
     *  @param  boolean $highlight      (Optional) If set to TRUE the debugging console will be opened automatically
     *                                  and the query will be shown - really useful for quick and easy debugging.
     *
     *                                  Default is FALSE.
     *
     *  @since  2.1
     *
     *  @return boolean                 Returns TRUE on success of FALSE on error.
     */
    function insert_update($table, $columns, $update = array(), $highlight = false)
    {

        // if $update is not given as an array, make it an empty array
        if (!is_array($update)) $update = array();

        // enclose the column names in grave accents
        $cols = '`' . implode('`,`', array_keys($columns)) . '`';

        // parameter markers for escaping values later on
        $values = rtrim(str_repeat('?,', count($columns)), ',');

        // if no $update specified
        if (empty($update)) {

            // use the columns specified in $columns
            $update_cols = '`' . implode('` = ?,`', array_keys($columns)) . '` = ?';

            // use the same column for update as for insert
            $update = $columns;

        // if $update is specified
        // generate the SQL from the $update array
        } else $update_cols = $this->_build_sql($update);

        // run the query
        $this->query('

            INSERT INTO
                `' . $table . '`
                (' . $cols . ')
            VALUES
                (' . $values . ')
            ON DUPLICATE KEY UPDATE
                ' . $update_cols

        , array_merge(array_values($columns), array_values($update)), false, false, $highlight);

        // return true if query was executed successfully
        if ($this->last_result) return true;

        return false;

    }

    /**
     *  Sets the language to be used for the messages in the debugging console.
     *
     *  <code>
     *  // show messages in the debugging console in German
     *  $db->language('german');
     *  </code>
     *
     *  @param  string  $language   The name of the PHP language file from the "languages" subdirectory.
     *
     *                              Must be specified without the extension!
     *                              (i.e. "german" for the german language not "german.php")
     *
     *                              Default is "english".
     *
     *  @since  1.0.6
     *
     *  @return void
     */
    function language($language)
    {

        // include the language file
        require $this->path . '/languages/' . $language . '.php';

    }

    /**
     *  Optimizes all tables that have overhead (unused, lost space)
     *
     *  <code>
     *  // optimize all tables in the database
     *  $db->optimize();
     *  </code>
     *
     *  @since  1.1.2
     *
     *  @return void
     */
    function optimize()
    {

        // fetch information on all the tables in the database
        $tables = $this->get_table_status();

        // iterate through the database's tables, and if it has overhead (unused, lost space), optimize it
        foreach ($tables as $table) if ($table['Data_free'] > 0) $this->query('OPTIMIZE TABLE `' . $table['Name'] . '`');

    }

    /**
     *  Parses a MySQL dump file (like an export from phpMyAdmin).
     *
     *  <i>If you must parse a very large file and your script crashed due to timeout or because of memory limitations,
     *  try the following:</i>
     *
     *  <code>
     *  // prevent script timeout
     *  set_time_limit(0);
     *
     *  // allow for more memory to be used by the script
     *  ini_set('memory_limit','128M');
     *  </code>
     *
     *  @param  string  $path   Path to the file to be parsed.
     *
     *  @return boolean         Returns TRUE on success or FALSE on failure.
     */
    function parse_file($path)
    {

        // if an active connection exists
        if ($this->_connected()) {

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
                    if ($tsql != '' && substr($tsql, 0, 2) != '--' && substr($tsql, 0, 1) != '#') {

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

            // if file could not be opened
            } else

                // save debug info
                $this->_log('errors', array(

                    'message'   =>  $this->language['file_could_not_be_opened'],

                ));

        }

        // we don't have to report any error as _connected() method already did or checking for file returned FALSE
        return false;

    }

    /**
     *  Runs a MySQL query.
     *
     *  After a SELECT query you can get the number of returned rows by reading the {@link returned_rows} property.
     *
     *  After an UPDATE, INSERT or DELETE query you can get the number of affected rows by reading the
     *  {@link affected_rows} property.
     *
     *  <b>Note that you don't need to return the result of this method in a variable for using it later with
     *  a fetch method like {@link fetch_assoc()} or {@link fetch_obj()}, as all these methods, if called without the
     *  resource arguments, work on the LAST returned result resource!</b>
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
     *  </code>
     *
     *  @param  string  $sql            MySQL statement to execute.
     *
     *  @param  array   $replacements   (Optional) An array with as many items as the total parameter markers ("?", question
     *                                  marks) in <i>$sql</i>. Each item will be automatically {@link escape()}-ed and
     *                                  will replace the corresponding "?".
     *
     *                                  Default is "" (an empty string).
     *
     *  @param  mixed   $cache          (Optional) Instructs the library on whether it should cache the query's results
     *                                  or not. Can be either FALSE - meaning no caching - or an integer representing the
     *                                  number of seconds after which the cache will be considered expired and the query
     *                                  executed again.
     *
     *                                  The caching method is specified by the value of the {@link caching_method} property.
     *
     *                                  Default is FALSE.
     *
     *  @param  boolean $calc_rows      (Optional) If query is a SELECT query, this argument is set to TRUE, and there is
     *                                  a LIMIT applied to the query, the value of the {@link found_rows} property (after
     *                                  the query was run) will represent the number of records that would have been
     *                                  returned if there was no LIMIT applied to the query.
     *
     *                                  This is very useful for creating pagination or computing averages. Also, note
     *                                  that this information will be available without running an extra query. Here's
     *                                  how {@link http://dev.mysql.com/doc/refman/5.0/en/information-functions.html#function_found-rows}
     *
     *                                  Default is FALSE.
     *
     *  @param  boolean $highlight      (Optional) If set to TRUE the debugging console will be opened automatically
     *                                  and the query will be shown - really useful for quick and easy debugging.
     *
     *                                  Default is FALSE.
     *
     *  @return mixed                   On success, returns a resource or an array (if results are taken from the cache)
     *                                  or FALSE on error.
     *
     *                                  <i>If query results are taken from cache, the returned result will be a pointer to
     *                                  the actual results of the query!</i>
     */
    function query($sql, $replacements = '', $cache = false, $calc_rows = false, $highlight= false)
    {

        // if an active connection exists
        if ($this->_connected()) {

            // remove spaces used for indentation (if any)
            $sql = preg_replace(array("/^\s+/m", "/\r\n/"), array('', ' '), $sql);

            unset($this->affected_rows);

            // if $replacements is specified but it's not an array
            if ($replacements != '' && !is_array($replacements))

                // save debug information
                $this->_log('unsuccessful-queries',  array(

                    'query' =>  $sql,
                    'error' =>  $this->language['warning_replacements_not_array']

                ));

            // if $replacements is specified and is an array
            elseif ($replacements != '' && is_array($replacements) && !empty($replacements)) {

                // found how many items to replace are there in the query string
                preg_match_all('/\?/', $sql, $matches, PREG_OFFSET_CAPTURE);

                // if the number of items to replace is different than the number of items specified in $replacements
                if (!empty($matches[0]) && count($matches[0]) != count($replacements))

                    // save debug information
                    $this->_log('unsuccessful-queries', array(

                        'query' => $sql,
                        'error' => $this->language['warning_replacements_wrong_number']

                    ));

                // if the number of items to replace is the same as the number of items specified in $replacements
                else {

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
                        if ($replacement === NULL) $replacements2[$key] = 'NULL';

                        // otherwise, mysqli_real_escape_string the items in replacements
                        // also, replace anything that looks like $45 to \$45 or else the next preg_replace-s will treat
                        // it as references
                        else $replacements2[$key] = '\'' . preg_replace('/\$([0-9]*)/', '\\\$$1', $this->escape($replacement)) . '\'';

                        // and also, prepare the new pattern to be replaced afterwards
                        $pattern2[$key] = '/' . $randomstr . '/';

                    }

                    // replace each question mark with something new
                    // (we do this intermediary step so that we can actually have question marks in the replacements)
                    $sql = preg_replace($pattern1, $replacements1, $sql, 1);

                    // perform the actual replacement
                    $sql = preg_replace($pattern2, $replacements2, $sql, 1);

                }

            }

            // $calc_rows is TRUE, we have a SELECT query and the SQL_CALC_FOUND_ROWS string is not in it
            // (we do this trick to get the numbers of records that would've been returned if there was no LIMIT applied)
            if ($calc_rows && strtolower(substr(ltrim($sql), 0, 6)) == 'select' && strpos($sql, 'SQL_CALC_FOUND_ROWS') === false)

                // add the 'SQL_CALC_FOUND_ROWS' parameter to the query
                $sql = preg_replace('/SELECT/i', 'SELECT SQL_CALC_FOUND_ROWS', $sql, 1);

            unset($this->last_result);

            // starts a timer
            list($usec, $sec) = explode(' ', microtime());

            $start_timer = (float)$usec + (float)$sec;

            $refreshed_cache = 'nocache';

            // if we need to look for a cached version of the query's results
            if ($cache !== false && (int)$cache > 0) {

                // by default, we assume that the cache exists and is not expired
                $refreshed_cache = false;

                // if caching method is "memcache"
                if ($this->caching_method == 'memcache') {

                    // the key to identify this particular information (prefix it if required)
                    $memcache_key = md5($this->memcache_key_prefix . $sql);

                    // if there is a cached version of what we're looking for, and data is valid
                    if (($result = $this->memcache->get($memcache_key)) && $this->cached_results[] = @unserialize(gzuncompress(base64_decode($result)))) {

                        // assign to the last_result property the pointer to the position where the array was added
                        $this->last_result = count($this->cached_results) - 1;

                        // reset the pointer of the array
                        reset($this->cached_results[$this->last_result]);

                    }

                // if caching method is "session"
                } elseif ($this->caching_method == 'session') {

                    // unique identifier of the current query
                    $key = md5($sql);

                    // if a cached version of this query's result already exists and it is not expired
                    if (isset($_SESSION[$key]) && isset($_SESSION[$key . '_timestamp']) && $_SESSION[$key . '_timestamp'] + $cache > time() && $this->cached_results[] = @unserialize(gzuncompress(base64_decode($_SESSION[$key])))) {

                        // assign to the last_result property the pointer to the position where the array was added
                        $this->last_result = count($this->cached_results) - 1;

                        // reset the pointer of the array
                        reset($this->cached_results[$this->last_result]);

                    }

                // if caching method is "disk"
                } else

                    // if cache folder exists and is writable
                    if (file_exists($this->cache_path) && is_dir($this->cache_path) && is_writable($this->cache_path)) {

                        // the cache file's name
                        $file_name = rtrim($this->cache_path, '/') . '/' . md5($sql);

                        // if a cached version of this query's result already exists and it is not expired
                        if (file_exists($file_name) && filemtime($file_name) + $cache > time())

                            // if cache file is valid
                            if ($this->cached_results[] = @unserialize(gzuncompress(base64_decode(file_get_contents($file_name))))) {

                                // assign to the last_result property the pointer to the position where the array was added
                                $this->last_result = count($this->cached_results) - 1;

                                // reset the pointer of the array
                                reset($this->cached_results[$this->last_result]);

                            }

                    // if folder doesn't exist
                    } else

                        // save debug information
                        $this->_log('errors', array(

                            'message'   =>  $this->language['cache_path_not_writable'],

                        ), false);

            }

            // if query was not read from the cache
            if (!isset($this->last_result)) {

                // run the query
                $this->last_result = @mysqli_query($this->connection, $sql);

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

            // if execution time exceeds max_query_time
            if ($stop_timer - $start_timer > $this->max_query_time)

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

                    // by default, consider this not to be a SELECT query
                    $is_select = false;

                    // if returned resource is a valid resource, consider query to be a SELECT query
                    if ($this->_is_result($this->last_result)) $is_select = true;

                    // reset these values for each query
                    $this->returned_rows = $this->found_rows = 0;

                    // if query was a SELECT query
                    if ($is_select) {

                        // the returned_rows property holds the number of records returned by a SELECT query
                        $this->returned_rows = $this->found_rows = @mysqli_num_rows($this->last_result);

                        // if we need the number of rows that would have been returned if there was no LIMIT
                        if ($calc_rows) {

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

                            'returned_rows' =>  $this->returned_rows,
                            'found_rows'    =>  $this->found_rows,
                            'column_info'   =>  $this->get_columns(),

                        ));

                        // the content to be cached
                        $content = base64_encode(gzcompress(serialize($cache_data)));

                        // if caching method is "memcache"
                        if ($this->caching_method == 'memcache')

                            // cache query data
                            $this->memcache->set($memcache_key, $content, ($this->memcache_compressed ? MEMCACHE_COMPRESSED : false), $cache);

                        // if caching method is "session"
                        elseif ($this->caching_method == 'session') {

                            // if there seems to be no active session
                            if (!isset($_SESSION))

                                // save debug information
                                $this->_log('errors', array(

                                    'message'   =>  $this->language['no_active_session'],

                                ), true);

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

                // if debugging is on
                if ($this->debug) {

                    $warning = '';

                    $result = array();

                    // if rows were returned
                    if ($is_select) {

                        $row_counter = 0;

                        // put the first rows, as defined by console_show_records, in an array to show them in the
                        // debugging console
                        // if query was not read from cache
                        if ($this->_is_result($this->last_result)) {

                            // iterate through the records until we displayed enough records
                            while ($row_counter++ < $this->console_show_records && $row = mysqli_fetch_assoc($this->last_result))

                                $result[] = $row;

                            // reset the pointer in the result afterwards
                            // we have to mute error reporting because if the result set is empty (mysqli_num_rows() == 0),
                            // a seek to 0 will fail with a E_WARNING!
                            @mysqli_data_seek($this->last_result, 0);

                        // if query was read from the cache
                        // put the first rows, as defined by console_show_records, in an array to show them in the
                        // debugging console
                        } else $result = array_slice($this->cached_results[$this->last_result], 0, $this->console_show_records);

                        // if there were queries run already
                        if (isset($this->debug_info['successful-queries'])) {

                            $keys = array();

                            // iterate through the run queries
                            // to find out if this query was already run
                            foreach ($this->debug_info['successful-queries'] as $key=>$query_data)

                                // if this query was run before
                                if (

                                    isset($query_data['records']) &&

                                    !empty($query_data['records']) &&

                                    $query_data['records'] == $result

                                // save the pointer to the query in an array
                                ) $keys[] = $key;

                            // if the query was run before
                            if (!empty($keys))

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

                        // if it's a SELECT query and query is not read from cache...
                        if ($is_select && $this->_is_result($this->last_result)) {

                            // ask the MySQL to EXPLAIN the query
                            $explain_resource = mysqli_query($this->connection, 'EXPLAIN EXTENDED ' . $sql);

                            // if query returned a result
                            // (as some queries cannot be EXPLAIN-ed like SHOW TABLE, DESCRIBE, etc)
                            if ($explain_resource)

                                // put all the records returned by the explain query in an array
                                while ($row = mysqli_fetch_assoc($explain_resource)) $explain[] = $row;

                        }

                    }

                    // save debug information
                    $this->_log('successful-queries', array(

                        'query'         =>  $sql,
                        'records'       =>  $result,
                        'returned_rows' =>  $this->returned_rows,
                        'explain'       =>  (isset($explain) ? $explain : ''),
                        'affected_rows' =>  (isset($this->affected_rows) ? $this->affected_rows : false),
                        'execution_time'=>  $stop_timer - $start_timer,
                        'warning'       =>  $warning,
                        'highlight'     =>  $highlight,
                        'from_cache'    =>  $refreshed_cache,
                        'transaction'   =>  ($this->transaction_status !== 0 ? true : false),

                    ), false);

                    // if at least one query is to be highlighted, set the "minimize_console" property to FALSE
                    if ($highlight) $this->minimize_console = false;

                }

                // return result resource
                return $this->last_result;

            }

            // in case of error
            // save debug information
            $this->_log('unsuccessful-queries', array(

                'query'     =>  $sql,
                'error'     =>  mysqli_error($this->connection)

            ));

        }

        // we don't have to report any error as _connected() method already did or any of the previous checks
        return false;

    }

    /**
     *  Moves the internal row pointer of the MySQL result associated with the specified result identifier to the
     *  specified row number.
     *
     *  The next call to a fetch method like {@link fetch_assoc()} or {@link fetch_obj()} would return that row.
     *
     *  @param  integer     $row        The row you want to move the pointer to.
     *
     *                                  <i>$row</i> starts at 0.
     *
     *                                  <i>$row</i> should be a value in the range from 0 to {@link returned_rows}
     *
     *  @param  resource    $resource   (Optional) Resource to fetch.
     *
     *                                  <i>If not specified, the resource returned by the last run query is used.</i>
     *
     *  @since  1.1.0
     *
     *  @return boolean                 Returns TRUE on success or FALSE on failure.
     */
    function seek($row, $resource = '')
    {

        // if an active connection exists
        if ($this->_connected()) {

            // if no resource was specified, and there was a previous call to the "query" method, assign the last resource
            if ($resource == '' && isset($this->last_result)) $resource = & $this->last_result;

            // check if given resource is valid
            if ($this->_is_result($resource)) {

                // return the fetched row
                // we have to mute error reporting because if the result set is empty (mysqli_num_rows() == 0),
                // a seek to 0 will fail with a E_WARNING!
                if (@mysqli_data_seek($resource, $row)) return true;

                // if error reporting was not supressed with @
                elseif (error_reporting() != 0)

                    // save debug information
                    $this->_log('errors', array(

                        'message'   =>  $this->language['could_not_seek'],

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
                    while (list($key, $value) = each($this->cached_results[$resource]))

                        // we check it like this because elseways we'll have the pointer moved one entry too far
                        if ($key == $row - 1) return true;

                    // save debug information
                    $this->_log('errors', array(

                        'message'   =>  $this->language['could_not_seek'],

                    ));

                }

            // if not a valid resource
            } else

                // save debug information
                $this->_log('errors', array(

                    'message'   =>  $this->language['not_a_valid_resource'],

                ));

        }

        // we don't have to report any error as _connected() method already did or checking for valid resource failed
        return false;

    }

    /**
     *  Shorthand for simple SELECT queries.
     *
     *  For complex queries (using UNION, JOIN, etc) use the {@link query()} method.
     *
     *  When using this method, column names will be enclosed in grave accents " ` " (thus, allowing seamless usage of
     *  reserved words as column names) and values will be automatically {@link escape()}d in order to prevent SQL injections.
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
     *                                  as used in a SELECT statement.
     *
     *                                  <samp>These will be enclosed in grave accents, so make sure you are only using
     *                                  column names and not things like "tablename.*"! You may also use "*" instead
     *                                  of column names to select all columns from a table.</samp>
     *
     *  @param  string  $table          Table in which to search.
     *
     *                                  <i>Note that table name will be enclosed in grave accents " ` " and thus only
     *                                  one table name should be used! For anything but a simple select query use the
     *                                  {@link query()} method.</i>
     *
     *  @param  string  $where          (Optional) A MySQL WHERE clause (without the WHERE keyword).
     *
     *                                  Default is "" (an empty string).
     *
     *  @param  array   $replacements   (Optional) An array with as many items as the total parameter markers ("?", question
     *                                  marks) in <i>$column</i>, <i>$table</i> and <i>$where</i>. Each item will be
     *                                  automatically {@link escape()}-ed and will replace the corresponding "?".
     *
     *                                  Default is "" (an empty string).
     *
     *  @param  string  $order          (Optional) A MySQL ORDER BY clause (without the ORDER BY keyword).
     *
     *                                  Default is "" (an empty string).
     *
     *  @param  mixed   $limit          (Optional) A MySQL LIMIT clause (without the LIMIT keyword).
     *
     *                                  Default is "" (an empty string).
     *
     *  @param  mixed   $cache          (Optional) Instructs the library on whether it should cache the query's results
     *                                  or not. Can be either FALSE - meaning no caching - or an integer representing the
     *                                  number of seconds after which the cache will be considered expired and the query
     *                                  executed again.
     *
     *                                  The caching method is specified by the value of the {@link caching_method} property.
     *
     *                                  Default is FALSE.
     *
     *  @param  boolean $calc_rows      (Optional) If query is a SELECT query, this argument is set to TRUE, and there is
     *                                  a LIMIT applied to the query, the value of the {@link found_rows} property (after
     *                                  the query was run) will represent the number of records that would have been
     *                                  returned if there was no LIMIT applied to the query.
     *
     *                                  This is very useful for creating pagination or computing averages. Also, note
     *                                  that this information will be available without running an extra query. Here's
     *                                  how {@link http://dev.mysql.com/doc/refman/5.0/en/information-functions.html#function_found-rows}
     *
     *                                  Default is FALSE.
     *
     *  @param  boolean $highlight      (Optional) If set to TRUE the debugging console will be opened automatically
     *                                  and the query will be shown - really useful for quick and easy debugging.
     *
     *                                  Default is FALSE.
     *
     *  @since  2.0
     *
     *  @return mixed                   On success, returns a resource or an array (if results are taken from the cache)
     *                                  or FALSE on error.
     *
     *                                  <i>If query results are taken from cache, the returned result will be a pointer to
     *                                  the actual results of the query!</i>
     */
    function select($columns, $table, $where = '', $replacements = '', $order = '', $limit = '', $cache = false, $calc_rows = false, $highlight = false)
    {

        // run the query
        return $this->query('

            SELECT
                ' . (is_string($columns) ? $columns : $this->_build_columns($columns)) . '
            FROM
                `' . $table . '`' .

            ($where != '' ? ' WHERE ' . $where : '') .

            ($order != '' ? ' ORDER BY ' . $order : '') .

            ($limit != '' ? ' LIMIT ' . $limit : '')

        , $replacements, $cache, $calc_rows, $highlight);

    }

    /**
     *  Sets MySQL character set and collation.
     *
     *  The ensure that data is both properly saved and retrieved from the database you should call this method first
     *  thing after connecting to the database.
     *
     *  If this method is not called a warning message will be displayed in the debugging console.
     *
     *  Warnings can be disabled by setting the {@link disable_warnings} property.
     *
     *  @param  string  $charset    (Optional) The character set to be used by the database.
     *
     *                              Default is 'utf8'.
     *
     *                              For a list of possible values see:
     *                              {@link http://dev.mysql.com/doc/refman/5.1/en/charset-charsets.html}
     *
     *  @param  string  $collation  (Optional) The collation to be used by the database.
     *
     *                              Default is 'utf8_general_ci'.
     *
     *                              For a list of possible values see:
     *                              {@link http://dev.mysql.com/doc/refman/5.1/en/charset-charsets.html}
     *
     *  @since  2.0
     *
     *  @return void
     */
    function set_charset($charset = 'utf8', $collation = 'utf8_general_ci')
    {

        // do not show the warning that this method has not been called
        unset($this->warnings['charset']);

        // set MySQL character set
		$this->query('SET NAMES "' . $this->escape($charset) . '" COLLATE "' . $this->escape($collation) . '"');

    }

    /**
     *  Shows the debugging console, <i>if</i> {@link debug} is TRUE and the viewer's IP address is in the
     *  {@link debugger_ip} array (or <i>$debugger_ip</i> is an empty array).
     *
     *  <i>This method must be called after all the queries in a script, preferably before </body>!</i>
     *
     *  <b>You should ALWAYS have this method called at the end of your scripts and control whether the debugging console
     *  will show or not with the {@link debug} property.</b>
     *
     *  @param  boolean $return         (Optional) If set to TRUE, the output will be returned instead of being printed
     *                                  to the screen.
     *
     *                                  Default is FALSE.
     *
     *  @return void
     */
    function show_debug_console($return = false)
    {

        // if
        if (

            // debug is enabled AND
            $this->debug &&

            // debugger_ip is an array AND
            is_array($this->debugger_ip) &&

                (

                    // debugger_ip is an empty array OR
                    empty($this->debugger_ip) ||

                    // the viewer's IP is the allowed array
                    in_array($_SERVER['REMOTE_ADDR'], $this->debugger_ip)

                )

        ) {

            // if warnings are not disabled
            if (!$this->disable_warnings)

                // if there are any warning messages iterate through them
                foreach (array_keys($this->warnings) as $warning)

                    // add them to the debugging console
                    $this->_log('warnings', array(

                        'message'   =>  $this->language['warning_' . $warning],

                    ), false);

            // blocks to be shown in the debugging console
            $blocks = array(
                'errors'                =>  array(
                                                'counter'       =>  0,
                                                'identifier'    =>  'e',
                                                'generated'     =>  '',
                                            ),
                'successful-queries'    =>  array(
                                                'counter'       =>  0,
                                                'identifier'    =>  'sq',
                                                'generated'     =>  '',
                                            ),
                'unsuccessful-queries'  =>  array(
                                                'counter'       =>  0,
                                                'identifier'    =>  'uq',
                                                'generated'     =>  '',
                                            ),
                'warnings'              =>  array(
                                                'counter'       =>  0,
                                                'identifier'    =>  'w',
                                                'generated'     =>  '',
                                            ),
                'globals'               =>  array(
                                                'generated'         =>  '',
                                            ),
            );

            // there are no warnings
            $warnings = false;

            // prepare output for each block
            foreach (array_keys($blocks) as $block) {

                $output = '';

                // if there is any information for the current block
                if (isset($this->debug_info[$block])) {

                    // iterate through the error message
                    foreach ($this->debug_info[$block] as $debug_info) {

                        // increment the messages counter
                        $counter = ++$blocks[$block]['counter'];

                        $identifier = $blocks[$block]['identifier'];

                        // if block is about queries
                        if ($block == 'successful-queries' || $block == 'unsuccessful-queries') {

                            // symbols in MySQL query
                            $symbols = array(
                                '=',
                                '>',
                                '<',
                                '*',
                                '+',
                                '-',
                                ',',
                                '.',
                                '(',
                                ')',
                            );

                            // escape special characters and prepare them to be used to regular expressions
                            array_walk($symbols, create_function('&$value', '$value="/(" . quotemeta($value) . ")/";'));

                            // strings in MySQL queries
                            $strings = array(
                                "/\'([^\']*)\'/",
                                "/\"([^\"]*)\"/",
                            );

                            // keywords in MySQL queries
                            $keywords = array(
                                'ADD',
                                'ALTER',
                                'ANALYZE',
                                'BETWEEN',
                                'CHANGE',
                                'COMMIT',
                                'CREATE',
                                'DELETE',
                                'DROP',
                                'EXPLAIN',
                                'FROM',
                                'GROUP BY',
                                'HAVING',
                                'INNER JOIN',
                                'INSERT INTO',
                                'LEFT JOIN',
                                'LIMIT',
                                'ON DUPLICATE KEY',
                                'OPTIMIZE',
                                'ORDER BY',
                                'RENAME',
                                'REPAIR',
                                'REPLACE INTO',
                                'RIGHT JOIN',
                                'ROLLBACK',
                                'SELECT',
                                'SET',
                                'SHOW',
                                'START TRANSACTION',
                                'STATUS',
                                'TABLE',
                                'TABLES',
                                'TRUNCATE',
                                'UPDATE',
                                'UNION',
                                'VALUES',
                                'WHERE'
                            );

                            // escape special characters and prepare them to be used to regular expressions
                            array_walk($keywords, create_function('&$value', '$value="/(\b" . quotemeta($value) . "\b)/i";'));

                            // more keywords (these are the keywords that we don't put a line break after in the debugging console
                            // when showing queries formatted and highlighted)
                            $keywords2 = array(
                                'AGAINST',
                                'ALL',
                                'AND',
                                'AS',
                                'ASC',
                                'AUTO INCREMENT',
                                'AVG',
                                'BINARY',
                                'BOOLEAN',
                                'BOTH',
                                'CASE',
                                'COLLATE',
                                'COUNT',
                                'DESC',
                                'DOUBLE',
                                'ELSE',
                                'END',
                                'ENUM',
                                'FIND_IN_SET',
                                'IN',
                                'INT',
                                'IS',
                                'KEY',
                                'LIKE',
                                'MATCH',
                                'MAX',
                                'MIN',
                                'MODE',
                                'NAMES',
                                'NOT',
                                'NULL',
                                'ON',
                                'OR',
                                'SQL_CALC_FOUND_ROWS',
                                'SUM',
                                'TEXT',
                                'THEN',
                                'TO',
                                'VARCHAR',
                                'WHEN',
                                'XOR',
                            );

                            // escape special characters and prepare them to be used to regular expressions
                            array_walk($keywords2, create_function('&$value', '$value="/(\b" . quotemeta($value) . "\b)/i";'));

                            $query_strings = array();

                            // if there are any strings in the query, store the offset where they start and the actual string
                            // in the $matches var
                            if (preg_match_all(

                                '/(\'|\"|\`)([^\1\\\]*?(?:\\\.[^\1\\\]*?)*)\\1/',

                                $debug_info['query'],

                                $matches,

                                PREG_OFFSET_CAPTURE

                            ) > 0) {

                                // reverse the order in which strings will be replaced so that we replace strings starting with
                                // the last one or else we scramble up the offsets...
                                $matches[2] = array_reverse($matches[2], true);

                                // iterate through the strings
                                foreach ($matches[2] as $match) {

                                    // save the strings
                                    $query_strings['/' . md5($match[0]) . '/'] = preg_replace('/\$([0-9]*)/', '\\\$$1', $match[0]);

                                    // replace strings with their md5 hashed equivalent
                                    // (we do this because we don't have to highlight anything in strings)
                                    $debug_info['query'] = substr_replace(

                                        $debug_info['query'],

                                        md5($match[0]),

                                        $match[1],

                                        strlen($match[0])

                                    );

                                }

                            }

                            // highlight symbols
                            $debug_info['query'] =

                                preg_replace($symbols, htmlentities('<span class="symbol">$1</span>'), $debug_info['query']);

                            // highlight strings
                            $replacement = htmlentities('<span class="string">\'$1\'</span>');

                            $debug_info['query'] = preg_replace($strings, $replacement, $debug_info['query']);

                            // highlight keywords
                            $debug_info['query'] =

                                preg_replace(

                                    $keywords,

                                    htmlentities('<br><span class="keyword">$1</span><br><span class="indent"></span>'),

                                    $debug_info['query']

                                );

                            // highlight more keywords
                            $debug_info['query'] =

                                preg_replace($keywords2, htmlentities('<span class="keyword">$1</span>'), $debug_info['query']);

                            // convert strings back to their original values
                            $debug_info['query'] = preg_replace(array_keys($query_strings), $query_strings, $debug_info['query']);

                        }

                        // all blocks are enclosed in tables
                        $output .= '
                            <table cellspacing="0" cellpadding="0" border="1" class="zdc-entry' .

                                // apply a class for even rows
                                ($counter % 2 == 0 ? ' even' : '') .

                                // should this query be highlighted
                                (isset($debug_info['highlight']) && $debug_info['highlight'] == 1 ? ' zdc-highlight' : '') .

                            '">
                                <tr>
                                    <td class="zdc-counter" valign="top">' . str_pad($counter, 3, '0', STR_PAD_LEFT) . '</td>
                                    <td class="zdc-data">
                        ';

                        // are there any error messages issued by the script?
                        if (isset($debug_info['message']) && trim($debug_info['message']) != '')

                            $output .= '
                                <div class="zdc-box zdc-error">
                                    ' . $debug_info['message'] . '
                                </div>
                            ';

                        // are there any error messages issued by MySQL?
                        if (isset($debug_info['error']) && trim($debug_info['error']) != '')

                            $output .= '
                                <div class="zdc-box zdc-error">
                                    ' . $debug_info['error'] . '
                                </div>
                            ';

                        // are there any warning messages issued by the script?
                        if (isset($debug_info['warning']) && trim($debug_info['warning']) != '') {

                            $output .= '
                                <div class="zdc-box zdc-error">' .
                                    $debug_info['warning'] . '
                                </div>
                            ';

                            // set a flag so that we show in the minimized debugging console that there are warnings
                            $warnings = true;

                        }

                        // is there a query to be displayed?
                        if (isset($debug_info['query']) )

                            $output .= '
                                <div class="zdc-box' . (isset($debug_info['transaction']) && $debug_info['transaction'] ? ' zdc-transaction' : '') . '">' .
                                    preg_replace('/^\<br\>/', '', html_entity_decode($debug_info['query'])) . '
                                </div>
                            ';

                        // start generating the actions box
                        $output .= '
                            <div class="zdc-box zdc-actions">
                                <ul>
                        ';

                        // actions specific to successful queries
                        if ($block == 'successful-queries') {

                            // info about whether the query results were taken from cache or not
                            if ($debug_info['from_cache'] != 'nocache')

                                $output .= '
                                    <li class="zdc-cache">
                                        <strong>' . $this->language['from_cache'] . ' (' . $this->caching_method . ')</strong>
                                    </li>
                                ';

                            // info about execution time
                            $output .= '
                                <li class="zdc-time">' .
                                    $this->language['execution_time'] . ': ' .
                                    $this->_fix_pow($debug_info['execution_time']) . ' ' .
                                    $this->language['miliseconds'] . ' (<strong>' .
                                    number_format(
                                        ($this->total_execution_time != 0 ? $debug_info['execution_time'] * 100 / $this->total_execution_time : 0),
                                        2, '.', ',') . '</strong>%)
                                </li>
                            ';

                            // if not an action query
                            if ($debug_info['affected_rows'] === false)

                                // button for reviewing returned rows
                                $output .= '
                                    <li class="zdc-records">
                                        <a href="javascript:zdc_toggle(\'zdc-records-sq' . $counter . '\')">' .
                                            $this->language['returned_rows'] . ': <strong>' . $debug_info['returned_rows'] . '</strong>
                                        </a>
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
                                        <a href="javascript:zdc_toggle(\'zdc-explain-sq' . $counter . '\')">' .
                                            $this->language['explain'] . '
                                        </a>
                                    </li>
                                ';

                        }

                        // if backtrace information is available
                        if (isset($debug_info['backtrace']))

                            $output .= '
                                <li class="zdc-backtrace">
                                    <a href="javascript:zdc_toggle(\'zdc-backtrace-' . $identifier . $counter . '\')">' .
                                        $this->language['backtrace'] . '
                                    </a>
                                </li>
                            ';

                        // common actions (to top, close all)
                        $output .= '
                            <li class="zdc-top">
                                <a href="' . preg_replace('/\#zdc\-top$/i', '', $_SERVER['REQUEST_URI']) . '#zdc-top">' .
                                    $this->language['to_top'] . '
                                </a>
                            </li>
                            <li class="zdc-close">
                                <a href="javascript:zdc_closeAll(\'\')">' .
                                    $this->language['close_all'] . '
                                </a>
                            </li>
                        ';

                        // wrap up actions bar
                        $output .= '
                                </ul>
                                <div class="clear"></div>
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
                                <div id="zdc-' . $table . '-' . $identifier . $counter . '" class="zdc-box zdc-' . $table . '-table">
                                    <table cellspacing="0" cellpadding="0" border="1">
                                        <tr>
                            ';

                            // print table headers
                            foreach (array_keys($debug_info[$table][0]) as $header) $output .= '<th>' . $header . '</th>';

                            $output .= '</tr>';

                            // print table rows and columns
                            foreach ($debug_info[$table] as $index => $row) {

                                $output .= '<tr class="' . (($index + 1) % 2 == 0 ? 'even' : '') . '">';

                                foreach (array_values($row) as $column) $output .= '<td valign="top">' . $column . '</td>';

                                $output .= '</tr>';

                            }

                            // wrap up data tables
                            $output .= '
                                    </table>
                                </div>
                            ';

                        }

                        // finish block
                        $output .= '
                                    </td>
                                </tr>
                            </table>
                        ';

                    }

                    // if anything was generated for the current block
                    // enclose generated output in a special div
                    if ($counter > 0) $blocks[$block]['generated'] = '<div id="zdc-' . $block . '">' . $output . '</div>';

                } elseif ($block == 'globals') {

                    // globals to show
                    $globals = array('POST', 'GET', 'SESSION', 'COOKIE', 'FILES', 'SERVER');

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
                                <a href="javascript:zdc_toggle(\'zdc-globals-' . strtolower($global) . '\')">$_' .
                                    $global . '
                                </a>
                            </li>
                        ';

                    // finish building the submenu
                    $output .= '
                            </ul>
                            <div class="clear"></div>
                        </div>
                    ';

                    // iterate thought the superglobals to show
                    foreach ($globals as $global) {

                        // make the superglobal available
                        global ${'_' . $global};

                        // add to the generated output
                        $output .= '
                            <table cellspacing="0" cellpadding="0" border="1" id="zdc-globals-' . strtolower($global) . '" class="zdc-entry">
                                <tr>
                                    <td class="zdc-counter" valign="top">001</td>
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

            // if there's an error, show the console
            if ($blocks['unsuccessful-queries']['counter'] > 0 || $blocks['errors']['counter'] > 0) $this->minimize_console = false;

            // finalize output by enclosing the debugging console's menu and generated blocks in a container
            $output =  '
                <div id="zdc" style="display:' . ($this->minimize_console ? 'none' : 'block') . '">
                    <a name="zdc-top"></a>
                    <ul class="zdc-main">
            ';

            // are there any error messages?
            if ($blocks['errors']['counter'] > 0)

                // button for reviewing errors
                $output .= '
                    <li>
                        <a href="javascript:zdc_toggle(\'zdc-errors\')">' .
                            $this->language['errors'] . ': <span>' . $blocks['errors']['counter'] . '</span>
                        </a>
                    </li>
                ';

            // common buttons
            $output .= '
                <li>
                    <a href="javascript:zdc_toggle(\'zdc-successful-queries\')">' .
                        $this->language['successful_queries'] . ': <span>' . $blocks['successful-queries']['counter'] . '</span>&nbsp;(' .
                        $this->_fix_pow($this->total_execution_time) . ' ' . $this->language['miliseconds'] . ')
                    </a>
                </li>
                <li>
                    <a href="javascript:zdc_toggle(\'zdc-unsuccessful-queries\')">' .
                        $this->language['unsuccessful_queries'] . ': <span>' . $blocks['unsuccessful-queries']['counter'] . '</span>
                    </a>
                </li>
            ';

            if (isset($this->debug_info['warnings']))

                $output .= '
                    <li>
                        <a href="javascript:zdc_toggle(\'zdc-warnings\')">' .
                            $this->language['warnings'] . ': <span>' . count($this->warnings) . '</span>
                        </a>
                    </li>
                ';

            $output .= '
                <li>
                    <a href="javascript:zdc_toggle(\'zdc-globals-submenu\')">' .
                        $this->language['globals'] . '
                    </a>
                </li>
            ';

            // wrap up debugging console's menu
            $output .= '
                </ul>
                <div class="clear"></div>
            ';

            foreach (array_keys($blocks) as $block) $output .= $blocks[$block]['generated'];

            // wrap up
            $output .= '</div>';

            // add the minified version of the debugging console
            $output .= '
                <div id="zdc-mini">
                    <a href="javascript:zdc_toggle(\'console\')">' .
                        $blocks['successful-queries']['counter'] . ($warnings ? '<span>!</span>' : '') . ' / ' . $blocks['unsuccessful-queries']['counter'] . '
                    </a>
                </div>
            ';

            // tidy the output
            $pattern = array(

                // remove blank lines
                "/[\r\n]+\s*[\r\n]+/",

                // remove spaces used for indentation
                "/^\s+/m",

            );

            $replacement = array(

                "\r\n",
                "",

            );

            // perform the tidying
            $output = preg_replace($pattern, $replacement, $output);

            // use the provided resource path for stylesheets and javascript (if any)
            if (!is_null($this->resource_path))

                $path = rtrim(preg_replace('/\\\/', '/', '//' . $_SERVER['SERVER_NAME'] . ($_SERVER['SERVER_PORT'] != '80' ? ':' . $_SERVER['SERVER_PORT'] : '') . DIRECTORY_SEPARATOR . $this->resource_path), '/');

            // if path not provided, determine the path automatically
            else 

                // this is the url that will be used for automatically including
                // the CSS and the JavaScript files
                $path = rtrim(preg_replace('/\\\/', '/', '//' . $_SERVER['SERVER_NAME'] . ($_SERVER['SERVER_PORT'] != '80' ? ':' . $_SERVER['SERVER_PORT'] : '') . DIRECTORY_SEPARATOR . substr(dirname(__FILE__), strlen($_SERVER['DOCUMENT_ROOT']))), '/');

            // link the required javascript
            $output = '<script type="text/javascript" src="' . $path . '/public/javascript/database.src.js"></script>' . $output;

            // link the required css file
            $output = '<link rel="stylesheet" href="' . $path . '/public/css/database.css" type="text/css">' . $output;

            // if output is to be returned rather than printed to the screen
            if ($return) return $output;

            // show generated output
            echo $output;

        }

    }

    /**
     *  Ends a transaction which means that if all the queries since {@link transaction_start()} are valid, it writes
     *  the data to the database, but if any of the queries had an error, ignore all queries and treat them as if they
     *  never happened.
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
     *  @return boolean                     Returns TRUE on success or FALSE on error.
     */
    function transaction_complete()
    {

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

            // if query was successful
            if ($this->last_result) return true;

            // if query was unsuccessful
            return false;

        }

        // if no transaction was in progress
        // save debug information
        $this->_log('unsuccessful-queries', array(

            'query' =>  $sql,
            'error' =>  $this->language['no_transaction_in_progress'],

        ), false);

        return false;

    }

    /**
     *  Starts the transaction system.
     *
     *  Transactions work only with databases that support transaction-safe table types. In MySQL, these are InnoDB or
     *  BDB table types. Working with MyISAM tables will not raise any errors but statements will be executed
     *  automatically as soon as they are called (just like if there was no transaction).
     *
     *  If you are not familiar with transactions, have a look at {@link http://dev.mysql.com/doc/refman/5.0/en/commit.html}
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
     *                                      all queries are valid.
     *
     *                                      Default is FALSE.
     *
     *  @since  2.1
     *
     *  @return boolean                     Returns TRUE on success or FALSE on error.
     */
    function transaction_start($test_only = false)
    {

        $sql = 'START TRANSACTION';

        // if a transaction is not in progress
        if ($this->transaction_status === 0) {

            // set flag so that the query method will know that a transaction is in progress
            $this->transaction_status = ($test_only ? 3 : 1);

            // try to start transaction
            $this->query($sql);

            // returns TRUE, if query was executed successfully
            if ($this->last_result) return true;

            return false;

        }

        // save debug information
        $this->_log('unsuccessful-queries', array(

            'query' =>  $sql,
            'error' =>  $this->language['transaction_in_progress'],

        ), false);

        return false;

    }

    /**
     *  Checks whether a table exists in the current database.
     *
     *  <code>
     *  // checks whether table "users" exists
     *  table_exists('users');
     *  </code>
     *
     *  @param  string  $table      The name of the table to check if it exists in the database.
     *
     *  @since  2.3
     *
     *  @return boolean             Returns TRUE if table given as argument exists in the database or FALSE if not.
     *
     *
     */
    function table_exists($table)
    {

        // check if table exists in the database
        return $this->fetch_assoc($this->query('SHOW TABLES LIKE ?', array($table))) !== false ? true : false;

    }

    /**
     *  Shorthand for truncating tables.
     *
     *  <i>Truncating a table is quicker then deleting all rows, as stated in the MySQL documentation at
     *  {@link http://dev.mysql.com/doc/refman/4.1/en/truncate-table.html}. Truncating a table also resets the value of
     *  the AUTO INCREMENT column.</i>
     *
     *  <code>
     *  $db->truncate('table');
     *  </code>
     *
     *  @param  string  $table          Table to truncate.
     *
     *  @param  boolean $highlight      (Optional) If set to TRUE the debugging console will be opened automatically
     *                                  and the query will be shown - really useful for quick and easy debugging.
     *
     *                                  Default is FALSE.
     *
     *  @since  1.0.9
     *
     *  @return boolean                 Returns TRUE on success of FALSE on error.
     */
    function truncate($table, $highlight = false)
    {

        // run the query
        $this->query('

            TRUNCATE
                `' . $table . '`'

        , '', false, false, $highlight);

        // returns TRUE, if query was executed successfully
        if ($this->last_result) return true;

        return false;

    }

    /**
     *  Shorthand for UPDATE queries.
     *
     *  When using this method column names will be enclosed in grave accents " ` " (thus, allowing seamless usage of
     *  reserved words as column names) and values will be automatically {@link escape()}d in order to prevent SQL injections.
     *
     *  After an update check {@link affected_rows} to find out how many rows were affected.
     *
     *  <code>
     *  $db->update(
     *      'table',
     *      array(
     *          'column1'   =>  'value1',
     *          'column2'   =>  'value2',
     *      ),
     *      'criteria = ?',
     *      array($criteria)
     *  );
     *  </code>
     *
     *  @param  string  $table          Table in which to update.
     *
     *  @param  array   $columns        An associative array where the array's keys represent the columns names and the
     *                                  array's values represent the values to be inserted in each respective column.
     *
     *                                  Column names will be enclosed in grave accents " ` " (thus, allowing seamless
     *                                  usage of reserved words as column names) and values will be automatically
     *                                  {@link escape()}d.
     *
     *                                  A special value may also be used for when a column's value needs to be
     *                                  incremented or decremented. In this case, use <i>INC(value)</i> where <i>value</i>
     *                                  is the value to increase the column's value with. Use <i>INC(-value)</i> to decrease
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
     *                                  $db->query('UPDATE table SET column = colum + ? WHERE criteria = ?', array($value, $criteria));
     *                                  </code>
     *
     *  @param  string  $where          (Optional) A MySQL WHERE clause (without the WHERE keyword).
     *
     *                                  Default is "" (an empty string).
     *
     *  @param  array   $replacements   (Optional) An array with as many items as the total parameter markers ("?", question
     *                                  marks) in <i>$column</i>, <i>$table</i> and <i>$where</i>. Each item will be
     *                                  automatically {@link escape()}-ed and will replace the corresponding "?".
     *
     *                                  Default is "" (an empty string).
     *
     *  @param  boolean $highlight      (Optional) If set to TRUE the debugging console will be opened automatically
     *                                  and the query will be shown - really useful for quick and easy debugging.
     *
     *                                  Default is FALSE.
     *
     *  @since  1.0.9
     *
     *  @return boolean                 Returns TRUE on success of FALSE on error
     */
    function update($table, $columns, $where = '', $replacements = '', $highlight = false)
    {

        // if $replacements is specified but it's not an array
        if ($replacements != '' && !is_array($replacements)) {

            // save debug information
            $this->_log('unsuccessful-queries',  array(

                'query' =>  '',
                'error' =>  $this->language['warning_replacements_not_array']

            ));

            return false;

        }

        // generate the SQL from the $columns array
        $cols = $this->_build_sql($columns);

        // run the query
        $this->query('

            UPDATE
                `' . $table . '`
            SET
                ' . $cols .

            ($where != '' ? ' WHERE ' . $where : '')

        , array_merge(array_values($columns), $replacements == '' ? array() : $replacements), false, false, $highlight);

        // returns TRUE if query was executed successfully
        if ($this->last_result) return true;

        return false;

    }

    /**
     *  Writes debug information to a <i>log.txt</i> log file at {@link log_path} <i>if</i> {@link debug} is TRUE and the
     *  viewer's IP address is in the {@link debugger_ip} array (or <i>$debugger_ip</i> is an empty array).
     *
     *  <i>This method must be called after all the queries in a script!</i>
     *
     *  <i>Make sure you're calling it BEFORE {@link show_debug_console()} so that you can see in the debugging console if
     *  writing to the log file was successful or not.</i>
     *
     *  Note that by default all logs are written to a single file. Refer to the method's arguments for grouping logs by
     *  days and/or hours.
     *
     *  @since  1.1.0
     * 
     *  @param boolean  $daily      Should logs be grouped by days?
     *
     *                              Log files will have their name in the form of "log_ymd.txt", where "y", "m" and "d"
     *                              represent two digit values for year, month and day, respectively.
     *
     *                              Default is FALSE.
     *
     *  @param boolean  $hourly     Should logs be also groupped by hours?
     * 
     *                              Log files will have their name in the form of "log_ymd_h.txt", where "y", "m" and "d"
     *                              represent two digit values for year, month and day, respectively, while "h" represents
     *                              the two digit value for hour.
     *
     *                              Note that if this argument is set to TRUE, the $daily argument will be automatically
     *                              set to TRUE.
     *
     *                              Default is FALSE.
     *
     *  @return void
     */
    function write_log($daily = false, $hourly = false)
    {

          // if
        if (

            // debug is enabled AND
            $this->debug &&

            // debugger_ip is an array AND
            is_array($this->debugger_ip) &&

                (

                    // debugger_ip is an empty array OR
                    empty($this->debugger_ip) ||

                    // the viewer's IP is the allowed array
                    in_array($_SERVER['REMOTE_ADDR'], $this->debugger_ip)

                )

        ) {
		
            // daily/hourly file?
            $file_name = 'log';

            // if $hourly is set to TRUE, $daily *must* be true
            if ($hourly) $daily = true;

            // are we writing daily logs?
            // (suppress "strict standards" warning for PHP 5.4+)
            $file_name .= ($daily ? '_' . @date('ymd') : '');

            // are we writing hourly logs?
            // (suppress "strict standards" warning for PHP 5.4+)
            $file_name .= ($hourly ? '_' . @date('H') : '');

            // log file's extension
            $file_name .= '.txt';
	
            // tries to create/open the 'log.txt' file
            if ($handle = @fopen(rtrim($this->log_path, '/') . '/' . $file_name, 'w')) {

                // iterate through the debug information
                foreach ($this->debug_info['successful-queries'] as $debug_info) {

                    // the following regular expressions strips newlines and indenting from the MySQL string, so that
                    // we have it in a single line
                    $pattern = array(
                        "/\s*(.*)\n|\r/",
                        "/\n|\r/"
                    );
                    $replace = array(
                        ' $1',
                        ' '
                    );

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
                    );

                    // determine the longest label (for propper indenting)
                    $longest_label_length = 0;

                    // iterate through the labels
                    foreach ($labels as $label)

                        // if the label is longer than the longest label so far
                        if (strlen($label) > $longest_label_length)

                            // this is the longes label, so far
                            // we use utf8_decode so that strlen counts correctly with accented chars
                            $longest_label_length = strlen(utf8_decode($label));

                    // write to log file
                    fwrite($handle, print_r(

                        // top border
                        str_pad('', $longest_label_length + 4, '#', STR_PAD_RIGHT) . "\n" .

                        // date
                        '# ' . $labels[0] . ':' . str_pad('', $longest_label_length - strlen(utf8_decode($labels[0])), ' ', STR_PAD_RIGHT) . '#: ' . @date('Y M d H:i:s') . "\n" .

                        // query
                        '# ' . $labels[1] . ':' . str_pad('', $longest_label_length - strlen(utf8_decode($labels[1])), ' ', STR_PAD_RIGHT) . '#: ' . trim(preg_replace($pattern, $replace, $debug_info['query'])) . "\n" .

                        // if execution time is available
                        // (is not available for unsuccessful queries)
                        (isset($debug_info['execution_time']) ?

                            '# ' . $labels[2] . ':' . str_pad('', $longest_label_length - strlen(utf8_decode($labels[2])), ' ', STR_PAD_RIGHT) . '#: ' .  $this->_fix_pow($debug_info['execution_time']) . ' ' . $this->language['miliseconds'] . "\n"
                             : ''

                        ) .

                        // if there is a warning message
                        (isset($debug_info['warning']) && $debug_info['warning'] != '' ?

                            '# ' . $labels[3] . ':' . str_pad('', $longest_label_length - strlen(utf8_decode($labels[3])), ' ', STR_PAD_RIGHT) . '#: ' . strip_tags($debug_info['warning']) . "\n"
                            : ''

                        ) .

                        // if there is an error message
                        (isset($debug_info['error']) && $debug_info['error'] != '' ?

                            '# ' . $labels[4] . ':' . str_pad('', $longest_label_length - strlen(utf8_decode($labels[4])), ' ', STR_PAD_RIGHT) . '#: ' . $debug_info['error'] . "\n"
                            : ''

                        ) .

                        // if not an action query, show whether the query was returned from the cache or was executed
                        ($debug_info['affected_rows'] === false ?

                            '# ' . $labels[5] . ':' . str_pad('', $longest_label_length - strlen(utf8_decode($labels[5])), ' ', STR_PAD_RIGHT) .  '#: ' .

                            (isset($debug_info['from_cache']) && $debug_info['from_cache'] === true  ?

                                $labels[6] :
                                $labels[7]

                            ) . "\n"

                            : ''

                        ) .

                        '# ' . $labels[8] . ':' . str_pad('', $longest_label_length - strlen(utf8_decode($labels[8])), ' ', STR_PAD_RIGHT) . '#:' . "\n"

                    , true));

                    // write full backtrace info
                    foreach ($debug_info['backtrace'] as $backtrace)

                        fwrite($handle, print_r(

                            '# ' . str_pad('', ($longest_label_length + 1), ' ', STR_PAD_RIGHT) . '#' . "\n" .
                            '# ' . $labels[9] . ':' . str_pad('', $longest_label_length - strlen(utf8_decode($labels[9])), ' ', STR_PAD_RIGHT) . '#: ' . $backtrace[$this->language['file']] . "\n" .
                            '# ' . $labels[10] . ':' . str_pad('', $longest_label_length - strlen(utf8_decode($labels[10])), ' ', STR_PAD_RIGHT) . '#: ' . $backtrace[$this->language['line']] . "\n" .
                            '# ' . $labels[11] . ':' . str_pad('', $longest_label_length - strlen(utf8_decode($labels[11])), ' ', STR_PAD_RIGHT) . '#: ' . $backtrace[$this->language['function']] . "\n"

                        , true));

                    // finish writing to the log file by adding a bottom border
                    fwrite($handle, str_pad('', $longest_label_length + 4, '#', STR_PAD_RIGHT) . "\n\n");

                }

                // close log file
                fclose($handle);

            // if log file could not be created/opened
            } else

                // save debug information
                $this->_log('errors', array(

                    'message'   =>  $this->language['could_not_write_to_log'],

                ));

        }

    }

    /**
     *  Given an indexed array or a string with comma separated values where the values represent column names, this
     *  method will enclose column names in grave accents " ` " (thus, allowing seamless usage of reserved words as column
     *  names) and automatically {@link escape()} value.
     *
     *  @access private
     */
    private function _build_columns($columns)
    {
        $sql = '';

        // if the argument is not an array
        if (!is_array($columns))

            // transform it to an array
            $columns = explode(',', $columns);

        // loop through each column
        foreach($columns as &$col)

            // wrap in grave accents " ` "
            $col = '`' . trim(trim($col), '`') . '`';

        // create string from array
        $sql = join(', ', $columns);

        return $sql;

    }
    
    /**
     *  Given an associative array where the array's keys represent column names and the array's values represent the
     *  values to be associated with each respective column, this method will enclose column names in grave accents " ` "
     *  (thus, allowing seamless usage of reserved words as column names) and automatically {@link escape()} value.
     *
     *  It will also take care of particular cases where the INC keyword is used in the values, where the INC keyword is
     *  used with a parameter marker ("?", question mark) or where a value is a single question mark - which throws an
     *  error message.
     *
     *  This method may also alter the original variable given as argument, as it is passed by reference!
     *
     *  @access private
     */
    private function _build_sql(&$columns)
    {

        $sql = '';

        // start creating the SQL string and enclose field names in `
        foreach ($columns as $column_name => $value)

            // if value is just a parameter marker ("?", question mark)
            if (trim($value) == '?')

                // throw an error
                $this->_log('unsuccessful-queries',  array(

                    'error' =>  sprintf($this->language['cannot_use_parameter_marker'], print_r($columns, true)),

                ));

            // if special INC() keyword is used
            elseif (preg_match('/INC\((\-{1})?(.*?)\)/i', $value, $matches) > 0) {

                // translate to SQL
                $sql .= ($sql != '' ? ', ' : '') . '`' . $column_name . '` = `' . $column_name . '` ' . ($matches[1] == '-' ? '-' : '+') . ' ?';

                // if INC() contains an actual value and not a parameter marker ("?", question mark)
                // add the actual value to the array with the replacement values
                if ($matches[2] != '?') $columns[$column_name] = $matches[2];

                // if we have a parameter marker ("?", question mark) instead of a value, it means the replacement value
                // is already in the array with the replacement values, and that we don't need it here anymore
                else unset($columns[$column_name]);

            // the usual way
            } else $sql .= ($sql != '' ? ', ' : '') . '`' . $column_name . '` = ?';

        // return the built sql
        return $sql;

    }

    /**
     *  Checks if the connection to the MySQL server has been previously established by the connect() method.
     *
     *  @access private
     */
    private function _connected()
    {

        // if there's no connection to a MySQL database
        if (!$this->connection) {

            $this->connection = @mysqli_connect(
                $this->credentials['host'],
                $this->credentials['user'],
                $this->credentials['password'],
                $this->credentials['database']
            );

            // tries to connect to the MySQL database
            if (mysqli_connect_errno()) {

                // if connection could not be established
                // save debug information
                $this->_log('errors', array(

                    'message'   =>  $this->language['could_not_connect_to_database'],
                    'error'     =>  mysqli_connect_error(),

                ));

                // return FALSE
                return false;

            }

            // if caching is to be done to a memcache server and we don't yet have a connection
            if (!$this->memcache && $this->memcache_host !== false && $this->memcache_port !== false) {

                // if memcache extension is installed
                if (class_exists('Memcache')) {

                    // instance to the memcache object
                    $memcache = new Memcache();

                    // try to connect to the memcache server
                    if (!$memcache->connect($this->memcache_host, $this->memcache_port))

                        // if connection could not be established, save debug information
                        $this->_log('errors', array('message'   =>  $this->language['could_not_connect_to_memcache_server']));

                    else $this->memcache = $memcache;

                // if memcache extension is not installed
                } else

                    // if connection could not be established, save debug information
                    $this->_log('errors', array('message'   =>  $this->language['memcache_extension_not_installed']));

            }

        }

        // return TRUE if there is no error
        return true;

    }

    /**
     *  PHP's microtime() will return elapsed time as something like 9.79900360107E-5 when the elapsed time is too short.
     *
     *  This function takes care of that and returns the number in the human readable format.
     *
     *  @access private
     */
    private function _fix_pow($value)
    {

        // use value as literal
        $value = (string)$value;

        // if the power is present in the value
        if (preg_match('/E\-([0-9]+)$/', $value, $matches) > 0)

            // convert to human readable format
            $value = '0.' . str_repeat('0', $matches[1] - 1) . preg_replace('/\./', '', substr($value, 0, -strlen($matches[0])));

        // return the value
        return number_format($value * 1000, 3);

    }

    /**
     *  Checks is a value is a valid result set obtained from a query against the database
     *
     *  @access private
     */
    private function _is_result($value)
    {

        // check whether a value is a valid result set obtained from a query against the database
        return is_object($value) && strtolower(get_class($value)) == 'mysqli_result';

    }

    /**
     *  Handles saving of debug information and halts the execution of the script on fatal error or if the
     *  {@link halt_on_errors} property is set to TRUE
     *
     *  @access private
     */
    private function _log($category, $data, $fatal = true)
    {

        // if debugging is on
        if ($this->debug) {

            // if category is different than "warnings"
            // (warnings are generated internally)
            if ($category != 'warnings') {

                // get backtrace information
                $backtrace_data = debug_backtrace();

                // unset first entry as it refers to the call to this particular method
                unset($backtrace_data[0]);

                $data['backtrace'] = array();

                // iterate through the backtrace information
                foreach ($backtrace_data as $backtrace)

                    // extract needed information
                    $data['backtrace'][] = array(

                        $this->language['file']     =>  (isset($backtrace['file']) ? $backtrace['file'] : ''),
                        $this->language['function'] =>  $backtrace['function'] . '()',
                        $this->language['line']     =>  (isset($backtrace['line']) ? $backtrace['line'] : ''),

                    );

            }

            // saves debug information
            $this->debug_info[$category][] = $data;

            // if the saved debug info is about a fatal error
            // and execution is to be stopped on fatal errors
            if ($fatal && $this->halt_on_errors) {

                // show the debugging window
                $this->show_debug_console();

                // halt execution
                die();

            }

        // if there are any unsuccessful queries or other errors and no debugging
        } elseif (($category == 'unsuccessful-queries' || $category == 'errors') && !$this->debug) {

            // get backtrace information
            $backtraceInfo = debug_backtrace();

            // log error to the system logger
            error_log('Zebra_Database (MySQL): ' . (isset($data['error']) ? $data['error'] : $data['message']) . print_r(' in ' . $backtraceInfo[1]['file'] . ' on line ' . $backtraceInfo[1]['line'], true));

        }

    }

    /**
     *  Frees the memory associated with the last result.
     *
     *  @since 2.8
     *
     *  @access private
     */
    function __destruct()
    {

        // frees the memory associated with the last result
        @mysqli_free_result($this->last_result);

    }

}

?>
