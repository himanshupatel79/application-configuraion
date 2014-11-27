<?php
//$GLOBALS['useImportCache'] = true;
//echo "IMPORT->".$config['use_import_cache'];
/*
function __autoload($classname)
{
    global $webdev;
    $path = getClassPath($classname);
    if ($webdev && (!isset($GLOBALS['phpUnit']) || !$GLOBALS['phpUnit'])) {
        include_once($path . strtolower($classname) . '.class.php');
    } else {
        @include_once($path . strtolower($classname) . '.class.php');
    }
}*/
function __autoload($classname)
{
    global $webdev,$config;

    if ($config['use_import_cache']) {
        Import_Cache::autoLoad($classname);
        return;
    }

    $path = getClassPath($classname);
    if ($webdev && (!isset($GLOBALS['phpUnit']) || !$GLOBALS['phpUnit'])) {
        include_once($path . strtolower($classname) . '.class.php');
    } else {
        @include_once($path . strtolower($classname) . '.class.php');
    }
}

function get_vat($date = null)
{
    if ($date) $date = strtotime($date);
    else $date = time();

    static $rates;
    if (!isset($rates)) {
        $rates = array();
        // Cache all historic VAT rates, there's only a few anyway
        $rs = mysqlQuery('SELECT * FROM vat', 'e2web');
        while ($r = mysql_fetch_object($rs)) {
            $r->start = strtotime($r->start);
            $r->end = $r->end ? strtotime($r->end) : false;
            $rates[] = $r;
        }
        if (!count($rates)) {
            error_log('There are no VAT rates defined in the e2web.vat table');
        }
    }

    // Try to match a VAT rate by date
    foreach ($rates as $rate) {
        if ($date > $rate->start && ($date < $rate->end || !$rate->end)) {
            return $rate->rate;
        }
    }

    // Default VAT rate
    return 0.2;
}

function checkIP($ip)
{
    if (!empty($ip) && ip2long($ip) != -1 && ip2long($ip) != false) {
        $private_ips = array(
            array('192.168.0.0', '192.168.255.255'),
            array('127.0.0.0', '127.255.255.255'),
            array('255.255.255.0', '255.255.255.255')
        );

        foreach ($private_ips as $r) {
            $min = ip2long($r[0]);
            $max = ip2long($r[1]);
            if ((ip2long($ip) >= $min) && (ip2long($ip) <= $max)) return false;
        }
        return true;
    } else {
        return false;
    }
}

function determineIP()
{
    if (checkIP($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }

    foreach (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) as $ip) {
        if (checkIP(trim($ip))) {
            return $ip;
        }
    }

    if (checkIP($_SERVER['HTTP_X_FORWARDED'])) {
        return $_SERVER['HTTP_X_FORWARDED'];
    } elseif (checkIP($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
        return $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
    } elseif (checkIP($_SERVER['HTTP_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_FORWARDED_FOR'];
    } elseif (checkIP($_SERVER['HTTP_FORWARDED'])) {
        return $_SERVER['HTTP_FORWARDED'];
    } elseif (checkIP($_SERVER['REMOTE_ADDR'])) {
        return $_SERVER['REMOTE_ADDR'];
    } else {
        $ip = '';
        return $ip;
    }
}

function getIXRClient()
{
    import('xmlrpc.*');
    if (!isset($GLOBALS['IXRClient'])) {
        $GLOBALS['IXRclient'] = new IXR_Client();
    }
    return $GLOBALS['IXRclient'];
}

function esc($str)
{
    //$str = mysql_real_escape_string($value);
    $str = addslashes($str);

    // Return result
    return $str;
}

/** VENU--
 * This entry should be returned from NEW_CONFIG ini file
 * Should remove all the conditions.
 */

function getFlexMediaHost()
{
/**    //SET THE BELOW IN INI FILE. AND RETURN $config["FLEX_MEDIA_HOST"] here
    //DEV ON VAGRANT - THE URL WILL BE SAME
        FLEX_MEDIA_HOST = 'http://iops.e2save.webdev998/flex/';
    //??? WHAT IS THIS?
        FLEX_MEDIA_HOST = 'http://iops.e2save.dev/flex/';
    //LIVE
        FLEX_MEDIA_HOST= 'http://iops.e2save.com/flex/';

    if (preg_match('/webdev(\d+)/', $_SERVER["HTTP_HOST"], $matches)) {
        $host = 'http://iops.e2save.webdev' . $matches[1] . '/flex/';
    } elseif (preg_match('/dev/', $_SERVER["HTTP_HOST"], $matches)) {
        $host = 'http://iops.e2save.dev/flex/';
    } else {
        $host = 'http://iops.e2save.com/flex/';
    }
 */
    return Cosmos::getConfigValue('FLEX_MEDIA_HOST');
}

/*
This function returns the phone number to be displayed having been passed the whitelabelcode and affiliatesource cookie value
*/
function get_phone_number($wlcode, $affiliatesource, $type = '')
{

    $aff = explode('|', $affiliatesource);

    $affiliatesource = strtolower($aff[0]);

    if (preg_match('/^(reff?err?er=)?aw/', $affiliatesource)) {
        $affiliatesource = 'aw';
    }

    if ($type != 'custServ' && (($_SERVER['PHP_SELF'] == '/e2001/php/customers/disporder.php')
            || preg_match('/=(confirm|basket)/', $_SERVER['QUERY_STRING'])
            || ($_SERVER['SERVER_NAME'] == 'www.secure-mobiles.com')
            || ($_SERVER['SERVER_NAME'] == 'www.secure-mobiles.dev'))) {
        $type = 'basket';
    }

    if (substr($_GET['category'], -8) == 'upgrades') $type = 'upgrade';

    //escape
    $wlcode = mysql_real_escape_string($wlcode);
    $affiliatesource = mysql_real_escape_string($affiliatesource);
    $type = mysql_real_escape_string($type);
    //

    if (preg_match('/loginArea/', $GLOBALS['REQUEST_URI']) && $GLOBALS['wlcode'] == 'www' && $type == '' && $affiliatesource == '') {
        $result = mysqlQuery("select number from phonenumbers where wlcode='www' and affiliate='' AND type='loginArea'", 'e2web');
    } else {
        $sql = 'select number from phonenumbers where wlcode='."'" . $wlcode . "'".' and affiliate='."'" . $affiliatesource . "'".' AND type='."'" . $type . "'";

        $result = mysqlQuery($sql, 'e2web');

        if (mysql_num_rows($result) === 0 && $affiliatesource) {
            $sql = 'select number from phonenumbers where wlcode=' ."'" . $wlcode . "' and affiliate='ALL' AND type='" . $type . "'";
            $result = mysqlQuery($sql, 'e2web');
        }

        if ($wlcode === 'firefly' && mysql_num_rows($result) === 0) {
            return false;
        }

        if (mysql_num_rows($result) === 0) {
            $sql = 'select number from phonenumbers where wlcode='. "'" . $wlcode . "' and affiliate='' AND type='" . $type . "'";
            $result = mysqlQuery($sql, 'e2web');
        }

        if (mysql_num_rows($result) === 0) {
            $sql = 'select number from phonenumbers where wlcode=' ."'" . $wlcode . "' and affiliate='' AND type=''";
            $result = mysqlQuery($sql, 'e2web');
        }
    }
    if (mysql_num_rows($result) === 0) {
        return false;
    }

    $p = mysql_fetch_row($result);
    return $p[0];
}

/*
This function publishes a file on the live web server solution from the dev box. Takes an array of one file at a time
*/
function publishFile($name, $fullPath, $publishTo = null)
{
    if ($publishTo === null) {
        $publishTo = array('dexter');
    }

    $address = Cosmos::getParams('webdevE2SaveEmail');
    $subject = 'Problem Publishing File in all.php';
    $cont = 'SERVER ADDRESS: ' . $_SERVER['SERVER_ADDR'] . PHP_EOL;
    $cont .= 'SCRIPT: ' . $_SERVER['PHP_SELF'] . PHP_EOL;
    $cont .= 'NAME: ' . $name . PHP_EOL;
    $cont .= 'FULL PATH: ' . $fullPath . PHP_EOL;
    $from = 'From: IOPS <'.Cosmos::getParams('iopsE2SaveEmail').'>'.PHP_EOL;

    $addr = $_SERVER['SERVER_ADDR'];
    if (!$addr) {
        $addr = '192.168.222.88';
    }
    if ($addr != '192.168.222.88') {
        mail($address, $subject, $cont . PHP_EOL.'PROBLEM: Server address is ' . $addr, $from);
        return false;
    }

    if (!is_dir($fullPath)) {
        $curDir = '/';
        $arrDir = explode('/', trim($fullPath, '/'));

        foreach ($arrDir as $dir) {
            $curDir .= $dir . '/';
            @mkdir($curDir);
            foreach ($publishTo as $publish) {
                @exec('ssh root@' . $publish . ' mkdir ' . $curDir);
            }
        }
    }

    if (!file_exists($name)) {
        mail($address, $subject, $cont . PHP_EOL .'PROBLEM: File ' . $name . ' does not exist', $from);
        return false;
    }

    $f = "";
    if (is_array($name)) {
        foreach ($name as $val) {
            $f .= '\"' . $val . '\"';
        }
    } else {
        $f = '\"' . $name . '\" ';
    }

    $fullPath = str_replace('(', '\(', $fullPath);
    $fullPath = str_replace(')', '\)', $fullPath);
    $reFile = Cosmos::getConfigValue('docRoot.base') . '/logs/publishFile.txt';

    foreach ($publishTo as $publish) {
        $cmd = 'scp ' . $f . 'root@' . $publish . ":'" . $fullPath . "' 2> " . $reFile;
        exec($cmd, $rtn2, $rtn);
    }

    if (filesize($reFile) > 0) {
        $f = fopen($reFile, 'r');
        $reInf = fread($f, filesize($reFile));
        fclose($f);
        $x = file_get_contents($reFile);
        if ($x) {
            $ok = 'true';
        } else {
            $ok = 'false';
        }
        mail(Cosmos::getParams('alertEmail'), 'scp Error' . $ok, $reInf);
    }
    return true;
}


function publishFile2($name, $fullPath, $publishTo = null, $progressCallback = null)
{
    $f="";
    if ($publishTo === null) {
        $publishTo = array('liono');
    }

    $address = Cosmos::getParams('webdevE2SaveEmail');
    $subject = 'Problem Publishing File in all.php';
    $cont = 'SERVER ADDRESS: ' . $_SERVER['SERVER_ADDR'] . PHP_EOL ;
    $cont .= 'SCRIPT: ' . $_SERVER['PHP_SELF'] . PHP_EOL ;
    $cont .= 'NAME: ' . $name . PHP_EOL ;
    $cont .= 'FULL PATH: ' . $fullPath . PHP_EOL ;
    $from = 'From: IOPS <'.Cosmos::getParams('iopsE2SaveEmail').'>'. PHP_EOL ;

    $addr = $_SERVER['SERVER_ADDR'];
    if (!$addr) $addr = '192.168.222.88';
    if ($addr != '192.168.222.88') {
        return false;
    }

    if (!is_dir($fullPath)) {
        $curDir = '/';
        $arrDir = explode('/', trim($fullPath, '/'));

        foreach ($arrDir as $dir) {
            $curDir .= $dir . '/';
            @mkdir($curDir);
            foreach ($publishTo as $publish) {
                @exec('ssh root@' . $publish . ' mkdir ' . $curDir);
            }
        }
    }

    if (!file_exists($name)) {
        return false;
    }

    $f = "";
    if (is_array($name)) {
        foreach ($name as $val) {
            $f .= $val . ' ';
        }
    } else {
        $f = $name . ' ';
    }

    $fullPath = str_replace('(', '\(', $fullPath);
    $fullPath = str_replace(')', '\)', $fullPath);
    $reFile = '/tmp/publish-' . getmypid() . '.pid';

    foreach ($publishTo as $publish) {
        $cmd = 'rsync --progress --chmod=u+rx --bwlimit=200 --ignore-times -avze ssh ' . $f . ' root@' . $publish . ":'" . $fullPath . "'".' 2> /dev/null';
        $fp = popen($cmd, 'rb');

        while (is_resource($fp) && !feof($fp)) {
            $line = trim(fread($fp, 2048));

            preg_match('/[0-9]+( )+([0-9]{1,3})\%( )+[0-9]+\.[0-9]{2}[a-zA-Z]{2}\/s( )+([0-9]{1,2}\:[0-9]{1,2}\:[0-9]{1,2})/', $line, $matches);
            if (count($matches) > 0) {
                $percentage = $matches[2];
                $estimate = $matches[5];

                if ($progressCallback != null && function_exists($progressCallback)) {
                    /** @var callable $progressCallback */
                    $progressCallback($percentage, $estimate);
                }
            }

            preg_match('/sent ([0-9]+) bytes/', $line, $matches);
            if (count($matches) > 0) {
                //Upload done
                pclose($fp);
            }
        }
    }

    return true;
}

function create_url($network, $make, $description, $model, $gift = false, $abtest = false, $network_specific = false)
{
    // used for e2save
    if (($make == 'find') || ($description == '')) {
        $sql = 'SELECT DISTINCT make, modelname FROM handsets WHERE model='."'".$model."'";
        $d = mysqlQuery($sql, "e2web");
        $line = mysql_fetch_assoc($d);
        $make = $line['make'];
        $description = $line['modelname'];
    }

    $description = str_replace('/', '-', $description);

    if ($network == 'tv') {
        $href = '/tv-' . $description . '/' . $model;
        $href = str_replace('"', '', $href);
    } else {
        if (($network == 'payg') || ($network_specific)) {
            $href = '/contract-mobile-phone/' . $make . '-' . $description . '/' . $model;
        } elseif ($network == "accessories") {
            $href = '/mobile-phone-accessory/' . $make . '-' . $description . '/' . $model;
        } else {
            $href = '/contract-mobile-phone/' . $make . '-' . $description . '/' . $model;
        }
    }

    if ($gift) {
        $href .= '-free-gift-' . $gift;
    }
    $href = str_replace('/-', '/', $href);
    $href = str_replace(' ', '-', $href);
    $href = htmlentities($href);
    if ($abtest) {
        $href .= '&site' . $abtest;
    }
    return $href;
}


/*
Get microtime function
This returns the current timestamp with microseconds
*/
function getMicrotime()
{
    list($usec, $sec) = explode(' ', microtime());
    return ((float)$usec + (float)$sec);
}

/*
This function changes relative links to absolute ones, and images to use getImage.php
Image paths MUST start http://www.domain.com/images/ or images/
*/
function replaceLinks($str, $site = 'e2save', $domain = 'www.e2save.com')
{
    // Change relative URLs to absolute

    $str = preg_replace('#(\s+(href|src)\s*=\s*(\'|")?)(https?://' . $domain . '|)/?(?!http)#i', '\$1http://' . $domain . '/', $str);

    // Change images to use getImage.php
    // & should really be &amp; for HTML docs but this would break CSS docs
    // $str = preg_replace("#(http://[^/]+|)/?(images/.*?\.(jpe?g|gif|png))#i", "/getImage.php?domain=".$site."&img=\$2&".session_name()."=".session_id(), $str);
    $str = preg_replace('#((href\s*=\s*|src\s*=\s*|url\()(\'|"|))(http://[^/]+|)(.*?\.(jpe?g|gif|png))#i', '\$1/getImage.php?domain=' . $site . '&img=\$5&' . session_name() . '=' . session_id(), $str);

    return $str;
}

/** VENU-- wait for applicaiton.ini files to merge with DEVELOP */
function getMediaHost()
{
    if (preg_match('/^10\.0\.2/', $_SERVER['SERVER_ADDR'])) {
        return 'http://media.e2save.com:9080';
    }

    $parts = explode('.', $_SERVER['SERVER_NAME']);
    $tld = array_pop($parts);

    if (!$GLOBALS['dev']) {
        $tld = 'com';
    }

    if ($_SERVER['HTTPS']) {
        return 'https://media.secure-mobiles.' . $tld;
    } elseif (strpos($_SERVER['SERVER_NAME'], '.mobiles.co.uk') !== false) {
        return 'http://media.mobiles.co.uk';
    } elseif (strpos($_SERVER['SERVER_NAME'], 'onestopphoneshop') !== false) {
        return 'http://media.onestopphoneshop.' . $tld;
    } elseif (strpos($_SERVER['SERVER_NAME'], 'readersdigest') !== false) {
        return '//media.secure-mobiles.' . $tld;
    } elseif (strpos($_SERVER['SERVER_NAME'], 'talktalk') !== false) {
        return '//media.secure-mobiles.' . $tld;
    } elseif (strpos($_SERVER['SERVER_NAME'], 'talkmobile') !== false) {
        return '//media.secure-mobiles.' . $tld;
    } else {
        return 'http://media.e2save.' . $tld;
    }
}

/*
This function prints an <img> tag that links to /image.php to auto-generate an image based on the querystring
*/
function img($data, $size, $alt, $other = '', $number = null)
{
    if (preg_match('/^media/i', $_SERVER['HTTP_HOST']) == false && substr_count($_SERVER['HTTP_HOST'], 'secure-mobiles') == false) {
        include_once($GLOBALS['sharedPath'] . 'imageSizes.php');

        if (array_key_exists('returnURL', $data)) {
            $returnURL = $data['returnURL'];
            unset($data['returnURL']);
        }

        $url = '';
        $model = 'none';
        foreach ($data as $key => $value) {
            if ($key == 'model') {
                $model = $value;
            }

            if (is_array($value)) {
                foreach ($value as $key2 => $value2) {
                    $url .= sprintf('d[%s][%s]=%s&', rawurlencode($key), rawurlencode($key2), rawurlencode($value2));
                }
            } else {
                $url .= sprintf('d[%s]=%s&', rawurlencode($key), rawurlencode($value));
            }
        }
        if (is_array($size)) {
            $width = $size[0];
            $height = $size[1];
            for ($x = 0; $x < count($size); $x++) {
                $url .= sprintf('s[]=%s&', rawurlencode($size[$x]));
            }
        } else {
            $width = $GLOBALS['imgSizes'][$size][0];
            $height = $GLOBALS['imgSizes'][$size][1];
            $url .= sprintf('s=%s&', rawurlencode($size));
        }

        $url = base64_encode($url);

        if ($data['giftoverlay']) {
            $prefix = $data['giftoverlay'] . '-' . $model;
        } else {
            $prefix = $model;
        }

        $img = $prefix . '/' . rtrim(chunk_split($url, 30, '/'), '/');
        $url = $prefix . '/' . str_replace('%2F', '/', str_replace('%2f', '/', rawurlencode(rtrim(chunk_split($url, 30, '/'), '/'))));


        if (date('Y-m-d') < '2007-12-12' && strtoupper($data['giftoverlay']) == 'OFSONYPS340GB') {
            @unlink(Cosmos::getConfigValue('docRoot.media') . '/images/cache/' . $img . '.' . ($data["type"] ? $data['type'] : 'png'));
        }

        $host = getMediaHost();

        if ($returnURL) {
            $img = sprintf('%s/images/cache/%s.%s', $host, $url, $data['type'] ? $data['type'] : 'png');
        } else {
            $img = sprintf("<img src=\"%s/images/cache/%s.%s\" width=\"%s\" height=\"%s\" alt=\"%s\" title=\"%s\" %s />", $host, $url, $data['type'] ? $data['type'] : 'png', $width, $height, htmlentities($alt), htmlentities($alt), $other);
        }
        return $img;
    }

    if (is_array($size)) {
        $GLOBALS['imgSizes']['custom_' . $size[0] . 'x' . $size[1]] = $size;
        $size = 'custom_' . $size[0] . 'x' . $size[1];
    }

    if (!array_key_exists($size, $GLOBALS['imgSizes'])) {
        die('Invalid image size ' . $size);
    }

    if (strlen($other)) {
        $other = ' ' . $other;
    }

    if ($data['handsetcode']) {
        $opt = mysqlQuery('SELECT image_options FROM handsets WHERE handsetcode='."'" . $data['handsetcode'] . "'". 'AND image_options!='."''", 'e2web');
        if (mysql_num_rows($opt)) {
            $opt = mysql_fetch_assoc($opt);
            $opt = explode('|', $opt['image_options']);

            foreach ($opt as $key) {
                $key = explode('=', $key);

                if (!array_key_exists(1, $key)) {
                    unset($data[$key[0]]);
                    if ($key[0] == 'line') {
                        unset($data['halfprice']);
                        unset($data['cb']);
                    }
                } else {
                    $data[$key[0]] = $key[1];
                }
            }
        }
    }

    if (!function_exists('doImage')) {
        //We're on *.e2save.com or phonehousedirekt.de

        require_once(Cosmos::getConfigValue('sharedPath') . 'imageFunctions.php');
        require_once(Cosmos::getConfigValue('docRoot.e2save') . '/imageInclude.php');
    }

    if (function_exists('doImage')) {
        $data['size'] = $size;
        return doImage($data, $alt, 'tag', $other);
    }

    //Old stuff - shouldn't reach here any more!
    $str = 'size=' . $size;
    foreach ($data as $key => $value) {
        $str .= "&amp;" . $key . '=' . urlencode($value);
    }

    $p = $GLOBALS['imagePath'];
    if (!$p) {
        $p = '/';
    }

    $str = '<img src=\"' . $p . 'image.php?' . $str . '\" width=\"' . $GLOBALS['imgSizes'][$size][0] . '\" height=\"' . $GLOBALS['imgSizes'][$size][1] . '\" alt=\"' . $alt . '\" title=\"' . $alt . '\"' . $other . ' />';

    return $str;
}

function safeBitCheck($number, $comparison)
{
    if ($number < 2147483647) {
        return ($number & $comparison) == $comparison;
    } else {
        $binNumber = strrev(base_convert($number, 10, 2));
        $binComparison = strrev(base_convert($comparison, 10, 2));
        for ($i = 0; $i < strlen($binComparison); $i++) {
            if (strlen($binNumber) < $i || ($binComparison{$i} === '1' && $binNumber{$i} === '0')) {
                return false;
            }
        }
        return true;
    }
}

function transfer($id = '')
{
    if ($id) {
        $sql = sprintf("SELECT * FROM dataTransfer WHERE id = '%s'", $id);
        $result = mysqlQuery($sql, 'no_rep_live');
        if (mysql_num_rows($result)) {
            $record = mysql_fetch_assoc($result);
            $_REQUEST = array();
            if ($record["get"]) {
                $_GET = unserialize($record['get']);
            }
            if ($record['post']) {
                $_POST = unserialize($record['post']);
            }
            if ($record['request']) {
                $_REQUEST = unserialize($record['request']);
            }
            if ($record['cookie']) {
                $_COOKIE = unserialize($record['cookie']);
                foreach ($_COOKIE as $key => $value) {
                    setCookie($key, $value);
                }
            }
            if ($record['files']) {
                $_FILES = unserialize($record['files']);
            }
            if ($record['session']) {
                $_SESSION = unserialize($record['session']);
            }
        }
    } else {
        $sql = sprintf('DELETE FROM dataTransfer WHERE stamp < DATE_SUB(NOW(), INTERVAL 2 HOUR)');
        mysqlQuery($sql, 'no_rep_live');

        $sql = sprintf("INSERT INTO dataTransfer (stamp, request, get, post, cookie, files, session) VALUES (NOW(), '%s', '%s', '%s', '%s', '%s', '%s')", count($_REQUEST) ? addslashes(serialize($_REQUEST)) : "", count($_GET) ? addslashes(serialize($_GET)) : "", count($_POST) ? addslashes(serialize($_POST)) : "", count($_COOKIE) ? addslashes(serialize($_COOKIE)) : "", count($_FILES) ? addslashes(serialize($_FILES)) : "", count($_SESSION) ? addslashes(serialize($_SESSION)) : "");
        mysqlQuery($sql, 'no_rep_live');
        return mysqlInsertId();
    }
    return true;
}

function validateUnique()
{
    $fileName = substr($_SERVER['SCRIPT_FILENAME'], strrpos($_SERVER['SCRIPT_FILENAME'], '/') + 1);
    $fileName = substr($fileName, 0, strrpos($fileName, '.')) . '.pid';
    $fileName = '/tmp/' . $fileName;

    if (file_exists($fileName)) {
        $pid = file_get_contents($fileName);
        $fp = popen('ps -A | egrep php', 'r');
        while (is_resource($fp) && !feof($fp)) {
            $line = fgets($fp);
            list($runningPid) = explode(' ', trim($line));

            if ($runningPid == $pid) {
                return false;
            }
        }

        @fclose($fp);
        unlink($fileName);
        file_put_contents($fileName, getmypid());
    } else {
        file_put_contents($fileName, getmypid());
    }

    return true;
}

//import functions
/**
 * Gets the class cache namespace depending on environment
 *
 * @return string
 */
function getClassPathCacheNamespace()
{
    /**
     *
    //SET THE BELOW IN INI FILE. AND RETURN $config["CP_CACHE_NAMESPACE"] here
    //WEBDEV
    CP_CACHE_NAMESPACE = 'webdev_classmap_';
    //UAT
    CP_CACHE_NAMESPACE = 'uat_classmap_';
    //LIVE
    CP_CACHE_NAMESPACE = 'live_classmap_';;
     *
     * replace this method code with the below line
     * return Cosmos::getConfigValue('CP_CACHE_NAMESPACE')
     */

    if (preg_match('/\.webdev/', $_SERVER['HTTP_HOST'])) {
        $environment = 'webdev';
    } elseif (preg_match('/\.uat/', $_SERVER['HTTP_HOST'])) {
        $environment = 'uat';
    } else {
        $environment = 'live';
    }
    return $environment . '_classmap_';
}

/**
 * Gets the full path for a given class
 *
 * @param $className
 * @return mixed|string
 */
function getClassPath($className)
{
    if ($GLOBALS['importCacheOn'] === false) {
        return;
    }
    $memcache = getMemcached();
    if ($memcache === null) {
        return '';
    }
    $path = $memcache->get(getClassPathCacheNamespace() . strtolower($className));
    if ($path) {
        $path .= '/';
    }
    return $path;
}

/**
 * Checks to see if the class name is duplicated between cosmic and shared
 *
 * @param $className
 * @return bool
 */
function isDuplicateClass($className)
{
    if (in_array(strtolower($className), $GLOBALS['classCacheExceptions'])) {
        return true;
    }
    return false;
}


/**
 * Adds a class name/path mapping to memcache/d
 *
 * @param $className
 * @param $classPath
 */
function addClassPath($className, $classPath)
{
    if (!getMemcached()) {
        return;
    }
    if (!isDuplicateClass($className)) {
        if (!getMemcached()->get(getClassPathCacheNamespace() . strtolower($className))) {
            getMemcached()->set(getClassPathCacheNamespace() . strtolower($className), $classPath);
        }
    } else {
        if (getMemcached()->get(getClassPathCacheNamespace() . strtolower($className))) {
            getMemcached()->delete(getClassPathCacheNamespace() . strtolower($className));
        }
    }
}

/**
 * Adds all classes within a given folder to the cache
 *
 * @param $currentPath
 * @param string $extension
 */
function addClassesToCache($currentPath, $extension = 'class.php')
{
    if ($GLOBALS['importCacheOn'] === false) {
        return;
    }
    if (!getMemcached()) {
        return;
    }
    $directory = new RecursiveDirectoryIterator($currentPath);
    $iterator = new RecursiveIteratorIterator($directory);
    $files = new RegexIterator($iterator, '/^.*\.' . addslashes($extension) . '$/', RegexIterator::GET_MATCH);
    foreach ($files as $file) {
        $className = str_replace('.' . $extension, '', basename($file[0]));
        addClassPath($className, dirname($file[0]));
    }
}
/*
function import($package)
{
//    global $argv;
    $package = strtolower(str_replace('.', '/', $package));
    // List of paths to search for libraries.  Put them in order
    // of decreasing priority.  We scan the shared code first
    // before the per-site code.

    $searchPaths = array(Cosmos::getConfigValue('docRoot.shared') . '/classes/', Cosmos::getConfigValue('docRoot.classes'), Cosmos::getConfigValue('docRoot.cosmos'));

    if ($_SERVER['SCRIPT_FILENAME']) {
        $searchPaths[] = dirname($_SERVER['SCRIPT_FILENAME']) . '/classes/';
    }

    if (count($GLOBALS['argv'])) {
        $searchPaths[] = dirname($GLOBALS['argv'][0]) . '/classes/';
    }

    if (basename($package) != '*') {
        // Import a single file if it exists
        foreach ($searchPaths as $path) {
            if (file_exists($path . $package . '.class.php')) {
                include_once($path . $package . '.class.php');
                return;
            }
        }
    } else {
        // Import a directory and all subdirectories
        $packageDirs = array(dirname($package));
        static $processedDirs = array(); // Prevents adding the directory multiple times

        while ($packageDirs) {
            // This sort of loop makes the code non-recursive
            $currentDir = array_shift($packageDirs);
            if (!isset($processedDirs[$currentDir])) {
                // It is possible for the same path to get added to $packageDirs
                // multiple times if it exists in both the shared and non-shared
                // codebases.
                $processedDirs[$currentDir] = 1;
                foreach ($searchPaths as $path) {
                    // If the directory exists, add it to the include_path and
                    // see if there are subdirectories we can process.
                    $currentPath = $path . $currentDir;
                    if (is_dir($currentPath)) {
                        addClassesToCache($currentPath);
                        ini_set('include_path', ini_get('include_path') . PATH_SEPARATOR . $currentPath);
                        $dirs = glob($currentPath . '/*', GLOB_ONLYDIR);
                        foreach ($dirs as $dir) {
                            $dirBase = basename($dir);
                            if ($dirBase != 'CVS') {
                                $packageDirs[] = $currentDir . '/' . $dirBase;
                            }
                        }
                    }
                }
            }
        }
    }
}*/

function import($package)
{
    global $argv,$config;
    if ($config['use_import_cache']) {
        Import_Cache::import($package);
        return;
    }

    $package = strtolower(str_replace('.', '/', $package));
    // List of paths to search for libraries.  Put them in order
    // of decreasing priority.  We scan the shared code first
    // before the per-site code.
    $searchPaths = array(
        $GLOBALS['docRoot']['shared'] . '/classes/',
        $GLOBALS['docRoot']['classes'], $GLOBALS['docRoot']['cosmos']
    );

    if ($_SERVER['SCRIPT_FILENAME']) {
        if (is_dir(dirname($_SERVER['SCRIPT_FILENAME']) . '/classes/')) {
            $searchPaths[] = dirname($_SERVER['SCRIPT_FILENAME']) . '/classes/';
        }
    }

    if (count($GLOBALS['argv'])) {
        $searchPaths[] = dirname($GLOBALS['argv'][0]) . '/classes/';
    }

    if (basename($package) != '*') {
        // Import a single file if it exists
        foreach ($searchPaths as $path) {
            if (file_exists($path . $package . '.class.php')) {
                include_once($path . $package . '.class.php');
                return;
            }
        }
    } else {
        // Import a directory and all subdirectories
        $packageDirs = array(dirname($package));
        static $processedDirs = array();  // Prevents adding the directory multiple times

        while ($packageDirs) {
            // This sort of loop makes the code non-recursive
            $currentDir = array_shift($packageDirs);
            if (! isset($processedDirs[$currentDir])) {
                // It is possible for the same path to get added to $packageDirs
                // multiple times if it exists in both the shared and non-shared
                // codebases.
                $processedDirs[$currentDir] = 1;
                foreach ($searchPaths as $path) {
                    // If the directory exists, add it to the include_path and
                    // see if there are subdirectories we can process.
                    $currentPath = $path . $currentDir;
                    if (is_dir($currentPath)) {
                        addClassesToCache($currentPath);
                        ini_set('include_path', ini_get('include_path') . PATH_SEPARATOR . $currentPath);
                        $dirs = glob($currentPath . '/*', GLOB_ONLYDIR);
                        foreach ($dirs as $dir) {
                            $dirBase = basename($dir);
                            if ($dirBase != 'CVS') {
                                $packageDirs[] = $currentDir . '/' . $dirBase;
                            }
                        }
                    }
                }
            }
        }
    }
}
//
/**
 * Get the memcache instance
 *
 * @return Memcache|Memcached|null
 */
function getMemcached()
{
    if ($GLOBALS['memcache'] instanceof Memcache || $GLOBALS['memcache'] instanceof Memcached) {
        return $GLOBALS['memcache'];
    }
    return null;
}

//setup memcache

function setupMemcache()
{
    $GLOBALS['memcache'] = new Memcache();
    //Cosmos::setConfigValue('memcache', new Memcache());
    if (!Cosmos::getConfigValue('memcache_ips')) {
        return;
    }
    foreach (Cosmos::getConfigValue('memcache_ips') as $mn) {
        if (is_array($mn)) {
            $ip = $mn[0];
            $port = $mn[1];
        } else {
            $ip = $mn;
            $port = 11211;
        }
        $GLOBALS['memcache']->addServer($ip, $port);
    }
}

function setupMemcacheD()
{
    $GLOBALS['memcache'] = new Memcached();
    if (!Cosmos::getConfigValue('memcache_ips')) {
        return;
    }
    foreach (Cosmos::getConfigValue('memcache_ips') as $mn) {
        if (is_array($mn)) {
            $ip = $mn[0];
            $port = $mn[1];
        } else {
            $ip = $mn;
            $port = 11211;
        }
        $GLOBALS['memcache']->addServer($ip, $port);
    }
}

/**
 * Below functions needs to be moved to screen logger file in Logger Module
 */
$GLOBALS['phpstart'] = microtime(1);

function debugmark($name = '')
{
    if (!$_GET['debugmark']) {
        return;
    }
    if (!$GLOBALS['debugmarkinit']) {
        register_shutdown_function('debugmarkprint');
        $GLOBALS['debugmarkinit'] = true;
    }
    $trace = debug_backtrace();
    $last = $trace[0];
    $time = microtime(1) - $GLOBALS['phpstart'];
    $lastmark = @end($GLOBALS['debugmarks']);
    $mark = array(
        'file' => $last['file'],
        'line' => $last['line'],
        'name' => $name,
        'time' => $time,
        'timed' => $time - $lastmark['time'],
        'sqltime' => $GLOBALS['sqlTime'],
        'sqltimed' => $GLOBALS['sqlTime'] - $lastmark['sqltime'],
        'phptime' => $time - $GLOBALS['sqlTime'],
        'phptimed' => $time - $GLOBALS['sqlTime'] - $lastmark['phptime'],
    );
    $GLOBALS['debugmarks'][] = $mark;
}

function debugmarkprint()
{
    debugmark('PHP End');
    echo '<h2 style="margin-top: 2em">Debug markers:</h2><table class="table table-striped table-bordered"><thead><th>Marker</th><th>Time</th><th>PHP Time</th><th>SQL time</th></tr></thead><tbody>';
    $totals = array();
    $time = $phptime = $sqltime = 0;
    foreach ($GLOBALS['debugmarks'] as $mark) {
        $time = round($mark['timed'], 3);
        $sqltime = round($mark['sqltimed'], 3);
        $phptime = round($mark['phptimed'], 3);

        echo "<tr><td>{$mark['file']} #{$mark['line']} ({$mark['name']})</td><td>{$time}</td><td>{$phptime}</td><td>{$sqltime}</td></tr>\n";
    }
    $time = round(microtime(1) - $GLOBALS['phpstart'], 3);
    $sqltime = round($GLOBALS['sqlTime'], 3);
    $phptime = round($time - $GLOBALS['sqlTime'], 3);
    echo "</tbody><tfoot><tr><th align='right'>TOTALS</th><th>{$time}</th><th>{$phptime}</th><th>{$sqltime}</th></tr></table>";
}

/**
 * Prints a line to the buffer, with a newline char at the end
 *
 * @param string $line
 */
function println($line)
{
    print($line . PHP_EOL);
}

//$startTime = getMicrotime();

/*
Set up debugging levels for mysqlQuery()
DEBUG_NONE = No debugging information - fastest
DEBUG_IN_QUERIES = Show backtraces embedded in mysql queries as comments
DEBUG_IN_QUERIES_EXTRA = Extended information in backtraces
DEBUG_TO_SCREEN = Print the query, time taken, rows returned etc.

IMPORTANT: DEBUG_TO_SCREEN should NEVER be used on live code!
*/
define('DEBUG_NONE', 0);
define('DEBUG_IN_QUERIES', 1);
define('DEBUG_TO_SCREEN', 2);
define('DEBUG_IN_QUERIES_EXTRA', 4);
define('DEBUG_TO_HTML', 8);

$GLOBALS['debugBuffer'] = array();

/*
Set up the default debug level - Scripts may individually override this
*/
$debug = DEBUG_NONE;


/*
This is a wrapper for print_r()
It is provided to aid debugging
*/
function printR($o)
{
    if ($GLOBALS['dev'] && $GLOBALS['config']['display_debug']) {
        global $argc, $argv;

        if ($argc > 1 && is_array($argv) && !isset($_SERVER['REQUEST_URI'])) {
            ob_start();
            print_r($o);
            ob_end_flush();
            println('');
        } else {
            $t = debug_backtrace();
            echo "<!-- {$t[0]['file']} Line #{$t[0]['line']}: -->\n";
            print('<pre style=\"text-align:left; border: 2px solid red;\">');
            ob_start('htmlentities');
            print_r($o);
            ob_end_flush();
            print('</pre>');
            println("");
        }
    }
}

function debugPrintQueries($sort = '')
{
    if ($sort) {
        usort($GLOBALS['allMysqlQueries'], create_function('$a,$b', 'if ($a["' . $sort . '"] == $b["' . $sort . '"]) return 0; return ($a["' . $sort . '"] < $b["' . $sort . '"]) ? -1 : 1;'));
    }
    print('<div style=\'padding: 10px; border: 3px solid #FF0000;\'>'.PHP_EOL);
    foreach ($GLOBALS['allMysqlQueries'] as $q) {
        printR($q);
    }
    print("</div>\n");
}

function debugPrintQueryStats($colour = '#000000')
{
    global $startTime;

    $time = getMicrotime() - $startTime;
    $connections = 0;
    foreach ($GLOBALS['dbConnections'] as $val) {
        if (!is_array($val)) $connections++;
    }

    print('<div style=\'text-align:right; color:'." . $colour . ".';\'><small>');
    print('Page build took ' . number_format($time, 3) . ' seconds, of which ' . number_format($GLOBALS['sqlTime'], 3) . ' ');
    print('seconds (' . number_format(100 * $GLOBALS['sqlTime'] / $time, 0) . '%) was in ' . $GLOBALS['sqlQueries'] . ' SQL queries. ');
    print('Made ' . $connections . ' connections.');
    print('</small></div>');
}

function debugPrintMemory($msg = '')
{
    if ($msg) {
        $msg = ':: ' . $msg;
    }

    $units = 'B';

    $mem = memory_get_usage();
    $delta = $mem - $GLOBALS['lastMemoryUsage'];

    if (function_exists('xdebug_peak_memory_usage')) {
        printR(debugMemFormat($mem) . ' (' . debugMemFormat($delta) . ') [Peak ' . debugMemFormat(xdebug_peak_memory_usage()) . '] ' . $msg);
    } else {
        printR(debugMemFormat($mem) . ' (' . debugMemFormat($delta) . ') [Peak unavailable] ' . $msg);
    }
    $GLOBALS['lastMemoryUsage'] = $mem;
}


function debugMemFormat($mem, $delta = false)
{
    if (abs($mem) > 1048576) {
        $mem = number_format($mem / 1048576, 2) . 'MB';
    } elseif (abs($mem) > 1024) {
        $mem = number_format($mem / 1024, 2) . 'kB';
    } else {
        $mem .= 'B';
    }

    if ($delta && ($mem{0} != '-')) {
        $mem = '+' . $mem;
    }

    return $mem;
}

function getFriendlyDebugBacktrace()
{
    $backtrace = debug_backtrace();
    $backtracestring = '';

    $step = count($backtrace);

    foreach ($backtrace as $tracestep) {
        $backtracestring .= '#' . $step-- . ': ' . (isset($tracestep['class']) ? $tracestep['class'] . '->' : '') . $tracestep['function'] . '(';

        foreach ($tracestep['args'] as $arg) {
            if (is_string($arg)) {
                $backtracestring .= '\"' . $arg . '\", ';
            } else if (is_array($arg)) {
                $backtracestring .= 'Array[' . count($arg) . '], ';
            } else if (is_object($arg)) {
                $backtracestring .= get_class($arg) . ' Object, ';
            } else {
                $backtracestring .= $arg . ', ';
            }
        }

        $backtracestring = rtrim($backtracestring, ', ') . ')\n        [' . $tracestep['file'] . ':' . $tracestep['line'] . ']'.PHP_EOL.PHP_EOL;
    }
    return $backtracestring;
}

function debugMail($to, $subject, $message, $additional_headers = null, $additional_parameters = null)
{
    $firststep = array_shift(debug_backtrace());

    $subject = 'debugMail: ' . $subject;
    $message = (empty($message) ? '' : $message . PHP_EOL.PHP_EOL);

    $message .= 'Called from ' . $firststep['file'] . ' (Line ' . $firststep['line'] . ')';
    try { // Why does this try/catch exist? :/
        $message .= ' on ' . date('Y-m-d H:i:s');
    } catch (ErrorException $e) {
        $message .= ' on [Unknown]';
    }

    if (is_array($_SESSION) && $_SESSION['employee']['username']) {
        $message .= PHP_EOL."User: {$_SESSION['employee']['username']}";
    } elseif (is_array($_SESSION) && $_SESSION['EmployeeUserObject']) {
        $user = @unserialize($_SESSION['EmployeeUserObject']);
        $message .= PHP_EOL."User: {$user->username}";
    }

    $message .= ' [' . ($_SERVER['SERVER_PORT'] == 80 ? 'http' : 'https') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . ']'.PHP_EOL.PHP_EOL;
    $message .= 'Debug Backtrace:'.PHP_EOL.PHP_EOL . getFriendlyDebugBacktrace();

    mail($to, $subject, $message, $additional_headers, $additional_parameters);
}

function ip_debug($addr, $value = '', $exit = false)
{
    global $argc, $argv;
    /**
     * Print debug information depending on the persons IP address.
     *
     * @param $addr string The ip address or address alias (stephenb, garethh, robw, etc) of a machine.
     * @param $value mixed The data you want to print to the screen.
     * @param $exit boolean If true then the script will die after printing the debug.
     * @author Steve Brewster <stephenb@e2save.com>
     * @return boolean
     */
    $alias = array('mikec' => '192\.168\.222\.74', 'stephenb' => '192\.168\.223\.94', 'robg' => '192\.168\.222\.158', 'garethh' => '192\.168\.223\.204', 'robw' => '192\.168\.223\.50', 'martinw' => '192\.168\.222\.115', 'nicks' => '192\.168\.222\.142', 'e2save' => '192\.168\.[0-9]+\.[0-9]+', 'webdev' => '192\.168\.(223\.94|223\.188|222\.115|222\.73|222\.121|222\.142)', 'nickp' => '(10\.0\.0\.121|192\.168\.222\.1(58|40))');
    $alias = array_merge($alias, array('tyler' => '192\.168\.222\.190', 'mikes' => '192\.168\.223\.181', 'tq' => '192\.168\.222\.130', 'drs' => '192\.168\.222\.59'));

    $ipaddr1 = isset($alias[$addr]) ? $alias[$addr] : $addr;
    $ipaddr2 = '';
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipaddr2 = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ipaddr2 = $_SERVER['REMOTE_ADDR'];
    } elseif ($argc > 1) {
        $ipaddr2 = $argv[1];
    } else {
        $ipaddr2 = '127.0.0.1';
    }

    if (preg_match('/^' . $ipaddr1 . '$/i', $ipaddr2) || ($_REQUEST[$addr]) || ($_SESSION['employee']['username'] == $addr)) {
        if ($value) {
            printr($value);
            println('');
        }
        if ($exit) {
            exit();
        }
        return true;
    }
    return false;
}

function showDebugBuffer()
{
    if (isset($_COOKIE['webdev_test']) && $_COOKIE['webdev_test'] == 'internal_test') {
        if (count($GLOBALS['debugBuffer']) > 0) {
            $GLOBALS['debugBuffer'][] = '<hr/>END<hr/>';
            $GLOBALS['debugBuffer'] = array_map('esc', $GLOBALS['debugBuffer']);
            echo '<script type="text/javascript" language="Javascript">' . PHP_EOL;
            echo 'function openQueryDebug() {' . PHP_EOL;
            echo ' var w = window.open("about:blank", "windywoo", "width=1024,height=800,scrollbars=yes");' . "\n";
            foreach ($GLOBALS['debugBuffer'] as $line) {
                $line = str_replace("\n", "<br/>", $line);
                $line = str_replace("\r", "", $line);
                echo ' w.document.write("' . $line . '");' . PHP_EOL;
            }
            echo ' w.focus();' . PHP_EOL;
            echo ' return false;' . PHP_EOL;
            echo '}' . PHP_EOL;
            echo '</script>' . PHP_EOL;
            echo '<a href="" onclick="javascript:openQueryDebug(); return false;">Open Query Debug</a>';
        }
    }
}

//Added all other common functions
function functionsTest($para = 0)
{
    return "This is working function and parameter passed is " . $para;
}

function loadAppplicationCofig(){
    echo "HI";
}

function getNewConfigEnabledIP()
{
    $newConfigEnabled = array();
// TalkMobile
    $newConfigEnabled[] = '37.188.115.192';
    $newConfigEnabled[] = '37.188.115.202';
    $newConfigEnabled[] = '5.79.0.32';
    $newConfigEnabled[] = '10.178.5.175';
    $newConfigEnabled[] = '10.178.8.64';
    $newConfigEnabled[] = '10.178.8.85';
// Opal
    $newConfigEnabled[] = '192.168.1.2';
    $newConfigEnabled[] = '192.168.1.3';
    $newConfigEnabled[] = '192.168.1.17';
    $newConfigEnabled[] = '192.168.1.20';
    $newConfigEnabled[] = '192.168.1.27';
    $newConfigEnabled[] = '192.168.1.28';
    $newConfigEnabled[] = '213.246.148.35';
    $newConfigEnabled[] = '10.8.0.1';
// Mobiles cloud
    $newConfigEnabled[] = '162.13.89.142';
    $newConfigEnabled[] = '162.13.12.250';
    $newConfigEnabled[] = '162.13.86.147';
    $newConfigEnabled[] = '10.179.201.59';
    $newConfigEnabled[] = '10.179.130.53';
    $newConfigEnabled[] = '10.179.196.162';
// Cosmos cloud
    $newConfigEnabled[] = '162.13.193.132';
    $newConfigEnabled[] = '162.13.193.133';
    $newConfigEnabled[] = '162.13.193.134';
    $newConfigEnabled[] = '162.13.193.135';
    $newConfigEnabled[] = '134.213.83.26';
    $newConfigEnabled[] = '134.213.83.27';
    $newConfigEnabled[] = '134.213.44.216';
    $newConfigEnabled[] = '134.213.44.217';
    $newConfigEnabled[] = '10.181.161.246';
    $newConfigEnabled[] = '10.181.161.253';
    $newConfigEnabled[] = '10.181.128.214';
    $newConfigEnabled[] = '10.181.162.34';
    $newConfigEnabled[] = '10.181.195.98';
    $newConfigEnabled[] = '10.181.198.196';
    $newConfigEnabled[] = '10.181.198.222';
    $newConfigEnabled[] = '10.181.198.7';
// Webdev
    $newConfigEnabled[] = '192.168.222.16';
//Virtualbox
    $newConfigEnabled[] = '10.0.2.15';
// RBS QA
    $newConfigEnabled[] = '192.168.222.150';
// RBS
    $newConfigEnabled[] = '162.13.3.22';
    $newConfigEnabled[] = '162.13.3.229';
    $newConfigEnabled[] = '5.79.22.39';
    $newConfigEnabled[] = '10.179.3.53';
    $newConfigEnabled[] = '10.179.2.224';
    $newConfigEnabled[] = '10.178.135.95';
    $newConfigEnabled[] = '162.13.104.80';
// TalkTalk UAT
    $newConfigEnabled[] = '134.213.140.49';
//Vagrant
    $newConfigEnabled[] = '192.168.56.102';
    return $newConfigEnabled;
}

/*
* PHP doesnt support object casting????? so this is a way of fudging it.
*/
function cast($castAs, $object)
{
    if (is_subclass_of($object, $castAs)) {
        $temp = serialize($object);
        $temp = preg_replace('/O:(\d*):"(.*?)"/', "O:" . strlen($castAs) . ':"' . $castAs . '"', $temp);
        $object = unserialize($temp);
    } else {
        throw new InvalidCastException(get_class($object) . " is not a sublcass of " . $castAs);
    }

    return $object;
}

if (!function_exists('session_is_registered')) {
    function session_is_registered($x) {
        if (isset($_SESSION[$x])){
            return true;
        }
        return false;
    }
}

//Profiling function to find where value of $$watch changes
if ($_SERVER["SERVER_ADDR"] == '192.168.222.88' && $GLOBALS["HTTP_X_FORWARDED_FOR"] == '192.168.223.50' && false) {
    function profile($show = true){
        $watch = 'staticGifts';
        global $$watch;
        static $var = null;

        if ($var != $$watch) {
            $var = $$watch;
            if($show){
                echo "<pre>";
                print_r($var);
                echo "\n";
                debug_print_backtrace();
                echo "</pre>";
            }
        }
    }

    // Set up a tick handler
    register_tick_function("profile");

    // Initialize the function before the declare block
    profile(false);

    // Throw a tick every statement
    declare(ticks=1);
}

/**
 * A recursive version of array_map
 *
 * @param $func string
 * @param $arr array
 *
 * @return array
 */
function array_map_r($func, $arr)
{
    $newArr = array();

    foreach ($arr as $key => $value) {
        $newArr[$key] = (is_array($value) ? array_map_r($func, $value) : (is_array($func) ? call_user_func_array($func, $value) : $func($value)));
    }

    return $newArr;
}