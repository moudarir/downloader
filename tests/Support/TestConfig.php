<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Support;

final class TestConfig
{

    /**
     * @var array<string, mixed>|null
     */
    private static ?array $config = null;

    /**
     * @return array<string, mixed>
     */
    public static function get(): array
    {
        return self::$config ??= require dirname(__DIR__, 2) . '/examples/config.php';
    }

    public static function rootPath(): string
    {
        $config = self::get();

        return $config['root_path'];
    }

    public static function url(string $key): string
    {
        $config = self::get();

        return $config['urls'][$key];
    }

    public static function resourcePath(): string
    {
        return self::rootPath() . 'examples/resources' . DIRECTORY_SEPARATOR;
    }

    /**
     * @return array<string, mixed>
     */
    public static function resourceFile(string $name): array
    {
        $config = self::get();

        return $config['resources']['files'][$name];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resourceData(): array
    {
        $config = self::get();

        return $config['resources']['data'];
    }

    /**
     * @return array<string, mixed>
     */
    public static function multipart(): array
    {
        $config = self::get();

        return $config['multipart'];
    }
}
