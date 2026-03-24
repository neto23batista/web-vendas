<?php

use FarmaVida\Core\Http\Request;

if (!defined('FARMAVIDA_ROOT')) {
    define('FARMAVIDA_ROOT', dirname(__DIR__));
}

$container = require FARMAVIDA_ROOT . '/src/bootstrap.php';

try {
    $request = Request::capture();
    $response = $container->get($controllerClass)->handle($request);
    $response->send();
} catch (Throwable $exception) {
    error_log((string)$exception);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo 'Erro interno do servidor.';
    exit;
}
