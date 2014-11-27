<?php
require dirname(__FILE__) . '/constants.php';
require CLASS_DIR . 'Backbone_Base.php';

$objComm = new Backbone_Base();

if (!$config) {
    $config = array();
}
$conf = $objComm->getConfig('config.ini');
$db_conf = $objComm->getConfig('database.ini');
$config = array_merge($conf, $db_conf);

if (file_exists(dirname(__FILE__) . '/applications/application.ini')) {
    $app_conf = $objComm->getConfig('applications/application.ini');
    $config = array_merge($config, $app_conf);
}

if (preg_match('/\.webdev(\d*)/', $_SERVER['HTTP_HOST'], $matches)) {
    $webdev = $matches[1];
}
require FUNCTIONS_DIR . 'common_functions.php';
require FUNCTIONS_DIR . 'db_functions.php';
require CLASS_DIR . 'Import_Cache.php';

/*
* Path locations for projects. These are used when importing code in projects.
*/
$config['docRoot']['base'] = DOCROOT_BASE;
$config['docRoot']['shared'] = SHARED_BASE;

$config['docRoot']['cosmos'] = str_replace(array('shared', 'website_engine/'), array('cosmos', ''), SHARED_BASE);
$config['docRoot']['classes'] = DOCROOT_BASE . '/www/classes/';
$config['docRoot']['website'] = DOCROOT_BASE . '/www/website/';
$config['websiteTemplateDir'] = $config['docRoot']['website'];
$config['docRoot']['assets'] = ASSETS_BASE;
$config['docRoot']['media'] = ASSETS_BASE . '/media/';
$config['docRoot']['monitors'] = '/var/www/monitors/';
$config['docRoot']['iops'] = $config['docroot']['iops'];
//$config['docRoot']['iops'] = '/var/www/webdev' . $webdev . '/iops/';

$config['docRoot']['e2save'] = SHARED_BASE . '/www.e2save.com/';
$config['docRoot']["e2save_new"] = SHARED_BASE . '/www.e2new.com/';

$config['docRoot']['wordpress'] = WORDPRESS_BASE . '/website_engine/';
$config['docRoot']['wordpress2'] = WORDPRESS_BASE . '/e2smarty/';
$config['docRoot']['newspress'] = WORDPRESS_BASE . '/e2smartynews/';

$config['docRoot']["secure-mobiles"] = DOCROOT_BASE . '/www/website';
$config['docRoot']["secure"] = DOCROOT_BASE . '/www/website';
$config['docRoot']['global'] = SHARED_BASE . '/include';

//$config['docRoot'] = $config['docRoot']['website'];

require 'globals.php';

/*
 * Memcache settings.
 */
// Are we using memcache or memcached?
// WARNING: Setting this to anything else will disable memcache completely,
// causing persistent sessions (logins, checkout) to be lost
$phpVersion = explode('.', PHP_VERSION);
if ($phpVersion[0] >= 5 && $phpVersion[1] >= 4) {
    $config['memcache_type'] = 'memcached'; // use memcached on PHP 5.4.* and above
} else {
    $config['memcache_type'] = 'memcache';
}

/*
 * Smarty cache location and expiry settings
 */
$config['websiteCacheExpiry'] = 12 * 60 * 60;

$config['smartyProject'] = 'website_engine_www2010';

// Path to the Smarty cache and compiled Smarty templates
if (preg_match('/\.webdev(\d*)/', $_SERVER['HTTP_HOST'], $matches)) {
    $config['smartyProject'] = 'website_engine';
}


if (preg_match('/\.webdev(\d*)/', $_SERVER['HTTP_HOST'], $matches) || preg_match('/webdev(\d*)/', $argv[0], $matches)) {
    $webdev = $matches[1];
//    $dev = true;
    $config['webdev'] = $webdev;
}

if ($config['smartyProject']) {
    $config['websiteCacheDir'] = SMARTY_BASE . $webdev . '/' . $config['smartyProject'] . $config['smarty']['cached_directory'];
    $config['websiteCompileDir'] = SMARTY_BASE . $webdev . '/' . $config['smartyProject'] . $config['smarty']['compiled_directory'];
}

// Forced load balancing across different read nodes
// TM2
if (in_array($_SERVER['SERVER_ADDR'], array('37.188.115.202', '10.178.8.64'))) {
    $config['database_ips']['read'] = '37.188.114.184:3306';
}

// TM3
if (in_array($_SERVER['SERVER_ADDR'], array('5.79.0.32', '10.178.8.85'))) {
    $config['database_ips']['read'] = '37.188.113.148:3306';
}

if (is_array($config[memcache_ipaddress])) {
    foreach ($config['memcache_ipaddress'] as $key => $value) {
        if (preg_match('/web/', $key)) {
            $config['memcache_ips']['web'][] = $value;
        } elseif (preg_match('/session/', $key)) {
            $config['memcache_ips']['session'][] = $value;
        }
    }
    unset($config[memcache_ipaddress]);
}

//Venu - Refactoring - examples of usage

$config['params'] = require(dirname(__FILE__) . '/config/params.php');
require(dirname(__FILE__) . '/../base/Cosmos.php');
$cosmosConfig = new Cosmos($config);

require FUNCTIONS_DIR . 'functions.php';
require FUNCTIONS_DIR . 'mysqldb.php';
require 'globals.php';
//require FUNCTIONS_DIR . 'all_new.php';
//print_r(Cosmos::getConfigValue('webdev'));
//  examples of usage
//print_r(Cosmos::getConfigValue('mysql_users.e2cust.user'));
/*    Cosmos::setConfigValue('KEY1.KEY2.KEY3.KEY4', 'key4 value...');
    Cosmos::setConfigValue('KEY1.KEY2.KEY3.KEY5', 'key5 value...');
    Cosmos::removeConfigValue('KEY1.KEY2.KEY3.KEY4');
    print_r(Cosmos::getConfigValue('KEY1'));*/
//print_r(Cosmos::getConfig());

/*
    echo ";alertEmail=----=>>".PHP_EOL;
    //you can't set params dynamically, you can only get
    print_r(Cosmos::getParams('alertEmail'));

    echo ";iopsE2SaveEmail=----=>>".PHP_EOL;
    print_r(Cosmos::getParams('iopsE2SaveEmail'));

    echo ";NEW_CONFIG_VALUE=----=>>".PHP_EOL;
    //You can set NEW config values dynamically

    Cosmos::setConfigValue('NEW_CONFIG_VALUE', 'THIS IS NEW CONFIG VALUE SET DYNAMICALLY');
   // Cosmos::removeConfigValue('NEW_CONFIG_VALUE');
    print_r(Cosmos::getConfigValue('NEW_CONFIG_VALUE'));

    echo ";websiteCacheDir=----=>>".PHP_EOL;
    //You can override existing config values dynamically
    Cosmos::setConfigValue('websiteCacheDir', 'Cache DIR changed dynamically');
    print_r(Cosmos::getConfigValue('websiteCacheDir'));

    echo ";docRoot.shared=----=>>".PHP_EOL;
    Cosmos::setConfigValue('docRoot.shared.test', 'docRoot ..shared.. test');
    //print_r(Cosmos::getConfigValue('docRoot'));

echo ";docRoot.shared.multilevel remove test=----=>>".PHP_EOL;
 print_r(Cosmos::removeConfigValue('docRoot.shared.test'));
echo "1--------------0-->";
print_r(Cosmos::getConfigValue('xxx.yyy.zzzz'));

   echo ";FULL CONFIG=----=>>".PHP_EOL;
    print_r(Cosmos::$config);
*/