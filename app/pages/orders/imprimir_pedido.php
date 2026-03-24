<?php
require_once FARMAVIDA_ROOT . '/app/core/bootstrap.php';
require_once FARMAVIDA_ROOT . '/app/core/config.php';
require_once FARMAVIDA_ROOT . '/app/core/helpers.php';

verificar_login('dono');

$id_pedido = (int) ($_GET['id'] ?? 0);

$pedido = $conn->query("SELECT p.*, u.nome as cliente_nome, u.telefone, u.endereco FROM pedidos p JOIN usuarios u ON p.id_cliente = u.id WHERE p.id = $id_pedido")->fetch_assoc();
if (!$pedido) die("Pedido não encontrado!");

$itens = $conn->query("SELECT pi.*, pr.nome as produto_nome FROM pedido_itens pi JOIN produtos pr ON pi.id_produto = pr.id WHERE pi.id_pedido = $id_pedido")->fetch_all(MYSQLI_ASSOC);

$status_labels = ['pendente'=>'AGUARDANDO','preparando'=>'SEPARANDO','pronto'=>'PRONTO','entregue'=>'ENTREGUE','cancelado'=>'CANCELADO'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota Fiscal - Pedido #<?= $id_pedido ?> | FarmaVida</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Courier New', monospace; padding: 20px; max-width: 800px; margin: 0 auto; background: #f0f7ff; }
        .receipt { background: white; padding: 36px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,.1); }
        .header-receipt { text-align: center; border-bottom: 2px dashed #00875a; padding-bottom: 20px; margin-bottom: 20px; }
        .logo-receipt { font-size: 28px; font-weight: 900; color: #00875a; margin-bottom: 8px; }
        .logo-receipt span { color: #0052cc; }
        .info-rest { font-size: 12px; line-height: 1.7; color: #5e7491; }
        .info-pedido { margin: 20px 0; padding: 16px; background: #f0f7ff; border-radius: 10px; border-left: 4px solid #00875a; }
        .info-pedido table { width: 100%; font-size: 13px; }
        .info-pedido td { padding: 5px 4px; }
        .info-pedido td:first-child { font-weight: 700; width: 130px; color: #0d1b2a; }
        .status-badge { display: inline-block; padding: 4px 14px; background: #00875a; color: #fff; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .itens-tabela { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .itens-tabela th { text-align: left; padding: 10px; background: #00875a; color: #fff; font-size: 12px; }
        .itens-tabela td { padding: 10px; border-bottom: 1px solid #dce8f2; font-size: 13px; }
        .itens-tabela tr:last-child td { border-bottom: 2px solid #00875a; }
        .total-section { margin-top: 20px; padding: 16px; background: #f0f7ff; border-radius: 10px; }
        .total-linha { display: flex; justify-content: space-between; padding: 8px 0; font-size: 14px; }
        .total-final { font-family: Arial, sans-serif; font-size: 24px; font-weight: 900; padding-top: 14px; border-top: 2px solid #0d1b2a; margin-top: 10px; color: #00875a; }
        .obs-box { margin: 18px 0; padding: 14px; background: #fffae6; border-left: 4px solid #ff8b00; border-radius: 6px; font-size: 13px; }
        .footer-receipt { text-align: center; margin-top: 28px; padding-top: 18px; border-top: 2px dashed #00875a; font-size: 12px; line-height: 1.9; color: #5e7491; }
        .botoes-tela { text-align: center; margin: 28px 0; }
        .btn { padding: 12px 28px; margin: 0 8px; border: none; border-radius: 25px; cursor: pointer; font-size: 14px; font-weight: 700; text-decoration: none; display: inline-block; }
        .btn-imprimir { background: linear-gradient(135deg, #00875a, #0052cc); color: white; }
        .btn-voltar { background: #6b7280; color: white; }
        @media print { body { background: white; padding: 0; } .botoes-tela { display: none; } .receipt { box-shadow: none; padding: 0; } }
    </style>
</head>
<body>
    <div class="botoes-tela">
        <button onclick="window.print()" class="btn btn-imprimir">🖨️ IMPRIMIR NOTA</button>
        <a href="painel_dono.php" class="btn btn-voltar">← VOLTAR</a>
    </div>

    <div class="receipt">
        <div class="header-receipt">
            <div class="logo-receipt">💊 Farma<span>Vida</span></div>
            <div class="info-rest">
                <strong>Farmácia e Drogaria FarmaVida</strong><br>
                Av. da Saúde, 456 – Centro<br>
                Telefone: (17) 99999-1234<br>
                CNPJ: 12.345.678/0001-00<br>
                CRF: 12345
            </div>
        </div>

        <div class="info-pedido">
            <table>
                <tr><td>Pedido Nº:</td><td><strong>#<?= $id_pedido ?></strong></td></tr>
                <tr><td>Data/Hora:</td><td><?= date('d/m/Y H:i:s', strtotime($pedido['criado_em'])) ?></td></tr>
                <tr><td>Status:</td><td><span class="status-badge"><?= $status_labels[$pedido['status']] ?></span></td></tr>
                <tr><td>Cliente:</td><td><?= htmlspecialchars($pedido['cliente_nome']) ?></td></tr>
                <?php if ($pedido['telefone']): ?><tr><td>Telefone:</td><td><?= htmlspecialchars($pedido['telefone']) ?></td></tr><?php endif; ?>
                <?php if ($pedido['endereco']): ?><tr><td>Endereço:</td><td><?= htmlspecialchars($pedido['endereco']) ?></td></tr><?php endif; ?>
            </table>
        </div>

        <table class="itens-tabela">
            <thead>
                <tr>
                    <th>QTD</th><th>PRODUTO</th><th style="text-align:right;">PREÇO UN.</th><th style="text-align:right;">SUBTOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($itens as $item): ?>
                    <tr>
                        <td style="text-align:center;"><strong><?= $item['quantidade'] ?>x</strong></td>
                        <td><?= htmlspecialchars($item['produto_nome']) ?></td>
                        <td style="text-align:right;"><?= formatar_preco($item['preco_unitario']) ?></td>
                        <td style="text-align:right;"><strong><?= formatar_preco($item['preco_unitario'] * $item['quantidade']) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($pedido['observacoes']): ?>
            <div class="obs-box"><strong>⚠️ OBSERVAÇÕES:</strong> <?= htmlspecialchars($pedido['observacoes']) ?></div>
        <?php endif; ?>

        <div class="total-section">
            <div class="total-linha"><span>Subtotal:</span><span><?= formatar_preco($pedido['total']) ?></span></div>
            <div class="total-linha" style="color:#00875a;font-weight:700;"><span>Desconto:</span><span>R$ 0,00</span></div>
            <div class="total-linha total-final"><span>TOTAL:</span><span><?= formatar_preco($pedido['total']) ?></span></div>
        </div>

        <div class="footer-receipt">
            <strong>OBRIGADO PELA CONFIANÇA! 💊</strong><br>
            Cuide bem da sua saúde — FarmaVida está aqui para você!<br>
            <small>Este documento não substitui nota fiscal eletrônica.<br>Dispensação sob responsabilidade do farmacêutico habilitado.</small>
        </div>
    </div>
</body>
</html>
