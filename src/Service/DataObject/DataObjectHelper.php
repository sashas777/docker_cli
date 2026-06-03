<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2026  Edeavour Inc. (https://www.sashas.org)
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
                    $this->setComplexValue($dataObject, $getterMethodName, $methodName, $value, $interfaceName);
                }
            }
        }

        return $this;
    }

    /**
     * Set complex nested objects or fallback to array value.
     *
     * @param mixed $dataObject
     * @param string $getterMethodName
     * @param string $methodName
     * @param array $value
     * @param string $interfaceName
     * @return void
     */
    protected function setComplexValue($dataObject, string $getterMethodName, string $methodName, array $value, string $interfaceName)
    {
        if (method_exists($dataObject, $getterMethodName)) {
            $existingValue = $dataObject->$getterMethodName();
            if (is_object($existingValue)) {
                $this->setDataValues($existingValue, $value, get_class($existingValue));
                $dataObject->$methodName($existingValue);
                return;
            }
        }

        try {
            $reflection = new \ReflectionMethod($dataObject, $methodName);
            $parameters = $reflection->getParameters();
            if (count($parameters) > 0) {
                $firstParam = $parameters[0];
                $type = $firstParam->getType();
                if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                    $className = $type->getName();

                    if (interface_exists($className)) {
                        $concreteClassName = preg_replace('/Interface$/', '', $className);
                        if (class_exists($concreteClassName)) {
                            $className = $concreteClassName;
                        }
                    }

                    if (class_exists($className)) {
                        $nestedObject = new $className();
                        $this->setDataValues($nestedObject, $value, $className);
                        $dataObject->$methodName($nestedObject);
                        return;
                    }
                }
            }
        } catch (\ReflectionException $e) {
            // Ignore reflection errors and fall back to setting value directly
        }

        $dataObject->$methodName($value);
    }
}