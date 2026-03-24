<?php
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');           
define('DB_NAME', getenv('DB_NAME') ?: 'farmavida');

if (!function_exists('responder_indisponibilidade_banco')) {
    function responder_indisponibilidade_banco(?Throwable $erro = null): never {
        if ($erro !== null) {
            error_log('DB runtime error: ' . $erro->getMessage());
        }

        if (!headers_sent()) {
            http_response_code(503);
            header('Content-Type: text/html; charset=UTF-8');
        }

        exit('Serviço temporariamente indisponível.');
    }
}

if (PHP_SAPI !== 'cli' && !defined('FARMAVIDA_DB_EXCEPTION_HANDLER')) {
    define('FARMAVIDA_DB_EXCEPTION_HANDLER', true);
    $handlerAnterior = set_exception_handler(function (Throwable $erro) use (&$handlerAnterior): void {
        if ($erro instanceof mysqli_sql_exception) {
            responder_indisponibilidade_banco($erro);
        }

        if (is_callable($handlerAnterior)) {
            $handlerAnterior($erro);
            return;
        }

        error_log((string)$erro);
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }
        exit('Erro interno do servidor.');
    });
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if ($conn->connect_error) {
    error_log('DB connect error: ' . $conn->connect_error);
    responder_indisponibilidade_banco();
}

$conn->set_charset("utf8mb4");
