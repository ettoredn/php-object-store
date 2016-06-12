<?php

namespace EttoreDN\PHPObjectStorage\ObjectStore;


use GuzzleHttp\Client;

interface ObjectStoreInterface extends \Countable
{
    /**
     * @param string $name
     * @return boolean
     */
    function exists(string $name): bool;

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
     * @param string $prefix
     * @param int $limit
     * @return array
     */
    function listObjectNames(string $prefix = '', int $limit = 0): array;

    /**
     * @return client Authenticated client
     */
    function getAuthenticatedClient(): Client;
}