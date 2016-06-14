<?php

namespace EttoreDN\PHPObjectStorage\StreamWrapper;


use EttoreDN\PHPObjectStorage\Exception\StreamWrapperException;
use EttoreDN\PHPObjectStorage\ObjectStorage;
use EttoreDN\PHPObjectStorage\ObjectStore\ObjectStoreInterface;
use EttoreDN\PHPObjectStorage\ObjectStore\SwiftObjectStore;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Class SwiftStreamWrapper
 * @package EttoreDN\PHPObjectStorage\StreamWrapper
 * 
 * TODO: implement a caching layer
 */
class SwiftStreamWrapper implements StreamWrapperInterface
{
    public $context;

    /**
     * @var array Used to retrieve object store options.
     */
    public static $options;

    /**
     * @var LoggerInterface
     */
    protected $logger;

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
        if (array_key_exists('logger', self::$options) && (self::$options['logger'] instanceof LoggerInterface))
            $this->logger = self::$options['logger'];
        else
            $this->logger = new Logger('swift.stream-wrapper', [new ErrorLogHandler()]);
        
        $this->store = ObjectStorage::getInstance(SwiftObjectStore::class, self::$options);
    }

    public function __destruct()
    {
        
    }

    public static function getProtocol(): string
    {
        return 'swift';
    }


    //======== Potentially frequently called operations that require performance

    private $readRequests = 0;
    /**
     * @param int $count
     * @return string
     * @throws StreamWrapperException
     */
    public function stream_read(int $count): string
    {
        if (!$this->canRead) {
            $this->logError(sprintf('Cannot read object %s as it was opened in read only mode', $this->pathname));
            return false;
        }

        $this->logger->debug(sprintf('read(%s): range=%d,%d', $this->pathname, $this->pointer, $this->pointer + $count - 1));

        if (is_array($this->content)) {
            if (count($this->content) < $this->pointer)
                return array_slice($this->content, $this->pointer, $count);
        }

        if (++$this->readRequests > 3) {
            // Fetch the damn think..



        }

        $request = new Request('GET', $this->pathname, [
            'X-Auth-Token' => $this->store->getTokenId(),
            'Range' => sprintf('bytes=%d-%d', $this->pointer, $this->pointer + $count-1)
        ]);
        try {
            $response = $this->getClient()->send($request);
            $body = $response->getBody();

            $data = $response->getBody()->getContents();
            $this->pointer += $body->getSize();

            return $data;
        } catch (GuzzleException $e) {
            $this->logError($e->getMessage());
            return '';
        }
    }

    /**
     * @param string $data
     * @return int
     * @throws GuzzleException
     * @throws StreamWrapperException
     */
    public function stream_write(string $data): int
    {
        if (!$this->canWrite) {
            $this->logError(sprintf('Cannot write file %s as it was opened read only', $this->pathname));
            return 0;
        }

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

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        if ($this->mode == 'a')
            return true;

        if ($whence == SEEK_END)
            throw new StreamWrapperException('NOT IMPLEMENTED');

        if (is_resource($this->content)) {
            fseek($this->content, $offset, $whence);
        }

        if ($whence == SEEK_SET)
            $this->pointer = $offset;
        if ($whence == SEEK_CUR)
            $this->pointer += $offset;

        return true;
    }

    public function stream_eof(): bool
    {
        return $this->pointer >= $this->getContentSize();
    }

    public function stream_flush(): bool
    {
        if (!$this->canWrite) {
            $this->logError(sprintf('Cannot write file %s as it was opened read only', $this->pathname));
            return 0;
        }

        try {
            $args = $this->getContent();
            array_unshift($args, 'C*');
            $binaryString = call_user_func_array('pack', $args);

            $request = new Request('PUT', $this->pathname, [
                'X-Auth-Token' => $this->store->getTokenId(),
                'ETag' => hash('md5', $binaryString)
            ], $binaryString);

            $this->getClient()->send($request);

            return true;
        } catch (GuzzleException $e) {
            $this->logError($e->getMessage());
            return false;
        }
    }


    //========= Stream wrapper operations

    public function stream_open(string $path, string $mode, int $options, &$opened_path): bool
    {
        if (STREAM_REPORT_ERRORS & $options)
            $this->reportErrors = true;

        $filteredMode = str_replace(['b', 't'], '', $mode); // b = bullshit mode

        $stat = $this->url_stat($path, STREAM_URL_STAT_QUIET);
        if (is_array($stat) && ($stat['mode'] & 0040000)) {
            $this->logError(sprintf('Cannot fopen directory %s', $path));
            return false;
        }

        $this->pathname = $this->stripProtocol($path);

        if ($filteredMode === 'r') {
            $this->getContentSize(); // Throws exception if the object does't exist
            $this->canRead = true;
            $this->pointer = 0;
        }
        else if ($filteredMode === 'r+') {
            $this->getContentSize(); // Throws exception if the object does't exist
            $this->canRead = true;
            $this->canWrite = true;
            $this->pointer = 0;
        }
        else if ($filteredMode === 'w') {
            $this->unlink($path);
            $this->canWrite = true;
            $this->content = [];
            $this->pointer = 0;
        }
        else if ($filteredMode === 'w+') {
            $this->unlink($path);
            $this->canRead = true;
            $this->canWrite = true;
            $this->content = [];
            $this->pointer = 0;
        }
        else if ($filteredMode === 'a') {
            $this->getContent(true);
            $this->canRead = true;
            $this->pointer = $this->getContentSize();

            // Open for writing only; place the file pointer at the end of the file.
            // ===> If the file does not exist, attempt to create it.
            // ===> In this mode, fseek() has no effect, writes are always appended.
        }
        else if ($filteredMode === 'a+') {
            $this->getContent(true);
            $this->canRead = true;
            $this->canWrite = true;
            $this->append = true;

            // Fetch whole object in /dev/shm

            // Open for reading and writing; place the file pointer at the end of the file.
            // ===> If the file does not exist, attempt to create it.
            // ===> In this mode, fseek() only affects the reading position, writes are always appended.

        }
        else if ($filteredMode === 'x') {
            if ($this->exists()) {
                trigger_error(sprintf('Object %s does not exist', $path), E_WARNING);
                return false;
            }
            $this->canWrite = true;
            $this->content = [];
            $this->pointer = 0;
        }
        else if ($filteredMode === 'x+') {
            if ($this->exists()) {
                trigger_error(sprintf('Object %s does not exist', $path), E_WARNING);
                return false;
            }
            $this->canRead = true;
            $this->canWrite = true;
            $this->content = [];
            $this->pointer = 0;
        }
        else if ($filteredMode === 'c') {
            $this->getContent(true);
            $this->canWrite = true;
            $this->pointer = 0;
        }
        else if ($filteredMode === 'c+') {
            $this->getContent(true);
            $this->canRead = true;
            $this->canWrite = true;
            $this->pointer = 0;
        }

        $this->mode = $filteredMode;

        if (STREAM_USE_PATH & $options)
            $opened_path = $path;

        $this->logger->debug(sprintf('fopen(%s, %s)', $this->pathname, $filteredMode));

        return true;
    }

    public function stream_close()
    {
        return;
    }

    /**
     * This method is called in response to fstat() **ONLY**.
     *
     * @return array|false
     * @throws GuzzleException
     */
    public function stream_stat(): array
    {
        $stat = [];
        $stat[0] = $stat['dev'] = 0;
        $stat[1] = $stat['ino'] = 0;
        // o+w is needed otherwise is_writable() returns false.
        $stat[2] = $stat['mode'] = 010 << 12 | 0666; // Regular file rw-rw-rw-
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

        $this->logger->debug(sprintf('stat(%s)', $this->pathname), $stat);

        return $stat;
    }

    private static $statCache = [];

    /**
     * This method is called in response to all stat() related functions.
     * See http://man7.org/linux/man-pages/man2/stat.2.html .
     *
     * @param string $path
     * @param int $flags
     * @return array|false
     * @throws GuzzleException
     * @throws StreamWrapperException
     */
    public function url_stat(string $path, int $flags)
    {
        $path = $this->stripProtocol($path);
//        $stat = [];
//        $stat[0] = $stat['dev'] = 0;
//        $stat[1] = $stat['ino'] = 0;
//        $stat[2] = $stat['mode'] = 010 << 12 | 0666; // Regular file rw-rw-r--
//        $stat[3] = $stat['nlink'] = 0;
//        $stat[4] = $stat['uid'] = 0;
//        $stat[5] = $stat['gid'] = 0;
//        $stat[6] = $stat['rdev'] = 0;
//        $stat[7] = $stat['size'] = 0;
//        $stat[8] = $stat['atime'] = 0;
//        $stat[9] = $stat['mtime'] = 0;
//        $stat[10] = $stat['ctime'] = 0;
//        $stat[11] = $stat['blksize'] = 0;
//        $stat[12] = $stat['blocks'] = 0;
//
//        if (substr($path, 0, 11) == 'swift://is_')
//            return $stat;

        if (STREAM_URL_STAT_LINK & $flags)
            /*
             * For resources with the ability to link to other resource
             * (such as an HTTP Location: forward, or a filesystem symlink).
             * This flag specified that only information about the link itself
             * should be returned, not the resource pointed to by the link.
             * This flag is set in response to calls to lstat(), is_link(), or filetype().
             */
            return false;

        if (array_key_exists($path, self::$statCache) && time() - self::$statCache[$path]['age'] < 60)
            return self::$statCache[$path]['stat'];

        $stat = [];
        $stat[0] = $stat['dev'] = 0;
        $stat[1] = $stat['ino'] = 0;
        // o+w is needed otherwise is_writable() returns false.
        $stat[2] = $stat['mode'] = 010 << 12 | 0666; // Regular file rw-rw-rw-
        $stat[3] = $stat['nlink'] = 0;
        $stat[4] = $stat['uid'] = 0;
        $stat[5] = $stat['gid'] = 0;
        $stat[6] = $stat['rdev'] = 0;
        $stat[7] = $stat['size'] = 0;
        $stat[8] = $stat['atime'] = 0;
        $stat[9] = $stat['mtime'] = 0;
        $stat[10] = $stat['ctime'] = 0;
        $stat[11] = $stat['blksize'] = 0;
        $stat[12] = $stat['blocks'] = 0;

        $request = new Request('HEAD', $path, ['X-Auth-Token' => $this->store->getTokenId()]);
        try {
            $response = $this->getClient()->send($request);

            $stat['ctime'] = $stat[10] = intval($response->getHeader('X-Timestamp')[0]);
            $stat['size'] = $stat[7] = intval($response->getHeader('Content-Length')[0]);

            if ($response->hasHeader('X-Container-Object-Count')) {
                // $path = swift://<container>
                $stat[2] = $stat['mode'] |= 0111; // ugo+x
                $stat[2] = $stat['mode'] = ($stat['mode'] & ~( 0170000 )) | 0040000; // Directory
            } else if ($response->hasHeader('X-Object-Meta-Directory')) {
                // Directory created with mkdir()
                $stat['mtime'] = $stat[9] = strtotime($response->getHeader('Last-Modified')[0]);

                $stat[2] = $stat['mode'] |= 0111; // ugo+x
                $stat[2] = $stat['mode'] = ($stat['mode'] & ~( 0170000 )) | 0040000; // Directory
            } else {
                // Object aka file
                $stat['mtime'] = $stat[9] = strtotime($response->getHeader('Last-Modified')[0]);
            }
        } catch (GuzzleException $e) {
            if (!($flags & STREAM_URL_STAT_QUIET))
                /*
                 * If this flag is set, your wrapper should not raise any errors. If this flag is not set,
                 * you are responsible for reporting errors using the trigger_error() function during stating
                 * of the path.
                 */
                $this->logError(sprintf('Unable to fetch metadata for object %s: %s', $path, $e->getMessage()));

            return false;
        }

        $this->logger->debug(sprintf('url_stat(%s, %d)', $path, $flags), $stat);

        self::$statCache[$path] = ['age' => time(), 'stat' => $stat];

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

    public function unlink(string $path): bool
    {
        // Container (swift://<container>/?)
        if (preg_match('/^swift:\/\/[^\/]+[\/]*$/', $path))
            return false;

        $stat = $this->url_stat($path, STREAM_URL_STAT_QUIET);
        if (!is_array($stat))
            // Non existent
            return true;

        // Directory
        if ($stat['mode'] & 0040000) {
            $this->logError(sprintf('Unlink should not be called on directories, use rmdir(%s)', $path));
            return false;
        }

        $request = new Request('DELETE', $this->stripProtocol($path), ['X-Auth-Token' => $this->store->getTokenId()]);
        
        try {
            $this->getClient()->send($request);
        } catch (GuzzleException $e) {
            if ($e->getCode() != 404) {
                if ($this->reportErrors)
                    trigger_error($e->getMessage(), E_ERROR);

                return false;
            }
        }

        $this->logger->debug(sprintf('unlink(%s): success', $this->stripProtocol($path)));
        
        return true;
    }

    public function rename(string $path_from, string $path_to): bool
    {
        throw new StreamWrapperException('NOT IMPLEMENTED');
    }

    public function stream_lock(int $operation): bool
    {
        // http://stackoverflow.com/questions/11837428/whats-the-difference-between-an-exclusive-lock-and-a-shared-lock
        return false;
    }
    public function stream_cast(int $cast_as)
    {
        return false;
    }
    public function stream_metadata(string $path, int $option, $value): bool
    {
        return false;
    }
    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        return false;
    }
    public function mkdir(string $path, int $mode, int $options): bool
    {
        if (preg_match('/^swift:\/\/[^\/]+[\/]*$/', $path)) {
            // Container (swift://<container>/?): should we create it? TODO
            return false;
        }
        try {
            $request = new Request('PUT', $this->stripProtocol($path), [
                'X-Auth-Token' => $this->store->getTokenId(),
                'X-Object-Meta-Directory' => '1'
            ], 'pseudo directory');

            $resp = $this->getClient()->send($request);
        } catch (GuzzleException $e) {
            $this->logger->error(sprintf('mkdir(%s, %d): %s', $path, $mode, $e->getMessage()));
            return false;
        }

        $this->logger->debug(sprintf('mkdir(%s): success', $this->stripProtocol($path)));

        return true;
    }
    public function rmdir(string $path, int $options): bool
    {
        if (preg_match('/^swift:\/\/[^\/]+[\/]*$/', $path)) {
            // Container (swift://<container>/?): delete? TODO
            return false;
        }

        $stat = $this->url_stat($path, STREAM_URL_STAT_QUIET);
        if (!is_array($stat))
            // Not existent
            return true;
        
        if (!$stat['mode'] & 0040000) {
            trigger_error(sprintf('%s is not a directory', $path));
            return false;
        }
        
        $request = new Request('DELETE', $this->stripProtocol($path), ['X-Auth-Token' => $this->store->getTokenId()]);
        try {
            $this->getClient()->send($request);
        } catch (GuzzleException $e) {
            if ($e->getCode() != 404) {
                $this->logger->error(sprintf('rmdir(%s): %s', $path, $e->getMessage()));
                return false;
            }
        }

        $this->logger->debug(sprintf('rmdir(%s): success', $this->stripProtocol($path)));

        return true;
    }


    //============== Helpers

    /**
     * @return Client
     */
    protected function getClient(): Client
    {
        if (!$this->client) {
            $this->client = $this->store->getAuthenticatedClient([
                'base_uri' => rtrim($this->store->getEndpoint(), '/') .'/',
                'connect_timeout' => 30
            ]);
        }

        return $this->client;
    }

    private function exists(): bool
    {
        try {
            $this->getClient()->head($this->pathname);
        } catch (GuzzleException $e) {
            if ($this->reportErrors && $e->getCode() != 404) {
                $this->logError(sprintf('Unable to check whether object %s exists: %s', $this->pathname, $e->getMessage()));
                throw new StreamWrapperException(sprintf('Unable to check whether object %s exists: %s', $this->pathname, $e->getMessage()));
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
                $response = $this->getClient()->send($request);
                $this->content = array_values(unpack('C*', $response->getBody()->getContents()));
            } catch (GuzzleException $e) {
                if ($e->getCode() == 404 && $create)
                    return $this->content = [];

                $this->logError(sprintf('Unable to fetch content for object %s: %s', $this->pathname, $e->getMessage()));
                throw new StreamWrapperException(sprintf('Unable to fetch content for object %s: %s', $this->pathname, $e->getMessage()));
            }
        }

        return $this->content;
    }

    /**
     * @return int
     * @throws StreamWrapperException
     */
    private function getContentSize()
    {
        if (is_array($this->content))
            return count($this->content);

        if (!is_int($this->contentSize)) {
            $request = new Request('HEAD', $this->pathname, ['X-Auth-Token' => $this->store->getTokenId()]);
            try {
                $response = $this->getClient()->send($request);
                $this->contentSize = intval($response->getHeader('Content-Length')[0]);
            } catch (GuzzleException $e) {
                $this->logError(sprintf('Unable to retrieve size for object %s: %s', $this->pathname, $e->getMessage()));
                throw new StreamWrapperException(sprintf('Unable to retrieve size for object %s: %s', $this->pathname, $e->getMessage()));
            }
        }

        return $this->contentSize;
    }

    private function getContentCreatedTime()
    {
        if (!is_int($this->contentCreatedTime)) {
            $request = new Request('HEAD', $this->pathname, ['X-Auth-Token' => $this->store->getTokenId()]);
            try {
                $response = $this->getClient()->send($request);
                $this->contentCreatedTime = intval($response->getHeader('X-Timestamp')[0]);
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
                $response = $this->getClient()->send($request);
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
        return rtrim(preg_replace('/\/{2,}/', '/', substr($path, strlen(self::getProtocol()) + 3)), '/');
    }

    private function logError(string $message) {
        if ($this->reportErrors)
            trigger_error($message, E_ERROR);
        $this->logger->error($message);
    }

    public function __call($name, $args){
        throw new StreamWrapperException(sprintf('Method %s does not exist', $name));
    }
}