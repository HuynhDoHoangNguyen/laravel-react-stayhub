<?php

namespace Tests\Support;

use RuntimeException;

final class Uc21ImageRaceProtocol
{
    public readonly string $directory;

    public function __construct(string $root, string $case, bool $create = false)
    {
        if (!preg_match('/\Acase-[a-f0-9]{32}\z/', $case) || !($resolved = realpath($root))) throw new RuntimeException('Invalid UC21 race root/case.');
        $path = $resolved.DIRECTORY_SEPARATOR.$case;
        if ($create && !is_dir($path)) mkdir($path);
        if (is_link($path) || realpath($path) !== $path) throw new RuntimeException('Unowned UC21 race directory.');
        $this->directory = $path;
    }

    private function path(string $name): string
    {
        if (!preg_match('/\A[a-zA-Z][a-zA-Z0-9-]{0,40}\z/', $name)) throw new RuntimeException('Invalid race message name.');
        return $this->directory.DIRECTORY_SEPARATOR.$name.'.json';
    }

    public function write(string $name, array $message): void
    {
        $path = $this->path($name);
        if (file_exists($path)) throw new RuntimeException('Race message cannot be overwritten.');
        $temp = $path.'.'.bin2hex(random_bytes(8)).'.tmp';
        file_put_contents($temp, json_encode($message, JSON_THROW_ON_ERROR), LOCK_EX);
        if (!rename($temp, $path)) throw new RuntimeException('Race message publish failed.');
    }

    public function wait(string $name, float $timeout = 12): array
    {
        $path = $this->path($name);
        $deadline = microtime(true) + $timeout;
        do {
            clearstatcache(true, $path);
            if (is_file($path)) {
                $value = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
                if (!is_array($value)) throw new RuntimeException('Malformed race message.');
                return $value;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        throw new RuntimeException('UC21 barrier timeout: '.$name);
    }
}
