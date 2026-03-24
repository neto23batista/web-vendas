<?php

namespace FarmaVida\Core;

final class Autoloader
{
    private static string $basePath = '';

    public static function register(string $basePath): void
    {
        self::$basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        spl_autoload_register([self::class, 'load']);
    }

    private static function load(string $className): void
    {
        $prefix = 'FarmaVida\\';
        if (strncmp($className, $prefix, strlen($prefix)) !== 0) {
            return;
        }

        $relative = substr($className, strlen($prefix));
        $file = self::$basePath . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
}
