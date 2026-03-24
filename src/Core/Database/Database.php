<?php

namespace FarmaVida\Core\Database;

use FarmaVida\Core\Config\AppConfig;
use mysqli;
use mysqli_sql_exception;
use RuntimeException;

final class Database
{
    private ?mysqli $connection = null;

    public function __construct(private readonly AppConfig $config)
    {
    }

    public function connection(): mysqli
    {
        if ($this->connection instanceof mysqli) {
            return $this->connection;
        }

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $connection = new mysqli(
                $this->config->dbHost(),
                $this->config->dbUser(),
                $this->config->dbPass(),
                $this->config->dbName(),
                $this->config->dbPort()
            );
            $connection->set_charset('utf8mb4');
        } catch (mysqli_sql_exception $exception) {
            error_log('DB connection error: ' . $exception->getMessage());
            throw new RuntimeException('Serviço temporariamente indisponível.');
        }

        $this->connection = $connection;
        return $this->connection;
    }
}
