<?php
list($usec, $sec) = explode(" ", microtime());
$startTime = ((float)$usec + (float)$sec);

require 'common/config.php';

if (ENVIRONMENT == "production") {
    if (preg_match('/(iops|intranet)/', getenv('DOCROOT_BASE')) || preg_match('/(iops|intranet)/', $_SERVER['DOCUMENT_ROOT'])) {
        echo "<div style='background: darkred; color: red; border: 1px solid red; font-size: 20px; padding: 5px; margin: 0px;'>Warning: This webdev site is pointing at the live database</div>";
    }
}
//echo getenv('ENVIRONMENT');
//printR($config['memcache_ips']);
//die;

