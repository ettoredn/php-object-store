<?php

namespace EttoreDN\PHPObjectStorage;


use EttoreDN\PHPObjectStorage\ObjectStore\SwiftObjectStore;
use EttoreDN\PHPObjectStorage\StreamWrapper\GoogleStreamWrapper;
use EttoreDN\PHPObjectStorage\StreamWrapper\S3StreamWrapper;
use EttoreDN\PHPObjectStorage\StreamWrapper\S3StreamWrapperInterface;
use EttoreDN\PHPObjectStorage\StreamWrapper\SwiftStreamWrapper;

class ObjectStorage
{
    const SWIFT = SwiftObjectStore::class;
    const GOOGLE = 'google_storage';
    const S3 = 'amazon_s3';
    
    
    public static function getInstance($class, array $options = [])
    {
        if (!$class)
            throw new ObjectStoreException('Type must be specified');
        
        // TODO configure region, get token, etc
        $store = new \ReflectionClass($class);
        return $store->newInstance($options);
    }

    public static function registerStreamWrapper($class, array $options = [])
    {
        switch ($class) {
            case self::SWIFT:
                $wrapperClass = SwiftStreamWrapper::class;
                break;
            case self::GOOGLE:
                $wrapperClass = GoogleStreamWrapper::class;
                break;
            case self::S3:
                $wrapperClass = S3StreamWrapper::class;
                break;
            default:
                throw new ObjectStorageException('Invalid wrapper');
        }

        $protocol = (new \ReflectionClass($wrapperClass))->getMethod('getProtocol')->invoke(null);

        if (!stream_wrapper_register($protocol, $wrapperClass))
            throw new ObjectStorageException('Unable to register stream wrapper protocol' . $protocol);
    }

    public static function getToken($class, array $options = [])
    {
        if ($class == self::SWIFT)
            return '2fe55833bac140a38945b92c4d99d9b2';
        
        throw new ObjectStorageException('Token type not supported');

//        $client = new Client([
//            'base_uri' => $config['authUrl'],
//            'handler'  => HandlerStack::create()
//        ]);
//
//        $identityService = Service::factory($client);
//
//        $this->authenticationToken = $identityService->generateToken();
    }
}