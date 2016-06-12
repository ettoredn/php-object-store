<?php

namespace EttoreDN\PHPObjectStorage\ObjectStore;


use EttoreDN\PHPObjectStorage\Exception\ObjectStoreException;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use OpenCloud\Common\Transport\HandlerStack;
use OpenStack\Identity\v2\Models\Token;
use OpenStack\Identity\v2\Service;

/**
 * Class SwiftObjectStore
 * @package EttoreDN\PHPObjectStorage\ObjectStore
 *
 * @property $container Must be specified before any invocation of getClient()
 */
class SwiftObjectStore implements ObjectStoreInterface
{
    /**
     * @var Token
     */
    protected $token;
    public function getTokenId(): string {
        if (!$this->token || $this->token->hasExpired())
            $this->authenticate();

        return $this->token->getId();
    }

    /**
     * @var array
     */
    protected $options;

    /**
     * @var string
     */
    protected $endpoint;
    public function getEndpoint(): string {
        if (!$this->endpoint)
            $this->authenticate();

        return $this->endpoint;
    }

    /**
     * @var string
     */
    protected $container;
    public function getContainer(): string {
        return $this->container;
    }
    public function setContainer(string $name) {
        return $this->container = $name;
    }

    /**
     * SwiftObjectStore constructor.
     * @param array $options
     * @throws ObjectStoreException
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;

        if (array_key_exists('container', $options))
            $this->setContainer($options['container']);
    }

    /**
     * @param string $name
     * @return boolean
     */
    function exists(string $name): bool
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
     * @param string $prefix
     * @param int $limit
     * @return array
     */
    function listObjectNames(string $prefix = '', int $limit = 0): array
    {
        $hasLimit = $limit > 0;
        $names = [];
        $marker = '';

        do {
            $results = 0;
            foreach ($this->_listNames($prefix, $limit, $marker) as $name) {
                $names[] = $name;

                $results++;
                $marker = $name;

                if ($hasLimit && count($names) >= $limit)
                    break;
            }
        } while ($results > 0 && (!$hasLimit || count($names) < $limit));

        return $names;
    }

    /**
     * Returns a client to use for endpoint requests.
     * @param array $defaults
     * @return Client
     */
    function getAuthenticatedClient(array $defaults = []): Client
    {
        return new Client($defaults + [
                'headers' => ['X-Auth-Token' => $this->getTokenId()]
            ]);
    }

    protected function getClient(): Client
    {
        if (empty($this->container))
            throw new ObjectStoreException('Container must be given in constructor');

        $baseUri = sprintf('%s/%s', $this->getEndpoint(), $this->container);
        return $this->getAuthenticatedClient(['base_uri' => $baseUri]);
    }

    private function _listNames(string $prefix = '', int $limit = 0, string $marker = '', string $endMarker = ''): array
    {
        $client = $this->getClient();
        $query = [];

        if ($limit > 0)
            $query['limit'] = $limit;
        if (!empty($marker))
            $query['marker'] = $marker;
        if (!empty($endMarker))
            $query['end_marker'] = $endMarker;


        $resp = $client->get($prefix, ['query' => $query]);
        $names = explode("\n", $resp->getBody()->getContents());
        array_pop($names);
        return $names;
    }



    /**
     * Refreshes the token and sets the endpoint URL.
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
     * @return int
     */
    public function count()
    {
        $r = $this->getClient()->head('');
        return intval($r->getHeader('X-Container-Object-Count'));
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