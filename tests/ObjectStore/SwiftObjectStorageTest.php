<?php

namespace PHPObjectStorageTest\ObjectStore;

use EttoreDN\PHPObjectStorage\ObjectStorage;
use EttoreDN\PHPObjectStorage\Exception\ObjectStoreException;
use EttoreDN\PHPObjectStorage\ObjectStore\SwiftObjectStore;

class SwiftObjectStorageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var SwiftObjectStore
     */
    private static $store;

    /**
     * @var array
     */
    private static $options;

    /**
     * @beforeClass
     * @throws ObjectStoreException
     */
    public static function getSwift()
    {
        self::$options = [] + json_decode(file_get_contents(__DIR__ .'/../auth.json'), true)['swift'];
        self::$store = ObjectStorage::getInstance(ObjectStorage::SWIFT, self::$options);
    }
    
    public function testContainer()
    {
        $this->assertEquals(self::$options['container'], self::$store->getContainer());
    }

    public function testAuthentication()
    {
        $token = self::$store->getTokenId();
        $this->assertTrue(is_string($token));
    }

    public function testSwiftEndpoint()
    {
        $endpoint = self::$store->getEndpoint();
        $this->assertTrue(is_string($endpoint));
    }

    public function testCountable()
    {
        $count = count(self::$store);
        $this->assertTrue(is_int($count));
    }
    
    public function testListNames()
    {
        $objects = self::$store->listObjectNames();
        $this->assertTrue(is_array($objects));
    }
}
