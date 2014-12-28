<?php

    /**
    * Russian language file for the Zebra_Database class, by Andrew Rumm <info@rayz.ru>.
    *
    * @version      1.0
    * @author       Andrew Rumm <info@rayz.ru>
    *
    */

    $this->language = array(

        'affected_rows'                         => 'затронутые строки',
        'backtrace'                             => 'Обратная трассировка',
        'cache_path_not_writable'               => 'Невозможно создать кеш запроса. Убедитесь в доступности и возможности записи пути.',
        'cannot_use_parameter_marker'           => 'Невозможно использовать маркер параметра ("?", вопросительный знак) в <br><br><pre>%s</pre><br>Используйте исходное значение, которое будет автоматически экранировано.',
        'close_all'                             => 'закрыть все',
        'could_not_connect_to_database'         => 'Невозможно приконнектится к базе',
        'could_not_connect_to_memcache_server'  => 'Невозможно приконнектится к серверу memcache',
        'could_not_seek'                        => 'невозможно перейти на указанную строку',
        'could_not_select_database'             => 'Невозможно выбрать базу',
        'could_not_write_to_log'                => 'Невозможно записать в лог файл. Убедитесь в его доступности.',
        'date'                                  => 'Дата',
        'email_subject'                         => 'Медленный запрос в %s!',
        'email_content'                         => "Следующий запрос превысил нормальное время выполнения в %s секунд, был выполнен за %s секунд: \n\n %s",
        'error'                                 => 'Ошибка',
        'errors'                                => 'ошибки',
        'execution_time'                        => 'время выполнения',
        'explain'                               => 'explain',
        'data_not_an_array'                     => 'Третий параметр <em>insert_bulk()</em> должен быть массивом массивов..',
        'file'                                  => 'файл',
        'file_could_not_be_opened'              => 'Невозможно открыть файл',
        'from_cache'                            => 'из кеша',
        'function'                              => 'функция',
        'globals'                               => 'глобальные переменные',
        'line'                                  => 'строка',
        'memcache_extension_not_installed'      => 'расширение Memcache не найдено.<br><span>
                                                    Для использования memcache в качестве кеширующего средства, PHP должен быть не ниже 4.3.3, скомпилирован
                                                    с <a href="http://pecl.php.net/package/memcache">memcached</a>, и настроен с опцией <em>--with-zlib[=DIR]</em>.</span>',
        'miliseconds'                           => 'мс',
        'mysql_error'                           => 'Ошибка MySQL',
        'no'                                    => 'Нет',
        'no_active_session'                     => 'Вы выбрали кеширование результатов запросоа в сессии, но активных сессий не найдено. Перед использованием библиотеки используйте <a href="http://php.net/manual/en/function.session-start.php" target="_blank">session_start()</a>!',
        'no_transaction_in_progress'            => 'Нет активных транзакций.',
        'not_a_valid_resource'                  => 'Недопустимый ресурс (убедитесь что вы указали ресурс в качестве аргумента для fetch_assoc()/fetch_obj() если вы выполняете запрос внутри цикла)',
        'optimization_needed'                   => '<strong>ВНИМАНИЕ</strong>: Несколько первых результатов текущего запроса такие же как у <strong>%s</strong> других запросов!',
        'returned_rows'                         => 'строк возвращено',
        'successful_queries'                    => 'успешных запросов',
        'to_top'                                => 'в начало',
        'transaction_in_progress'               => 'Транзакция не может быть запущена, пока выполняется другая.',
        'unsuccessful_queries'                  => 'неуспешных запросов',
        'warning_charset'                       => 'Значения по умолчанию для charset и collections небыли установлены. Вызовите set_charset() после подключения к базе.',
        'warning_memcache'                      => 'Расширение "memcache" доступно на вашем сервере, рассмотрите возможность использования memcache для кеширования результатов ваших запросов.<br>Подробности в <a href="http://stefangabos.ro/wp-content/docs/Zebra_Database/Zebra_Database/Zebra_Database.html#var$caching_method">документации</a>.',
        'warning_replacements_not_array'        => '<em>$replacements</em> должен быть массивом значений',
        'warning_replacements_wrong_number'     => 'количество значений для замены отличается от количества значений в массиве <em>$replacements</em>',
        'warning_wait_timeout'                  => 'Значение переменной  MySQL <em>wait_timeout</em> установлено в %s. Переменная <em>wait_timeout</em> обозначает время в секундах, которое MySQL будет ожидать перед тем как отключить idle подключение. После выполнения скрипта, подключение MySQL не уничтожается, а отправляется в состояние idle для будущего использования тем же пользователем, требующим подключение к базе (a very common scenario is when users navigate through the pages of a website). The default value of <em>wait_timeout</em> is 28800 seconds, or 8 hours. If you have lots of visitors this can lead to a <em><a href="http://dev.mysql.com/doc/refman/5.5/en/too-many-connections.html" target="_blank">Too many connections</a></em> error, as eventualy there will be times when no <a href="http://dev.mysql.com/doc/refman/5.5/en/server-system-variables.html#sysvar_max_connections" target="_blank">free connections</a> will be available. The recommended value is 300 seconds (5 minutes).',
        'warning'                               => 'Предупреждение',
        'warnings'                              => 'предупреждения',
        'yes'                                   => 'Да',

    );

?>