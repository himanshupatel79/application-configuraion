<?php

class Backbone_Base
{

    public function __construct()
    {
        include_once dirname(__FILE__) . '/../constants.php';
    }

    /**
     * @return string
     */
    public function test()
    {
        return "Cosmos Test";
    }

    /**
     * @param $array
     * @return array
     */
    private function parseIniAdvanced($array)
    {

        $returnArray = array();
        if (is_array($array)) {
            foreach ($array as $key => $value) {
                $element = explode(':', $key);
                if (!empty($element[1])) {
                    $data = array();

                    foreach ($element as $tkey => $environment) {
                        $data[$tkey] = trim($environment);
                    }

                    $data = array_reverse($data, true);
                    foreach ($data as $newKey => $newValue) {
                        $environment = $data[0];
                        if (empty($returnArray[$environment])) {
                            $returnArray[$environment] = array();
                        }
                        if (isset($returnArray[$data[1]])) {
                            $returnArray[$environment] = array_merge(
                                $returnArray[$environment],
                                $returnArray[$data[1]]
                            );
                        }
                        if ($newKey === 0) {
                            $returnArray[$environment] = array_merge(
                                $returnArray[$environment],
                                $array[$key]
                            );
                        }
                    }
                } else {
                    $returnArray[$key] = $array[$key];
                }
            }
        }
        return $returnArray;
    }

    /**
     * @param $array
     * @return array
     */
    private function recursiveParse($array)
    {
        $returnArray = array();
        if (is_array($array)) {
            foreach ($array as $key => $value) {
                if (is_array($value)) {
                    $array[$key] = $this->recursiveParse($value);
                }
                $elementData = explode('.', $key);

                if (!empty($elementData[1])) {
                    $elementData = array_reverse($elementData, true);
                    if (isset($returnArray[$key])) {
                        unset($returnArray[$key]);
                    }
                    if (!isset($returnArray[$elementData[0]])) {
                        $returnArray[$elementData[0]] = array();
                    }
                    $first = true;
                    foreach ($elementData as $newKey => $newValue) {
                        if ($first === true) {
                            $environmentDataArray = $array[$key];
                            $first = false;
                        }
                        $environmentDataArray = array($newValue => $environmentDataArray);
                    }
                    $returnArray[$elementData[0]] = array_merge_recursive(
                        $returnArray[$elementData[0]],
                        $environmentDataArray[$elementData[0]]
                    );
                } else {
                    $returnArray[$key] = $array[$key];
                }
            }
        }
        return $returnArray;
    }

    /**
     * @param $config_ini
     * @param $custom_ini
     * @return mixed
     */
    private function iniMerge12($config_ini, $custom_ini)
    {
        foreach ($custom_ini AS $key => $value):
            if (is_array($value)):
                $config_ini[$key] = $this->iniMerge12($config_ini[$key], $custom_ini[$key]);
            else:
                $config_ini[$key] = $value;
            endif;
        endforeach;
        return $config_ini;
    }

    /**
     * @param $array
     * @return array|bool
     */
    private function arrayFlatten($array)
    {
        if (!is_array($array)) {
            return false;
        }
        $result = array();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result = array_merge($result, $this->arrayFlatten($value));
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * @param $array
     * @return array
     */
    private function assoc2indexedMulti($array)
    {
        // initialize destination indexed array
        $indArr = array();
        // loop through source
        foreach ($array as $value) {
            // if the element is array call the recursion
            if (is_array($value)) {
                $indArr[] = $this->assoc2indexedMulti($value);
                // else add the value to destination array
            } else {
                $indArr[] = $value;
            }
        }
        return $indArr;
    }

    /**
     * @param $inifile
     * @return mixed
     */
    public function getConfig($inifile)
    {
        $cosmosLoc = str_replace(
            array('shared', 'website_engine/'),
            array('cosmos', ''),
            getenv('SHARED_BASE')
        );
        $array = parse_ini_file($cosmosLoc . '/bootstrap/common/' . $inifile, true);
        $array = $this->recursiveParse($this->parseIniAdvanced($array));

        if(defined(ENVIRONMENT))
            return $array[ENVIRONMENT];
        else
            return $array;
    }
}