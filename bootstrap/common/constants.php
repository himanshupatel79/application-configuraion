<?php
/* This file used to define Constant*/
define('CLASS_DIR', 'classes/');
define('FUNCTIONS_DIR', 'functions/');
define('ENVIRONMENT', getenv('ENVIRONMENT'));

define('DOCROOT_BASE', getenv('DOCROOT_BASE'));
define('SHARED_BASE', getenv('SHARED_BASE'));
define('ASSETS_BASE', getenv('ASSETS_BASE'));

define('SMARTY_BASE', getenv('ASSETS_BASE') . 'smarty/webdev');
define('WORDPRESS_BASE', '/var/www/wordpress');

define('COSMOS_BASE', str_replace("shared", "cosmos", SHARED_BASE));
//define('COSMOS_BASE', "/var/www/cosmos/");

/** CACHE EXPIRY DEFINITIONS **/
define("IOPS_MEMCACHE_EXPIRY", (60*60*12));
define("BASKET_MEMCACHE_EXPIRY", (60*60*2));
define("documentRoot", $GLOBALS["DOCUMENT_ROOT"]);
