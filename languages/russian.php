<?php

    /**
    * Russian language file for the Zebra_Database class, by Andrew Rumm <info@rayz.ru>.
    *
    * @version      1.1
    * @author       Andrew Rumm <info@rayz.ru>
    *
    */

    $this->language = array(

        'affected_rows'                         => 'затронутые строки',
        'backtrace'                             => 'Обратная трассировка',
        'cache_path_not_writable'               => 'Невозможно создать кэш запроса. Убедитесь в доступности и возможности записи пути.',
        'cannot_use_parameter_marker'           => 'Невозможно использовать маркер параметра ("?", вопросительный знак) в <br><br><pre>%s</pre><br>Используйте исходное значение, которое будет автоматически экранировано.',
        'close_all'                             => 'закрыть все',
        'could_not_connect_to_database'         => 'Невозможно приконнектится к базе данных',
        'could_not_connect_to_memcache_server'  => 'Невозможно приконнектится к memcache',
        'could_not_connect_to_redis_server'     => 'Could not connect to the redis server',
        'could_not_seek'                        => 'невозможно перейти на указанную запись',
        'could_not_select_database'             => 'Невозможно выбрать базу данных',
        'could_not_write_to_log'                => 'Невозможно записать в лог файл. Убедитесь в его доступности',
        'date'                                  => 'Дата',
        'email_subject'                         => 'Медленный запрос в %s!',
        'email_content'                         => "Следующий запрос превысил нормальное время выполнения в %s секунд, был выполнен за %s секунд: \n\n %s",
        'error'                                 => 'Ошибка',
        'errors'                                => 'ошибки',
        'execution_time'                        => 'время выполнения',
        'explain'                               => 'Explain',
        'data_not_an_array'                     => 'Третий параметр <em>insert_bulk()</em> должен быть массивом массивов',
        'file'                                  => 'файл',
        'file_could_not_be_opened'              => 'Невозможно открыть файл',
        'from_cache'                            => 'из кэша',
        'function'                              => 'функция',
        'globals'                               => 'Глобальные переменные',
        'invalid_option'                        => 'Неверный параметр поделючения %s',
        'line'                                  => 'строка',
        'memcache_extension_not_installed'      => 'расширение Memcache не найдено.<br><span>
                                                    Для использования memcache в качестве кэширующего средства, PHP должен быть не ниже 4.3.3, скомпилирован
                                                    с <a href="https://pecl.php.net/package/memcache">memcached</a>, и настроен с опцией <em>--with-zlib[=DIR]</em>.</span>',
        'redis_extension_not_installed'         => 'Redis extension not found.<br><span>
                                                    For using redis as caching method, PHP version must be 4.3.3+, must be compiled with the
                                                    <a href="https://pecl.php.net/package/redis">reids</a> extension, and needs to be
                                                    configured with <em>--with-zlib[=DIR]</em>.</span>',
        'milliseconds'                          => 'мс.',
        'mysql_error'                           => 'Ошибка MySQL',
        'no'                                    => 'Нет',
        'no_active_session'                     => 'Вы выбрали кэширование результатов запросоа в сессии, но активных сессий не найдено. Перед использованием библиотеки используйте <a href="https://php.net/manual/en/function.session-start.php" target="_blank">session_start()</a>!',
        'no_transaction_in_progress'            => 'Нет активных транзакций.',
        'not_a_valid_resource'                  => 'Недопустимый ресурс (убедитесь что вы указали ресурс в качестве аргумента для fetch_assoc()/fetch_obj() если вы выполняете запрос внутри цикла)',
        'optimization_needed'                   => '<strong>ВНИМАНИЕ</strong>: Несколько первых результатов текущего запроса такие же как у <strong>%s</strong> других запросов!',
        'options_before_connect'                => 'The "option" method must be called before connecting to a MySQL server',
        'returned_rows'                         => 'строк возвращено',
        'seconds'                               => 'сек.',
        'successful_queries'                    => 'успешных запросов',
        'to_top'                                => 'в начало',
        'transaction_in_progress'               => 'Транзакция не может быть запущена, пока выполняется другая.',
        'unbuffered'                            => 'небуферизовано',
        'unbuffered_queries_cannot_be_cached'   => 'Небуфиризированные запросы не могут быть закэшированы',
        'unsuccessful_queries'                  => 'неуспешных запросов',
        'unusable_method_unbuffered_queries'    => 'Mетод %s() не может быть использован наряду с небуферизованными запросами',
        'warning_charset'                       => 'Значения по умолчанию для charset и collation небыли установлены. Вызовите set_charset() после подключения к базе.',
        'warning_memcache'                      => 'Расширение "memcache" доступно на вашем сервере, рассмотрите возможность использования memcache для кэширования результатов ваших запросов.<br>Подробности в <a href="https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#var$caching_method">документации</a>.',
        'warning_redis'                         => 'The "redis" extension is available on your server - consider using redis for caching query results.<br>See <a href="https://stefangabos.github.io/Zebra_Database/Zebra_Database/Zebra_Database.html#var$caching_method">the documentation</a> for more information.',
        'warning_replacements_not_array'        => '<em>$replacements</em> должен быть массивом значений',
        'warning_replacements_wrong_number'     => 'количество значений для замены отличается от количества значений в массиве <em>$replacements</em>',
        'warning_wait_timeout'                  => 'Значение переменной MySQL <em>wait_timeout</em> установлено в %s.
 													Переменная <em>wait_timeout</em> обозначает время в секундах, которое MySQL будет ожидать
 													перед тем как отключить неиспользуемое подключение.
 													После выполнения скрипта, подключение MySQL не уничтожается, а отправляется в состояние idle для
 													будущего использования тем же пользователем, требующим подключение к базе
 													(обычное поведение, когда пользователи пользуются веб-сайтом). Значение <em>wait_timeout</em>
 													по умолчанию 28800 секунд или 8 часов. В случае, когда у вас большое количество пользователей это приводит
 													к ошибке <em><a href="https://dev.mysql.com/doc/refman/8.0/en/too-many-connections.html" target="_blank">
													Слишком много соединений</a></em>, и возможны ситуации, когда нет возможности настроить максимальное количество
													<a href="https://dev.mysql.com/doc/refman/8.0/en/server-system-variables.html#sysvar_max_connections" target="_blank">свободных подключений</a>.
													Рекомендуемое значение - 300 секунд (5 минут).',
        'warning'                               => 'Предупреждение',
        'warnings'                              => 'предупреждения',
        'yes'                                   => 'Да',
    );
