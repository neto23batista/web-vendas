<?php
// ============================================================
// RETORNO DO MERCADO PAGO APÓS PAGAMENTO
// MP redireciona para cá com: status, payment_id, preference_id
// ============================================================
session_start();
include "config.php";
include "helpers.php";
include "mercadopago_config.php";

$status        = $_GET['status']        ?? '';
$payment_id    = $_GET['payment_id']    ?? '';
$preference_id = $_GET['preference_id'] ?? '';
$id_pedido     = (int)($_GET['pedido']  ?? 0);

// ---- VERIFICAR PAGAMENTO NA API (não confiar só na URL) ----
$pagamento_verificado = null;
if (!empty($payment_id)) {
    $pagamento_verificado = mp_request('GET', "/v1/payments/$payment_id");
}

// Mapear status do MP para o nosso
function mapear_status_mp(string $status_mp): array {
    return match ($status_mp) {
        'approved'     => ['pagamento_status' => 'aprovado',    'status_pedido' => 'preparando', 'cor' => '#00875a', 'icone' => 'circle-check',   'titulo' => 'Pagamento Aprovado!',    'msg' => 'Seu pagamento foi confirmado. Já estamos separando seu pedido!'],
        'in_process',
        'pending'      => ['pagamento_status' => 'em_analise',  'status_pedido' => 'pendente',   'cor' => '#f59e0b', 'icone' => 'clock',           'titulo' => 'Pagamento em Análise',   'msg' => 'Seu pagamento está sendo processado. Você receberá uma confirmação em breve.'],
        'rejected'     => ['pagamento_status' => 'recusado',    'status_pedido' => 'pendente',   'cor' => '#ef4444', 'icone' => 'circle-xmark',    'titulo' => 'Pagamento Recusado',     'msg' => 'Seu pagamento foi recusado. Tente outro cartão ou forma de pagamento.'],
        'cancelled'    => ['pagamento_status' => 'cancelado',   'status_pedido' => 'cancelado',  'cor' => '#6b7280', 'icone' => 'ban',             'titulo' => 'Pagamento Cancelado',    'msg' => 'O pagamento foi cancelado.'],
        default        => ['pagamento_status' => 'pendente',    'status_pedido' => 'pendente',   'cor' => '#f59e0b', 'icone' => 'clock',           'titulo' => 'Aguardando Pagamento',   'msg' => 'Aguardando confirmação do pagamento.'],
    };
}

// Status real vem da verificação na API, não da URL
$status_real = 'pending';
if ($pagamento_verificado && isset($pagamento_verificado['status'])) {
    $status_real = $pagamento_verificado['status'];
} elseif ($status === 'sucesso') {
    $status_real = 'approved';
} elseif ($status === 'falha') {
    $status_real = 'rejected';
}

$info = mapear_status_mp($status_real);

// ---- ATUALIZAR BANCO SE TIVER PEDIDO ----
if ($id_pedido > 0) {
    $pg_status = $conn->real_escape_string($info['pagamento_status']);
    $st_pedido = $conn->real_escape_string($info['status_pedido']);
    $pg_id     = $conn->real_escape_string($payment_id);
    $pg_type   = $conn->real_escape_string($pagamento_verificado['payment_type_id'] ?? '');
    $pago_em   = $info['pagamento_status'] === 'aprovado' ? ", pago_em = NOW()" : '';

    $conn->query("
        UPDATE pedidos
        SET pagamento_status = '$pg_status',
            status           = '$st_pedido',
            mp_payment_id    = '$pg_id',
            mp_payment_type  = '$pg_type'
            $pago_em
        WHERE id = $id_pedido
    ");

    // Log
    $log = date('Y-m-d H:i:s') . " | Pedido #$id_pedido | Payment: $payment_id | Status: $status_real | HTTP retorno\n";
    file_put_contents('logs/mp_pagamentos.log', $log, FILE_APPEND);
}

// Buscar dados do pedido para exibir
$pedido = $id_pedido > 0
    ? $conn->query("SELECT * FROM pedidos WHERE id=$id_pedido")->fetch_assoc()
    : null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento - FarmaVida</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=1774207549">
</head>
<body>
    <div class="header">
        <div class="header-container">
            <a href="index.php" class="logo">
                <div class="logo-icon"><i class="fas fa-prescription-bottle-medical"></i></div>
                Farma<span>Vida</span>
            </a>
        </div>
    </div>

    <div class="container" style="max-width:560px;">
        <div class="card" style="text-align:center;padding:48px 32px;">

            <!-- ÍCONE STATUS -->
            <div style="width:90px;height:90px;border-radius:50%;background:<?= $info['cor'] ?>1a;border:3px solid <?= $info['cor'] ?>;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;">
                <i class="fas fa-<?= $info['icone'] ?>" style="font-size:40px;color:<?= $info['cor'] ?>;"></i>
            </div>

            <h1 style="font-family:'Sora',sans-serif;font-size:26px;font-weight:800;color:var(--dark);margin-bottom:10px;">
                <?= $info['titulo'] ?>
            </h1>

            <p style="color:var(--gray);font-size:15px;line-height:1.7;margin-bottom:28px;">
                <?= $info['msg'] ?>
            </p>

            <?php if ($pedido): ?>
                <!-- RESUMO DO PEDIDO -->
                <div style="background:var(--bg);border-radius:var(--radius-md);padding:20px;text-align:left;margin-bottom:28px;border:1px solid var(--light-gray);">
                    <div style="display:flex;justify-content:space-between;margin-bottom:10px;">
                        <span style="font-size:13px;color:var(--gray);font-weight:600;">Pedido</span>
                        <span style="font-size:13px;font-weight:700;color:var(--dark);">#<?= $pedido['id'] ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:10px;">
                        <span style="font-size:13px;color:var(--gray);font-weight:600;">Total</span>
                        <span style="font-size:13px;font-weight:700;color:var(--primary);"><?= formatar_preco($pedido['total']) ?></span>
                    </div>
                    <?php if ($payment_id): ?>
                        <div style="display:flex;justify-content:space-between;margin-bottom:10px;">
                            <span style="font-size:13px;color:var(--gray);font-weight:600;">ID do Pagamento</span>
                            <span style="font-size:13px;font-weight:600;color:var(--dark);"><?= htmlspecialchars($payment_id) ?></span>
                        </div>
                    <?php endif; ?>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="font-size:13px;color:var(--gray);font-weight:600;">Status</span>
                        <span style="font-size:12px;font-weight:700;padding:3px 12px;border-radius:20px;background:<?= $info['cor'] ?>1a;color:<?= $info['cor'] ?>;">
                            <?= $info['titulo'] ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($info['pagamento_status'] === 'recusado'): ?>
                <!-- BOTÃO TENTAR DE NOVO -->
                <?php if ($id_pedido > 0): ?>
                    <a href="criar_preferencia.php?pedido=<?= $id_pedido ?>" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;margin-bottom:12px;">
                        <i class="fas fa-rotate-right"></i> Tentar Novamente
                    </a>
                <?php endif; ?>
            <?php endif; ?>

            <a href="painel_cliente.php" class="btn btn-secondary" style="width:100%;justify-content:center;">
                <i class="fas fa-clipboard-list"></i> Ver Meus Pedidos
            </a>

            <?php if ($info['pagamento_status'] === 'aprovado'): ?>
                <p style="margin-top:18px;font-size:12px;color:var(--gray);">
                    <i class="fas fa-envelope"></i> Um e-mail de confirmação será enviado pelo Mercado Pago.
                </p>
            <?php endif; ?>
        </div>

        <?php if (MP_AMBIENTE === 'sandbox'): ?>
            <div class="alert alert-warning" style="font-size:13px;">
                <i class="fas fa-flask"></i>
                <strong>Modo Sandbox ativo.</strong> Use o <a href="https://www.mercadopago.com.br/developers/pt/docs/checkout-pro/additional-content/test-cards" target="_blank" style="color:var(--warning);font-weight:700;">cartão de teste</a> do Mercado Pago.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
