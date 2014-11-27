<?php

/**
 * Class ImportCache
 *
 * Caching class for import calls
 *
 */
class Import_Cache
{
    const classExtension = ".class.php";
    const defaultClassesDirectory = "/classes/";
    const inspectFileContents = false;
    const addSingleFilesToCache = false;
    const processSubDirs = true;

    /**
     * Populate search paths global
     */
    public static function init()
    {
        if (empty($GLOBALS['importPackages'])) {

            $GLOBALS['importPackages'] = array();
            if (empty($GLOBALS['searchPaths'])) {
                $GLOBALS['searchPaths'] = array(
                    $GLOBALS['docRoot']['shared'] . self::defaultClassesDirectory,
                    $GLOBALS['docRoot']['classes'],
                    $GLOBALS['docRoot']['cosmos']
                );

//Special case for deferredDBUpdater
                if (count($GLOBALS['argv']) && is_dir(dirname($GLOBALS['argv'][0]) . self::defaultClassesDirectory)) {
                    array_push($GLOBALS['searchPaths'], dirname($GLOBALS['argv'][0]) . self::defaultClassesDirectory);
                }

                if ($_SERVER['SCRIPT_FILENAME'] &&
                    is_dir(dirname($_SERVER['SCRIPT_FILENAME']) . self::defaultClassesDirectory)
                ) {
                    array_push($GLOBALS['searchPaths'],
                        dirname($_SERVER['SCRIPT_FILENAME']) . self::defaultClassesDirectory);
                }
            }

        }
    }

    /**
     * Auto loader
     *
     * @param $className
     * @return bool
     */
    public static function autoLoad($className)
    {
        global $webdev;

        $loadSuccess = false;

        self::init();
        if (!empty($className)) {
            $path = self::_getClassPathName($className);
            if (!empty($path)) {
                if ($webdev && (!isset($GLOBALS['phpUnit']) || !$GLOBALS['phpUnit'])) {
                    include_once($path);
                } else {
                    @include_once($path);
                }
                $loadSuccess = true;
            } else {
                $loadSuccess = false;
            }
        }

        return $loadSuccess;
    }

    /**
     * Main entry point for adding classes to the cache
     *
     * @param $package
     */
    public static function import($package)
    {
        debugmark($package);
        self::init();
        if (!in_array($package, $GLOBALS['importPackages'])) {
            array_push($GLOBALS['importPackages'], $package);

            $package = self::_convertPackageNameToPath($package);
            if (basename($package) != '*') {
                if (self::addSingleFilesToCache) {
                    self::_addSingleFileToCache($package);
                } else {
                    self::_includeSingleFile($package);
                }
            } else {
                self::_addDirectoryToCache(dirname($package));
            }
        }
    }

    /**
     * Fetch the absolute path from the cache for a given class name
     *
     * @param $className
     * @return string
     */
    private static function _getClassPathName($className)
    {
        $path = "";
        if (array_key_exists($className, $GLOBALS['importCache'])) {
            $path = $GLOBALS['importCache'][$className]['path'];
        }
        if (array_key_exists(strtolower($className), $GLOBALS['importCache'])) {
            $path = $GLOBALS['importCache'][strtolower($className)]['path'];
        }
        return $path;
    }

    /**
     * Where the package refers to a single file rather than a directory
     * include the file directly bypassing the cache
     *
     * @param $package
     */
    private static function _includeSingleFile($package)
    {
        foreach ($GLOBALS['searchPaths'] as $path) {
            $fileName = $path . $package . self::classExtension;
            if (file_exists($fileName)) {
                include_once($fileName);
            }
        }
        return;
    }

    /**
     * Add a single file package to the cache
     * Caters for multiple classes defined within one physical file
     *
     * @param $package
     */
    private static function _addSingleFileToCache($package)
    {
        foreach ($GLOBALS['searchPaths'] as $path) {
            $fullPathFileName = $path . $package . self::classExtension;
            if (file_exists($fullPathFileName)) {
                $classes = self::_extractClassNamesFromFile($fullPathFileName);
                for ($index = 0; $index < sizeof($classes[0]); $index++) {
                    self::_appendToCache($fullPathFileName, $classes[0][$index]);
                }
            }
        }
    }

    /**
     * Recursively add all classes in the directory and sub directories
     * from a given root path
     *
     * @param $rootDir
     */
    private static function _addDirectoryToCache($rootDir)
    {
        foreach ($GLOBALS['searchPaths'] as $path) {
            if (is_dir($path . $rootDir)) {

//Find classes defined in files in this dir
                $handle = opendir($path . $rootDir);
                while ($entry = readdir($handle)) {
                    if ($entry != '.' && $entry != '..' && strpos($entry, '.class.php') != -1) {
                        $classes = self::_extractClassNamesFromFile($path . $rootDir . '/' . $entry);
                        for ($index = 0; $index < sizeof($classes[0]); $index++) {
                            self::_appendToCache($path . $rootDir . '/' . $entry, $classes[0][$index]);
                        }
                    }
                }

//Recursively process any sub dirs
                if (self::processSubDirs) {
                    $subDirs = glob($path . $rootDir . '/*', GLOB_ONLYDIR);
                    foreach ($subDirs as $dir) {
                        $dir = substr($dir, strpos($dir, $rootDir));
                        self::_addDirectoryToCache($dir);
                    }
                }
            }
        }
    }

    /**
     * Perform translation on the package name to convert it into a partial path
     *
     * @param $package
     * @return mixed
     */
    private static function _convertPackageNameToPath($package)
    {
        return str_replace('.', '/', $package);
    }

    /**
     * inspect the contents of a given file to analyse the classes and interfaces
     * contained within
     *
     * @param $fullPathFileName
     * @return array
     */
    private static function _extractClassNamesFromFile($fullPathFileName)
    {
        if (self::inspectFileContents) {
            $fileContents = file_get_contents($fullPathFileName);
            preg_match_all('/class[\s\n]+([a-zA-Z0-9_]+)[\s\na-zA-Z0-9_]+/', $fileContents, $classes);
            array_shift($classes);
            if (empty($classes[0])) {
                preg_match_all('/interface[\s\n]+([a-zA-Z0-9_]+)[\s\na-zA-Z0-9_]+/', $fileContents, $classes);
                array_shift($classes);
            }
        } else {
            $pathParts = explode("/", $fullPathFileName);
            $fileName = $pathParts[sizeof($pathParts) - 1];
            $fileName = substr($fileName, 0, strpos($fileName, '.class.php'));
            $classes = array(
                array(
                    $fileName
                )
            );
        }

        return $classes;
    }

    /**
     * Add a class and it's associated source file to the cache
     *
     * @param $fullPathFileName
     * @param $className
     */
    private static function _appendToCache($fullPathFileName, $className)
    {
        if (!empty($fullPathFileName) && !empty($className) && empty($GLOBALS['importCache'][strtolower($className)])) {
            $GLOBALS['importCache'][strtolower($className)] = array(
                "path" => $fullPathFileName
            );
        }
    }
}