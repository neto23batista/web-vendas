<?php

namespace FarmaVida\Core\Config;

final class AppConfig
{
    public function __construct(
        private readonly string $rootPath,
        private readonly string $dbHost,
        private readonly int $dbPort,
        private readonly string $dbUser,
        private readonly string $dbPass,
        private readonly string $dbName
    ) {
    }

    public static function fromEnvironment(string $rootPath): self
    {
        return new self(
            $rootPath,
            getenv('DB_HOST') ?: 'localhost',
            (int)(getenv('DB_PORT') ?: 3306),
            getenv('DB_USER') ?: 'root',
            getenv('DB_PASS') ?: '',
            getenv('DB_NAME') ?: 'farmavida'
        );
    }

    public function rootPath(): string
    {
        return $this->rootPath;
    }

    public function viewPath(): string
    {
        return $this->rootPath . '/src/Presentation/Web/Views';
    }

    public function dbHost(): string
    {
        return $this->dbHost;
    }

    public function dbPort(): int
    {
        return $this->dbPort;
    }

    public function dbUser(): string
    {
        return $this->dbUser;
    }

    public function dbPass(): string
    {
        return $this->dbPass;
    }

    public function dbName(): string
    {
        return $this->dbName;
    }

    public function assetVersion(string $relativeFile): int
    {
        $path = $this->rootPath . '/' . ltrim($relativeFile, '/');
        return is_file($path) ? (int)filemtime($path) : time();
    }
}
