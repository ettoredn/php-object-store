<?php


use EttoreDN\PHPObjectStorage\ObjectStorage;

class ObjectStorageTest extends PHPUnit_Framework_TestCase
{
    public function testStuff()
    {
        $token = ObjectStorage::getToken(ObjectStorage::SWIFT);

        $this->assertTrue(!empty($token));
    }

    public function testRegisterStreamWrapper()
    {
        ObjectStorage::registerStreamWrapper(ObjectStorage::SWIFT);
        ObjectStorage::registerStreamWrapper(ObjectStorage::S3);
        ObjectStorage::registerStreamWrapper(ObjectStorage::GOOGLE);

        $this->assertContains('swift', stream_get_wrappers(), 'swift:// protocol registered');
        $this->assertContains('s3', stream_get_wrappers(), 's3:// protocol registered');
        $this->assertContains('gstorage', stream_get_wrappers(), 'gstorage:// protocol not registered');
    }
}
