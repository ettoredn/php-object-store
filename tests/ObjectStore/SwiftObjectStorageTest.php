<?php

namespace PHPObjectStorageTest\ObjectStore;

use EttoreDN\PHPObjectStorage\ObjectStorage;
use EttoreDN\PHPObjectStorage\Exception\ObjectStoreException;

class SwiftObjectStorageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @return \EttoreDN\PHPObjectStorage\ObjectStore\SwiftObjectStore
     * @throws ObjectStoreException
     */
    private function getSwift()
    {
        $options = [] + json_decode(file_get_contents(__DIR__ .'/../auth.json'), true)['swift'];
        return ObjectStorage::getInstance(ObjectStorage::SWIFT, $options);
    }

    public function testAuthentication()
    {
        $token = $this->getSwift()->getTokenId();
        $this->assertTrue(is_string($token));
    }

    public function testSwiftEndpoint()
    {
        /** @var \EttoreDN\PHPObjectStorage\ObjectStore\SwiftObjectStore $swift */
        $endpoint = $this->getSwift()->getEndpoint();
        $this->assertTrue(is_string($endpoint));
    }
}
