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
    
    public function testExists() {
        $this->assertTrue(self::$store->exists('swift-test.txt'));
    }
    
    public function testDelete() {
        $this->assertFalse(self::$store->delete(time() . rand()));
    }

    public function testListNames() {
        $objects = self::$store->listObjectNames();
        $this->assertTrue(is_array($objects));
    }

    public function testUploadDownload() {
        $name = time() . rand(). '.txt';
        $content = '13112983u12938u13981u39821u39812u39821u39812u893u192';

        self::$store->upload($name, $content);
        $this->assertTrue(self::$store->exists($name));

        $this->assertEquals($content, self::$store->download($name), 'downloaded content differs from what was uploaded');
    }

    public function testUploadArchive() {
        $pathname = '/tmp/archive-test.tar';
        $a = new \PharData($pathname);
        $a->addFromString('archive-test_file-1.txt', 'hello world');
        $a->addFromString('archive-test/subfile-1.txt', 'hello me');
        self::$store->uploadArchive(new \SplFileInfo($pathname), 'tar');
        self::$store->uploadArchive(new \SplFileInfo($pathname), 'tar', 'uploadPath');

        $this->assertTrue(self::$store->exists('archive-test_file-1.txt'));
        $this->assertTrue(self::$store->exists('archive-test/subfile-1.txt'));
        $this->assertTrue(self::$store->exists('uploadPath/archive-test_file-1.txt'));
        $this->assertTrue(self::$store->exists('uploadPath/archive-test/subfile-1.txt'));
    }
}
