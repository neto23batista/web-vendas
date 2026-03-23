<?php
require_once FARMAVIDA_ROOT . '/app/core/bootstrap.php';

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'] ?? '',
        (bool)($params['secure'] ?? false),
        (bool)($params['httponly'] ?? false)
    );
}

session_destroy();
header("Location: index.php");
exit;
?>
