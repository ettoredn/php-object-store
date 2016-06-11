<?php

namespace EttoreDN\PHPObjectStorage\StreamWrapper;

interface StreamWrapperInterface
{
    public static function getProtocol(): string;

    public function dir_closedir(): bool;
    
//    public function dir_opendir ( string $path , int $options ): bool;
//    public string dir_readdir ( void )
//    public bool dir_rewinddir ( void )
//    public bool mkdir ( string $path , int $mode , int $options )
//    public bool rename ( string $path_from , string $path_to )
//    public bool rmdir ( string $path , int $options )
    
    public function stream_cast(int $cast_as);
    
    public function stream_close();
    
    public function stream_eof(): bool;
    public function stream_flush (): bool;
    public function stream_lock (int $operation): bool;
    public function stream_metadata (string $path , int $option , mixed $value): bool;
    public function stream_open (string $path , string $mode , int $options , string &$opened_path): bool;
    public function stream_read (int $count): string;
    public function stream_seek (int $offset , int $whence = SEEK_SET): bool;
    public function stream_set_option (int $option , int $arg1 , int $arg2): bool;
    public function stream_stat (): array;
    public function stream_tell (): int;
    public function stream_truncate (int $new_size):bool;
    public function stream_write (string $data): int;
    public function unlink (string $path): bool;
    public function url_stat (string $path , int $flags): array;
}