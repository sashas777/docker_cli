<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2023  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Service\DataObject;

/**
 * Data object helper.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class DataObjectHelper
{
    /**
     * @param $dataObject
     * @param array $data
     * @param $interfaceName
     *
     * @return $this
     */
    public function populateWithArray($dataObject, array $data, $interfaceName)
    {
        $this->setDataValues($dataObject, $data, $interfaceName);
        return $this;
    }

    /**
     * Update Data Object with the data from array
     *
     * @param mixed $dataObject
     * @param array $data
     * @param string $interfaceName
     * @return $this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function setDataValues($dataObject, array $data, $interfaceName)
    {
        $dataObjectMethods = get_class_methods(get_class($dataObject));
        foreach ($data as $key => $value) {
            /* First, verify is there any setter for the key on the Service Data Object */
            $camelCaseKey = SimpleDataObjectConverter::snakeCaseToUpperCamelCase($key);
            $possibleMethods = [
                'set' . $camelCaseKey,
                'setIs' . $camelCaseKey,
            ];
            if ($methodNames = array_intersect($possibleMethods, $dataObjectMethods)) {
                $methodName = array_values($methodNames)[0];
                if (!is_array($value)) {
                    $dataObject->$methodName($value);
                } else {
                    $getterMethodName = 'get' . $camelCaseKey;
                    die('setComplexValue');
                    //$this->setComplexValue($dataObject, $getterMethodName, $methodName, $value, $interfaceName);
                }
            }
        }

        return $this;
    }
}