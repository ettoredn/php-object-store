<?php

namespace EttoreDN\PHPObjectStorage\ObjectStore;


use EttoreDN\PHPObjectStorage\Exception\ObjectStoreException;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use OpenCloud\Common\Transport\HandlerStack;
use OpenStack\Identity\v2\Models\Token;
use OpenStack\Identity\v2\Service;

class SwiftObjectStore implements ObjectStoreInterface
{
    /**
     * @var Token
     */
    protected $token;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var string
     */
    protected $endpoint;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    /**
     * Returns the REST endpoint.
     * @return string
     * @internal param string $region
     * @internal param string $urlType
     */
    public function getEndpoint(): string
    {
        if (!$this->endpoint)
            $this->authenticate();

        return $this->endpoint;
    }

    /**
     * @return string
     */
    public function getTokenId(): string
    {
        if (!$this->token || $this->token->hasExpired())
            $this->authenticate();

        return $this->token->getId();
    }

    /**
     * Refreshes the token.
     * @return SwiftToken
     * @throws ObjectStoreException
     */
    protected function authenticate()
    {
        $client = new Client();

        $response = $client->get($this->options['authUrl']);
        $response = \GuzzleHttp\json_decode($response->getBody());

        foreach ($response->versions->values as $version)
            if ($version->id == $this->options['authId'])
                $endpoint = $version->links[0]->href;

        if (empty($endpoint))
            throw new ObjectStoreException('Cannot determine authentication endpoint');

        if ($this->options['authId'] == 'v2.0') {
            $uri = $endpoint .'tokens';
            $request = new Request('POST', $uri, ['Content-Type' => 'application/json'], \GuzzleHttp\json_encode([
                'auth' => [
                    'tenantName' => $this->options['tenantName'],
                    'passwordCredentials' => [
                        'username' => $this->options['username'],
                        'password' => $this->options['password']
                    ]
                ]
            ]));
            $response = \GuzzleHttp\json_decode($client->send($request)->getBody());

            // Retrieves the token
            $this->token = new SwiftToken(
                $response->access->token->id,
                new \DateTimeImmutable($response->access->token->expires));

            // Retrieves the endpoint of the object store from the catalog
            $this->endpoint = null;
            foreach ($response->access->serviceCatalog as $catalogEntry) {
                if ($catalogEntry->type == 'object-store' && $catalogEntry->name == 'swift')
                    foreach ($catalogEntry->endpoints as $endpoint)
                        if ($endpoint->region == $this->options['region'])
                            $this->endpoint = $endpoint->publicURL;
            }
            if (!$this->endpoint)
                throw new ObjectStoreException('Unable to find object store URL from the catalog: ' . $response);

        } else
            throw new ObjectStoreException(sprintf('Identity API %s not supported', $this->options['authId']));

        return $this->token;
    }

    /**
     * @param string $name
     * @return mixed
     */
    function exists(string $name)
    {
        // TODO: Implement exists() method.
    }

    /**
     * @param string $name
     * @param mixed $content
     * @param bool $overwrite
     * @return mixed
     */
    function upload(string $name, $content, bool $overwrite = true)
    {
        // TODO: Implement upload() method.
    }

    /**
     * @param array $files
     * @param array $options
     */
    function uploadBulk(array $files, array $options)
    {
        // TODO: Implement uploadBulk() method.
    }

    /**
     * @param string|null $objectName
     * @return string
     */
    function getObjectUrl(string $objectName)
    {
        // TODO: Implement getObjectUrl() method.
    }

    /**
     * @param string $objectName
     * @return mixed
     */
    function delete(string $objectName)
    {
        // TODO: Implement delete() method.
    }

    /**
     * @param array $options
     * @return array
     */
    function listObjects(array $options = [])
    {
        // TODO: Implement listObjects() method.
    }
}


/**
 * Class SwiftToken
 * @package EttoreDN\PHPObjectStorage\ObjectStore
 */
class SwiftToken
{
    /** @var string */
    public $id;

    /** @var \DateTimeImmutable */
    public $expires;

    public function __construct(string $id, \DateTimeImmutable $expires)
    {
        $this->id = $id;
        $this->expires = $expires;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function hasExpired(): bool
    {
        return $this->expires <= new \DateTimeImmutable('now', $this->expires->getTimezone());
    }
}