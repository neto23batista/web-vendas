<?php
 
 
 
 
 
require_once FARMAVIDA_ROOT . '/app/core/config.php';
require_once FARMAVIDA_ROOT . '/app/integrations/mercadopago_config.php';

 
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

 
$body  = file_get_contents('php://input');
$dados = json_decode($body, true);

@mkdir('logs', 0755, true);
$log_webhook = date('Y-m-d H:i:s')
    . " | tipo=" . ($dados['type'] ?? $_GET['topic'] ?? 'desconhecido')
    . " | id=" . ($dados['data']['id'] ?? $_GET['id'] ?? 'n/a')
    . " | origem=" . ($_SERVER['REMOTE_ADDR'] ?? 'n/a')
    . "\n";
file_put_contents('logs/mp_webhook.log', $log_webhook, FILE_APPEND);

 
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
        $row = $conn->query("SELECT id FROM pedidos WHERE id=$external_ref AND forma_pagamento='app'")->fetch_assoc();
        if ($row) $id_pedido = $row['id'];
    }
    if (!$id_pedido && $preference_id) {
        $pref_esc = $conn->real_escape_string($preference_id);
        $row = $conn->query("SELECT id FROM pedidos WHERE mp_preference_id='$pref_esc'")->fetch_assoc();
        if ($row) $id_pedido = $row['id'];
    }

    if ($id_pedido > 0) {
        $pg_s_esc   = $conn->real_escape_string($pagamento_status);
        $st_p_esc   = $conn->real_escape_string($status_pedido);
        $pay_id_esc = $conn->real_escape_string($payment_id);
        $pay_t_esc  = $conn->real_escape_string($payment_type);
        $pago_em    = $pagamento_status === 'aprovado' ? ", pago_em = NOW()" : '';

        $conn->query("
            UPDATE pedidos
            SET pagamento_status = '$pg_s_esc',
                status           = '$st_p_esc',
                mp_payment_id    = '$pay_id_esc',
                mp_payment_type  = '$pay_t_esc'
                $pago_em
            WHERE id = $id_pedido
        ");

        $log = date('Y-m-d H:i:s') . " | Webhook processado | Pedido #$id_pedido | Payment $payment_id | Status: $pagamento_status\n";
        file_put_contents('logs/mp_pagamentos.log', $log, FILE_APPEND);
    } else {
        $log = date('Y-m-d H:i:s') . " | Webhook: pedido não encontrado | Payment $payment_id | External ref: $external_ref\n";
        file_put_contents('logs/mp_pagamentos.log', $log, FILE_APPEND);
    }
}

http_response_code(200);
echo 'OK';
?>
