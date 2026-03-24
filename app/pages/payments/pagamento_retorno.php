<?php
require_once FARMAVIDA_ROOT . '/app/core/bootstrap.php';
 
 
 
 
require_once FARMAVIDA_ROOT . '/app/core/config.php';
require_once FARMAVIDA_ROOT . '/app/core/helpers.php';
require_once FARMAVIDA_ROOT . '/app/integrations/mercadopago_config.php';

$payment_id    = trim((string)($_GET['payment_id'] ?? ''));
$preference_id = trim((string)($_GET['preference_id'] ?? ''));
$id_pedido_url = (int)($_GET['pedido'] ?? 0);

 
$pagamento_verificado = null;
if (!empty($payment_id)) {
    $pagamento_verificado = mp_request('GET', "/v1/payments/$payment_id");
}

 
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

function buscar_pedido_para_exibicao(mysqli $conn, int $id_pedido, ?int $id_cliente = null): ?array {
    if ($id_pedido <= 0) return null;

    if ($id_cliente !== null) {
        $stmt = $conn->prepare("SELECT * FROM pedidos WHERE id = ? AND id_cliente = ?");
        $stmt->bind_param("ii", $id_pedido, $id_cliente);
    } else {
        $stmt = $conn->prepare("SELECT * FROM pedidos WHERE id = ?");
        $stmt->bind_param("i", $id_pedido);
    }

    $stmt->execute();
    $pedido = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $pedido ?: null;
}

$pedido_verificado = null;
if ($pagamento_verificado && isset($pagamento_verificado['status'], $pagamento_verificado['id'])) {
    $external_ref_api  = (int)($pagamento_verificado['external_reference'] ?? 0);
    $preference_id_api = trim((string)($pagamento_verificado['preference_id'] ?? ''));
    $id_pedido_candidato = $external_ref_api > 0 ? $external_ref_api : $id_pedido_url;

    if ($id_pedido_candidato > 0) {
        $stmt = $conn->prepare("SELECT * FROM pedidos WHERE id = ? AND forma_pagamento = 'app' LIMIT 1");
        $stmt->bind_param("i", $id_pedido_candidato);
        $stmt->execute();
        $pedido_candidato = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($pedido_candidato) {
            $ids_conferem = true;
            if ($external_ref_api > 0) {
                $ids_conferem = $external_ref_api === (int)$pedido_candidato['id'];
            }
            if ($ids_conferem && $id_pedido_url > 0) {
                $ids_conferem = $id_pedido_url === (int)$pedido_candidato['id'];
            }

            $pref_salva = trim((string)($pedido_candidato['mp_preference_id'] ?? ''));
            $pref_url   = $preference_id;
            $preferencias_conferem = true;
            if ($pref_salva !== '' || $preference_id_api !== '') {
                $preferencias_conferem = $pref_salva !== ''
                    && $preference_id_api !== ''
                    && hash_equals($pref_salva, $preference_id_api);
            }
            if ($preferencias_conferem && $pref_url !== '') {
                $preferencias_conferem = $pref_salva !== ''
                    && hash_equals($pref_salva, $pref_url);
            }

            if ($ids_conferem && $preferencias_conferem) {
                $pedido_verificado = $pedido_candidato;
            }
        }
    }
}

 
$status_real = $pagamento_verificado['status'] ?? 'pending';
$info = mapear_status_mp($status_real);

if ($pedido_verificado) {
    $payment_id_verificado = (string)$pagamento_verificado['id'];
    $sql = "
        UPDATE pedidos
        SET pagamento_status = ?,
            status           = ?,
            mp_payment_id    = ?,
            mp_payment_type  = ?
            " . ($info['pagamento_status'] === 'aprovado' ? ", pago_em = COALESCE(pago_em, NOW())" : "") . "
        WHERE id = ? AND forma_pagamento = 'app'
    ";
    $stmt = $conn->prepare($sql);
    $payment_type = (string)($pagamento_verificado['payment_type_id'] ?? '');
    $pedido_id = (int)$pedido_verificado['id'];
    $stmt->bind_param(
        "ssssi",
        $info['pagamento_status'],
        $info['status_pedido'],
        $payment_id_verificado,
        $payment_type,
        $pedido_id
    );
    $stmt->execute();
    $stmt->close();

    @mkdir('logs', 0755, true);
    $log = date('Y-m-d H:i:s') . " | Pedido #$pedido_id | Payment: $payment_id_verificado | Status: $status_real | HTTP retorno validado\n";
    file_put_contents('logs/mp_pagamentos.log', $log, FILE_APPEND);
}

$pedido = null;
if ($pedido_verificado) {
    $pedido = buscar_pedido_para_exibicao($conn, (int)$pedido_verificado['id']);
} elseif (isset($_SESSION['id_usuario'])) {
    $id_usuario_sessao = (int)$_SESSION['id_usuario'];
    if (($_SESSION['tipo'] ?? '') === 'dono') {
        $pedido = buscar_pedido_para_exibicao($conn, $id_pedido_url);
    } else {
        $pedido = buscar_pedido_para_exibicao($conn, $id_pedido_url, $id_usuario_sessao);
    }
}
$payment_id_exibicao = $pedido_verificado ? (string)$pagamento_verificado['id'] : '';
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
                
                <div style="background:var(--bg);border-radius:var(--radius-md);padding:20px;text-align:left;margin-bottom:28px;border:1px solid var(--light-gray);">
                    <div style="display:flex;justify-content:space-between;margin-bottom:10px;">
                        <span style="font-size:13px;color:var(--gray);font-weight:600;">Pedido</span>
                        <span style="font-size:13px;font-weight:700;color:var(--dark);">#<?= $pedido['id'] ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:10px;">
                        <span style="font-size:13px;color:var(--gray);font-weight:600;">Total</span>
                        <span style="font-size:13px;font-weight:700;color:var(--primary);"><?= formatar_preco($pedido['total']) ?></span>
                    </div>
                    <?php if ($payment_id_exibicao !== ''): ?>
                        <div style="display:flex;justify-content:space-between;margin-bottom:10px;">
                            <span style="font-size:13px;color:var(--gray);font-weight:600;">ID do Pagamento</span>
                            <span style="font-size:13px;font-weight:600;color:var(--dark);"><?= htmlspecialchars($payment_id_exibicao) ?></span>
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
                
                <?php if ($pedido && (int)$pedido['id'] > 0): ?>
                    <a href="criar_preferencia.php?pedido=<?= (int)$pedido['id'] ?>" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;margin-bottom:12px;">
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
