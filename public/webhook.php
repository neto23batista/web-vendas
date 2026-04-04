<?php
if (!defined('FARMAVIDA_ROOT')) { define('FARMAVIDA_ROOT', dirname(__DIR__)); }
require_once FARMAVIDA_ROOT . '/app/core/bootstrap.php';
require_once FARMAVIDA_ROOT . '/app/core/config.php';

$payload = file_get_contents('php://input') ?: '';
$segredoHeader = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '';

$stmt = $conn->prepare("SELECT id, segredo FROM integracoes_webhooks WHERE ativo = 1");
$stmt->execute();
$hooks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($hooks as $hook) {
    if (hash_equals($hook['segredo'], $segredoHeader)) {
        $stmt = $conn->prepare("INSERT INTO integracoes_eventos (id_webhook, payload) VALUES (?, ?)");
        $stmt->bind_param("is", $hook['id'], $payload);
        $stmt->execute();
        $stmt->close();
        http_response_code(202);
        echo 'ok';
        exit;
    }
}

http_response_code(403);
echo 'forbidden';
