<?php
 
 
 
 
 
require_once FARMAVIDA_ROOT . '/app/core/bootstrap.php';
require_once FARMAVIDA_ROOT . '/app/core/config.php';
require_once FARMAVIDA_ROOT . '/app/integrations/mercadopago_config.php';

 
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

 
$body  = file_get_contents('php://input');
$dados = json_decode($body, true);

$logsDir = FARMAVIDA_ROOT . '/logs';
@mkdir($logsDir, 0755, true);
$logWebhookPath = $logsDir . '/mp_webhook.log';
$logPagamentosPath = $logsDir . '/mp_pagamentos.log';
$log_webhook = date('Y-m-d H:i:s')
    . " | tipo=" . ($dados['type'] ?? $_GET['topic'] ?? 'desconhecido')
    . " | id=" . ($dados['data']['id'] ?? $_GET['id'] ?? 'n/a')
    . " | origem=" . ($_SERVER['REMOTE_ADDR'] ?? 'n/a')
    . "\n";
file_put_contents($logWebhookPath, $log_webhook, FILE_APPEND);

 
$tipo = $dados['type'] ?? $_GET['topic'] ?? '';
$id   = $dados['data']['id'] ?? $_GET['id'] ?? '';

if (!in_array($tipo, ['payment', 'merchant_order']) || empty($id)) {
    http_response_code(200);
    exit('OK - ignored');
}

 
if ($tipo === 'payment') {
    $pagamento = mp_request('GET', "/v1/payments/$id");

    if (!isset($pagamento['status'])) {
        http_response_code(200);
        exit('OK - could not fetch payment');
    }

    $payment_id       = (string)$pagamento['id'];
    $status_mp        = $pagamento['status'];
    $external_ref     = (int)($pagamento['external_reference'] ?? 0);
    $payment_type     = $pagamento['payment_type_id'] ?? '';
    $preference_id    = $pagamento['preference_id'] ?? '';

     
    $pg_status_map = [
        'approved'   => 'aprovado',
        'in_process' => 'em_analise',
        'pending'    => 'em_analise',
        'rejected'   => 'recusado',
        'cancelled'  => 'cancelado',
        'refunded'   => 'cancelado',
        'charged_back' => 'cancelado',
    ];
    $pagamento_status = $pg_status_map[$status_mp] ?? 'pendente';

     
    $status_pedido_map = [
        'aprovado'   => 'preparando',
        'em_analise' => 'pendente',
        'recusado'   => 'pendente',
        'cancelado'  => 'cancelado',
        'pendente'   => 'pendente',
    ];
    $status_pedido = $status_pedido_map[$pagamento_status] ?? 'pendente';

     
    $id_pedido = 0;
    if ($external_ref > 0) {
        $stmt = $conn->prepare("SELECT id FROM pedidos WHERE id = ? AND forma_pagamento = 'app'");
        $stmt->bind_param("i", $external_ref);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $id_pedido = (int)$row['id'];
        }
    }
    if (!$id_pedido && $preference_id) {
        $stmt = $conn->prepare("SELECT id FROM pedidos WHERE mp_preference_id = ?");
        $stmt->bind_param("s", $preference_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $id_pedido = (int)$row['id'];
        }
    }

    if ($id_pedido > 0) {
        if ($pagamento_status === 'aprovado') {
            $stmt = $conn->prepare(
                "UPDATE pedidos
                 SET pagamento_status = ?,
                     status = ?,
                     mp_payment_id = ?,
                     mp_payment_type = ?,
                     pago_em = NOW()
                 WHERE id = ?"
            );
            $stmt->bind_param("ssssi", $pagamento_status, $status_pedido, $payment_id, $payment_type, $id_pedido);
        } else {
            $stmt = $conn->prepare(
                "UPDATE pedidos
                 SET pagamento_status = ?,
                     status = ?,
                     mp_payment_id = ?,
                     mp_payment_type = ?
                 WHERE id = ?"
            );
            $stmt->bind_param("ssssi", $pagamento_status, $status_pedido, $payment_id, $payment_type, $id_pedido);
        }
        $stmt->execute();
        $stmt->close();

        $log = date('Y-m-d H:i:s') . " | Webhook processado | Pedido #$id_pedido | Payment $payment_id | Status: $pagamento_status\n";
        file_put_contents($logPagamentosPath, $log, FILE_APPEND);
    } else {
        $log = date('Y-m-d H:i:s') . " | Webhook: pedido não encontrado | Payment $payment_id | External ref: $external_ref\n";
        file_put_contents($logPagamentosPath, $log, FILE_APPEND);
    }
}

http_response_code(200);
echo 'OK';
?>
