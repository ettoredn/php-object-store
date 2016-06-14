<?php

namespace EttoreDN\PHPObjectStorage;


use EttoreDN\PHPObjectStorage\Exception\ObjectStoreException;
use EttoreDN\PHPObjectStorage\ObjectStore\ObjectStoreInterface;
use EttoreDN\PHPObjectStorage\ObjectStore\SwiftObjectStore;
use EttoreDN\PHPObjectStorage\StreamWrapper\StreamWrapperInterface;
use EttoreDN\PHPObjectStorage\Exception\ObjectStorageException;


class ObjectStorage
{
    const SWIFT = SwiftObjectStore::class;
    
    protected static $instancesCount = [];


    /**
     * @param $class
     * @param array $options
     * @return mixed
     * @throws ObjectStorageException
     * @throws ObjectStoreException
     * 
     * TODO no singletons, count instances
     */
    public static function getInstance($class, array $options = [])
    {
        if (!($class instanceof \ReflectionClass))
            $class = new \ReflectionClass($class);

        if (!$class->implementsInterface(ObjectStoreInterface::class))
            throw new ObjectStorageException(sprintf('Given class %s must implement %s', $class->getName(), ObjectStoreInterface::class));

        $instance = $class->newInstance($options);
        
        if (!array_key_exists($class->getName(), self::$instancesCount))
            self::$instancesCount[$class->getName()] = 0;

        self::$instancesCount[$class->getName()]++;
        
        return $instance;
    }

    /**
     * @param $class
     * @param array $options
     * @return bool
     * @throws ObjectStorageException
     */
    public static function registerStreamWrapper($class, array $options = [])
    {
        if (!($class instanceof \ReflectionClass))
            $class = new \ReflectionClass($class);
        
        if (!$class->implementsInterface(StreamWrapperInterface::class))
            throw new ObjectStorageException(sprintf('Given stream wrapper class %s must implement %s', $class->getName(), StreamWrapperInterface::class));

        $class->setStaticPropertyValue('options', $options);
        $protocol = $class->getMethod('getProtocol')->invoke(null);

        if (!stream_wrapper_register($protocol, $class->getName(), STREAM_IS_URL))
//        if (!stream_wrapper_register($protocol, $class->getName()))
            throw new ObjectStorageException('Unable to register stream wrapper protocol' . $protocol);
    }

    public static function unregisterStreamWrapper($class)
    {
        if (!($class instanceof \ReflectionClass))
            $class = new \ReflectionClass($class);

        if (!$class->implementsInterface(StreamWrapperInterface::class))
            throw new ObjectStorageException(sprintf('Given stream wrapper class %s must implement %s', $class->getName(), StreamWrapperInterface::class));

        $protocol = $class->getMethod('getProtocol')->invoke(null);

        @stream_wrapper_unregister($protocol);
    }
}