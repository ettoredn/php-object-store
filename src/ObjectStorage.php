<?php

namespace EttoreDN\PHPObjectStorage;


use EttoreDN\PHPObjectStorage\ObjectStore\ObjectStoreInterface;
use EttoreDN\PHPObjectStorage\ObjectStore\SwiftObjectStore;
use EttoreDN\PHPObjectStorage\StreamWrapper\SwiftStreamWrapper;
use EttoreDN\PHPObjectStorage\Exception\ObjectStorageException;


class ObjectStorage
{
    const SWIFT = SwiftObjectStore::class;
    const GOOGLE = 'google_storage';
    const S3 = 'amazon_s3';
    
    protected static $instances = [];
    
    
    public static function getInstance($class, array $options = [])
    {
        if (!$class)
            throw new ObjectStoreException('Type must be specified');

        if (!($class instanceof \ReflectionClass))
            $class = new \ReflectionClass($class);

        if (!$class->implementsInterface(ObjectStoreInterface::class))
            throw new ObjectStorageException(sprintf('Given class %s must implement %s', $class->getName(), ObjectStoreInterface::class));

        if (!array_key_exists($class->getName(), self::$instances))
            self::$instances[$class->getName()] = $class->newInstance($options);

        return self::$instances[$class->getName()];
    }

    /**
     * @param $class
     * @return bool
     * @throws ObjectStorageException
     */
    public static function registerStreamWrapper($class)
    {
        switch ($class) {
            case self::SWIFT:
                $wrapperClass = SwiftStreamWrapper::class;
                break;
//            case self::GOOGLE:
//                $wrapperClass = GoogleStreamWrapper::class;
//                break;
//            case self::S3:
//                $wrapperClass = S3StreamWrapper::class;
//                break;
            default:
                throw new ObjectStorageException('Invalid wrapper');
        }

        $protocol = (new \ReflectionClass($wrapperClass))->getMethod('getProtocol')->invoke(null);

        if (!stream_wrapper_register($protocol, $wrapperClass))
            throw new ObjectStorageException('Unable to register stream wrapper protocol' . $protocol);
    }

    public static function unregisterStreamWrapper($class)
    {
        switch ($class) {
            case self::SWIFT:
                $wrapperClass = SwiftStreamWrapper::class;
                break;
//            case self::GOOGLE:
//                $wrapperClass = GoogleStreamWrapper::class;
//                break;
//            case self::S3:
//                $wrapperClass = S3StreamWrapper::class;
//                break;
            default:
                throw new ObjectStorageException('Invalid wrapper');
        }

        $protocol = (new \ReflectionClass($wrapperClass))->getMethod('getProtocol')->invoke(null);

        @stream_wrapper_unregister($protocol);
    }
}