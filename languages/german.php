<?php

    /**
    * German language file for the Zebra_Database class, by Stefan Gabos <contact@stefangabos.ro>.
    *
    * @version      2.0
    * @author       etb09
    * @author       Bernhard Morgenstern (https://github.com/bmorg)
    *
    */

    $this->language = array(

        'affected_rows'                         => 'Betroffene Zeilen',
        'backtrace'                             => 'Ablaufverfolgung',
        'cache_path_not_writable'               => 'Abfrage konnte nicht gecacht werden. Stellen Sie sicher, dass der Pfad existiert und beschreibbar ist.',
        'cannot_use_parameter_marker'           => 'Der Parameter-Marker ("?", Fragezeichen) kann nicht in <br><br><pre>%s</pre> verwendet werden.<br>Verwenden Sie den tatsächlichen Wert. Er wird automatisch escaped.',
        'close_all'                             => 'Alle Einträge ausblenden',
        'could_not_connect_to_database'         => 'Verbindung zur Datenbank konnte nicht hergestellt werden',
        'could_not_connect_to_memcache_server'  => 'Verbindung zum Memcache Server konnte nicht hergestellt werden',
        'could_not_connect_to_redis_server'     => 'Could not connect to the redis server',
        'could_not_seek'                        => 'Interner Datensatz-Zeiger konnte nicht zur angegebenen Zeile bewegt werden', // same wording as https://www.php.net/manual/de/function.mysql-data-seek.php
        'could_not_select_database'             => 'Datenbank konnte nicht ausgewählt werden',
        'could_not_write_to_log'                => 'Log-Datei konnte nicht geschrieben werden. Stellen Sie sicher, dass der Ordner existiert und beschreibbar ist',
        'date'                                  => 'Datum',
        'email_subject'                         => 'Langsame Abfrage auf %s!',
        'email_content'                         => "Die nachfolgende Abfrage hat die normale Ausführungszeit von %s Sekunden überschritten. Die Abfrage dauerte %s Sekunden: \n\n %s",
        'error'                                 => 'Fehler',
        'errors'                                => 'Fehler',
        'execution_time'                        => 'Ausführungszeit',
        'explain'                               => 'Abfrage erklären',
        'data_not_an_array'                     => 'Das dritte Argument von <em>insert_bulk()</em> muss ein Array von Arrays sein.',
        'file'                                  => 'Datei',
        'file_could_not_be_opened'              => 'Datei konnte nicht geöffnet werden',
        'from_cache'                            => 'aus dem Cache',
        'function'                              => 'Funktion',
        'globals'                               => 'Globale Variablen',
        'invalid_option'                        => 'Ungültige Verbindungsoption: %s',
        'line'                                  => 'Zeile',
        'memcache_extension_not_installed'      => 'Die Memcache-Erweiterung wurde nicht gefunden.<br><span>
                                                    Um Memcache als Caching-Methode zu verwenden ist PHP Version 4.3.3+ notwendig, PHP muss mit der
                                                    <a href="https://pecl.php.net/package/memcache">memcached</a> Erweiterung kompiliert werden und muss mit
                                                    <em>--with-zlib[=DIR]</em> konfiguriert werden.</span>',
        'redis_extension_not_installed'         => 'Redis extension not found.<br><span>
                                                    For using redis as caching method, PHP version must be 4.3.3+, must be compiled with the
                                                    <a href="https://pecl.php.net/package/redis">reids</a> extension, and needs to be
                                                    configured with <em>--with-zlib[=DIR]</em>.</span>',
        'milliseconds'                          => 'ms',
        'mysql_error'                           => 'MySQL-Fehler',
        'no'                                    => 'Nein',
        'no_active_session'                     => 'Sie haben ausgewählt, dass die Abfrageresultate in der Session zwischengespeichert (gecacht) werden sollen, aber es gibt keine aktive Session. Rufen Sie <a href="https://php.net/manual/en/function.session-start.php" target="_blank">session_start()</a> auf bevor Sie Funktionen dieser Bibliothek benutzen!',
        'no_transaction_in_progress'            => 'Keine Transaktion aktiv.',
        'not_a_valid_resource'                  => 'Keine gültige Ressource (stellen Sie sicher, dass sie eine Ressource als Argument für fetch_assoc()/fetch_obj() übergeben, falls sie die Abfrage in einer Schleife ausführen)',
        'optimization_needed'                   => '<strong>WARNUNG</strong>: Die ersten Ergebnisse dieser Abfrage sind identisch mit den Ergebnissen von <strong>%s</strong> anderen Abfragen!',
        'options_before_connect'                => 'Die Methode "option" muss aufgerufen werden bevor die Verbindung zum MySQL-Server hergestellt wird.',
        'returned_rows'                         => 'Abgefragte Zeilen',
        'seconds'                               => 'Sekunden',
        'successful_queries'                    => 'Erfolgreiche Abfragen',
        'to_top'                                => 'nach oben',
        'transaction_in_progress'               => 'Die Transaktion konnte nicht durchgeführt werden, da bereits eine andere Transaktion aktiv ist.',
        'unbuffered'                            => 'ungepuffert',
        'unbuffered_queries_cannot_be_cached'   => 'Ungepufferte Abfragen können nicht gecacht werden',
        'unsuccessful_queries'                  => 'Fehlerhafte Abfragen',
        'unusable_method_unbuffered_queries'    => 'Methode %s() kann nicht mit ungepufferten Abfragen verwendet werden',
        'warning_charset'                       => 'Standard-Zeichenkodierung und Standard-Kollation wurden nicht gesetzt. Bitte rufen Sie <a href="https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#methodset_charset">set_charset()</a> nach dem Verbindungsaufbau auf.',
        'warning_memcache'                      => 'Die "memcache"-Erweiterung ist auf diesem Server verfügbar. Memcache kann zum Cachen von Ergebnissen verwendet werden.<br>Weitere Informationen in <a href="https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#var$caching_method">der Dokumentation</a>.',
        'warning_redis'                         => 'The "redis" extension is available on your server - consider using redis for caching query results.<br>See <a href="https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#var$caching_method">the documentation</a> for more information.',
        'warning_replacements_not_array'        => '<em>$replacements</em> muss ein Array mit Werten enthalten.',
        'warning_replacements_wrong_number'     => 'Die Anzahl der Ersetzungen stimmt nicht mit der Anzahl der Elemente im <em>$replacements</em>-Array überein.',
        'warning_wait_timeout'                  => 'Die MySQL-Variable <em>wait_timeout</em> hat den Wert %s. Die Variable <em>wait_timeout</em> repräsentiert die Zeit in Sekunden, die MySQL wartet, bevor eine inaktive Verbindung beendet wird. Nach Beendigung der Ausführung wird die MySQL Verbindung nicht beendet sondern in einen Inaktiv-Status gesetzt und erneut benutzt, wenn derselbe Nutzer wieder eine Datenbankverbindung benötigt (ein gängiges Beispiel ist die Navigation durch die einzelnen Seiten einer Webseite). Der Standardwert für <em>wait_timeout</em> ist 28800 Sekunden bzw. 8 Stunden. Sofern Sie viele Besucher haben, kann dies zu <em><a href="https://dev.mysql.com/doc/refman/8.0/en/too-many-connections.html" target="_blank">Too many connections</a></em>-Fehlern führen, weil es möglicherweise Zeiten gibt, in denen keine <a href="https://dev.mysql.com/doc/refman/8.0/en/server-system-variables.html#sysvar_max_connections" target="_blank">freien Verbindungen</a> mehr verfügbar sind. Der empfohlene Wert ist 300 Sekunden (5 Minuten).',
        'warning'                               => 'Warnung',
        'warnings'                              => 'Warnungen',
        'yes'                                   => 'Ja',

    );

?>
