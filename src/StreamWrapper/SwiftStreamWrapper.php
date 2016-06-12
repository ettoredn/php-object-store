<?php

namespace EttoreDN\PHPObjectStorage\StreamWrapper;


use EttoreDN\PHPObjectStorage\Exception\ObjectStoreException;
use EttoreDN\PHPObjectStorage\ObjectStorage;
use EttoreDN\PHPObjectStorage\ObjectStore\ObjectStoreInterface;
use EttoreDN\PHPObjectStorage\ObjectStore\SwiftObjectStore;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;

class SwiftStreamWrapper implements StreamWrapperInterface
{
    /**
     * @var string
     */
    protected $endpoint;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var SwiftObjectStore
     */
    protected $store;

    /**
     * @var bool
     */
    protected $canRead = false;
    /**
     * @var bool
     */
    protected $canWrite = false;

    /**
     * @var bool
     */
    protected $append = false;

    /**
     * @var bool
     */
    protected $reportErrors = false;

    /**
     * @var int
     */
    protected $pointer;

    /**
     * @var array of bytes
     */
    protected $content;

    /**
     * @var int
     */
    protected $contentSize;

    /**
     * @var string
     */
    protected $pathname;

    /**
     * @var string
     */
    protected $mode;

    /**
     * @var integer
     */
    private $contentCreatedTime;

    /**
     * @var int
     */
    private $contentModifiedTime;

    public function __construct()
    {
        $this->store = ObjectStorage::getInstance(SwiftObjectStore::class);

        // Create HTTP client
        $this->client = new Client([
            'base_uri' => $this->store->getEndpoint() .'/',
            'connect_timeout' => 30
        ]);
    }

    /**
     * @return bool
     * @throws GuzzleException
     */
    private function exists(): bool
    {
        $request = new Request('HEAD', $this->pathname, ['X-Auth-Token' => $this->store->getTokenId()]);
        try {
            $this->client->send($request);
        } catch (GuzzleException $e) {
            if ($this->reportErrors && $e->getCode() != 404) {
                trigger_error($e->getMessage(), E_ERROR);
                throw $e;
            }

            return false;
        }

        return true;
    }

    private function &getContent(bool $create = false)
    {
        if (!is_array($this->content)) {
            $request = new Request('GET', $this->pathname, ['X-Auth-Token' => $this->store->getTokenId()]);
            try {
                $response = $this->client->send($request);
                $this->content = array_values(unpack('C*', $response->getBody()->getContents()));
            } catch (GuzzleException $e) {
                if ($e->getCode() == 404 && $create)
                    return $this->content = [];

                if ($this->reportErrors)
                    trigger_error($e->getMessage(), E_ERROR);

                throw $e;
            }
        }

        return $this->content;
    }

    private function getContentSize()
    {
        if (is_array($this->content))
            return count($this->content);

        if (!is_int($this->contentSize)) {
            $request = new Request('HEAD', $this->pathname, ['X-Auth-Token' => $this->store->getTokenId()]);
            try {
                $response = $this->client->send($request);
                $this->contentSize = intval($response->getHeader('Content-Length'));
            } catch (GuzzleException $e) {
                if ($this->reportErrors)
                    trigger_error($e->getMessage(), E_ERROR);

                throw $e;
            }
        }

        return $this->contentSize;
    }

    private function getContentCreatedTime()
    {
        if (!is_int($this->contentCreatedTime)) {
            $request = new Request('HEAD', $this->pathname, ['X-Auth-Token' => $this->store->getTokenId()]);
            try {
                $response = $this->client->send($request);
                $this->contentCreatedTime = intval($response->getHeader('X-Timestamp'));
            } catch (GuzzleException $e) {
                if ($this->reportErrors)
                    trigger_error($e->getMessage(), E_ERROR);

                $this->contentCreatedTime = time();
            }
        }

        return $this->contentCreatedTime;
    }

    private function getContentModifiedTime()
    {
        if (!is_int($this->contentModifiedTime)) {
            $request = new Request('HEAD', $this->pathname, ['X-Auth-Token' => $this->store->getTokenId()]);
            try {
                $response = $this->client->send($request);
                $this->contentModifiedTime = strtotime($response->getHeader('Last-Modified')[0]);
            } catch (GuzzleException $e) {
                if ($this->reportErrors)
                    trigger_error($e->getMessage(), E_ERROR);

                return $this->contentModifiedTime = time();
            }
        }

        return $this->contentModifiedTime;
    }
    
    private function stripProtocol(string $path)
    {
        return substr($path, strlen(self::getProtocol()) + 3);
    }

    public function stream_open(string $path, string $mode, int $options, &$opened_path): bool
    {
        if (STREAM_REPORT_ERRORS & $options)
            $this->reportErrors = true;

        $this->pathname = $this->stripProtocol($path);

        if ($mode === 'r') {
            $this->getContentSize();
            $this->canRead = true;
            $this->pointer = 0;
        }
        if ($mode === 'r+') {
            $this->getContentSize();
            $this->canRead = true;
            $this->canWrite = true;
            $this->pointer = 0;
        }
        if ($mode === 'w') {
            $this->unlink($path);
            $this->canWrite = true;
            $this->content = [];
            $this->pointer = 0;
        }
        if ($mode === 'w+') {
            $this->unlink($path);
            $this->canRead = true;
            $this->canWrite = true;
            $this->content = [];
            $this->pointer = 0;
        }
        if ($mode === 'a') {
            $this->getContent(true);
            $this->canRead = true;
            $this->pointer = $this->getContentSize();

            // Open for writing only; place the file pointer at the end of the file.
            // ===> If the file does not exist, attempt to create it.
            // ===> In this mode, fseek() has no effect, writes are always appended.
        }
        if ($mode === 'a+') {
            $this->getContent(true);
            $this->canRead = true;
            $this->canWrite = true;
            $this->append = true;

            // Fetch whole object in /dev/shm

            // Open for reading and writing; place the file pointer at the end of the file.
            // ===> If the file does not exist, attempt to create it.
            // ===> In this mode, fseek() only affects the reading position, writes are always appended.

        }
        if ($mode === 'x') {
            if ($this->exists()) {
                trigger_error(sprintf('Object %s does not exist', $path), E_WARNING);
                return false;
            }
            $this->canWrite = true;
            $this->content = [];
            $this->pointer = 0;
        }
        if ($mode === 'x+') {
            if ($this->exists()) {
                trigger_error(sprintf('Object %s does not exist', $path), E_WARNING);
                return false;
            }
            $this->canRead = true;
            $this->canWrite = true;
            $this->content = [];
            $this->pointer = 0;
        }

        if ($mode === 'c') {
            $this->getContent(true);
            $this->canWrite = true;
            $this->pointer = 0;
        }

        if ($mode === 'c+') {
            $this->getContent(true);
            $this->canRead = true;
            $this->canWrite = true;
            $this->pointer = 0;
        }

        $this->mode = $mode;

        if (STREAM_USE_PATH & $options)
            $opened_path = $path;

        return true;
    }

    public function stream_cast(int $cast_as)
    {
        throw new \RuntimeException('NOT IMPLMENETED');
    }

    public function stream_close()
    {
        return;
    }

    public function stream_eof(): bool
    {
        return $this->pointer >= $this->getContentSize();
    }

    public function stream_flush(): bool
    {
        if (!$this->canWrite)
            throw new ObjectStoreException(sprintf('Cannot write file %s as it was opened as read only', $this->pathname));

        try {
            $args = $this->getContent();
            array_unshift($args, 'C*');
            $binaryString = call_user_func_array('pack', $args);

            $request = new Request('PUT', $this->pathname, [
                'X-Auth-Token' => $this->store->getTokenId(),
                'ETag' => hash('md5', $binaryString)
            ], $binaryString);

            $resp = $this->client->send($request);

            return true;
        } catch (GuzzleException $e) {
            if ($this->reportErrors)
                trigger_error($e->getMessage(), E_ERROR);

            return false;
        }
    }

    public function stream_lock(int $operation): bool
    {
        // http://stackoverflow.com/questions/11837428/whats-the-difference-between-an-exclusive-lock-and-a-shared-lock

        throw new \RuntimeException('NOT IMPLMENETED');
    }

    public function stream_metadata(string $path, int $option, mixed $value): bool
    {
        throw new \RuntimeException('NOT IMPLMENETED');
    }


    public function stream_read(int $count): string
    {
        if (!$this->canRead)
            throw new ObjectStoreException(sprintf('Cannot read file %s as it was opened as write only', $this->pathname));

        $request = new Request('GET', $this->pathname, [
            'X-Auth-Token' => $this->store->getTokenId(),
            'Range' => sprintf('bytes=%d-%d', $this->pointer, $this->pointer + $count)
        ]);
        try {
            $response = $this->client->send($request);
            $body = $response->getBody();

            $data = $response->getBody()->getContents();
            $this->pointer += $body->getSize();

            return $data;
        } catch (GuzzleException $e) {
            if ($this->reportErrors)
                trigger_error($e->getMessage(), E_ERROR);

            return false;
        }
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        if ($this->mode == 'a')
            return true;

        if ($whence == SEEK_END)
            throw new \RuntimeException('NOT IMPLEMENTED');

        if (is_resource($this->content)) {
            fseek($this->content, $offset, $whence);
        }

        if ($whence == SEEK_SET)
            $this->pointer = $offset;
        if ($whence == SEEK_CUR)
            $this->pointer += $offset;

        return true;
    }

    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        throw new \RuntimeException('NOT IMPLMENETED');
    }

    public function stream_stat(): array
    {
        $stat = [];
        $stat[0] = $stat['dev'] = 0;
        $stat[1] = $stat['ino'] = 0;
        $stat[2] = $stat['mode'] = 0;
        $stat[3] = $stat['nlink'] = 0;
        $stat[4] = $stat['uid'] = 0;
        $stat[5] = $stat['gid'] = 0;
        $stat[6] = $stat['rdev'] = 0;
        $stat[7] = $stat['size'] = $this->getContentSize();
        $stat[8] = $stat['atime'] = 0;
        $stat[9] = $stat['mtime'] = $this->getContentModifiedTime();
        $stat[10] = $stat['ctime'] = $this->getContentCreatedTime();
        $stat[11] = $stat['blksize'] = 0;
        $stat[12] = $stat['blocks'] = 0;

        return $stat;
    }

    public function stream_tell(): int
    {
        return $this->pointer;
    }

    public function stream_truncate(int $new_size):bool
    {
        if (!$this->canWrite) {
            if ($this->reportErrors)
                trigger_error(sprintf('Cannot truncate read-only file %s', $this->pathname, E_ERROR));
            return false;
        }
        
        $content = &$this->getContent();
        $size = $this->getContentSize(); // count($content)

        if ($new_size > $size) {
            foreach (range($size, $new_size-1) as $index) {
                $content[$index] = 0;
            }
        } else
            array_splice($content, $new_size);

        return true;
    }

    public function stream_write(string $data): int
    {
        if (!$this->canWrite)
            throw new ObjectStoreException(sprintf('Cannot write file %s as it was opened write only', $this->pathname));

        $writePointer = $this->pointer;
        if (in_array($this->mode, ['a+', 'a']))
            $writePointer = $this->getContentSize();

        $data = unpack('C*', $data);

        foreach ($data as $char) {
            $content = &$this->getContent();
            $content[$writePointer++] = $char;
        }

        if (!in_array($this->mode, ['a+', 'a']))
            $this->pointer = $writePointer;

        return count($data);
    }

    public function unlink(string $path): bool
    {
        $request = new Request('DELETE', $this->stripProtocol($path), ['X-Auth-Token' => $this->store->getTokenId()]);
        
        try {
            $this->client->send($request);
        } catch (GuzzleException $e) {
            if ($e->getCode() != 404) {
                if ($this->reportErrors)
                    trigger_error($e->getMessage(), E_ERROR);

                return false;
            }
        }
        
        return true;
    }

    public function url_stat(string $path, int $flags): array
    {
        throw new ObjectStoreException('NOT SUPPORTED');
    }

    public static function getProtocol(): string
    {
        return 'swift';
    }

    public function rename(string $path_from, string $path_to): bool
    {
        throw new ObjectStoreException('NOT SUPPORTED');
    }
}