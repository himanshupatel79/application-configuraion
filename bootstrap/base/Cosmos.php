<?php
/**
 * This class is to make new config array available to all the files.
 */
//namespace cosmos\base;
//downgraded to 5.2
//use cosmos\base\UnknownPropertyException;

class Cosmos
{
    public static $config;

    public $params = array();

    /**
     * Returns the fully qualified name of this class.
     *
     * @return string the fully qualified name of this class.
     */
    public static function className()
    {
        //downgrade to 5.2
        //return get_called_class();
        return get_class();
    }

    /**
     * Constructor.
     *
     * @param array $config name-value pairs that will be used to initialize the object properties
     */
    public function __construct($config = array())
    {
        if (!empty($config)) {
            self::$config = $config;

        }
        $this->init();
    }

    /**
     * Initializes the object.
     * This method is invoked at the end of the constructor after the object is initialized with the
     * given configuration.
     */
    public function init()
    {
    }

    /**
     * Return the Configuration array
     *
     */
    public static function  getConfig()
    {
        return self::$config;
    }

    /**
     * Return the value from the Configuration array
     * @param $name
     *
     * @return mixed
     * @throws UnknownPropertyException if the property is not defined
     */
    public static function  getConfigValue($name)
    {
       // if(isset(static::$config[$name])){
            //return static::$config[$name];
            return self::getValue(self::$config, $name);
       /* }else {
           throw new UnknownPropertyException('Getting unknown property: ' . get_class($this) . '::' . $name);
        }*/
    }

    /**
     * Returns value from
     * @param      $key
     * @param null $default
     *
     * @return mixed
     */
    public static function getParams($key, $default = null)
    {
        $array = 'params';
        $params = self::$config[$array];
        return self::getValue($params, $key, $default);
    }

    /**
     * Set an array item to a given value using "dot" notation.
     *
     * If no key is given to the method, the entire array will be replaced.
     * NOTE: If the existing value of a key is not an array, and if we are adding an array value to that key
     * The existing value will be overridden.
     *
     * @param  array   $array
     * @param  string  $key
     * @param  mixed   $value
     *
     * @return array
     */
    public static function setVal(&$array, $key, $value)
    {
        if (is_null($key)) return $array = $value;

        $keys = explode('.', $key);

        while (count($keys) > 1)
        {
            $key = array_shift($keys);

            // If the key doesn't exist at this depth, we will just create an empty array
            // to hold the next value, allowing us to create the arrays to hold final
            // values at the correct depth. Then we'll keep digging into the array.
            if ( ! isset($array[$key]) || ! is_array($array[$key]))
            {
                $array[$key] = array();
            }

            $array =& $array[$key];
        }

        $array[array_shift($keys)] = $value;

        return $array;
    }

     /**
      * This method will set the single array value to the Application's config array
      *
      * The key may be specified in a dot format to set the value.
      * @param $key
      * @param $value
      */
    public static function setConfigValue($key, $value)
    {
        if (($pos = strrpos($key, '.')) !== false) {
            self::setVal(self::$config, $key, $value);
        }else{
            self::$config[$key] = $value;
        }

        //Below is a copy of GLOBALS managing, until we solve the GLOBALS ISSUE,
        //So the value of a GLOBAL will be available in both static array and GLOBALS []
        $GLOBALS[$key] = $value;
    }

    /**
     * Retrieves the value of an array element or object property with the given key or property name.
     * If the key does not exist in the array or object, the default value will be returned instead.
     *
     * The key may be specified in a dot format to retrieve the value of a sub-array or the property
     * of an embedded object. In particular, if the key is `x.y.z`, then the returned value would
     * be `$array['x']['y']['z']` or `$array->x->y->z` (if `$array` is an object). If `$array['x']`
     * or `$array->x` is neither an array nor an object, the default value will be returned.
     * Note that if the array already has an element `x.y.z`, then its value will be returned
     * instead of going through the sub-arrays.
     *
     * Below are some usage examples,
     *
     * ~~~
     * // working with array
     * $username = Application::getValue($_POST, 'username');
     * // working with object
     * $username = \cosmos\base\Application::getValue($user, 'username');
     * // working with anonymous function
     * $fullName = \cosmos\base\Application::getValue($user, function ($user, $defaultValue) {
     *     return $user->firstName . ' ' . $user->lastName;
     * });
     * // using dot format to retrieve the property of embedded object
     * $street = \cosmos\base\Application::getValue($users, 'address.street');
     * ~~~
     *
     * @param array|object $array array or object to extract value from
     * @param string|\Closure $key key name of the array element, or property name of the object,
     * or an anonymous function returning the value. The anonymous function signature should be:
     * `function($array, $defaultValue)`.
     * @param mixed $default the default value to be returned if the specified array key does not exist. Not used when
     * getting value from an object.
     * @return mixed the value of the element if found, default value otherwise
     */
    public static function getValue($array, $key, $default = null)
    {
      //downgraded to 5.2 - to test without Closure
/*        if ($key instanceof \Closure) {
            return $key($array, $default);
        }*/

        if (is_array($array) && array_key_exists($key, $array)) {
            return $array[$key];
        }

        if (($pos = strrpos($key, '.')) !== false) {
            $array = self::getValue($array, substr($key, 0, $pos), $default);
            $key = substr($key, $pos + 1);
        }

        if (is_object($array)) {
            return $array->$key;
        } elseif (is_array($array)) {
            return array_key_exists($key, $array) ? $array[$key] : $default;
        } else {
            return $default;
        }
    }

    /**
     * Removes an item from an array and returns the value. If the key does not exist in the array, the default value
     * will be returned instead.
     *
     * Usage examples,
     *
     * ~~~
     * // $array = ['type' => 'A', 'options' => [1, 2]];
     * // working with array
     * $type = \Cosmos::remove($array, 'type');
     * // $array content
     * // $array = ['options' => [1, 2]];
     * ~~~
     *
     * @param array $array the array to extract value from
     * @param string $keys key name of the array element
     * @param default
     */
    public static function remove(&$array, $keys, $default)
    {
        $original =& $array;

        foreach ((array) $keys as $key)
        {
            $parts = explode('.', $key);
            while (count($parts) > 1)
            {
                $part = array_shift($parts);

                if (isset($array[$part]) && is_array($array[$part]))
                {
                    $array =& $array[$part];
                }
            }

            unset($array[array_shift($parts)]);

            // clean up after each pass
            $array =& $original;
        }
        return $default;
    }

    /**
     * @param string $key key name of the array element
     * @param mixed $default the default value to be returned if the specified key does not exist
     * @return mixed|null the value of the element if found, default value otherwise
     */
    public static function removeConfigValue($key, $default = null)
    {
        $value = self::remove(self::$config, $key, $default);
        return $value;
    }
}
