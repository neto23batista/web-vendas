<?php
if (!defined('FARMAVIDA_ROOT')) { define('FARMAVIDA_ROOT', dirname(__DIR__)); }
require_once FARMAVIDA_ROOT . '/app/core/bootstrap.php';
require_once FARMAVIDA_ROOT . '/app/core/config.php';
require_once FARMAVIDA_ROOT . '/app/core/helpers.php';

$idPedido = (int)($_GET['pedido'] ?? 0);
$pedido = null;
if ($idPedido > 0) {
    $stmt = $conn->prepare("SELECT id, status, tipo_retirada, forma_pagamento, pagamento_status, rastreio_url, reserva_expira_em, janela_inicio, janela_fim FROM pedidos WHERE id = ?");
    $stmt->bind_param("i", $idPedido);
    $stmt->execute();
    $pedido = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Rastreio - FarmaVida</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container" style="max-width:720px;padding:24px 0;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
            <a href="/index.php" class="logo" style="text-decoration:none;"><div class="logo-icon"><i class="fas fa-prescription-bottle-medical"></i></div> Farma<span>Vida</span></a>
            <a href="/painel_cliente.php" class="btn btn-secondary"><i class="fas fa-user"></i> Minha conta</a>
        </div>
        <h1 style="font-family:'Bricolage Grotesque',sans-serif;font-size:22px;margin-bottom:6px;">Rastreio do pedido</h1>
        <?php if (!$pedido): ?>
            <div class="card" style="padding:16px;border:1px solid var(--border);background:var(--surface);border-radius:var(--radius-lg);">
                Pedido não encontrado.
            </div>
        <?php else: ?>
            <div class="card" style="padding:16px;border:1px solid var(--border);background:var(--surface);border-radius:var(--radius-lg);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <div><strong>Pedido #<?= htmlspecialchars($pedido['id']) ?></strong></div>
                    <span style="padding:6px 10px;border-radius:var(--radius-full);background:rgba(255,255,255,.06);border:1px solid var(--border);text-transform:uppercase;font-size:11px;letter-spacing:.4px;"><?= htmlspecialchars($pedido['status']) ?></span>
                </div>
                <p style="color:var(--gray);font-size:13px;margin-bottom:10px;">Retirada: <?= htmlspecialchars($pedido['tipo_retirada']) ?> | Pagamento: <?= htmlspecialchars($pedido['pagamento_status']) ?></p>
                <?php if (!empty($pedido['janela_inicio']) && !empty($pedido['janela_fim'])): ?>
                    <p style="font-size:13px;color:var(--gray);">Janela estimada: <?= htmlspecialchars($pedido['janela_inicio']) ?> - <?= htmlspecialchars($pedido['janela_fim']) ?></p>
                <?php endif; ?>
                <div style="margin-top:12px;">
                    <div style="height:6px;border-radius:var(--radius-full);background:rgba(255,255,255,.08);overflow:hidden;">
                        <div style="width:65%;background:var(--primary);height:100%;"></div>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--gray);margin-top:6px;">
                        <span>Recebido</span><span>Separação</span><span>Entrega</span>
                    </div>
                </div>
                <p style="margin-top:12px;font-size:12px;color:var(--gray);display:flex;gap:6px;align-items:center;">
                    <i class="fas fa-bell"></i> Receba atualizações no WhatsApp: <a href="https://wa.me/?text=Quero%20acompanhar%20o%20pedido%20<?= urlencode('#'.$pedido['id']) ?>" target="_blank" style="color:var(--primary);">solicitar</a>
                </p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
