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
    $observacoes  = sanitizar_texto($_POST['observacoes'] ?? '');
    $tipo_retirada = $_POST['tipo_retirada'] ?? 'balcao';
    $numero_mesa  = '';

    if ($tipo_retirada == 'mesa') {
        $numero_mesa = sanitizar_texto($_POST['numero_mesa'] ?? '');
        if (empty($numero_mesa)) {
            redirecionar('carrinho.php', 'Por favor, informe o número do guichê!', 'erro');
        }
    }

    $itens = $conn->query("SELECT c.*, p.nome, p.preco FROM carrinho c JOIN produtos p ON c.id_produto = p.id WHERE c.id_cliente = $id_cliente AND p.disponivel = 1")->fetch_all(MYSQLI_ASSOC);

    if (empty($itens)) { redirecionar('carrinho.php', 'Sacola vazia!', 'erro'); }

    $total = 0;
    foreach ($itens as $item) { $total += $item['preco'] * $item['quantidade']; }

    $stmt = $conn->prepare("INSERT INTO pedidos (id_cliente, total, observacoes, numero_mesa, tipo_retirada) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("idsss", $id_cliente, $total, $observacoes, $numero_mesa, $tipo_retirada);
    $stmt->execute();
    $id_pedido = $conn->insert_id;

    foreach ($itens as $item) {
        $stmt = $conn->prepare("INSERT INTO pedido_itens (id_pedido, id_produto, quantidade, preco_unitario) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiid", $id_pedido, $item['id_produto'], $item['quantidade'], $item['preco']);
        $stmt->execute();
    }

    $conn->query("DELETE FROM carrinho WHERE id_cliente=$id_cliente");
    redirecionar('painel_cliente.php', "Pedido #$id_pedido realizado! Aguarde a confirmação do farmacêutico.");
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
        <div class="card" style="background:var(--gradient-main);color:white;margin-bottom:28px;">
            <h1 style="color:white;margin-bottom:6px;"><i class="fas fa-shopping-bag"></i> Minha Sacola</h1>
            <p style="opacity:.9;" id="header-itens"><?= $total_itens ?> item(ns) na sacola</p>
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
                        <div style="margin-bottom:18px;">
                            <label style="display:block;margin-bottom:10px;font-weight:700;color:var(--dark);font-size:14px;">
                                <i class="fas fa-hand-holding-medical"></i> Como deseja retirar?
                            </label>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                                <label style="cursor:pointer;">
                                    <input type="radio" name="tipo_retirada" value="balcao" checked style="display:none;" id="r-balcao">
                                    <div class="retirada-opt" id="lbl-balcao" onclick="selecionarRetirada('balcao')" style="padding:16px;border:2px solid var(--primary);border-radius:var(--radius-md);text-align:center;background:rgba(0,135,90,.07);">
                                        <i class="fas fa-store" style="font-size:22px;color:var(--primary);display:block;margin-bottom:6px;"></i>
                                        <span style="font-weight:700;font-size:13px;color:var(--dark);">No Balcão</span>
                                    </div>
                                </label>
                                <label style="cursor:pointer;">
                                    <input type="radio" name="tipo_retirada" value="mesa" style="display:none;" id="r-mesa">
                                    <div class="retirada-opt" id="lbl-mesa" onclick="selecionarRetirada('mesa')" style="padding:16px;border:2px solid var(--light-gray);border-radius:var(--radius-md);text-align:center;">
                                        <i class="fas fa-chair" style="font-size:22px;color:var(--gray-light);display:block;margin-bottom:6px;"></i>
                                        <span style="font-weight:700;font-size:13px;color:var(--dark);">Na Cadeira</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div id="mesa-field" style="display:none;margin-bottom:18px;">
                            <div class="form-group">
                                <label><i class="fas fa-hashtag"></i> Número da Cadeira *</label>
                                <input type="text" name="numero_mesa" id="numero_mesa" placeholder="Ex: 3">
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
                        <i class="fas fa-credit-card"></i> Pagamento no balcão
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        let itens = <?= json_encode(array_map(function($i){ return ['id'=>$i['id'],'preco'=>floatval($i['preco']),'quantidade'=>intval($i['quantidade'])]; }, $itens)) ?>;

        function selecionarRetirada(tipo) {
            document.getElementById('r-balcao').checked = (tipo === 'balcao');
            document.getElementById('r-mesa').checked   = (tipo === 'mesa');
            const lblBalcao = document.getElementById('lbl-balcao');
            const lblMesa   = document.getElementById('lbl-mesa');
            lblBalcao.style.borderColor = tipo === 'balcao' ? 'var(--primary)' : 'var(--light-gray)';
            lblBalcao.style.background  = tipo === 'balcao' ? 'rgba(0,135,90,.07)' : '';
            lblBalcao.querySelector('i').style.color = tipo === 'balcao' ? 'var(--primary)' : 'var(--gray-light)';
            lblMesa.style.borderColor = tipo === 'mesa' ? 'var(--primary)' : 'var(--light-gray)';
            lblMesa.style.background  = tipo === 'mesa' ? 'rgba(0,135,90,.07)' : '';
            lblMesa.querySelector('i').style.color = tipo === 'mesa' ? 'var(--primary)' : 'var(--gray-light)';
            const mesaField = document.getElementById('mesa-field');
            const numMesa   = document.getElementById('numero_mesa');
            mesaField.style.display = tipo === 'mesa' ? 'block' : 'none';
            numMesa.required = (tipo === 'mesa');
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
