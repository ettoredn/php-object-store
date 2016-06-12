<?php

namespace EttoreDN\PHPObjectStorage\ObjectStore;


interface ObjectStoreInterface
{
    /**
     * @param string $name
     * @return mixed
     */
    function exists(string $name);

    /**
     * @param string $name
     * @param mixed $content
     * @param bool $overwrite
     * @return mixed
     */
    function upload(string $name, $content, bool $overwrite = true);

    /**
     * @param array $files
     * @param array $options
     */
    function uploadBulk(array $files, array $options);

    /**
     * @param string|null $objectName
     * @return string
     */
    function getObjectUrl(string $objectName);

    /**
     * @param string $objectName
     * @return mixed
     */
    function delete(string $objectName);

    /**
     * @param array $options
     * @return array
     */
    function listObjects(array $options = []);
}