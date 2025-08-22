<?php

/**
 *  This file is required when {@link Zebra_Database::$debug_ajax debug_ajax} is enabled.
 *
 *  >   You need to move the `update-debug-info.php` file from the library's root folder to location accessible by an
 *      AJAX GET request. Additionally, you can rename the file to whatever suits your needs - or use whatever technique
 *      you want, as long as the file is publicly accessible and its content is unchanged.
 *
 *  Read more {@link Zebra_Database::$debug_ajax here}.
 *
 *  @license    https://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE
 *  @package    Zebra_Database
 */

// no POST data, only a single GET parameter, is the right GET parameter, it contains a valid ID (numeric only), a file with the right name exists (zdb-log-[numeric ID])
if (empty($_POST) && isset($_GET) && is_array($_GET) && count($_GET) == 1 && isset($_GET['id']) && preg_match('/^[0-9]{12}$/', $_GET['id']) && file_exists(($path = sys_get_temp_dir() . '/zdb-log-' . $_GET['id'])) && is_file($path)) {
    echo file_get_contents($path);
    unlink($path);
    die();
}
