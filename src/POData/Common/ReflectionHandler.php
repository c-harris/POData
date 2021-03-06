<?php

declare(strict_types=1);

namespace POData\Common;

use ReflectionException;
use ReflectionProperty;

/**
 * Class ReflectionHandler.
 * @package POData\Common
 */
class ReflectionHandler
{
    /**
     * @param $entryObject
     * @param $property
     *
     * @throws ReflectionException
     * @return mixed
     */
    public static function getProperty(&$entryObject, $property)
    {
        // If magic method exists, try that first, else try property directly
        if (method_exists($entryObject, '__get')) {
            $value = $entryObject->{$property};
        } else {
            if (null === $entryObject) {
                $msg = 'Property POData\\Common\\ReflectionHandler::$' . $property . ' does not exist';
                throw new ReflectionException($msg);
            }
            $reflectionProperty = new ReflectionProperty(get_class($entryObject), $property);
            $reflectionProperty->setAccessible(true);
            $value = $reflectionProperty->getValue($entryObject);
        }

        return $value;
    }

    /**
     * @param  object              $entity
     * @param  string              $property
     * @param  mixed               $value
     * @throws ReflectionException
     */
    public static function setProperty(&$entity, $property, $value)
    {
        // If magic method exists, try that first, else try property directly
        if (method_exists($entity, '__set')) {
            $entity->{$property} = $value;
        } else {
            $reflect   = new ReflectionProperty($entity, $property);
            $oldAccess = $reflect->isPublic();
            $reflect->setAccessible(true);
            $reflect->setValue($entity, $value);
            $reflect->setAccessible($oldAccess);
        }
    }
}
