<?php


namespace EttoreDN\PHPObjectStorage\StreamWrapper;


class S3StreamWrapper implements StreamWrapperInterface
{

    public function dir_closedir(): bool
    {
        // TODO: Implement dir_closedir() method.
    }

    public function stream_cast(int $cast_as)
    {
        // TODO: Implement stream_cast() method.
    }

    public function stream_close()
    {
        // TODO: Implement stream_close() method.
    }

    public function stream_eof(): bool
    {
        // TODO: Implement stream_eof() method.
    }

    public function stream_flush(): bool
    {
        // TODO: Implement stream_flush() method.
    }

    public function stream_lock(int $operation): bool
    {
        // TODO: Implement stream_lock() method.
    }

    public function stream_metadata(string $path, int $option, mixed $value): bool
    {
        // TODO: Implement stream_metadata() method.
    }

    public function stream_open(string $path, string $mode, int $options, string &$opened_path): bool
    {
        // TODO: Implement stream_open() method.
    }

    public function stream_read(int $count): string
    {
        // TODO: Implement stream_read() method.
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        // TODO: Implement stream_seek() method.
    }

    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        // TODO: Implement stream_set_option() method.
    }

    public function stream_stat(): array
    {
        // TODO: Implement stream_stat() method.
    }

    public function stream_tell(): int
    {
        // TODO: Implement stream_tell() method.
    }

    public function stream_truncate(int $new_size):bool
    {
        // TODO: Implement stream_truncate() method.
    }

    public function stream_write(string $data): int
    {
        // TODO: Implement stream_write() method.
    }

    public function unlink(string $path): bool
    {
        // TODO: Implement unlink() method.
    }

    public function url_stat(string $path, int $flags): array
    {
        // TODO: Implement url_stat() method.
    }

    public static function getProtocol(): string
    {
        return 's3';
    }
}