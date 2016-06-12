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
     * @param \SplFileInfo $archive
     * @param string $format
     * @param string $uploadPath
     * @return
     */
    function uploadArchive(\SplFileInfo $archive, string $format, string $uploadPath = '');

    /**
     * @param string $name
     * @return string
     */
    function download(string $name): string;

    /**
     * @param string $name
     * @return mixed
     */
    function delete(string $name): bool;

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