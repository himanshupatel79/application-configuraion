<?php

$sqlTime = 0;
$sqlQueries = 0;
$allMysqlQueries = array();
$debugMysqlQueries = false;

/**
 * This method implements to pick the master, reporting dbs from the replicated DB servers
 * @param $db
 * @param bool $sql
 * @return string
 */
function chooseDB($db, $sql = false)
{
    if (!isLocalDb($db)
        && !isSlaveDb($db) // I still haven't found it's matching case
        && !isForceMasterDB($db) // I still haven't found it's matching case
        && !isReadQuery($sql)
    ) {

        return preg_replace('/(_\d+|_select|_live|_local)$/', '', $db) . '_live';
    }

    if (isAffliateStatsQuery() || isIopsReportQuery()) {
        return preg_replace('/(_\d+|_select)$/', '', $db) . '_reporting';
    }

    return $db;
}

/**
 * Returns a value indicating whether a SQL statement is for read purpose.
 * @param string $sql the SQL statement
 * @return boolean whether a SQL statement is for read purpose.
 */
function isReadQuery($sql)
{
    $pattern = '/^\s*(SELECT|EXPLAIN|SHOW|DESCRIBE)\b/i';
    return preg_match($pattern, $sql) > 0;
}

/**
 * Returns true if DB name has number or 'local' in its name
 * @param $db
 * @return bool
 */
function isLocalDb($db)
{
    $pattern = '/_(\d+|local)$/i';
    return preg_match($pattern, $db) > 0;
}

/**
 * Returns true if DB name has slave in its name.
 * @param $db
 * @return bool
 */
function isSlaveDb($db)
{
    $pattern = '/^slave\d+$/i';
    return preg_match($pattern, $db) > 0;
}

/**
 * Returns true if DB name has forcemaster in its name.
 * @param $db
 * @return bool
 */
function isForceMasterDB($db)
{
    $pattern = '/forcemaster$/i';
    return preg_match($pattern, $db) > 0;
}

/**
 * Returns true if the current URL contains affiliates/stats
 * @return bool
 */
function isAffliateStatsQuery()
{
    $pattern = '/^\/affiliates\/stats/i';
    return preg_match($pattern, $_SERVER['REQUEST_URI']) > 0;
}

/**
 * Returns true if current URL contains e2internal/iops/all_new.php
 * @return bool
 */
function isIopsReportQuery()
{
    $pattern = '/^\/e2internal\/iops\/all_new.php/i';
    return preg_match($pattern, $_SERVER['REQUEST_URI']) > 0;
}

/**
 * This method implements to get the right DB server from the replicated DB servers, establishes
 * connection, executes query and returns the results.
 * @param $sql
 * @param $db
 * @param string $charset
 * @return resource
 * @throws MySQLException
 */
function mysqlQuery($sql, $db, $charset = 'latin1')
{
    global $currentCharset, $data;
    $sql = trim($sql);
    $db = chooseDB($db, $sql);

    $con = null;
    $elapsedTime = null;

    try {
        $con = getMysqlConnection($db);

        if (!isset($currentCharset) || $currentCharset != $charset) {
            $currentCharset = $charset;
            mysql_set_charset($currentCharset, $con);
        }
        $s = getMicrotime();

        $result = mysql_query($sql, $con);

        $elapsedTime = getMicrotime() - $s;

        if (Cosmos::getConfigValue('trace_queries')) {
            trace($db, $con, mysql_error($con), mysql_errno($con), $sql, $elapsedTime, $result);
        }
    } catch (MySQLException $e) {
        //retry to get the connection back if error code is 2006 or 2013
        $countDown = 10;
        while ($countDown > 0 && $GLOBALS['dev'] && ((mysql_ping($con) === false) || (in_array(mysql_errno($con), array(2006, 2013))))) {
            $con = getMysqlConnection($db, true);
            $result = mysql_query($sql, $con);
            //$errorNum = mysql_errno($con);
            $countDown--;
        }
        if (!empty($result)) {
            return $result;
        }

        /**
         * If the ENVIRONMENT setting equals to LIVE/PRODUCTION - based on the NEW CONFIG.
         * Send email to Administrator in case DB errors in LIVE
         */
        if (ENVIRONMENT === 'production') {
            mail(Cosmos::getParams('webdevE2SaveEmail'), "DB Connection Error.",
                $e .
                "\r\nServer Address = " . $_SERVER["SERVER_ADDR"] . "\r\nScript = " . $_SERVER["DOCUMENT_ROOT"] . $_SERVER["PHP_SELF"]);
        }

        //Log error here into file logger.
        throw new MySQLException($e->getMessage(), (int)$e->getCode(), $e);
    }

    return $result;
}

/**
 * This method establishes a DB connection.
 * @param $db
 * @param bool $reset
 * @return resource
 * @throws MySQLException
 */
function getMysqlConnection($db, $reset = false)
{

    $patternDbLbName = '/(_\d+|_select|_live|_local|_forcemaster|_reporting|_oreka)$/';
    $info = & $GLOBALS['dbConnections'][$db];

    if ($reset && isset($info['conn'])) {
        mysql_close($info['conn']);
        unset($info['conn']);
    }
    $db = preg_replace($patternDbLbName, '', $db);

    try {

        if (!isset($info['conn'])) {
            if ($info['conn'] = mysql_connect($info[0], $info[1], $info[2])) {
                //if (!is_resource($info['conn'])) return FALSE;
            } else {
                throw new MySQLException("Error in getting DB Connection" . mysql_error() . ' (Debug - DB=' . $db . ')');
            }
        }
        if (mysql_select_db($db == 'all' ? 'e2web' : $db, $info['conn'])) {
            return $info['conn'];
        } else {
            throw new MySQLException("Error in selecting DB." . mysql_error() . ' (Debug - DB=' . $db . ')');
        }

    } catch (Exception $e) {
        //Log exception here and throw again.
        throw new MySQLException($e->getMessage(), (int)$e->getCode(), $e);
    }
}

function mysqlExplainRowCount($sql, $db, $limit = false)
{
    if ($limit != false) {
        //	$sql = substr($sql, 0, strpos(strtolower($sql), " limit ")). " limit 0,".$limit;
    }

    $sql = "EXPLAIN $sql";
    $rs = mysqlQuery($sql, $db);
    $totalrows = 0;
    while ($row = mysql_fetch_assoc($rs)) {
        if ($row['table'] == 'e2customers') {
            $rowmult = 3;
        } elseif ($row['table'] == 'ordsinprogress') {
            $rowmult = 1.2;
        } elseif ($row['table'] == 'orderlines' || strpos($row['table'], "orderline") !== false) {
            $rowmult = 1.4;
        } elseif (in_array($row['table'], array('handsets', 'networks', 'tariffs'))) {
            $rowmult = 2;
        } else {
            $rowmult = 1;
        }
        if (strpos($row['Extra'], 'Using filesort') !== false) {
            $rowmult = $rowmult * 1.4;
        }

        $totalrows += ($row['rows'] * $rowmult);
    }
    return $totalrows;
}

function mysqlCarefulQuery($sql, $db, $limit = 20000, $rowLimit = false)
{
    $rows = mysqlExplainRowCount($sql, $db, $rowLimit);

    if ($rows > $limit && strpos('reporting', $db) !== false) {
        throw new TooManyRowsException('Unsafe SQL - too many rows to process');
    }
    return mysqlQuery($sql, $db);
}

function mysqlInsertId()
{
    for ($i = count($GLOBALS['allMysqlQueries']) - 1; $i >= 0; $i--) {
        if (preg_match('/^replace|insert/i', $GLOBALS['allMysqlQueries'][$i]['q'])) {
            return $GLOBALS['allMysqlQueries'][$i]['insert_id'];
        }
    }

    return false;
}

function mysqlAffectedRows()
{
    for ($i = count($GLOBALS['allMysqlQueries']) - 1; $i >= 0; $i--) {
        if (preg_match('/^(replace|insert|update|delete)/i', $GLOBALS['allMysqlQueries'][$i]['q'])) {
            return $GLOBALS['allMysqlQueries'][$i]['num'];
        }
    }

    return false;
}

function mysqlRealEscapeString($string, $db = 'all')
{
    $con = getMysqlConnection($db);
    return mysql_real_escape_string($string, $con);
}


class MySQLException extends Exception
{
    global $currentCharset;

    /**
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        return 'MySQL Exception';
    }
}

class MySQLErrorException extends MySQLException
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        return 'MySQLError Exception,';
    }
}

class TooManyRowsException extends MySQLException
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        return 'TooMany Rows Exception';
    }
}

/**
 * This method is to trace DB queries and prints on current page.
 * @param $db
 * @param $con
 * @param $errorMsg
 * @param $errorNum
 * @param $sql
 * @param $elapsedTime
 * @param $data
 */
function trace($db, $con, $errorMsg, $errorNum, $sql, $elapsedTime, $data)
{
    // Only append debug trace information to query on IOPS or webdev. Improves MySQL query caching
    if (preg_match('/(iops|intranet)/', getenv('DOCROOT_BASE')) || preg_match('/(iops|intranet)/', $_SERVER['DOCUMENT_ROOT'])) {
        $trace = $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'] . "\n";
        if (isset($_GET['dbtrace']) && $GLOBALS['dev']) {
            $debug = debug_backtrace();
            foreach ($debug as $t) {
                if (strpos($t['file'], '/model.class.php') !== false) continue;
                $trace .= "<small><br>{$t['file']} #{$t['line']}</small>";
                break;
            }
        }

        if (is_array($_SESSION) && $_SESSION['employee']['username']) {
            $trace .= " ({$_SESSION['employee']['username']})";
        } elseif (is_array($_SESSION) && $_SESSION['EmployeeUserObject']) {
            $user = @unserialize($_SESSION['EmployeeUserObject']);
            $trace .= " ({$user->username})";
        }

        $sql .= "\n/*\n" . str_replace('*/', '* /', $trace) . '*/';
    }

    if ($GLOBALS['debugMysqlQueries'] == true || preg_match('/^(replace|insert|update|delete)/i', $sql)) {
        $queryStats = array(
            'index' => count($GLOBALS['allMysqlQueries']),
            'q' => $sql,
            'time' => $elapsedTime,
            'num' => mysql_affected_rows($con),
            'insert_id' => mysql_insert_id($con),
            'db' => $db,
        );

        $GLOBALS['allMysqlQueries'][] = $queryStats;
    }

    if ($elapsedTime > 10 || ($elapsedTime > 2 && $db == 'e2web')) {
        $str = date('Y-m-d H:i:s') . "\n";
        $str .= "Long query: " . number_format($elapsedTime, 3) . " seconds\n";
        $str .= $sql;
        $str .= "\n\n";

        $f = @fopen('/var/log/mysql/mysqlQuerySlow', 'a');
        if ($f) {
            fwrite($f, $str);
            fclose($f);
        } else {
            mail(Cosmos::getParams('webdevE2SaveEmail'), "Error opening /var/log/mysql/mysqlQuerySlow", "Server Address = " . $_SERVER["SERVER_ADDR"] . "\r\nScript = " . $_SERVER["DOCUMENT_ROOT"] . $_SERVER["PHP_SELF"] . "\r\nDetails = \r\n" . $str);
        }
    }

    if ($errorNum) {
        $str = date('Y-m-d H:i:s') . "\n";
        $str .= $errorMsg . " (" . $errorNum . ")\n";
        $str .= $sql;
        $str .= "\n\n";

        $f = @fopen('/var/log/mysql/mysqlQueryErrors', 'a');
        if ($f) {
            fwrite($f, $str);
            fclose($f);
        } else {
            mail(Cosmos::getParams('webdevE2SaveEmail'), "Error opening /var/log/mysql/mysqlQueryErrors", "Server Address = " . $_SERVER["SERVER_ADDR"] . "\r\nScript = " . $_SERVER["DOCUMENT_ROOT"] . $_SERVER["PHP_SELF"] . "\r\nDetails = \r\n" . $str);
        }

        /** VENU-- Delete this GLOBAL variable all over the project with proper exception handling.
         *
         * if ($GLOBALS['throw_mysql_exceptions']) {
         * throwMysqlError("MySQL: $errorMsg ($errorNum)");
         * }
         * */

    }

    if ($GLOBALS['debug'] & DEBUG_TO_SCREEN) {
        if (true) {
            printf("<pre class=\"query\">\n");
            printf("<p>%s</p>\n", $sql);
            printf("<p>Affected <b>%s</b> rows, returned <b>%s</b> rows.</p>\n", mysql_affected_rows($con), is_resource($data) ? mysql_num_rows($data) : 0);
            printf("<p>Query took <b>%s</b> seconds.</p>\n", number_format($elapsedTime, 3));
            echo '<p>Server: ', $GLOBALS['dbConnections'][$db][0], '</p>';
            printf("</pre>\n");
            printf("<hr/>\n");
        }

        if ($GLOBALS['debug'] & DEBUG_TO_HTML) {
            $GLOBALS['debugBuffer'][] = $sql;
            $GLOBALS['debugBuffer'][] = sprintf("<p>Affected <b>%s</b> rows, returned <b>%s</b> rows.</p>\n", mysql_affected_rows($con), is_resource($data) ? mysql_num_rows($data) : 0);
            $GLOBALS['debugBuffer'][] = "<p>Query took <b>" . number_format($elapsedTime, 3) . "</b> seconds.</p>";
        }

        if ($GLOBALS['debug'] & DEBUG_TO_HTML) {
            if ($GLOBALS['dev']) {
                $tmp = debug_backtrace();
                $c = $tmp[1]['class'] . $tmp[1]['type'] . $tmp[1]['function'] . '()';
                $GLOBALS['debugBuffer'][] = '<p>Source: <b>' . $tmp[1]['file'] . '</b> on line ' . $tmp[1]['line'] . "</p>";
                $GLOBALS['debugBuffer'][] = '<p>Call: <b>' . $c . '</b></p>' . "";

                if (mysql_affected_rows($con) < 0) {
                    $GLOBALS['debugBuffer'][] = '<p>MySQL Error: <b>' . mysql_error($con) . '</b></p>';
                    $GLOBALS['debugBuffer'][] = '<p>Connection Info: <b>' . implode(', ', $GLOBALS['dbConnections'][$db]) . '</b></p>';
                }

            }
            $GLOBALS['debugBuffer'][] = '<hr/>';
        }


        $GLOBALS['sqlTime'] += $elapsedTime;
        $GLOBALS['sqlQueries']++;
    }

}

function throwMysqlError($message)
    {
        error_log($message);
        if (Cosmos::getConfigValue('throw_mysql_exceptions')) {
            throw new MySQLErrorException($message);
        }
    }

    return false;
}

/* ---------------------------------------------------------------------- */

function mysqlAffectedRows()
{
    for ($i = count($GLOBALS['allMysqlQueries']) - 1; $i >= 0; $i--) {
        if (preg_match('/^(replace|insert|update|delete)/i', $GLOBALS['allMysqlQueries'][$i]['q'])) {
            return $GLOBALS['allMysqlQueries'][$i]['num'];
        }
    }

    return false;
}

/* ---------------------------------------------------------------------- */

function mysqlRealEscapeString($string, $db = 'all')
{
    $con = getMysqlConnection($db);
    return mysql_real_escape_string($string, $con);
}

/* ---------------------------------------------------------------------- */
