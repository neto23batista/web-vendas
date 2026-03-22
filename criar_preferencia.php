<?php
// ============================================================
// CRIAR PREFERÊNCIA MERCADO PAGO
// Chamado após criar o pedido com forma_pagamento = 'app'
// ============================================================
session_start();
include "config.php";
include "helpers.php";
include "mercadopago_config.php";

verificar_login('cliente');

$id_pedido  = isset($_GET['pedido']) ? (int)$_GET['pedido'] : 0;
$id_cliente = (int)$_SESSION['id_usuario'];

if ($id_pedido <= 0) {
    redirecionar('painel_cliente.php', 'Pedido inválido.', 'erro');
}

// Buscar pedido — deve pertencer ao cliente logado, ser do tipo 'app' e estar pendente
$stmt = $conn->prepare(
    "SELECT p.*, u.nome AS cliente_nome, u.email AS cliente_email, u.telefone
     FROM pedidos p
     JOIN usuarios u ON p.id_cliente = u.id
     WHERE p.id = ?
       AND p.id_cliente = ?
       AND p.forma_pagamento = 'app'
       AND p.pagamento_status = 'pendente'"
);
$stmt->bind_param("ii", $id_pedido, $id_cliente);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pedido) {
    redirecionar('painel_cliente.php', 'Pedido não encontrado ou já pago.', 'erro');
}

// Buscar itens do pedido
$stmt = $conn->prepare(
    "SELECT pi.quantidade, pi.preco_unitario, pr.nome, pr.descricao
     FROM pedido_itens pi
     JOIN produtos pr ON pi.id_produto = pr.id
     WHERE pi.id_pedido = ?"
);
$stmt->bind_param("i", $id_pedido);
$stmt->execute();
$itens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($itens)) {
    redirecionar('painel_cliente.php', 'Pedido sem itens.', 'erro');
}

// ── MONTAR PREFERÊNCIA ────────────────────────────────────────
$items_mp = [];
foreach ($itens as $item) {
    $items_mp[] = [
        'id'          => 'item-' . $id_pedido . '-' . uniqid(),
        'title'       => $item['nome'],
        'description' => $item['descricao'] ?: $item['nome'],
        'quantity'    => (int)$item['quantidade'],
        'unit_price'  => (float)$item['preco_unitario'],
        'currency_id' => 'BRL',
    ];
}

$preferencia = [
    'items'       => $items_mp,
    'payer'       => [
        'name'  => $pedido['cliente_nome'],
        'email' => $pedido['cliente_email'],
        'phone' => ['number' => preg_replace('/\D/', '', $pedido['telefone'] ?? '')],
    ],
    'back_urls' => [
        'success' => MP_URL_SUCESSO . '&pedido=' . $id_pedido,
        'failure' => MP_URL_FALHA   . '&pedido=' . $id_pedido,
        'pending' => MP_URL_PENDENTE. '&pedido=' . $id_pedido,
    ],
    'auto_return'          => 'approved',
    'notification_url'     => MP_URL_WEBHOOK,
    'external_reference'   => (string)$id_pedido,
    'statement_descriptor' => 'FARMAVIDA',
    'expires'              => false,
    'payment_methods'      => [
        'excluded_payment_types' => [],
        'installments'           => 12,
        'default_installments'   => 1,
    ],
    'metadata' => [
        'pedido_id'  => $id_pedido,
        'cliente_id' => $id_cliente,
        'sistema'    => 'farmavida',
    ],
];

// ── CHAMAR API DO MERCADO PAGO ────────────────────────────────
$resposta = mp_request('POST', '/checkout/preferences', $preferencia);

if (isset($resposta['erro']) || !isset($resposta['id'])) {
    @mkdir('logs', 0755, true);
    $log = date('Y-m-d H:i:s') . " | Pedido #$id_pedido | Erro MP: " . json_encode($resposta) . "\n";
    file_put_contents('logs/mp_erros.log', $log, FILE_APPEND);
    redirecionar('painel_cliente.php', 'Erro ao conectar ao Mercado Pago. Tente novamente.', 'erro');
}

$preference_id = $resposta['id'];
$init_point    = MP_AMBIENTE === 'sandbox'
    ? ($resposta['sandbox_init_point'] ?? $resposta['init_point'])
    : $resposta['init_point'];

// Salvar preference_id no pedido com prepared statement
$stmt = $conn->prepare("UPDATE pedidos SET mp_preference_id = ? WHERE id = ?");
$stmt->bind_param("si", $preference_id, $id_pedido);
$stmt->execute();
$stmt->close();

// Log de sucesso
@mkdir('logs', 0755, true);
$log = date('Y-m-d H:i:s') . " | Pedido #$id_pedido | Preference: $preference_id\n";
file_put_contents('logs/mp_pagamentos.log', $log, FILE_APPEND);

// ── REDIRECIONAR PARA O CHECKOUT DO MERCADO PAGO ─────────────
header('Location: ' . $init_point);
exit;
