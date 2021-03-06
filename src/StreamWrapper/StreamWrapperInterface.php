<?php

namespace EttoreDN\PHPObjectStorage\StreamWrapper;

interface StreamWrapperInterface
{
    public static function getProtocol(): string;

    /* Directory operations */
//    public function dir_closedir(): bool;
//    public function dir_opendir ( string $path , int $options ): bool;
//    public string dir_readdir ( void )
//    public bool dir_rewinddir ( void )
    public function mkdir (string $path, int $mode, int $options): bool;
    public function rmdir (string $path, int $options): bool;
    
    /* File operations */
    public function stream_cast (int $cast_as);
    public function stream_close ();
    public function stream_eof (): bool;
    public function stream_flush (): bool;
    public function stream_lock (int $operation): bool;
    public function stream_metadata (string $path , int $option , $value): bool;
    public function stream_open (string $path , string $mode , int $options , &$opened_path): bool;
    public function stream_read (int $count);
    public function stream_seek (int $offset , int $whence = SEEK_SET): bool;
    public function stream_set_option (int $option , int $arg1 , int $arg2): bool;
    public function stream_stat ();
    public function stream_tell (): int;
    public function stream_truncate (int $new_size):bool;
    public function stream_write (string $data): int;
    
    public function rename (string $path_from, string $path_to): bool;
    public function unlink (string $path): bool;
    public function url_stat (string $path , int $flags);
}