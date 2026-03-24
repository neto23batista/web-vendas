<?php
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');           
define('DB_NAME', getenv('DB_NAME') ?: 'farmavida');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if ($conn->connect_error) {
     
    error_log('DB connect error: ' . $conn->connect_error);
    http_response_code(503);
    die('Serviço temporariamente indisponível.');
}

$conn->set_charset("utf8mb4");
