<?php
//Feature toggle - when set to false, no caching will be applied to the autoload path
if (preg_match('/cosmic/', $_SERVER["HTTP_HOST"]) || preg_match('/iops4g\.e2save/', $_SERVER["HTTP_HOST"])) {
    $GLOBALS['importCacheOn'] = false;
} else {
    $GLOBALS['importCacheOn'] = false;
}

//The below class names are duplicated between cosmic and shared - these should not be cached
$GLOBALS['classCacheExceptions'] = array(
    'amcatservice',
    'app',
    'auditmodel',
    'brand',
    'cache',
    'cashbackclaimtypeticket',
    'cashbackcontroller',
    'celatonconstants',
    'celatondata',
    'celatonresponsedata',
    'checkoutcontroller',
    'contactform',
    'controller',
    'customeremail',
    'customervo',
    'datafrombosales',
    'datafromwebservice',
    'dbvo',
    'deal',
    'dealbasket',
    'dealfinder',
    'debug',
    'dispatcher',
    'e2form',
    'e2validator',
    'fileman',
    'formhelper',
    'gettriviafromdob',
    'getweatherfrompostcode',
    'handset',
    'home',
    'hook',
    'htmlhelper',
    'login',
    'menuitem',
    'model',
    'modeliterator',
    'modelvalidation',
    'ordercontroller',
    'orderdeliveryaddress',
    'ordernote',
    'orderscontroller',
    'ordersplit',
    'orderstatus',
    'orderstatuscontroller',
    'pieproductdata',
    'pieproductgetter',
    'pietransaction',
    'product',
    'producttypes',
    'productvo',
    'report',
    'restapiclient',
    'searchresultvo',
    'smarty',
    'staticgifts',
    'support',
    'talkmobiletradein',
    'tariff',
    'tarifffields',
    'trackingpixels',
    'usercontroller',
    'validator',
    'valueobject',
    'view',
    'vouchercampaigncontroller',
    'whitelabel',
    'whitelabelvo'
);


// Flags for enabling insurance / dotd on webdev
if (preg_match('/\.webdev(\d*)/', $_SERVER['HTTP_HOST'], $matches) || preg_match('/webdev(\d*)/', $argv[0], $matches)) {
    $GLOBALS['useNewVoucherCampaign'] = true;
    $GLOBALS['useNewInsurance'] = false;
} else {
    $GLOBALS['useNewVoucherCampaign'] = true;
    $GLOBALS['useNewInsurance'] = false;
}

$cosmosLoc = str_replace(array('shared', 'website_engine/'), array('cosmos', ''), Cosmos::getConfigValue('docRoot.shared'));

//require_once($cosmosLoc.'configs/init_config.php');
///////////////
if (getenv('SERVER_IP')) {
    // This is used when running from the command line on webdev, otherwise the mysql_v3 script doesn't know what database to look at
    $_SERVER["SERVER_ADDR"] = "192.168.222.16";
    $GLOBALS["dev"] = 1;
}

if (preg_match('/\.webdev(\d*)/', $_SERVER['HTTP_HOST'], $matches)) {
    $webdev = $matches[1];
    $GLOBALS["dev"] = 1;
}

###### MEMCACHE ######
$GLOBALS['usememcached'] = false;
$GLOBALS['usememcache'] = false;

if ($config['memcache_type'] == 'memcached' || $config['memcache_type'] == 'memcache') {
    if (count($config['memcache_ips']['session']) || count($config['memcache_ips']['web'])) {
        $memcacheString = '';

        if ($config['memcache_type'] == 'memcached') {
            $GLOBALS['usememcached'] = true;
            $GLOBALS['usememcache'] = false;
            $mcStringPrefix = ',';
        } elseif ($config['memcache_type'] == 'memcache') {
            $GLOBALS['usememcached'] = false;
            $GLOBALS['usememcache'] = true;
            $mcStringPrefix = ',tcp://';
        }

        if (count($config['memcache_ips']['session'])) {
            foreach ($config['memcache_ips']['session'] as $ipPortPair) {
                $memcacheString .= $mcStringPrefix . $ipPortPair[0] . ':' . $ipPortPair[1];
            }
            $memcacheString = substr($memcacheString, 1); // Remove initial comma

            ini_set("session.save_handler", $config['memcache_type']);
            ini_set("session.save_path", $memcacheString);
        }

        if (count($config['memcache_ips']['web'])) {
            $webmemcacheNodes = $config['memcache_ips']['web'];
            $serviceCacheNodes = $config['memcache_ips']['web'];
        }
    }
}

###### PATHS ######
//SETUP COMMON PATHS
foreach ($config['docRoot'] as $drKey => $drVal) {
    $docRoot[$drKey] = $drVal;
}

//$sharedPath = $config['docRoot']['shared'].'/include/';
Cosmos::setConfigValue('sharedPath', Cosmos::getConfigValue('docRoot.shared').'/include/');

ini_set('include_path', ini_get('include_path') . PATH_SEPARATOR . $sharedPath);

$base = $docRoot['base'];
$shared = $docRoot['shared'];
$website = $base;
$assets = $docRoot['assets'];

###### DATABASE ######
$dbs = $config['database_ips'];

//$GLOBALS['trace_queries_on_live'] = $config['trace_queries'];
//$GLOBALS['throw_mysql_exceptions'] = $config['throw_mysql_exceptions'];

Cosmos::setConfigValue('trace_queries_on_live', Cosmos::getConfigValue('trace_queries'));
Cosmos::setConfigValue('throw_mysql_exceptions', Cosmos::getConfigValue('throw_mysql_exceptions'));

$dbConnections = array();

if ($dbs['read']) {
    $dbs['default'] = $dbs['read'];
    $dbs['local'] = $dbs['read'];
    $dbs['select'] = $dbs['read'];
    unset($dbs['read']);
}
if ($dbs['write']) {
    $dbs['live'] = $dbs['write'];
    unset($dbs['write']);
}

foreach ($dbs as $id => $addr) {
    $key = $id === 'default' ? '' : '_' . $id;
    foreach ($config['mysql_users'] as $dbName => $dbConDetails) {
        /*$key = pack("H*", "a7cbf6da8923cb76765ca25cab3454bf");
        $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
        $hashPass = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $dbConDetails['password'], MCRYPT_MODE_CBC, $iv);
        $hashPass = base64_encode($hashPass);
        $hashUser = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $dbConDetails['user'], MCRYPT_MODE_CBC, $iv);
        $hashUser = base64_encode($hashUser);
        $ivHash = base64_encode($iv);
        echo "'user' => '".$hashUser."', 'password' => '".$hashPass."', 'iv' => '".$ivHash."'<br />";*/

        $iv = base64_decode($dbConDetails['iv']);
        $decKey = pack("H*", "a7cbf6da8923cb76765ca25cab3454bf");
        $decodedUser = base64_decode($dbConDetails['user']);
        $decodedUser = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $decKey, $decodedUser, MCRYPT_MODE_CBC, $iv);
        $decodedPass = base64_decode($dbConDetails['password']);
        $decodedPass = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $decKey, $decodedPass, MCRYPT_MODE_CBC, $iv);

        // Trim any extra characters off the end which were added during encryption
        // Or not removed on decryption. Only seems to break in PHP 5.4+
        $decodedUser = trim($decodedUser);
        $decodedPass = trim($decodedPass);

        $dbConnections[$dbName . $key] = array($addr, $decodedUser, $decodedPass);

        if ($dbConDetails['user'] == 'all') {
            $allPass = $decodedPass;
        }
    }

    if ($id === 'default') {
        $dbConnections['slave'] = array($addr, 'all', $allPass);
    } elseif (is_numeric($id)) {
        $dbConnections['slave' . ($id + 1)] = array($addr, 'all', $allPass);
    }
}

//Required in later versions of PHP - global vars don't propagate in the same way
//$GLOBALS['dbConnections'] = $dbConnections;
Cosmos::setConfigValue('dbConnections', $dbConnections);
###### MONGODB DATABASE ######

// NOTE : any changes here MUST also be reflected in MongoDBModel::initConfigs()
$mongoDbConnections = array();
if (count($config['mongodb_connection_options'])) {
    foreach ($config['mongodb_connection_options'] as $id => $connection_array) {
        foreach ($config['mongodb_users'] as $connection_id => $identity_array) {
            if ($connection_id != $id) {
                continue;
            }

            /*
             $key = pack("H*", "a7cbf6da8923cb76765ca25cab3454bf");
            $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
            $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
            $hashPass = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $identity_array['password'], MCRYPT_MODE_CBC, $iv);
            $hashPass = base64_encode($hashPass);
            $hashUser = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $identity_array['user'], MCRYPT_MODE_CBC, $iv);
            $hashUser = base64_encode($hashUser);
            $ivHash = base64_encode($iv);
            echo "'user' => '" . $hashUser . "', 'password' => '" . $hashPass . "', 'iv' => '" . $ivHash . "'<br />";
            die();
            */
            $iv = base64_decode($identity_array['iv']);
            $decKey = pack("H*", "a7cbf6da8923cb76765ca25cab3454bf");
            $decodedUser = base64_decode($identity_array['user']);
            $decodedUser = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $decKey, $decodedUser, MCRYPT_MODE_CBC, $iv);
            $decodedPass = base64_decode($identity_array['password']);
            $decodedPass = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $decKey, $decodedPass, MCRYPT_MODE_CBC, $iv);

            // Trim any extra characters off the end which were added during encryption
            // Or not removed on decryption. Only seems to break in PHP 5.4+
            $decodedUser = trim($decodedUser);
            $decodedPass = trim($decodedPass);

            $mongoDbConnections[$id] = $connection_array;
            $mongoDbConnections[$id]['username'] = $decodedUser;
            $mongoDbConnections[$id]['password'] = $decodedPass;

        }
    }
}
/////////////////
//require_once($GLOBALS['docRoot']['shared'] . 'include/mysql_v3.php');

$dev = false;
if (@trim(`/bin/hostname`) == 'dev.localdomain' || $GLOBALS["HOSTNAME"] == "dev.localdomain" || $_SERVER["SERVER_ADDR"] == "192.168.222.88") {
    $dev = true;
}

//SET THE SHARED TAG NAME INTO A VAR
$GLOBALS['shared_code_cvstag'] = '$Name$';

if (preg_match('/\.webdev(\d*)/', $_SERVER['HTTP_HOST'], $matches) || preg_match('/webdev(\d*)/', $argv[0], $matches)) {
    $webdev = $matches[1];
    $dev = true;
}

//Set the server address if it's not set (which means we are running off the command line)
if (!isset($_SERVER['SERVER_ADDR'])) {
    if (is_numeric(substr(gethostbyname('self'), 0, 1))) {
        $_SERVER['SERVER_ADDR'] = gethostbyname('self');
    }
}

require Cosmos::getConfigValue('sharedPath').'forward_compat.php';

import("exceptions.*");
import("utils.*");

$deverrortype = array(
    E_NOTICE => 'Notice',
    E_PARSE => 'Parsing Error',
    E_CORE_WARNING => 'Core Warning',
    E_CORE_ERROR => 'Core Error',
    E_COMPILE_ERROR => 'Compile Error',
    E_COMPILE_WARNING => 'Compile Warning',
    E_STRICT => 'Runtime Notice',
    E_USER_ERROR => 'User Error',
    E_USER_WARNING => 'User Warning',
    E_USER_NOTICE => 'User Notice',
    E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
    E_ERROR => 'Error',
    E_WARNING => 'Warning'
);

/* DEFINING A CONSTANT FOR AN INVALID GIFT COMBINATION (E.G. A PRICE MATCH TARIFF) */
define("INVALID_INSTANT_CASH_DEAL", -4095);

/** VENU -------- WE ARE NOT USING THIS $clientIP. Do you have any idea about its usage??? */
$clientIP = determineIP();


//Number of items per page from boxquery
$itemsPerPage = 12;

if (isset($GLOBALS['_SERVER']['ComSpec'])) {
    $GLOBALS['usememcache'] = false;
    $GLOBALS['usememcached'] = false;
}

if ($GLOBALS['usememcache']) {
    setupMemcache();
    $config['memcache'] = $GLOBALS['memcache'];
} elseif ($GLOBALS['usememcached']) {
    setupMemcacheD();
    $config['memcache'] = $GLOBALS['memcache'];
}

$config['memcache'] = $GLOBALS['memcache'];

/** ALWAYS SETUP THE BASKET MEMCACHE **/
if (!$GLOBALS['phpUnit'] && isset($GLOBALS['_SERVER']['ComSpec']) === false) {
    if ($GLOBALS['usememcached']) {
        $GLOBALS["serviceMemcache"] = new Memcached();
    } else {
        $GLOBALS["serviceMemcache"] = new Memcache();
    }
    if (!$GLOBALS["serviceCacheNodes"]) return;
    foreach ($GLOBALS["serviceCacheNodes"] as $mn) {
        // this code is used by both the memcache and memcached flows, but the interface is the same, so we'll reuse it
        if (is_array($mn)) {
            $ip = $mn[0];
            $port = $mn[1];
        } else {
            $ip = $mn;
            $port = 11213;
        }
        $GLOBALS["serviceMemcache"]->addServer($ip, $port);
    }
}

/** SECURE SERVER ADDRESS **/
if ($_SERVER["SERVER_ADDR"] == "192.168.222.88") {
    define("SECURE_CHECKOUT_DOMAIN", "https://www.secure-mobiles.dev");
} elseif (strpos($_SERVER['SERVER_ADDR'], '192.168.222') !== false || strpos($_SERVER['SERVER_ADDR'], '192.168.223') !== false) {
    list(, , $tld) = explode('.', $_SERVER['SERVER_NAME']);
    define("SECURE_CHECKOUT_DOMAIN", "https://www.secure-mobiles." . $tld);
} else {
    define("SECURE_CHECKOUT_DOMAIN", "https://www.secure-mobiles.com");
}

/** IS THE REQUEST FROM LOUGHBOROUGH (E2SAVE) ? **/
if (strpos($GLOBALS["clientIP"], "213.106.248.") !== false || strpos($GLOBALS["clientIP"], "213.246.161.") !== false) {
    $ips = explode(".", $GLOBALS["clientIP"]);
    if ($ips[3] >= 201 && $ips[3] <= 206) {
        define("E2SAVE_PAGE_REQUEST", true);
    } else {
        define("E2SAVE_PAGE_REQUEST", false);
    }
    unset($ips);
} elseif (SECURE_CHECKOUT_DOMAIN == "https://www.secure-mobiles.dev") {
    define("E2SAVE_PAGE_REQUEST", true);
} else {
    define("E2SAVE_PAGE_REQUEST", false);
}

/*
$startTime = getMicrotime();
*/
if (preg_match("/\bapplication\/x-shockwave-flash\b/i", $_SERVER["HTTP_ACCEPT"])) {
    $_SESSION["isFlash"] = "isset";
}


include_once(Cosmos::getConfigValue('sharedPath') . "imageSizes.php");

//check that subdomain of e2save.com is active

if (substr($_SERVER["HTTP_HOST"], 0, 6) != "media.") {
    if ((substr($_SERVER["HTTP_HOST"], -11) == ".e2save.dev") && $_SERVER["HTTP_HOST"] != "iops.e2save.com" && $_SERVER["HTTP_HOST"] != "iops.e2save.dev" && $_SERVER["HTTP_HOST"] != "cosmic" && $_SERVER["HTTP_HOST"] != "cosmic.e2save.dev" && $_SERVER["HTTP_HOST"] != "old.e2save.com") {
        $r = mysqlQuery("select approved, dhomepage, type from whitelabel where whitelabelcode='" . substr($_SERVER["HTTP_HOST"], 0, -11) . "'", "e2web");
        $data = mysql_fetch_assoc($r);
        $GLOBALS["dhomepage"] = $data["dhomepage"];
        if ($data["approved"] != 1) {
            print("Your whitelabel website application is currently being approved. Please try again later.");
            die();
        }

        //stop iframe affiliates breaking through being sent to the wrong node if cookies are blocked
        if ($data["type"] == "iframe" && substr($_SERVER["SERVER_ADDR"], 0, 10) == "192.168.1.") {
            ini_set("session.save_handler", "memcache");
            ini_set("session.save_path", "tcp://192.168.1.2:11212,tcp://192.168.1.3:11212,tcp://192.168.1.10:11212,tcp://192.168.1.17:11212");
        }
    }
}

// Check that subdomain of onestopphoneshop.co(m|.uk) is active
if (substr($_SERVER["HTTP_HOST"], 0, 6) != "media." && substr($_SERVER["HTTP_HOST"], 0, 4) != "www.") {
    $domain_match = array();
    if (preg_match('/\.onestopphoneshop\.(?:co(?:m|\.uk)|dev)$/i', $_SERVER['HTTP_HOST'], $domain_match) === 1) {
        $domain_match = $domain_match[0];
        $osps_subdomain = str_replace('osps_', '', strtolower(substr($_SERVER["HTTP_HOST"], 0, strlen($domain_match) * -1)));
        $sql = "select approved, dhomepage from whitelabel where whitelabelcode='osps_" . $osps_subdomain . "'";
        $r = mysqlQuery($sql, "e2web");
        $data = mysql_fetch_assoc($r);
        $GLOBALS["dhomepage"] = $data["dhomepage"];
        if ($data["approved"] != 1) {
            print("Your whitelabel website application is currently being approved. Please try again later.");
            die();
        }
    }
}

// Check that subdomain of vanillamobile.co.uk is active
if (substr($_SERVER["HTTP_HOST"], -20) == ".vanillamobile.co.uk" && $_SERVER["HTTP_HOST"] != "iops.e2save.com" && $_SERVER["HTTP_HOST"] != "old.e2save.com") {
    $r = mysqlQuery("select approved from whitelabel where whitelabelcode='" . substr($_SERVER["HTTP_HOST"], 0, -20) . "'", "e2web");
    $data = mysql_fetch_assoc($r);
    if ($data["approved"] != 1) {
        print("Your whitelabel website application is currently being approved. Please try again later.");
        die();
    }
}

$hosts = array("mobilesquad", "snapandchat");
foreach ($hosts as $host) {
    if (preg_match("/^" . $host . "\.co/i", $_SERVER["HTTP_HOST"])) {
        header("Location: http://www." . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . "?" . $_SERVER["QUERY_STRING"]);
        exit();
    }
}

if (substr_count($_SERVER["QUERY_STRING"], "&amp;") && !substr_count($_SERVER["HTTP_HOST"], "secure-mobiles")) {
    header("Location: http://" . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . "?" . str_replace("&amp;", "&", $_SERVER["QUERY_STRING"]));
    exit();
}

if ($_SERVER["HTTP_HOST"] == "www.e2save.co.uk") {
    header("Location: http://www.e2save.com");
}

if (!$GLOBALS['VAT']) {
    $GLOBALS["VAT"] = get_vat() + 1;
}


// Nasty 'register globals' workaround hack for IOPS
if (preg_match('/(iops)/', getenv('DOCROOT_BASE')) || preg_match('/(iops)/', $_SERVER['DOCUMENT_ROOT'])) {
    // todo: This next IF block and array can be dropped once we're off Opal
    $newCosmosServers = array();
    $newCosmosServers[] = '162.13.193.132';
    $newCosmosServers[] = '162.13.193.133';
    $newCosmosServers[] = '162.13.193.134';
    $newCosmosServers[] = '162.13.193.135';
    $newCosmosServers[] = '134.213.83.26';
    $newCosmosServers[] = '134.213.83.27';
    $newCosmosServers[] = '134.213.44.216';
    $newCosmosServers[] = '134.213.44.217';
    $newCosmosServers[] = '10.181.161.246';
    $newCosmosServers[] = '10.181.161.253';
    $newCosmosServers[] = '10.181.128.214';
    $newCosmosServers[] = '10.181.162.34';
    $newCosmosServers[] = '10.181.195.98';
    $newCosmosServers[] = '10.181.198.196';
    $newCosmosServers[] = '10.181.198.222';
    $newCosmosServers[] = '10.181.198.7';
    if (in_array($_SERVER['SERVER_ADDR'], $newCosmosServers)) {
        extract($_REQUEST);
    }
}