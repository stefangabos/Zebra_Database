<?php

    /**
    * English language file for the Zebra_Database class, by Stefan Gabos <contact@stefangabos.ro>.
    *
    * @version      1.0.1
    * @author       Stefan Gabos <contact@stefangabos.ro>
    *
    */

    $this->language = array(

        'affected_rows'                         => 'affected rows',
        'backtrace'                             => 'backtrace',
        'cache_path_not_writable'               => 'Could not cache query. Make sure path exists and is writable.',
        'cannot_use_parameter_marker'           => 'Cannot use a parameter marker ("?", question mark) in <br><br><pre>%s</pre><br>Use an actual value instead as it will be automatically escaped.',
        'close_all'                             => 'close all',
        'could_not_connect_to_database'         => 'Could not connect to database',
        'could_not_connect_to_memcache_server'  => 'Could not connect to the memcache server',
        'could_not_connect_to_redis_server'     => 'Could not connect to the redis server',
        'could_not_seek'                        => 'could not seek to specified row',
        'could_not_select_database'             => 'Could not select database',
        'could_not_write_to_log'                => 'Could not write to log file. Make sure the folder exists and is writable',
        'date'                                  => 'Date',
        'email_subject'                         => 'Slow query on %s!',
        'email_content'                         => "The following query exceeded normal running time of %s seconds by running %s seconds: \n\n %s",
        'error'                                 => 'Error',
        'errors'                                => 'errors',
        'execution_time'                        => 'duration',
        'explain'                               => 'explain',
        'data_not_an_array'                     => 'The third argument of <em>insert_bulk()</em> needs to be an array of arrays.',
        'file'                                  => 'File',
        'file_could_not_be_opened'              => 'Could not open file',
        'from_cache'                            => 'from cache',
        'function'                              => 'Function',
        'globals'                               => 'globals',
        'invalid_option'                        => '%s is an invalid connection option',
        'line'                                  => 'Line',
        'memcache_extension_not_installed'      => 'Memcache extension not found.<br><span>
                                                    For using memcache as caching method, PHP version must be 4.3.3+, must be compiled with the
                                                    <a href="https://pecl.php.net/package/memcache">memcached</a> extension, and needs to be
                                                    configured with <em>--with-zlib[=DIR]</em>.</span>',
        'redis_extension_not_installed'         => 'Redis extension not found.<br><span>
                                                    For using redis as caching method, PHP version must be 4.3.3+, must be compiled with the
                                                    <a href="https://pecl.php.net/package/redis">reids</a> extension, and needs to be
                                                    configured with <em>--with-zlib[=DIR]</em>.</span>',
        'milliseconds'                          => 'ms',
        'mysql_error'                           => 'MySQL error',
        'no'                                    => 'No',
        'no_active_session'                     => 'You have chosen to cache query results in sessions but there are no active session. Call <a href="https://php.net/manual/en/function.session-start.php" target="_blank">session_start()</a> before using the library!',
        'no_transaction_in_progress'            => 'No transaction in progress.',
        'not_a_valid_resource'                  => 'Not a valid resource (make sure you specify a resource as argument for fetch_assoc()/fetch_obj() if you are executing a query inside the loop)',
        'optimization_needed'                   => '<strong>WARNING</strong>: The first few results returned by this query are the same as returned by <strong>%s</strong> other queries!',
        'options_before_connect'                => 'The "option" method must be called before connecting to a MySQL server',
        'returned_rows'                         => 'returned rows',
        'seconds'                               => 'seconds',
        'successful_queries'                    => 'successful queries',
        'to_top'                                => 'to the top',
        'transaction_in_progress'               => 'Transaction could not be started as another transaction is in progress.',
        'unbuffered'                            => 'unbuffered',
        'unbuffered_queries_cannot_be_cached'   => 'Unbuffered queries cannot be cached',
        'unsuccessful_queries'                  => 'unsuccessful queries',
        'unusable_method_unbuffered_queries'    => '%s() method cannot be used with unbuffered queries',
        'warning_charset'                       => 'No default charset and collation were set. Call <a href="https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#methodset_charset">set_charset()</a> after connecting to the database.',
        'warning_memcache'                      => 'The "memcache" extension is available on your server - consider using memcache for caching query results.<br>See <a href="https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#var$caching_method">the documentation</a> for more information.',
        'warning_redis'                         => 'The "redis" extension is available on your server - consider using redis for caching query results.<br>See <a href="https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#var$caching_method">the documentation</a> for more information.',
        'warning_replacements_not_array'        => '<em>$replacements</em> must be an arrays of values',
        'warning_replacements_wrong_number'     => 'the number of items to replace is different than the number of items in the <em>$replacements</em> array',
        'warning_wait_timeout'                  => 'The value of MySQL\'s <em>wait_timeout</em> variable is set to %s. The <em>wait_timeout</em> variable represents the time, in seconds, that MySQL will wait before killing an idle connection. After a script finishes execution, the MySQL connection is not actually terminated but it is put in an idle state and is being reused if the same user requires a database connection (a very common scenario is when users navigate through the pages of a website). The default value of <em>wait_timeout</em> is 28800 seconds, or 8 hours. If you have lots of visitors this can lead to a <em><a href="https://dev.mysql.com/doc/refman/8.0/en/too-many-connections.html" target="_blank">Too many connections</a></em> error, as eventualy there will be times when no <a href="https://dev.mysql.com/doc/refman/8.0/en/server-system-variables.html#sysvar_max_connections" target="_blank">free connections</a> will be available. The recommended value is 300 seconds (5 minutes).',
        'warning'                               => 'Warning',
        'warnings'                              => 'warnings',
        'yes'                                   => 'Yes',

    );

?>
