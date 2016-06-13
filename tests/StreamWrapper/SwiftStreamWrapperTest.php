<?php

namespace PHPObjectStorage\Test\ObjectStore;

use EttoreDN\PHPObjectStorage\Exception\StreamWrapperException;
use EttoreDN\PHPObjectStorage\ObjectStorage;
use EttoreDN\PHPObjectStorage\Exception\ObjectStoreException;
use EttoreDN\PHPObjectStorage\StreamWrapper\SwiftStreamWrapper;

class SwiftStreamWrapperTest extends \PHPUnit_Framework_TestCase
{
    const fixtureUrl = 'swift://test/swift-test.txt';
    const fixtureContent = '012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789';
    private static $fixtureSize;

    /** @beforeClass */
    public static function initFixture() {
        $options = [] + json_decode(file_get_contents(__DIR__ .'/../auth.json'), true)['swift'];
        
        ObjectStorage::registerStreamWrapper(SwiftStreamWrapper::class, $options);
        self::$fixtureSize = strlen(self::fixtureContent);
    }

    /** @afterClass */
    public static function removeFixture() {
//        unlink(self::fixtureUrl);
        ObjectStorage::unregisterStreamWrapper(SwiftStreamWrapper::class);
    }

    private function createFixture() {
        $h = fopen(self::fixtureUrl, 'w');
        $this->assertEquals(strlen(self::fixtureContent), fwrite($h, self::fixtureContent), 'should write 6 bytes');
        fclose($h);
    }


    public function testRegisterStreamWrapper() {
        $this->assertContains('swift', stream_get_wrappers(), 'swift:// protocol not registered');
    }

    public function testSwiftWrapperWrite() {
        $h = fopen('swift://test/swift-test-write.txt', 'w');

        $this->assertEquals(6, fwrite($h, 'ettore'), 'should write 6 bytes');
        fseek($h, 1, SEEK_SET);
        $this->assertEquals(2, fwrite($h, 'TT'), 'should write 2 bytes');
        $this->assertTrue(ftruncate($h, 4), 'cannot truncate');
        $this->assertEquals(4, fstat($h)['size'], 'Length expected to be 4 after being truncated');
        
        fclose($h);
    }

    public function testUrlStat() {
        $this->createFixture();
        $this->assertTrue(file_exists(self::fixtureUrl));
        $this->assertTrue(is_file(self::fixtureUrl));
        $this->assertTrue(is_writable(self::fixtureUrl));
        $this->assertTrue(is_readable(self::fixtureUrl));
        $this->assertFalse(is_executable(self::fixtureUrl));
        $this->assertEquals(0100666, fileperms(self::fixtureUrl));
        $this->assertFalse(is_dir(self::fixtureUrl));
        $this->assertFalse(is_link(self::fixtureUrl));
        $this->assertGreaterThan(0, filemtime(self::fixtureUrl));
        $this->assertGreaterThan(0, filectime(self::fixtureUrl));
        $this->assertGreaterThan(0, filesize(self::fixtureUrl));
    }
    
    public function testUnlink() {
        $this->createFixture();
        $this->assertTrue(unlink(self::fixtureUrl), 'unlink error');
    }
    
    public function testSwiftWrapperRead() {
        $this->createFixture();
        $h = fopen(self::fixtureUrl, 'r');

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

    public function testSwiftWrapperAppendPlus() {
        $this->createFixture();
        $h = fopen(self::fixtureUrl, 'a+');

        $this->assertEquals(2, fwrite($h, 're'), 'should write 2 bytes');
        fseek($h, 0, SEEK_SET);
        $this->assertEquals('01', fread($h, 2), 'should move the internal pointer for freads');
        $this->assertEquals(2, fwrite($h, 'dn'), 'should write 2 bytes');
        $this->assertEquals(self::$fixtureSize + 4, fstat($h)['size'], 'should ignore fseek when appending');

        fclose($h);
    }

    /**
     * @expectedException \EttoreDN\PHPObjectStorage\Exception\StreamWrapperException
     */
    public function testReadOnly() {
        $this->createFixture();
        $h = fopen(self::fixtureUrl, 'r');
        fwrite($h, 'test');
        fclose($h);
    }


}
