<?php

namespace PHPObjectStorageTest\ObjectStore;

use EttoreDN\PHPObjectStorage\ObjectStorage;
use EttoreDN\PHPObjectStorage\Exception\ObjectStoreException;

class ObjectStorageTest extends \PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        ObjectStorage::unregisterStreamWrapper(ObjectStorage::SWIFT);
    }

    /**
     * @return \EttoreDN\PHPObjectStorage\ObjectStore\SwiftObjectStore
     * @throws ObjectStoreException
     */
    private function getSwift()
    {
        $options = [] + json_decode(file_get_contents(__DIR__ .'/../auth.json'), true)['swift'];
        return ObjectStorage::getInstance(ObjectStorage::SWIFT, $options);
    }

    public function testRegisterStreamWrapper()
    {
        ObjectStorage::registerStreamWrapper(ObjectStorage::SWIFT);

        $this->assertContains('swift', stream_get_wrappers(), 'swift:// protocol not registered');
    }
    
    public function testSwiftWrapperRead()
    {
        ObjectStorage::registerStreamWrapper(ObjectStorage::SWIFT);

        $h = fopen('swift://media_uploads/text.txt', 'r');

        $read = fread($h, 5);
        $this->assertEquals('01234', $read);
        $read = fread($h, 1);
        $this->assertEquals('5', $read);

        $this->assertEquals(0, fseek($h, 15, SEEK_CUR));
        $read = fread($h, 1);
        $this->assertEquals('1', $read);


        $this->assertEquals(0, fseek($h, 20, SEEK_SET));
        $read = fread($h, 10);
        $this->assertEquals('0123456789', $read);

        $this->assertEquals(30, ftell($h));

        fclose($h);
    }

    public function testSwiftWrapperWrite()
    {
        ObjectStorage::registerStreamWrapper(ObjectStorage::SWIFT);

        $h = fopen('swift://media_uploads/swift.txt', 'w');

        $this->assertEquals(6, fwrite($h, 'ettore'), 'should write 6 bytes');
        fseek($h, 1, SEEK_SET);
        $this->assertEquals(2, fwrite($h, 'TT'), 'should write 2 bytes');
        $this->assertTrue(ftruncate($h, 4), 'cannot truncate');
        $this->assertEquals(4, fstat($h)['size'], 'Length expected to be 4 after being truncated');

        fclose($h);

        $this->assertTrue(unlink('swift://media_uploads/swift'), 'unlink error');
    }

    public function testSwiftWrapperAppend()
    {
        ObjectStorage::registerStreamWrapper(ObjectStorage::SWIFT);

        $h = fopen('swift://media_uploads/swift.txt', 'a+');

        $this->assertEquals(2, fwrite($h, 're'), 'should write 2 bytes');
        fseek($h, 0, SEEK_SET);
        $this->assertEquals('eT', fread($h, 2), 'should move the internal pointer for freads');
        $this->assertEquals(2, fwrite($h, 'dn'), 'should write 2 bytes');
        $this->assertEquals(8, fstat($h)['size'], 'should ignore fseek when appending');

        fclose($h);
    }
}
