<?php

namespace EttoreDN\PHPObjectStorage\ObjectStore;


interface ObjectStoreInterface
{
    /**
     * Returns the REST endpoint.
     * 
     * @return string
     */
    public function getEndpoint(): string;
}