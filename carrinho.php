<?php
session_start();
include "config.php";
include "helpers.php";

verificar_login('cliente');

$id_cliente = $_SESSION['id_usuario'];

// ADICIONAR AO CARRINHO
if (isset($_POST['adicionar_carrinho'])) {
    $id_produto = (int)$_POST['id_produto'];
    $quantidade = (int)$_POST['quantidade'];
    $tipo = $_POST['tipo_produto'] ?? 'normal';

    $produto = $conn->query("SELECT * FROM produtos WHERE id=$id_produto AND disponivel=1")->fetch_assoc();

    if ($produto) {
        $existe = $conn->query("SELECT * FROM carrinho WHERE id_cliente=$id_cliente AND id_produto=$id_produto AND tipo_produto='$tipo'")->fetch_assoc();
        if ($existe) {
            $nova_qtd = $existe['quantidade'] + $quantidade;
            $conn->query("UPDATE carrinho SET quantidade=$nova_qtd WHERE id={$existe['id']}");
        } else {
            $conn->query("INSERT INTO carrinho (id_cliente, id_produto, tipo_produto, quantidade) VALUES ($id_cliente, $id_produto, '$tipo', $quantidade)");
        }

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['sucesso' => true]); exit;
        }
        redirecionar($_POST['redirect'] ?? 'carrinho.php', 'Produto adicionado à sacola!');
    } else {
        redirecionar('index.php', 'Produto não disponível!', 'erro');
    }
}

// ATUALIZAR QUANTIDADE VIA AJAX
if (isset($_POST['ajax_atualizar_quantidade'])) {
    header('Content-Type: application/json');
    $id_carrinho = (int)$_POST['id_carrinho'];
    $quantidade  = (int)$_POST['quantidade'];

    if ($quantidade > 0) {
        $conn->query("UPDATE carrinho SET quantidade=$quantidade WHERE id=$id_carrinho AND id_cliente=$id_cliente");
        $item = $conn->query("SELECT c.quantidade, p.preco FROM carrinho c JOIN produtos p ON c.id_produto = p.id WHERE c.id = $id_carrinho")->fetch_assoc();
        $itens_all = $conn->query("SELECT c.quantidade, p.preco FROM carrinho c JOIN produtos p ON c.id_produto = p.id WHERE c.id_cliente = $id_cliente AND p.disponivel = 1")->fetch_all(MYSQLI_ASSOC);
        $total = 0; $total_itens = 0;
        foreach ($itens_all as $i) { $total += $i['preco'] * $i['quantidade']; $total_itens += $i['quantidade']; }
        echo json_encode(['sucesso' => true, 'subtotal_item' => $item['preco'] * $quantidade, 'subtotal_item_formatado' => formatar_preco($item['preco'] * $quantidade), 'total' => $total, 'total_formatado' => formatar_preco($total), 'total_itens' => $total_itens]);
    } else {
        $conn->query("DELETE FROM carrinho WHERE id=$id_carrinho AND id_cliente=$id_cliente");
        echo json_encode(['sucesso' => true, 'removido' => true]);
    }
    exit;
}

// REMOVER ITEM
if (isset($_GET['remover'])) {
    $conn->query("DELETE FROM carrinho WHERE id=" . (int)$_GET['remover'] . " AND id_cliente=$id_cliente");
    redirecionar('carrinho.php', 'Item removido!');
}

// LIMPAR CARRINHO
if (isset($_GET['limpar'])) {
    $conn->query("DELETE FROM carrinho WHERE id_cliente=$id_cliente");
    redirecionar('carrinho.php', 'Sacola esvaziada!');
}

// FINALIZAR PEDIDO
if (isset($_POST['finalizar_pedido'])) {
    $observacoes    = sanitizar_texto($_POST['observacoes'] ?? '');
    $tipo_retirada  = $_POST['tipo_retirada'] ?? 'balcao';
    $forma_pagamento = sanitizar_texto($_POST['forma_pagamento'] ?? 'presencial');
    $numero_mesa    = '';

    // Labels legíveis para salvar no pedido
    $label_retirada  = $tipo_retirada === 'delivery' ? 'Delivery' : 'Retirada no Local';
    $label_pagamento = $forma_pagamento === 'app' ? 'Pagamento pelo App' : ($tipo_retirada === 'delivery' ? 'Pagar na Entrega' : 'Pagar na Retirada');

    if ($tipo_retirada == 'delivery') {
        $endereco_entrega = sanitizar_texto($_POST['endereco_entrega'] ?? '');
        if (empty($endereco_entrega)) {
            redirecionar('carrinho.php', 'Por favor, informe o endereço de entrega!', 'erro');
        }
        $prefixo = "📦 DELIVERY – Endereço: $endereco_entrega | 💳 $label_pagamento";
        $observacoes = $prefixo . ($observacoes ? " | Obs: $observacoes" : '');
    } else {
        $prefixo = "🏪 RETIRADA NO LOCAL | 💳 $label_pagamento";
        $observacoes = $prefixo . ($observacoes ? " | Obs: $observacoes" : '');
    }

    $itens = $conn->query("SELECT c.*, p.nome, p.preco FROM carrinho c JOIN produtos p ON c.id_produto = p.id WHERE c.id_cliente = $id_cliente AND p.disponivel = 1")->fetch_all(MYSQLI_ASSOC);

    if (empty($itens)) { redirecionar('carrinho.php', 'Sacola vazia!', 'erro'); }

    $total = 0;
    foreach ($itens as $item) { $total += $item['preco'] * $item['quantidade']; }

    $pg_status_inicial = $forma_pagamento === 'app' ? 'pendente' : 'aprovado';
    $stmt = $conn->prepare("INSERT INTO pedidos (id_cliente, total, observacoes, numero_mesa, tipo_retirada, forma_pagamento, pagamento_status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("idsssss", $id_cliente, $total, $observacoes, $numero_mesa, $tipo_retirada, $forma_pagamento, $pg_status_inicial);
    $stmt->execute();
    $id_pedido = $conn->insert_id;

    foreach ($itens as $item) {
        $stmt = $conn->prepare("INSERT INTO pedido_itens (id_pedido, id_produto, quantidade, preco_unitario) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiid", $id_pedido, $item['id_produto'], $item['quantidade'], $item['preco']);
        $stmt->execute();
    }

    $conn->query("DELETE FROM carrinho WHERE id_cliente=$id_cliente");

    // Se pagamento pelo app → criar preferência no Mercado Pago
    if ($forma_pagamento === 'app') {
        header("Location: criar_preferencia.php?pedido=$id_pedido");
        exit;
    }

    $msg_pedido = $tipo_retirada == 'delivery'
        ? "Pedido #$id_pedido confirmado! Em breve seu delivery será enviado."
        : "Pedido #$id_pedido confirmado! Retire no balcão quando estiver pronto.";
    redirecionar('painel_cliente.php', $msg_pedido);
}

$itens = $conn->query("SELECT c.*, p.nome, p.descricao, p.preco, p.imagem, p.categoria FROM carrinho c JOIN produtos p ON c.id_produto = p.id WHERE c.id_cliente = $id_cliente AND p.disponivel = 1")->fetch_all(MYSQLI_ASSOC);

$total = 0; $total_itens = 0;
foreach ($itens as $item) { $total += $item['preco'] * $item['quantidade']; $total_itens += $item['quantidade']; }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sacola - FarmaVida</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <div class="header-container">
            <a href="index.php" class="logo">
                <div class="logo-icon"><i class="fas fa-prescription-bottle-medical"></i></div>
                Farma<span>Vida</span>
            </a>
            <div class="nav-buttons">
                <a href="index.php" class="btn btn-secondary"><i class="fas fa-store"></i> Continuar Comprando</a>
                <a href="painel_cliente.php" class="btn btn-primary"><i class="fas fa-user"></i> Minha Conta</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;padding:20px 24px;background:var(--white);border-radius:var(--radius-lg);border-left:4px solid var(--primary);box-shadow:var(--shadow-sm);">
            <div style="display:flex;align-items:center;gap:14px;">
                <div style="width:42px;height:42px;background:var(--bg2);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-shopping-bag" style="color:var(--primary);font-size:18px;"></i>
                </div>
                <div>
                    <h1 style="font-family:'Sora',sans-serif;font-size:20px;font-weight:800;color:var(--dark);margin:0 0 2px;">Minha Sacola</h1>
                    <p style="margin:0;font-size:13px;color:var(--gray);" id="header-itens"><?= $total_itens ?> item(ns) na sacola</p>
                </div>
            </div>
            <a href="index.php" style="font-size:13px;font-weight:600;color:var(--primary);text-decoration:none;display:flex;align-items:center;gap:6px;opacity:.8;transition:opacity .2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=.8">
                <i class="fas fa-plus"></i> Adicionar mais
            </a>
        </div>

        <?php if (isset($_SESSION['sucesso'])): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $_SESSION['sucesso'] ?></div>
            <?php unset($_SESSION['sucesso']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['erro'])): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $_SESSION['erro'] ?></div>
            <?php unset($_SESSION['erro']); ?>
        <?php endif; ?>

        <?php if (empty($itens)): ?>
            <div class="card empty">
                <i class="fas fa-shopping-bag"></i>
                <h2>Sua sacola está vazia</h2>
                <p>Adicione produtos da nossa farmácia!</p>
                <a href="index.php" class="btn btn-primary"><i class="fas fa-pills"></i> Ver Produtos</a>
            </div>
        <?php else: ?>
            <div class="cart-layout">
                <!-- ITENS -->
                <div id="cart-items-container">
                    <?php foreach ($itens as $item): ?>
                        <div class="cart-item" id="item-<?= $item['id'] ?>" data-preco="<?= $item['preco'] ?>">
                            <?php if ($item['imagem'] && file_exists($item['imagem'])): ?>
                                <img src="<?= $item['imagem'] ?>" alt="<?= htmlspecialchars($item['nome']) ?>">
                            <?php else: ?>
                                <div style="width:90px;height:90px;background:var(--bg);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;border:1px solid var(--light-gray);">
                                    <i class="fas fa-pills" style="font-size:28px;color:var(--light-gray);"></i>
                                </div>
                            <?php endif; ?>

                            <div class="cart-item-info">
                                <div class="cart-item-categoria"><i class="fas fa-tag"></i> <?= htmlspecialchars($item['categoria']) ?></div>
                                <div class="cart-item-nome"><?= htmlspecialchars($item['nome']) ?></div>
                                <div class="cart-item-preco"><?= formatar_preco($item['preco']) ?> <span style="font-size:12px;color:var(--gray);font-weight:400;">/ unidade</span></div>
                            </div>

                            <div class="qty-controls">
                                <button type="button" class="qty-btn minus" onclick="alterarQtd(<?= $item['id'] ?>, -1)" title="Diminuir"><i class="fas fa-minus"></i></button>
                                <span class="qty-value" id="qty-<?= $item['id'] ?>"><?= $item['quantidade'] ?></span>
                                <button type="button" class="qty-btn plus" onclick="alterarQtd(<?= $item['id'] ?>, 1)" title="Aumentar"><i class="fas fa-plus"></i></button>
                            </div>

                            <div class="subtotal-value" id="subtotal-<?= $item['id'] ?>"><?= formatar_preco($item['preco'] * $item['quantidade']) ?></div>

                            <button type="button" class="remove-btn" onclick="removerItem(<?= $item['id'] ?>)" title="Remover"><i class="fas fa-trash"></i></button>
                        </div>
                    <?php endforeach; ?>

                    <div style="display:flex;justify-content:space-between;margin-top:14px;flex-wrap:wrap;gap:10px;">
                        <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Continuar Comprando</a>
                        <a href="?limpar" class="btn btn-danger" onclick="return confirm('Esvaziar a sacola?')"><i class="fas fa-trash"></i> Limpar Sacola</a>
                    </div>
                </div>

                <!-- CHECKOUT -->
                <div class="checkout-card">
                    <h3><i class="fas fa-receipt" style="color:var(--primary);"></i> Finalizar Compra</h3>

                    <form method="POST" id="checkout-form">

                        <!-- OPÇÕES DE ENTREGA -->
                        <div style="margin-bottom:20px;">
                            <label style="display:block;margin-bottom:12px;font-weight:700;color:var(--dark);font-size:14px;">
                                <i class="fas fa-truck-medical"></i> Como deseja receber?
                            </label>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">

                                <!-- RETIRAR NO LOCAL -->
                                <label style="cursor:pointer;">
                                    <input type="radio" name="tipo_retirada" value="balcao" checked style="display:none;" id="r-balcao">
                                    <div id="lbl-balcao" onclick="selecionarRetirada('balcao')"
                                         style="padding:18px 12px;border:2px solid var(--primary);border-radius:var(--radius-md);text-align:center;background:rgba(0,135,90,.07);transition:all .25s;cursor:pointer;">
                                        <i class="fas fa-store-alt" id="icon-balcao" style="font-size:28px;color:var(--primary);display:block;margin-bottom:8px;"></i>
                                        <span style="font-weight:700;font-size:13px;color:var(--dark);display:block;">Buscar no Local</span>
                                        <span style="font-size:11px;color:var(--gray);display:block;margin-top:3px;">Retire na farmácia</span>
                                    </div>
                                </label>

                                <!-- DELIVERY -->
                                <label style="cursor:pointer;">
                                    <input type="radio" name="tipo_retirada" value="delivery" style="display:none;" id="r-delivery">
                                    <div id="lbl-delivery" onclick="selecionarRetirada('delivery')"
                                         style="padding:18px 12px;border:2px solid var(--light-gray);border-radius:var(--radius-md);text-align:center;transition:all .25s;cursor:pointer;">
                                        <i class="fas fa-motorcycle" id="icon-delivery" style="font-size:28px;color:var(--gray-light);display:block;margin-bottom:8px;"></i>
                                        <span style="font-weight:700;font-size:13px;color:var(--dark);display:block;">Delivery</span>
                                        <span style="font-size:11px;color:var(--gray);display:block;margin-top:3px;">Entrega em casa</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- CAMPO ENDEREÇO (aparece só no delivery) -->
                        <div id="delivery-field" style="display:none;margin-bottom:18px;animation:fadeUp .3s ease;">
                            <div style="background:linear-gradient(135deg,#e6f0ff,#f0f7ff);border:1.5px solid #bfdbfe;border-radius:var(--radius-md);padding:16px;">
                                <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                                    <i class="fas fa-map-marker-alt" style="color:var(--secondary);font-size:18px;"></i>
                                    <span style="font-weight:700;color:var(--dark);font-size:14px;">Endereço de Entrega *</span>
                                </div>
                                <input type="text" name="endereco_entrega" id="endereco_entrega"
                                       placeholder="Rua, número, bairro, cidade, CEP"
                                       style="background:white;border-color:#bfdbfe;">
                                <p style="font-size:11px;color:var(--gray);margin-top:8px;display:flex;align-items:center;gap:5px;">
                                    <i class="fas fa-info-circle" style="color:var(--info);"></i>
                                    Consulte a taxa de entrega com o atendente
                                </p>
                            </div>
                        </div>

                        <!-- FORMA DE PAGAMENTO (muda dinamicamente) -->
                        <div style="margin-bottom:20px;">
                            <label style="display:block;margin-bottom:12px;font-weight:700;color:var(--dark);font-size:14px;">
                                <i class="fas fa-wallet"></i> Como deseja pagar?
                            </label>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" id="pagamento-opcoes">

                                <!-- OPÇÃO 1: muda o label conforme retirada (Na Retirada / Na Entrega) -->
                                <label style="cursor:pointer;">
                                    <input type="radio" name="forma_pagamento" value="presencial" checked style="display:none;" id="p-presencial">
                                    <div id="lbl-presencial" onclick="selecionarPagamento('presencial')"
                                         style="padding:16px 10px;border:2px solid var(--primary);border-radius:var(--radius-md);text-align:center;background:rgba(0,135,90,.07);transition:all .25s;cursor:pointer;">
                                        <i class="fas fa-money-bill-wave" id="icon-presencial" style="font-size:26px;color:var(--primary);display:block;margin-bottom:7px;"></i>
                                        <span id="txt-presencial" style="font-weight:700;font-size:13px;color:var(--dark);display:block;">Na Retirada</span>
                                        <span id="sub-presencial" style="font-size:10px;color:var(--gray);display:block;margin-top:2px;">Dinheiro ou cartão</span>
                                    </div>
                                </label>

                                <!-- OPÇÃO 2: Pagar pelo App -->
                                <label style="cursor:pointer;">
                                    <input type="radio" name="forma_pagamento" value="app" style="display:none;" id="p-app">
                                    <div id="lbl-app" onclick="selecionarPagamento('app')"
                                         style="padding:16px 10px;border:2px solid var(--light-gray);border-radius:var(--radius-md);text-align:center;transition:all .25s;cursor:pointer;">
                                        <i class="fas fa-mobile-screen-button" id="icon-app" style="font-size:26px;color:var(--gray-light);display:block;margin-bottom:7px;"></i>
                                        <span style="font-weight:700;font-size:13px;color:var(--dark);display:block;">No Aplicativo</span>
                                        <span style="font-size:10px;color:var(--gray);display:block;margin-top:2px;">Pix, cartão online</span>
                                    </div>
                                </label>
                            </div>

                            <!-- Campo Pix / dados app (aparece ao selecionar app) -->
                            <div id="app-field" style="display:none;margin-top:12px;animation:fadeUp .3s ease;">
                                <div style="background:linear-gradient(135deg,#f5f3ff,#ede9fe);border:1.5px solid #c4b5fd;border-radius:var(--radius-md);padding:14px;display:flex;align-items:center;gap:12px;">
                                    <i class="fas fa-qrcode" style="font-size:28px;color:#7c3aed;flex-shrink:0;"></i>
                                    <div>
                                        <strong style="color:#4c1d95;font-size:13px;display:block;">Pagamento pelo App</strong>
                                        <span style="font-size:12px;color:#6d28d9;">Após confirmar, enviaremos o link de pagamento via WhatsApp ou e-mail.</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div style="background:var(--bg);padding:16px;border-radius:var(--radius-md);margin-bottom:18px;">
                            <div class="checkout-line">
                                <span>Subtotal (<span id="qtd-itens"><?= $total_itens ?></span> itens)</span>
                                <span id="subtotal-geral"><?= formatar_preco($total) ?></span>
                            </div>
                            <div class="checkout-line">
                                <span>Taxa de serviço</span>
                                <span style="color:var(--success);font-weight:700;">Grátis</span>
                            </div>
                            <div class="checkout-total">
                                <span>Total</span>
                                <span id="total-geral"><?= formatar_preco($total) ?></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-comment-medical"></i> Observações (opcional)</label>
                            <textarea name="observacoes" rows="2" placeholder="Prescrição, alergias, orientações do médico..."></textarea>
                        </div>

                        <button type="submit" name="finalizar_pedido" class="btn btn-success btn-lg" style="width:100%;justify-content:center;">
                            <i class="fas fa-check"></i> Confirmar Pedido
                        </button>
                    </form>

                    <p style="text-align:center;margin-top:14px;font-size:12px;color:var(--gray);">
                        <i class="fas fa-shield-halved"></i> Compra 100% segura &nbsp;|&nbsp;
                        <i class="fas fa-credit-card"></i> Pagamento na retirada ou na entrega
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        let itens = <?= json_encode(array_map(function($i){ return ['id'=>$i['id'],'preco'=>floatval($i['preco']),'quantidade'=>intval($i['quantidade'])]; }, $itens)) ?>;

        function selecionarRetirada(tipo) {
            document.getElementById('r-balcao').checked   = (tipo === 'balcao');
            document.getElementById('r-delivery').checked = (tipo === 'delivery');

            const lblBalcao   = document.getElementById('lbl-balcao');
            const lblDelivery = document.getElementById('lbl-delivery');
            const iconBalcao  = document.getElementById('icon-balcao');
            const iconDelivery = document.getElementById('icon-delivery');

            // Visual — Balcão
            const isBalcao = tipo === 'balcao';
            lblBalcao.style.borderColor = isBalcao ? 'var(--primary)' : 'var(--light-gray)';
            lblBalcao.style.background  = isBalcao ? 'rgba(0,135,90,.07)' : '';
            iconBalcao.style.color      = isBalcao ? 'var(--primary)' : 'var(--gray-light)';

            // Visual — Delivery
            const isDelivery = tipo === 'delivery';
            lblDelivery.style.borderColor = isDelivery ? 'var(--secondary)' : 'var(--light-gray)';
            lblDelivery.style.background  = isDelivery ? 'rgba(0,82,204,.07)' : '';
            iconDelivery.style.color      = isDelivery ? 'var(--secondary)' : 'var(--gray-light)';

            // Campo endereço
            const deliveryField   = document.getElementById('delivery-field');
            const enderecoEntrega = document.getElementById('endereco_entrega');
            deliveryField.style.display = isDelivery ? 'block' : 'none';
            enderecoEntrega.required    = isDelivery;
            if (!isDelivery) enderecoEntrega.value = '';

            // Atualizar label do pagamento presencial conforme tipo
            document.getElementById('txt-presencial').textContent = isDelivery ? 'Na Entrega'  : 'Na Retirada';
            document.getElementById('sub-presencial').textContent = isDelivery ? 'Pago ao entregador' : 'Dinheiro ou cartão';

            // Reset seleção de pagamento para presencial ao trocar modo
            selecionarPagamento('presencial');
        }

        function selecionarPagamento(tipo) {
            document.getElementById('p-presencial').checked = (tipo === 'presencial');
            document.getElementById('p-app').checked        = (tipo === 'app');

            const lblPresencial = document.getElementById('lbl-presencial');
            const lblApp        = document.getElementById('lbl-app');
            const iconPresencial = document.getElementById('icon-presencial');
            const iconApp        = document.getElementById('icon-app');
            const appField       = document.getElementById('app-field');

            // Visual — Presencial
            const isPres = tipo === 'presencial';
            lblPresencial.style.borderColor = isPres ? 'var(--primary)' : 'var(--light-gray)';
            lblPresencial.style.background  = isPres ? 'rgba(0,135,90,.07)' : '';
            iconPresencial.style.color      = isPres ? 'var(--primary)' : 'var(--gray-light)';

            // Visual — App
            const isApp = tipo === 'app';
            lblApp.style.borderColor = isApp ? '#7c3aed' : 'var(--light-gray)';
            lblApp.style.background  = isApp ? 'rgba(124,58,237,.07)' : '';
            iconApp.style.color      = isApp ? '#7c3aed' : 'var(--gray-light)';

            // Info box
            appField.style.display = isApp ? 'block' : 'none';
        }

        function formatarPreco(v) { return 'R$ ' + parseFloat(v).toFixed(2).replace('.', ','); }

        function mostrarToast(msg) {
            document.querySelectorAll('.update-toast').forEach(t=>t.remove());
            const t = document.createElement('div');
            t.className='toast'; t.innerHTML=`<i class="fas fa-check-circle"></i> ${msg}`;
            document.body.appendChild(t);
            setTimeout(()=>{ t.style.opacity='0'; setTimeout(()=>t.remove(),300); },2000);
        }

        async function alterarQtd(idCarrinho, delta) {
            const item = itens.find(i=>i.id==idCarrinho);
            if (!item) return;
            const novaQtd = item.quantidade + delta;
            const cartItem = document.getElementById('item-'+idCarrinho);

            if (novaQtd <= 0) { if(confirm('Remover este item?')) removerItem(idCarrinho); return; }

            cartItem.style.opacity='.6';
            item.quantidade = novaQtd;
            document.getElementById('qty-'+idCarrinho).textContent = novaQtd;
            const novoSub = item.preco * novaQtd;
            document.getElementById('subtotal-'+idCarrinho).textContent = formatarPreco(novoSub);
            atualizarTotalGeral();
            cartItem.style.opacity='1';

            const fd = new FormData();
            fd.append('ajax_atualizar_quantidade','1'); fd.append('id_carrinho',idCarrinho); fd.append('quantidade',novaQtd);
            await fetch('carrinho.php',{method:'POST',body:fd});
            mostrarToast('Quantidade atualizada!');
        }

        function atualizarTotalGeral() {
            let total=0, qtd=0;
            itens.forEach(i=>{ total+=i.preco*i.quantidade; qtd+=i.quantidade; });
            document.getElementById('subtotal-geral').textContent = formatarPreco(total);
            document.getElementById('total-geral').textContent = formatarPreco(total);
            document.getElementById('qtd-itens').textContent = qtd;
            document.getElementById('header-itens').textContent = qtd + ' item(ns) na sacola';
        }

        function removerItem(idCarrinho) {
            const cartItem = document.getElementById('item-'+idCarrinho);
            cartItem.style.opacity='0'; cartItem.style.transform='translateX(-100%)'; cartItem.style.transition='.3s';
            itens = itens.filter(i=>i.id!=idCarrinho);
            atualizarTotalGeral();
            setTimeout(()=>{ window.location.href='?remover='+idCarrinho; },300);
        }
    </script>
</body>
</html>
