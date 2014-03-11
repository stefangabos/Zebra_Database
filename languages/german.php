<?php

    /**
    * German language file for the Zebra_Database class, by Stefan Gabos <contact@stefangabos.ro>.
    *
    * @version      1.0
    * @author       etb09
    *
    */

    $this->language = array(

        'affected_rows'                         => 'Betroffene Zeilen',
        'backtrace'                             => 'Ablaufverfolgung',
        'cache_path_not_writable'               => 'Konnte die Abfrage nicht cachen. Stellen Sie sicher das der Pfad existiert und beschreibbar ist.',
        'cannot_use_parameter_marker'           => 'Der Parameter Marker ("?", question mark) kann in <br><br><pre>%s</pre> nicht genutzt werden.<br>Nutzen Sie den tatsächlichen Wert. Er wird automatisch escaped.',
        'close_all'                             => 'Alle Einträge ausblenden',
        'could_not_connect_to_database'         => 'Keine Verbindung zur Datenbank',
        'could_not_connect_to_memcache_server'  => 'Keine Verbindung zum Memcache Server',
        'could_not_seek'                        => 'Konnte die definierte Zeile nicht suchen',
        'could_not_select_database'             => 'Konnte die Datenbank nicht auswählen',
        'could_not_write_to_log'                => 'Konnte nicht in die Log-Datei schreiben. Stellen Sie sicher, dass das Ordner existiert und beschreibbar ist.',
        'date'                                  => 'Datum',
        'email_subject'                         => 'Langsame Abfrage in %s!',
        'email_content'                         => "Die nachfolgende Abfrage hat die normale Ausführungszeit von %s Sekunden überschritten. Sie dauerte %s Sekunden: \n\n %s",
        'error'                                 => 'Fehler',
        'errors'                                => 'Fehler',
        'execution_time'                        => 'Ausführungszeit',
        'explain'                               => 'Abfrage erklären',
        'data_not_an_array'                     => 'Das dritte Argument von <em>insert_bulk()</em> muss ein Array von Arrays sein.',
        'file'                                  => 'Datei',
        'file_could_not_be_opened'              => 'Konnte/Kann die Datei nicht öffnen',
        'from_cache'                            => 'aus dem Cache',
        'function'                              => 'Funktion',
        'globals'                               => 'globale Variablen',
        'line'                                  => 'Zeile',
        'memcache_extension_not_installed'      => 'Die Memcache Erweiterung wurde nicht gefunden.<br><span>
                                                    Um memcache als Caching Methode zu nutzen ist PHP Version 4.3.3+ notwendig, PHP muss mit der
                                                    <a href="http://pecl.php.net/package/memcache">memcached</a> Erweiterung kompiliert werden und muss mit 
                                                    <em>--with-zlib[=DIR]</em> konfiguriert werden.</span>',
        'miliseconds'                           => 'ms',
        'mysql_error'                           => 'MySQL Fehler',
        'no'                                    => 'Nein',
        'no_active_session'                     => 'You have chosen to cache query results in sessions but there are no active session. Call <a href="http://php.net/manual/en/function.session-start.php" target="_blank">session_start()</a> before using the library!',
        'no_transaction_in_progress'            => 'Keine Transaktion(en) aktiv.',
        'not_a_valid_resource'                  => 'Keine gültige Ressource (Stellen Sie sicher, dass sie eine Ressource als Argument für fetch_assoc()/fetch_obj() nutzen, sofern sie die Abfrage in einer Schleife/Loop ausführen.)',
        'optimization_needed'                   => '<strong>Warnung</strong>: Die ersten Ergebnisse dieser Abfrage sind identisch mit denen von <strong>%s</strong> anderen Abfragen!',
        'returned_rows'                         => 'Abgefragte Zeilen',
        'successful_queries'                    => 'erfolgreiche Abfragen',
        'to_top'                                => 'nach oben',
        'transaction_in_progress'               => 'Die Transaktion konnte nicht durchgeführt werden, weil noch eine andere Transaktion in Arbeit ist.',
        'unsuccessful_queries'                  => 'Fehlerhafte Abfragen',
        'warning_charset'                       => 'Standard Charset und Collection wurde nicht gesetzt. Bitte rufen Sie set_charset() nach dem Verbindungsaufbau auf.',
        'warning_memcache'                      => 'Die "memcache" Erweiterung ist auf diesem Server nicht verfügbar. überlegen Sie, memcache fürs Caching von Ergebnissen zu nutzen.<br>Weitere Informationen in <a href="http://stefangabos.ro/wp-content/docs/Zebra_Database/Zebra_Database/Zebra_Database.html#var$caching_method">der Dokumentation</a>.',
        'warning_replacements_not_array'        => '<em>$replacements</em> muss ein Array mit Werten enthalten.',
        'warning_replacements_wrong_number'     => 'Die Anzahl der Ersetzungen stimmt nicht mit der Anzahl der Elemente im <em>$replacements</em> Array überein.',
        'warning_wait_timeout'                  => 'Die MySQL-Variable <em>wait_timeout</em> hat den Wert %s. Die Variable <em>wait_timeout</em> repräsentiert die Zeit in Sekunden, die MySQL wartet, bevor eine inaktive Verbindung beendet wird. Nach Beendigung der Ausführung wird die MySQL Verbindung nicht beendet sondern in einen inaktiv-Status gesetzt und erneut benutzt, wenn derselbe Nutzer wieder eine Datenbankverbindung benötigt (ein gängiges Beispiel ist die Navigation durch die einzelnen Seiten einer Webseite). Der Standardwert für <em>wait_timeout</em> ist 28800 Sekunden oder 8 Stunden. Sofern Sie viele Besucher haben, kann das zu <em><a href="http://dev.mysql.com/doc/refman/5.5/en/too-many-connections.html" target="_blank">Too many connections</a></em>-Fehlern führen, weil es möglicherweise Zeiten gibt in denen keine freien Verbindungen (<a href="http://dev.mysql.com/doc/refman/5.5/de/server-system-variables.html#sysvar_max_connections" target="_blank">free connections</a>) mehr verfügbar sind. Der empfohlene Wert ist 300 Sekunden (5 Minuten).',
        'warning'                               => 'Warnung',
        'warnings'                              => 'Warnungen',
        'yes'                                   => 'Ja',

    );

?>