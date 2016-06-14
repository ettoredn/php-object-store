<?php

namespace EttoreDN\PHPObjectStorage\ObjectStore;


use EttoreDN\PHPObjectStorage\Exception\ObjectStoreException;
use GuzzleHttp\Client;

interface ObjectStoreInterface extends \Countable
{
    /**
     * @param string $name
     * @return boolean
     * @throws ObjectStoreException
     */
    function exists(string $name): bool;

    /**
     * @param string $name
     * @param mixed $content
     * @param bool $overwrite
     * @return mixed
     * @throws ObjectStoreException
     */
    function upload(string $name, $content, bool $overwrite = true);

    /**
     * @param \SplFileInfo $archive
     * @param string $format
     * @param string $uploadPath
     * @return
     * @throws ObjectStoreException
     */
    function uploadArchive(\SplFileInfo $archive, string $format, string $uploadPath = '');

    /**
     * @param string $name
     * @return string
     * @throws ObjectStoreException
     */
    function download(string $name): string;

    /**
     * @param string $name
     * @return mixed
     * @throws ObjectStoreException
     */
    function delete(string $name): bool;

    /**
     * @param string $prefix
     * @param int $limit
     * @return array
     * @throws ObjectStoreException
     */
    function listObjectNames(string $prefix = '', int $limit = 0): array;

    /**
     * @param array $defaults
     * @return Client Authenticated client
     * @throws ObjectStoreException
     */
    function getAuthenticatedClient(array $defaults = []): Client;
}