<?php
require_once FARMAVIDA_ROOT . '/app/core/bootstrap.php';
require_once FARMAVIDA_ROOT . '/app/core/config.php';
require_once FARMAVIDA_ROOT . '/app/core/helpers.php';
require_once FARMAVIDA_ROOT . '/app/integrations/mailer.php';
require_once FARMAVIDA_ROOT . '/services/pedido_service.php';

verificar_login('cliente');

$id_cliente = $_SESSION['id_usuario'];

// Taxa de entrega â€” definida em um Ãºnico lugar
const TAXA_DELIVERY_VALOR = 5.00;

// â”€â”€ ADICIONAR AO CARRINHO â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if (isset($_POST['adicionar_carrinho'])) {
    verificar_csrf();
    $id_produto = (int)($_POST['id_produto'] ?? 0);
    $quantidade = max(1, (int)($_POST['quantidade'] ?? 1));
    $tipo       = in_array($_POST['tipo_produto'] ?? '', ['normal','especial']) ? $_POST['tipo_produto'] : 'normal';

    $stmt = $conn->prepare("SELECT * FROM produtos WHERE id = ? AND disponivel = 1");
    $stmt->bind_param("i", $id_produto);
    $stmt->execute();
    $produto = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($produto) {
        $stmt = $conn->prepare(
            "SELECT * FROM carrinho WHERE id_cliente = ? AND id_produto = ? AND tipo_produto = ?"
        );
        $stmt->bind_param("iis", $id_cliente, $id_produto, $tipo);
        $stmt->execute();
        $existe = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existe) {
            $nova_qtd = $existe['quantidade'] + $quantidade;
            $stmt = $conn->prepare("UPDATE carrinho SET quantidade = ? WHERE id = ?");
            $stmt->bind_param("ii", $nova_qtd, $existe['id']);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO carrinho (id_cliente, id_produto, tipo_produto, quantidade) VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("iisi", $id_cliente, $id_produto, $tipo, $quantidade);
            $stmt->execute();
            $stmt->close();
        }

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['sucesso' => true]); exit;
        }
        redirecionar($_POST['redirect'] ?? 'carrinho.php', 'Produto adicionado Ã  sacola!');
    } else {
        redirecionar('index.php', 'Produto nÃ£o disponÃ­vel!', 'erro');
    }
}

// â”€â”€ ATUALIZAR QUANTIDADE VIA AJAX â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if (isset($_POST['ajax_atualizar_quantidade'])) {
    verificar_csrf();
    header('Content-Type: application/json');
    $id_carrinho = (int)($_POST['id_carrinho'] ?? 0);
    $quantidade  = (int)($_POST['quantidade']  ?? 0);

    if ($quantidade > 0) {
        $stmt = $conn->prepare(
            "UPDATE carrinho SET quantidade = ? WHERE id = ? AND id_cliente = ?"
        );
        $stmt->bind_param("iii", $quantidade, $id_carrinho, $id_cliente);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare(
            "SELECT c.quantidade, p.preco FROM carrinho c
             JOIN produtos p ON c.id_produto = p.id
             WHERE c.id = ?"
        );
        $stmt->bind_param("i", $id_carrinho);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } else {
        $stmt = $conn->prepare(
            "DELETE FROM carrinho WHERE id = ? AND id_cliente = ?"
        );
        $stmt->bind_param("ii", $id_carrinho, $id_cliente);
        $stmt->execute();
        $stmt->close();
        $item = null;
    }

    $stmt = $conn->prepare(
        "SELECT c.quantidade, p.preco FROM carrinho c
         JOIN produtos p ON c.id_produto = p.id
         WHERE c.id_cliente = ? AND p.disponivel = 1"
    );
    $stmt->bind_param("i", $id_cliente);
    $stmt->execute();
    $itens_all = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $total = 0; $total_itens = 0;
    foreach ($itens_all as $i) {
        $total       += $i['preco'] * $i['quantidade'];
        $total_itens += $i['quantidade'];
    }
    echo json_encode([
        'sucesso'               => true,
        'removido'              => $quantidade <= 0,
        'subtotal_item'         => $item ? $item['preco'] * $quantidade : 0,
        'subtotal_item_formatado' => $item ? formatar_preco($item['preco'] * $quantidade) : 'R$ 0,00',
        'total'                 => $total,
        'total_formatado'       => formatar_preco($total),
        'total_itens'           => $total_itens,
    ]);
    exit;
}

// â”€â”€ REMOVER ITEM â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if (isset($_POST['remover_item'])) {
    verificar_csrf();
    $id_item = (int)($_POST['id_item'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM carrinho WHERE id = ? AND id_cliente = ?");
    $stmt->bind_param("ii", $id_item, $id_cliente);
    $stmt->execute();
    $stmt->close();

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        echo json_encode(['sucesso' => true]);
        exit;
    }
    redirecionar('carrinho.php', 'Item removido!');
}

// â”€â”€ LIMPAR CARRINHO â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if (isset($_POST['limpar_carrinho'])) {
    verificar_csrf();
    $stmt = $conn->prepare("DELETE FROM carrinho WHERE id_cliente = ?");
    $stmt->bind_param("i", $id_cliente);
    $stmt->execute();
    $stmt->close();

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        echo json_encode(['sucesso' => true]);
        exit;
    }
    redirecionar('carrinho.php', 'Sacola esvaziada!');
}

// â”€â”€ FINALIZAR PEDIDO â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if (isset($_POST['finalizar_pedido'])) {
    verificar_csrf();

    $observacoes     = sanitizar_texto($_POST['observacoes']     ?? '');
    $tipo_retirada   = in_array($_POST['tipo_retirada'] ?? '', ['balcao','delivery']) ? $_POST['tipo_retirada'] : 'balcao';
    $forma_pagamento = in_array($_POST['forma_pagamento'] ?? '', ['presencial','app']) ? $_POST['forma_pagamento'] : 'presencial';

    $label_pagamento = $forma_pagamento === 'app'
        ? 'Pagamento pelo App'
        : ($tipo_retirada === 'delivery' ? 'Pagar na Entrega' : 'Pagar na Retirada');

    if ($tipo_retirada === 'delivery') {
        $endereco_entrega = sanitizar_texto($_POST['endereco_entrega'] ?? '');
        if (empty($endereco_entrega)) {
            redirecionar('carrinho.php', 'Por favor, informe o endereÃ§o de entrega!', 'erro');
        }
        $taxa_fmt    = formatar_preco(TAXA_DELIVERY_VALOR);
        $prefixo     = "ðŸ“¦ DELIVERY â€“ EndereÃ§o: $endereco_entrega | Taxa: $taxa_fmt | ðŸ’³ $label_pagamento";
        $observacoes = $prefixo . ($observacoes ? " | Obs: $observacoes" : '');
    } else {
        $prefixo     = "ðŸª RETIRADA NO LOCAL | ðŸ’³ $label_pagamento";
        $observacoes = $prefixo . ($observacoes ? " | Obs: $observacoes" : '');
    }

    try {
        $pedidoCriado = pedido_criar_do_carrinho(
            $conn,
            $id_cliente,
            $observacoes,
            $tipo_retirada,
            $forma_pagamento,
            TAXA_DELIVERY_VALOR
        );
    } catch (Throwable $e) {
        error_log('Erro ao finalizar pedido: ' . $e->getMessage());
        redirecionar('carrinho.php', 'NÃƒÂ£o foi possÃƒÂ­vel finalizar o pedido agora. Tente novamente.', 'erro');
    }

    $id_pedido = (int)$pedidoCriado['id_pedido'];
    $itens = $pedidoCriado['itens'];
    $total = (float)$pedidoCriado['total'];

    if ($forma_pagamento === 'app') {
        header("Location: criar_preferencia.php?pedido=$id_pedido");
        exit;
    }

    // E-mail de confirmaÃ§Ã£o
    $stmt_cli = $conn->prepare("SELECT nome, email FROM usuarios WHERE id = ?");
    $stmt_cli->bind_param("i", $id_cliente);
    $stmt_cli->execute();
    $cli = $stmt_cli->get_result()->fetch_assoc();
    $stmt_cli->close();
    if ($cli) {
        $itens_email = array_map(fn($i) => ['nome' => $i['nome'], 'preco' => $i['preco'], 'quantidade' => $i['quantidade']], $itens);
        $corpo = email_confirmacao_pedido($id_pedido, $cli['nome'], $itens_email, $total, $tipo_retirada);
        enviar_email($cli['email'], "Pedido #$id_pedido confirmado â€“ FarmaVida", $corpo);
    }

    $msg_pedido = $tipo_retirada === 'delivery'
        ? "Pedido #$id_pedido confirmado! Em breve seu delivery serÃ¡ enviado."
        : "Pedido #$id_pedido confirmado! Retire no balcÃ£o quando estiver pronto.";
    redirecionar('painel_cliente.php', $msg_pedido);
}

// â”€â”€ LEITURA DOS ITENS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$stmt = $conn->prepare(
    "SELECT c.*, p.nome, p.descricao, p.preco, p.imagem, p.categoria
     FROM carrinho c
     JOIN produtos p ON c.id_produto = p.id
     WHERE c.id_cliente = ? AND p.disponivel = 1"
);
$stmt->bind_param("i", $id_cliente);
$stmt->execute();
$itens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total = 0; $total_itens = 0;
foreach ($itens as $item) {
    $total       += $item['preco'] * $item['quantidade'];
    $total_itens += $item['quantidade'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sacola - FarmaVida</title>
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
            <div class="nav-buttons">
                <a href="index.php"          class="btn btn-secondary"><i class="fas fa-store"></i> Continuar Comprando</a>
                <a href="painel_cliente.php" class="btn btn-primary"  ><i class="fas fa-user"></i> Minha Conta</a>
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
            <a href="index.php" style="font-size:13px;font-weight:600;color:var(--primary);text-decoration:none;display:flex;align-items:center;gap:6px;opacity:.8;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=.8">
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
                <h2>Sua sacola estÃ¡ vazia</h2>
                <p>Adicione produtos da nossa farmÃ¡cia!</p>
                <a href="index.php" class="btn btn-primary"><i class="fas fa-pills"></i> Ver Produtos</a>
            </div>
        <?php else: ?>
            <div class="cart-layout">

                <!-- ITENS -->
                <div id="cart-items-container">
                    <?php foreach ($itens as $item): ?>
                        <div class="cart-item" id="item-<?= $item['id'] ?>" data-preco="<?= $item['preco'] ?>">
                            <?php if ($item['imagem'] && file_exists($item['imagem'])): ?>
                                <img src="<?= htmlspecialchars($item['imagem']) ?>"
                                     alt="<?= htmlspecialchars($item['nome']) ?>">
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
                                <button type="button" class="qty-btn minus" onclick="alterarQtd(<?= $item['id'] ?>, -1)"><i class="fas fa-minus"></i></button>
                                <span class="qty-value" id="qty-<?= $item['id'] ?>"><?= $item['quantidade'] ?></span>
                                <button type="button" class="qty-btn plus" onclick="alterarQtd(<?= $item['id'] ?>, 1)"><i class="fas fa-plus"></i></button>
                            </div>

                            <div class="subtotal-value" id="subtotal-<?= $item['id'] ?>"><?= formatar_preco($item['preco'] * $item['quantidade']) ?></div>

                            <button type="button" class="remove-btn" onclick="removerItem(<?= $item['id'] ?>)"><i class="fas fa-trash"></i></button>
                        </div>
                    <?php endforeach; ?>

                    <div style="display:flex;justify-content:space-between;margin-top:14px;flex-wrap:wrap;gap:10px;">
                        <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Continuar Comprando</a>
                        <button type="button" class="btn btn-danger" onclick="limparCarrinho()"><i class="fas fa-trash"></i> Limpar Sacola</button>
                    </div>
                </div>

                <!-- CHECKOUT -->
                <div class="checkout-card">
                    <h3><i class="fas fa-receipt" style="color:var(--primary);"></i> Finalizar Compra</h3>

                    <form method="POST" id="checkout-form">
                        <?= campo_csrf() ?>

                        <!-- TIPO DE ENTREGA -->
                        <div style="margin-bottom:20px;">
                            <label style="display:block;margin-bottom:12px;font-weight:700;color:var(--dark);font-size:14px;">
                                <i class="fas fa-truck-medical"></i> Como deseja receber?
                            </label>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                <label style="cursor:pointer;">
                                    <input type="radio" name="tipo_retirada" value="balcao" checked style="display:none;" id="r-balcao">
                                    <div id="lbl-balcao" onclick="selecionarRetirada('balcao')"
                                         style="padding:18px 12px;border:2px solid var(--primary);border-radius:var(--radius-md);text-align:center;background:rgba(0,135,90,.07);transition:all .25s;cursor:pointer;">
                                        <i class="fas fa-store-alt" id="icon-balcao" style="font-size:28px;color:var(--primary);display:block;margin-bottom:8px;"></i>
                                        <span style="font-weight:700;font-size:13px;color:var(--dark);display:block;">Buscar no Local</span>
                                        <span style="font-size:11px;color:var(--success);display:block;margin-top:3px;font-weight:700;">âœ“ Sem taxa</span>
                                    </div>
                                </label>
                                <label style="cursor:pointer;">
                                    <input type="radio" name="tipo_retirada" value="delivery" style="display:none;" id="r-delivery">
                                    <div id="lbl-delivery" onclick="selecionarRetirada('delivery')"
                                         style="padding:18px 12px;border:2px solid var(--light-gray);border-radius:var(--radius-md);text-align:center;transition:all .25s;cursor:pointer;">
                                        <i class="fas fa-motorcycle" id="icon-delivery" style="font-size:28px;color:var(--gray-light);display:block;margin-bottom:8px;"></i>
                                        <span style="font-weight:700;font-size:13px;color:var(--dark);display:block;">Delivery</span>
                                        <span style="font-size:11px;color:var(--warning);display:block;margin-top:3px;font-weight:700;">+ <?= formatar_preco(TAXA_DELIVERY_VALOR) ?></span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- ENDEREÃ‡O ENTREGA -->
                        <div id="delivery-field" style="display:none;margin-bottom:18px;animation:fadeUp .3s ease;">
                            <div style="background:linear-gradient(135deg,#e6f0ff,#f0f7ff);border:1.5px solid #bfdbfe;border-radius:var(--radius-md);padding:16px;">
                                <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                                    <i class="fas fa-map-marker-alt" style="color:var(--secondary);font-size:18px;"></i>
                                    <span style="font-weight:700;color:var(--dark);font-size:14px;">EndereÃ§o de Entrega *</span>
                                </div>
                                <input type="text" name="endereco_entrega" id="endereco_entrega"
                                       placeholder="Rua, nÃºmero, bairro, cidade, CEP"
                                       style="background:white;border-color:#bfdbfe;">
                                <p style="font-size:11px;color:var(--gray);margin-top:8px;display:flex;align-items:center;gap:5px;">
                                    <i class="fas fa-info-circle" style="color:var(--info);"></i>
                                    Taxa de entrega: <strong><?= formatar_preco(TAXA_DELIVERY_VALOR) ?></strong> (jÃ¡ incluÃ­da no total)
                                </p>
                            </div>
                        </div>

                        <!-- FORMA DE PAGAMENTO -->
                        <div style="margin-bottom:20px;">
                            <label style="display:block;margin-bottom:12px;font-weight:700;color:var(--dark);font-size:14px;">
                                <i class="fas fa-wallet"></i> Como deseja pagar?
                            </label>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                <label style="cursor:pointer;">
                                    <input type="radio" name="forma_pagamento" value="presencial" checked style="display:none;" id="p-presencial">
                                    <div id="lbl-presencial" onclick="selecionarPagamento('presencial')"
                                         style="padding:16px 10px;border:2px solid var(--primary);border-radius:var(--radius-md);text-align:center;background:rgba(0,135,90,.07);transition:all .25s;cursor:pointer;">
                                        <i class="fas fa-money-bill-wave" id="icon-presencial" style="font-size:26px;color:var(--primary);display:block;margin-bottom:7px;"></i>
                                        <span id="txt-presencial" style="font-weight:700;font-size:13px;color:var(--dark);display:block;">Na Retirada</span>
                                        <span id="sub-presencial" style="font-size:10px;color:var(--gray);display:block;margin-top:2px;">Dinheiro ou cartÃ£o</span>
                                    </div>
                                </label>
                                <label style="cursor:pointer;">
                                    <input type="radio" name="forma_pagamento" value="app" style="display:none;" id="p-app">
                                    <div id="lbl-app" onclick="selecionarPagamento('app')"
                                         style="padding:16px 10px;border:2px solid var(--light-gray);border-radius:var(--radius-md);text-align:center;transition:all .25s;cursor:pointer;">
                                        <i class="fas fa-mobile-screen-button" id="icon-app" style="font-size:26px;color:var(--gray-light);display:block;margin-bottom:7px;"></i>
                                        <span style="font-weight:700;font-size:13px;color:var(--dark);display:block;">No Aplicativo</span>
                                        <span style="font-size:10px;color:var(--gray);display:block;margin-top:2px;">Pix, cartÃ£o online</span>
                                    </div>
                                </label>
                            </div>
                            <div id="app-field" style="display:none;margin-top:12px;animation:fadeUp .3s ease;">
                                <div style="background:linear-gradient(135deg,#f5f3ff,#ede9fe);border:1.5px solid #c4b5fd;border-radius:var(--radius-md);padding:14px;display:flex;align-items:center;gap:12px;">
                                    <i class="fas fa-qrcode" style="font-size:28px;color:#7c3aed;flex-shrink:0;"></i>
                                    <div>
                                        <strong style="color:#4c1d95;font-size:13px;display:block;">Pagamento pelo App</strong>
                                        <span style="font-size:12px;color:#6d28d9;">ApÃ³s confirmar, vocÃª serÃ¡ redirecionado ao Mercado Pago.</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- RESUMO DE VALORES -->
                        <div style="background:var(--bg);padding:16px;border-radius:var(--radius-md);margin-bottom:18px;">
                            <div class="checkout-line">
                                <span>Subtotal (<span id="qtd-itens"><?= $total_itens ?></span> itens)</span>
                                <span id="subtotal-geral"><?= formatar_preco($total) ?></span>
                            </div>
                            <div class="checkout-line" id="linha-frete" style="display:none;">
                                <span style="display:flex;align-items:center;gap:6px;">
                                    <i class="fas fa-motorcycle" style="color:var(--secondary);font-size:12px;"></i>
                                    Taxa de entrega
                                </span>
                                <span style="color:var(--secondary);font-weight:700;">+ <?= formatar_preco(TAXA_DELIVERY_VALOR) ?></span>
                            </div>
                            <div class="checkout-line" id="linha-frete-gratis">
                                <span>Taxa de entrega</span>
                                <span style="color:var(--success);font-weight:700;">GrÃ¡tis</span>
                            </div>
                            <div class="checkout-total">
                                <span>Total</span>
                                <span id="total-geral"><?= formatar_preco($total) ?></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-comment-medical"></i> ObservaÃ§Ãµes (opcional)</label>
                            <textarea name="observacoes" rows="2" placeholder="PrescriÃ§Ã£o, alergias, orientaÃ§Ãµes do mÃ©dico..."></textarea>
                        </div>

                        <button type="submit" name="finalizar_pedido" class="btn btn-success btn-lg"
                                style="width:100%;justify-content:center;">
                            <i class="fas fa-check"></i> Confirmar Pedido
                        </button>
                    </form>

                    <p style="text-align:center;margin-top:14px;font-size:12px;color:var(--gray);">
                        <i class="fas fa-shield-halved"></i> Compra 100% segura
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const CSRF_TOKEN = <?= json_encode(gerar_token_csrf()) ?>;

        // Taxa de delivery vinda do PHP â€” fonte Ãºnica de verdade
        const TAXA_DELIVERY = <?= TAXA_DELIVERY_VALOR ?>;

        let itens = <?= json_encode(array_map(function($i){
            return ['id' => $i['id'], 'preco' => floatval($i['preco']), 'quantidade' => intval($i['quantidade'])];
        }, $itens)) ?>;

        let isDeliveryAtivo = false;

        function selecionarRetirada(tipo) {
            document.getElementById('r-balcao').checked   = (tipo === 'balcao');
            document.getElementById('r-delivery').checked = (tipo === 'delivery');

            const isBalcao = tipo === 'balcao';
            const lblB = document.getElementById('lbl-balcao');
            const lblD = document.getElementById('lbl-delivery');
            const icB  = document.getElementById('icon-balcao');
            const icD  = document.getElementById('icon-delivery');

            lblB.style.borderColor = isBalcao ? 'var(--primary)'   : 'var(--light-gray)';
            lblB.style.background  = isBalcao ? 'rgba(0,135,90,.07)' : '';
            icB.style.color        = isBalcao ? 'var(--primary)'   : 'var(--gray-light)';

            isDeliveryAtivo = !isBalcao;
            lblD.style.borderColor = isDeliveryAtivo ? 'var(--secondary)'     : 'var(--light-gray)';
            lblD.style.background  = isDeliveryAtivo ? 'rgba(0,82,204,.07)'   : '';
            icD.style.color        = isDeliveryAtivo ? 'var(--secondary)'     : 'var(--gray-light)';

            const dfld  = document.getElementById('delivery-field');
            const frete = document.getElementById('linha-frete');
            const gratis= document.getElementById('linha-frete-gratis');
            const endEl = document.getElementById('endereco_entrega');

            dfld.style.display  = isDeliveryAtivo ? 'block' : 'none';
            endEl.required      = isDeliveryAtivo;
            if (!isDeliveryAtivo) endEl.value = '';

            frete.style.display  = isDeliveryAtivo ? 'flex' : 'none';
            gratis.style.display = isDeliveryAtivo ? 'none' : 'flex';

            document.getElementById('txt-presencial').textContent = isDeliveryAtivo ? 'Na Entrega'        : 'Na Retirada';
            document.getElementById('sub-presencial').textContent = isDeliveryAtivo ? 'Pago ao entregador' : 'Dinheiro ou cartÃ£o';

            atualizarTotalGeral();
            selecionarPagamento('presencial');
        }

        function selecionarPagamento(tipo) {
            document.getElementById('p-presencial').checked = (tipo === 'presencial');
            document.getElementById('p-app').checked        = (tipo === 'app');

            const isPres = tipo === 'presencial';
            const isApp  = !isPres;

            const lblP = document.getElementById('lbl-presencial');
            const lblA = document.getElementById('lbl-app');
            const icP  = document.getElementById('icon-presencial');
            const icA  = document.getElementById('icon-app');

            lblP.style.borderColor = isPres ? 'var(--primary)'     : 'var(--light-gray)';
            lblP.style.background  = isPres ? 'rgba(0,135,90,.07)' : '';
            icP.style.color        = isPres ? 'var(--primary)'     : 'var(--gray-light)';

            lblA.style.borderColor = isApp ? '#7c3aed'              : 'var(--light-gray)';
            lblA.style.background  = isApp ? 'rgba(124,58,237,.07)' : '';
            icA.style.color        = isApp ? '#7c3aed'              : 'var(--gray-light)';

            document.getElementById('app-field').style.display = isApp ? 'block' : 'none';
        }

        function formatarPreco(v) {
            return 'R$ ' + parseFloat(v).toFixed(2).replace('.', ',');
        }

        function mostrarToast(msg) {
            document.querySelectorAll('.update-toast').forEach(t => t.remove());
            const t = document.createElement('div');
            t.className = 'toast';
            t.innerHTML = `<i class="fas fa-check-circle"></i> ${msg}`;
            document.body.appendChild(t);
            setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 2000);
        }

        async function alterarQtd(idCarrinho, delta) {
            const item = itens.find(i => i.id == idCarrinho);
            if (!item) return;

            const novaQtd    = item.quantidade + delta;
            const cartItem   = document.getElementById('item-' + idCarrinho);

            if (novaQtd <= 0) {
                if (confirm('Remover este item?')) removerItem(idCarrinho);
                return;
            }

            cartItem.style.opacity = '.6';
            item.quantidade = novaQtd;
            document.getElementById('qty-' + idCarrinho).textContent       = novaQtd;
            document.getElementById('subtotal-' + idCarrinho).textContent  = formatarPreco(item.preco * novaQtd);
            atualizarTotalGeral();
            cartItem.style.opacity = '1';

            const fd = new FormData();
            fd.append('ajax_atualizar_quantidade', '1');
            fd.append('id_carrinho', idCarrinho);
            fd.append('quantidade',  novaQtd);
            fd.append('csrf_token', CSRF_TOKEN);
            await fetch('carrinho.php', {
                method:'POST',
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                body:fd
            });
            mostrarToast('Quantidade atualizada!');
        }

        function atualizarTotalGeral() {
            let subtotal = 0, qtd = 0;
            itens.forEach(i => { subtotal += i.preco * i.quantidade; qtd += i.quantidade; });
            const frete = isDeliveryAtivo ? TAXA_DELIVERY : 0;
            document.getElementById('subtotal-geral').textContent = formatarPreco(subtotal);
            document.getElementById('total-geral').textContent    = formatarPreco(subtotal + frete);
            document.getElementById('qtd-itens').textContent      = qtd;
            document.getElementById('header-itens').textContent   = qtd + ' item(ns) na sacola';
        }

        async function removerItem(idCarrinho) {
            const cartItem = document.getElementById('item-' + idCarrinho);
            cartItem.style.opacity    = '0';
            cartItem.style.transform  = 'translateX(-100%)';
            cartItem.style.transition = '.3s';
            itens = itens.filter(i => i.id != idCarrinho);
            atualizarTotalGeral();
            setTimeout(async () => {
                const fd = new FormData();
                fd.append('remover_item', '1');
                fd.append('id_item', idCarrinho);
                fd.append('csrf_token', CSRF_TOKEN);
                await fetch('carrinho.php', {
                    method:'POST',
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    body:fd
                });
                window.location.reload();
            }, 300);
        }

        async function limparCarrinho() {
            if (!confirm('Esvaziar a sacola?')) return;
            const fd = new FormData();
            fd.append('limpar_carrinho', '1');
            fd.append('csrf_token', CSRF_TOKEN);
            await fetch('carrinho.php', {
                method:'POST',
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                body:fd
            });
            window.location.reload();
        }
    </script>
</body>
</html>
