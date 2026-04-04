<?php
require_once FARMAVIDA_ROOT . '/app/core/bootstrap.php';
require_once FARMAVIDA_ROOT . '/app/core/config.php';
require_once FARMAVIDA_ROOT . '/app/core/helpers.php';
require_once FARMAVIDA_ROOT . '/app/integrations/mailer.php';
require_once FARMAVIDA_ROOT . '/services/pedido_service.php';
require_once FARMAVIDA_ROOT . '/services/recomendacao_service.php';

verificar_login('cliente');

$id_cliente = $_SESSION['id_usuario'];

const TAXA_DELIVERY_VALOR          = 5.00;
const TAXA_DELIVERY_EXPRESSA_VALOR = 9.00;
const RESERVA_MINUTOS_RETIRADA     = 60;
const RESERVA_MINUTOS_EXPRESSA     = 90;

// Link de recuperação de carrinho
if (isset($_GET['recuperar'])) {
    $token = sanitizar_texto($_GET['recuperar']);
    $stmt = $conn->prepare("SELECT payload, expiracao FROM carrinho_tokens WHERE token = ? AND id_cliente = ? LIMIT 1");
    $stmt->bind_param("si", $token, $id_cliente);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        redirecionar('carrinho.php', 'Link de recuperação inválido ou expirado.', 'erro');
    }
    $agora = new DateTimeImmutable('now');
    if ($agora > new DateTimeImmutable($row['expiracao'])) {
        redirecionar('carrinho.php', 'Link de recuperação expirado.', 'erro');
    }

    $payload = json_decode($row['payload'], true) ?? [];
    $conn->begin_transaction();
    try {
        $stmtDel = $conn->prepare("DELETE FROM carrinho WHERE id_cliente = ?");
        $stmtDel->bind_param("i", $id_cliente);
        $stmtDel->execute();
        $stmtDel->close();

        $stmtIns = $conn->prepare(
            "INSERT INTO carrinho (id_cliente, id_produto, tipo_produto, quantidade)
             VALUES (?, ?, ?, ?)"
        );
        foreach ($payload as $item) {
            $idProd = (int)($item['id_produto'] ?? 0);
            $qtd    = max(1, (int)($item['quantidade'] ?? 1));
            $tipo   = in_array($item['tipo_produto'] ?? 'normal', ['normal','especial'], true) ? $item['tipo_produto'] : 'normal';
            $stmtIns->bind_param("iisi", $id_cliente, $idProd, $tipo, $qtd);
            $stmtIns->execute();
        }
        $stmtIns->close();
        $conn->commit();
        redirecionar('carrinho.php', 'Sua sacola foi restaurada. Pode finalizar quando quiser!');
    } catch (Throwable $e) {
        $conn->rollback();
        redirecionar('carrinho.php', 'Não foi possível restaurar a sacola.', 'erro');
    }
}

if (isset($_POST['gerar_link_recuperacao'])) {
    verificar_csrf();
    // carrega itens atuais
    $stmt = $conn->prepare(
        "SELECT id_produto, quantidade, tipo_produto
         FROM carrinho
         WHERE id_cliente = ?"
    );
    $stmt->bind_param("i", $id_cliente);
    $stmt->execute();
    $snapshot = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($snapshot)) {
        redirecionar('carrinho.php', 'Sua sacola está vazia para gerar o link.', 'erro');
    }

    $token = bin2hex(random_bytes(16));
    $expira = (new DateTimeImmutable('now'))->modify('+7 days')->format('Y-m-d H:i:s');
    $payload = json_encode($snapshot);

    $stmt = $conn->prepare(
        "INSERT INTO carrinho_tokens (id_cliente, token, payload, expiracao)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("isss", $id_cliente, $token, $payload, $expira);
    if (!$stmt->execute()) {
        $stmt->close();
        redirecionar('carrinho.php', 'Não foi possível gerar o link agora.', 'erro');
    }
    $stmt->close();

    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    $path    = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
    $linkRecuperacao = $baseUrl . $path . '/carrinho.php?recuperar=' . urlencode($token);
    $msg = 'Use este link para retomar sua sacola: ' . $linkRecuperacao;
    $whats = 'https://wa.me/?text=' . urlencode($msg);

    $_SESSION['sucesso'] = "Link gerado! Você pode copiar ou enviar pelo WhatsApp: <a href=\"$linkRecuperacao\" style=\"color:var(--primary);\">Abrir link</a> | <a href=\"$whats\" target=\"_blank\" style=\"color:var(--secondary);\">Enviar no WhatsApp</a>";
    redirecionar('carrinho.php');
}
// Adicionar produto ao carrinho
if (isset($_POST['adicionar_carrinho'])) {
    verificar_csrf();
    $id_produto = (int)($_POST['id_produto'] ?? 0);
    $quantidade = max(1, (int)($_POST['quantidade'] ?? 1));
    $tipo       = in_array($_POST['tipo_produto'] ?? '', ['normal', 'especial'], true) ? $_POST['tipo_produto'] : 'normal';

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
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            echo json_encode(['sucesso' => true]);
            exit;
        }
        redirecionar($_POST['redirect'] ?? 'carrinho.php', 'Produto adicionado à sacola!');
    } else {
        redirecionar('index.php', 'Produto não disponível!', 'erro');
    }
}

// Atualização de quantidade via AJAX
if (isset($_POST['ajax_atualizar_quantidade'])) {
    verificar_csrf();
    header('Content-Type: application/json');
    $id_carrinho = (int)($_POST['id_carrinho'] ?? 0);
    $quantidade  = (int)($_POST['quantidade'] ?? 0);

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

    $total = 0;
    $total_itens = 0;
    foreach ($itens_all as $i) {
        $total       += $i['preco'] * $i['quantidade'];
        $total_itens += $i['quantidade'];
    }
    echo json_encode([
        'sucesso'                 => true,
        'removido'                => $quantidade <= 0,
        'subtotal_item'           => $item ? $item['preco'] * $quantidade : 0,
        'subtotal_item_formatado' => $item ? formatar_preco($item['preco'] * $quantidade) : 'R$ 0,00',
        'total'                   => $total,
        'total_formatado'         => formatar_preco($total),
        'total_itens'             => $total_itens,
    ]);
    exit;
}

// Remover item
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

// Limpar carrinho
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

// Finalizar pedido
if (isset($_POST['finalizar_pedido'])) {
    verificar_csrf();

    $observacoes     = sanitizar_texto($_POST["observacoes"] ?? "");
    $tipo_retirada   = in_array($_POST["tipo_retirada"] ?? "", ["balcao", "delivery", "retirada_1h", "delivery_expressa"], true)
        ? $_POST["tipo_retirada"]
        : "balcao";
    $forma_pagamento = in_array($_POST["forma_pagamento"] ?? "", ["presencial", "app"], true)
        ? $_POST["forma_pagamento"]
        : "presencial";

    $label_pagamento = $forma_pagamento === "app"
        ? "Pagamento pelo App"
        : (($tipo_retirada === "delivery" || $tipo_retirada === "delivery_expressa") ? "Pagar na Entrega" : "Pagar na Retirada");

    $taxa_delivery = $tipo_retirada === "delivery_expressa" ? TAXA_DELIVERY_EXPRESSA_VALOR : TAXA_DELIVERY_VALOR;
    $janela_inicio = null;
    $janela_fim    = null;

    if ($tipo_retirada === "delivery" || $tipo_retirada === "delivery_expressa") {
        $endereco_entrega = sanitizar_texto($_POST["endereco_entrega"] ?? "");
        if (empty($endereco_entrega)) {
            redirecionar("carrinho.php", "Por favor, informe o endereço de entrega!", "erro");
        }
        $taxa_fmt    = formatar_preco($taxa_delivery);
        $prefixo     = "DELIVERY - Endereço: $endereco_entrega | Taxa: $taxa_fmt | $label_pagamento";
        $observacoes = $prefixo . ($observacoes ? " | Obs: $observacoes" : "");
        if ($tipo_retirada === "delivery_expressa") {
            $agora = new DateTimeImmutable("now");
            $janela_inicio = $agora->modify("+60 minutes")->format("Y-m-d H:i:s");
            $janela_fim    = $agora->modify("+120 minutes")->format("Y-m-d H:i:s");
        }
    } else {
        $prefixo     = $tipo_retirada === "retirada_1h"
            ? "RETIRADA EM ATÉ 1H | $label_pagamento"
            : "RETIRADA NO LOCAL | $label_pagamento";
        $observacoes = $prefixo . ($observacoes ? " | Obs: $observacoes" : "");
        if ($tipo_retirada === "retirada_1h") {
            $agora = new DateTimeImmutable("now");
            $janela_inicio = $agora->format("Y-m-d H:i:s");
            $janela_fim    = $agora->modify("+" . RESERVA_MINUTOS_RETIRADA . " minutes")->format("Y-m-d H:i:s");
        }
    }

    // Upload de receita, se necessário
    $caminhoReceita = null;
    if ($receitaObrigatoria) {
        if (!isset($_FILES['receita_arquivo']) || $_FILES['receita_arquivo']['error'] !== UPLOAD_ERR_OK) {
            redirecionar('carrinho.php', 'É necessário anexar a receita para estes itens.', 'erro');
        }
        $file = $_FILES['receita_arquivo'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf','jpg','jpeg','png'], true)) {
            redirecionar('carrinho.php', 'Formato de receita inválido. Use PDF ou imagem.', 'erro');
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            redirecionar('carrinho.php', 'Arquivo maior que 5MB.', 'erro');
        }
        $dir = FARMAVIDA_ROOT . '/uploads/receitas/' . $id_cliente;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $nomeArq = 'receita_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destino = $dir . '/' . $nomeArq;
        if (!move_uploaded_file($file['tmp_name'], $destino)) {
            redirecionar('carrinho.php', 'Não foi possível salvar a receita.', 'erro');
        }
        $caminhoReceita = '/uploads/receitas/' . $id_cliente . '/' . $nomeArq;
    }

    try {
        $pedidoCriado = pedido_criar_do_carrinho(
            $conn,
            $id_cliente,
            $observacoes,
            $tipo_retirada,
            $forma_pagamento,
            $taxa_delivery,
            $janela_inicio,
            $janela_fim,
            $caminhoReceita
        );
    } catch (Throwable $e) {
        error_log('Erro ao finalizar pedido: ' . $e->getMessage());
        redirecionar('carrinho.php', 'Não foi possível finalizar o pedido agora. Tente novamente.', 'erro');
    }

    $id_pedido = (int)$pedidoCriado['id_pedido'];
    $itens = $pedidoCriado['itens'];
    $total = (float)$pedidoCriado['total'];

    if ($forma_pagamento === 'app') {
        header("Location: criar_preferencia.php?pedido=$id_pedido");
        exit;
    }

    // Confirmação por e-mail (pagamento presencial)
    $stmt_cli = $conn->prepare("SELECT nome, email FROM usuarios WHERE id = ?");
    $stmt_cli->bind_param("i", $id_cliente);
    $stmt_cli->execute();
    $cli = $stmt_cli->get_result()->fetch_assoc();
    $stmt_cli->close();
    if ($cli) {
        $itens_email = array_map(fn($i) => ['nome' => $i['nome'], 'preco' => $i['preco'], 'quantidade' => $i['quantidade']], $itens);
        $corpo = email_confirmacao_pedido($id_pedido, $cli['nome'], $itens_email, $total, $tipo_retirada);
        enviar_email($cli['email'], "Pedido #$id_pedido confirmado — FarmaVida", $corpo);
    }

    if ($tipo_retirada === 'delivery' || $tipo_retirada === 'delivery_expressa') {
        $msg_pedido = "Pedido #$id_pedido confirmado! Em breve seu delivery será enviado.";
    } elseif ($tipo_retirada === 'retirada_1h') {
        $msg_pedido = "Pedido #$id_pedido confirmado! Retire em até 1 hora.";
    } else {
        $msg_pedido = "Pedido #$id_pedido confirmado! Retire no balcão quando estiver pronto.";
    }
    redirecionar('painel_cliente.php', $msg_pedido);
}

$stmt = $conn->prepare(
    "SELECT c.*, p.nome, p.descricao, p.preco, p.imagem, p.categoria, p.exige_receita, p.classe_medicamento
     FROM carrinho c
     JOIN produtos p ON c.id_produto = p.id
     WHERE c.id_cliente = ? AND p.disponivel = 1"
);
$stmt->bind_param("i", $id_cliente);
$stmt->execute();
$itens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total = 0;
$total_itens = 0;
foreach ($itens as $item) {
    $total       += $item['preco'] * $item['quantidade'];
    $total_itens += $item['quantidade'];
}
$receitaObrigatoria = false;
$classesBloqueadas = ['antibiotico','controlado','psicotropico'];
foreach ($itens as $item) {
    if ((int)($item['exige_receita'] ?? 0) === 1 || in_array($item['classe_medicamento'] ?? 'livre', $classesBloqueadas, true)) {
        $receitaObrigatoria = true;
        break;
    }
}

// Recomendações e kits
$idsCarrinho = array_map(fn($i) => (int)$i['id_produto'], $itens);
$categoriasCarrinho = array_values(array_unique(array_filter(array_map(fn($i) => $i['categoria'] ?? '', $itens))));
$recs = recomendar_por_historico($conn, $id_cliente, $idsCarrinho, 4);
if (count($recs) < 4) {
    $faltam = 4 - count($recs);
    $mais = recomendar_por_categoria($conn, $categoriasCarrinho, $idsCarrinho, $faltam);
    $recs = array_merge($recs, $mais);
}
$recs_genericos = recomendar_genericos($conn, $idsCarrinho, 3);
$kits = kits_pre_montados();

// HTML e JS inseridos abaixo
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

    <script>
        const CSRF_TOKEN = <?= json_encode(gerar_token_csrf()) ?>;
        const TAXA_DELIVERY = <?= TAXA_DELIVERY_VALOR ?>;
        const TAXA_DELIVERY_EXPRESSA = <?= TAXA_DELIVERY_EXPRESSA_VALOR ?>;

        let itens = <?= json_encode(array_map(function ($i) {
            return ['id' => $i['id'], 'preco' => floatval($i['preco']), 'quantidade' => intval($i['quantidade'])];
        }, $itens)) ?>;

        let tipoRetirada = 'balcao';
        let taxaEntregaSelecionada = 0;

        function marcarCard(tipo, ativo, cor, bg) {
            const lbl = document.getElementById('lbl-' + tipo);
            const ic  = document.getElementById('icon-' + tipo);
            if (!lbl || !ic) return;
            lbl.style.borderColor = ativo ? cor : 'var(--light-gray)';
            lbl.style.background  = ativo ? bg  : '';
            lbl.style.boxShadow   = ativo ? '0 6px 20px rgba(0,0,0,0.08)' : 'none';
            ic.style.color        = ativo ? cor : 'var(--gray-light)';
        }

        function selecionarRetirada(tipo) {
            tipoRetirada = tipo;
            ['balcao','retirada_1h','delivery','delivery_expressa'].forEach(r => {
                const radio = document.getElementById('r-' + r);
                if (radio) radio.checked = (r === tipo);
            });

            marcarCard('balcao', tipo === 'balcao', 'var(--primary)', 'rgba(0,135,90,.08)');
            marcarCard('retirada_1h', tipo === 'retirada_1h', '#0ea5e9', 'rgba(14,165,233,.1)');
            marcarCard('delivery', tipo === 'delivery', 'var(--secondary)', 'rgba(0,82,204,.08)');
            marcarCard('delivery_expressa', tipo === 'delivery_expressa', '#f97316', 'rgba(249,115,22,.12)');

            const isDelivery = (tipo === 'delivery' || tipo === 'delivery_expressa');
            taxaEntregaSelecionada = isDelivery
                ? (tipo === 'delivery_expressa' ? TAXA_DELIVERY_EXPRESSA : TAXA_DELIVERY)
                : 0;

            const dfld   = document.getElementById('delivery-field');
            const frete  = document.getElementById('linha-frete');
            const gratis = document.getElementById('linha-frete-gratis');
            const endEl  = document.getElementById('endereco_entrega');
            const valorFrete = document.getElementById('valor-frete');
            const taxaInfo  = document.getElementById('taxa-entrega-text');

            dfld.style.display   = isDelivery ? 'block' : 'none';
            endEl.required       = isDelivery;
            if (!isDelivery) endEl.value = '';

            frete.style.display  = isDelivery ? 'flex' : 'none';
            gratis.style.display = isDelivery ? 'none' : 'flex';
            const taxaFmt = formatarPreco(taxaEntregaSelecionada);
            valorFrete.textContent = '+ ' + taxaFmt;
            taxaInfo.textContent   = taxaFmt;

            document.getElementById('txt-presencial').textContent = isDelivery ? 'Na entrega' : 'Na retirada';
            document.getElementById('sub-presencial').textContent = isDelivery ? 'Pagar ao entregador' : 'Dinheiro ou cartão';

            atualizarTotalGeral();
            selecionarPagamento(document.getElementById('p-app').checked ? 'app' : 'presencial');
        }

        function selecionarPagamento(tipo) {
            document.getElementById('p-presencial').checked = (tipo === 'presencial');
            document.getElementById('p-app').checked        = (tipo === 'app');

            const isPres = tipo === 'presencial';
            const lblP = document.getElementById('lbl-presencial');
            const lblA = document.getElementById('lbl-app');
            const icP  = document.getElementById('icon-presencial');
            const icA  = document.getElementById('icon-app');

            lblP.style.borderColor = isPres ? 'var(--primary)'     : 'var(--light-gray)';
            lblP.style.background  = isPres ? 'rgba(0,135,90,.07)' : '';
            icP.style.color        = isPres ? 'var(--primary)'     : 'var(--gray-light)';

            lblA.style.borderColor = !isPres ? '#7c3aed'              : 'var(--light-gray)';
            lblA.style.background  = !isPres ? 'rgba(124,58,237,.07)' : '';
            icA.style.color        = !isPres ? '#7c3aed'              : 'var(--gray-light)';

            document.getElementById('app-field').style.display = !isPres ? 'block' : 'none';
        }

        function formatarPreco(v) {
            return 'R$ ' + parseFloat(v).toFixed(2).replace('.', ',');
        }

        function mostrarToast(msg) {
            document.querySelectorAll('.update-toast').forEach(t => t.remove());
            const t = document.createElement('div');
            t.className = 'toast';
            t.innerHTML = `<i class=\"fas fa-check-circle\"></i> ${msg}`;
            document.body.appendChild(t);
            setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 2000);
        }

        async function alterarQtd(idCarrinho, delta) {
            const item = itens.find(i => i.id == idCarrinho);
            if (!item) return;

            const novaQtd  = item.quantidade + delta;
            const cartItem = document.getElementById('item-' + idCarrinho);

            if (novaQtd <= 0) {
                if (confirm('Remover este item?')) removerItem(idCarrinho);
                return;
            }

            cartItem.style.opacity = '.6';
            item.quantidade = novaQtd;
            document.getElementById('qty-' + idCarrinho).textContent      = novaQtd;
            document.getElementById('subtotal-' + idCarrinho).textContent = formatarPreco(item.preco * novaQtd);
            atualizarTotalGeral();
            cartItem.style.opacity = '1';

            const fd = new FormData();
            fd.append('ajax_atualizar_quantidade', '1');
            fd.append('id_carrinho', idCarrinho);
            fd.append('quantidade',  novaQtd);
            fd.append('csrf_token', CSRF_TOKEN);
            await fetch('carrinho.php', {
                method: 'POST',
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                body: fd
            });
            mostrarToast('Quantidade atualizada!');
        }

        function atualizarTotalGeral() {
            let subtotal = 0, qtd = 0;
            itens.forEach(i => { subtotal += i.preco * i.quantidade; qtd += i.quantidade; });
            const frete = taxaEntregaSelecionada;
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
                    method: 'POST',
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    body: fd
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
                method: 'POST',
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                body: fd
            });
            window.location.reload();
        }

        // Estado inicial
        if (document.getElementById('r-balcao')) {
            selecionarRetirada('balcao');
        }
    </script>
</body>
</html>

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
            <form method="POST" style="margin-left:12px;">
                <?= campo_csrf() ?>
                <input type="hidden" name="gerar_link_recuperacao" value="1">
                <button type="submit" class="btn btn-secondary" style="gap:6px;">
                    <i class="fas fa-share-square"></i> Salvar e enviar depois
                </button>
            </form>
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

                <!-- Itens do carrinho -->
                <div id="cart-items-container">
                    <?php foreach ($itens as $item): ?>
                        <div class="cart-item" id="item-<?= $item['id'] ?>" data-preco="<?= $item['preco'] ?>">
                            <img src="<?= htmlspecialchars(url_imagem_produto($item['imagem'] ?? null, $item['nome'] ?? 'Produto', $item['categoria'] ?? 'Sem categoria')) ?>"
                                 alt="<?= htmlspecialchars($item['nome']) ?>">

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

                <!-- Checkout -->
                <div class="checkout-card">
                    <h3><i class="fas fa-receipt" style="color:var(--primary);"></i> Finalizar Compra</h3>

                    <form method="POST" id="checkout-form" enctype="multipart/form-data">
                        <?= campo_csrf() ?>

                        <!-- Tipo de entrega -->
                        <div style="margin-bottom:20px;">
                            <label style="display:block;margin-bottom:12px;font-weight:700;color:var(--dark);font-size:14px;">
                                <i class="fas fa-truck-medical"></i> Como deseja receber?
                            </label>
                            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
                                <label style="cursor:pointer;">
                                    <input type="radio" name="tipo_retirada" value="balcao" checked style="display:none;" id="r-balcao">
                                    <div id="lbl-balcao" onclick="selecionarRetirada('balcao')"
                                         style="padding:18px 12px;border:2px solid var(--primary);border-radius:var(--radius-md);text-align:center;background:rgba(0,135,90,.08);transition:all .25s;cursor:pointer;">
                                        <i class="fas fa-store-alt" id="icon-balcao" style="font-size:28px;color:var(--primary);display:block;margin-bottom:8px;"></i>
                                        <span style="font-weight:700;font-size:13px;color:var(--dark);display:block;">Retirar no balcão</span>
                                        <span style="font-size:11px;color:var(--success);display:block;margin-top:3px;font-weight:700;">Sem taxa</span>
                                    </div>
                                </label>
                                <label style="cursor:pointer;">
                                    <input type="radio" name="tipo_retirada" value="retirada_1h" style="display:none;" id="r-retirada_1h">
                                    <div id="lbl-retirada_1h" onclick="selecionarRetirada('retirada_1h')"
                                         style="padding:18px 12px;border:2px solid var(--light-gray);border-radius:var(--radius-md);text-align:center;transition:all .25s;cursor:pointer;">
                                        <i class="fas fa-clock" id="icon-retirada_1h" style="font-size:28px;color:var(--gray-light);display:block;margin-bottom:8px;"></i>
                                        <span style="font-weight:700;font-size:13px;color:var(--dark);display:block;">Retirar em até 1h</span>
                                        <span style="font-size:11px;color:#0ea5e9;display:block;margin-top:3px;font-weight:700;">Reserva garantida</span>
                                    </div>
                                </label>
                                <label style="cursor:pointer;">
                                    <input type="radio" name="tipo_retirada" value="delivery" style="display:none;" id="r-delivery">
                                    <div id="lbl-delivery" onclick="selecionarRetirada('delivery')"
                                         style="padding:18px 12px;border:2px solid var(--light-gray);border-radius:var(--radius-md);text-align:center;transition:all .25s;cursor:pointer;">
                                        <i class="fas fa-motorcycle" id="icon-delivery" style="font-size:28px;color:var(--gray-light);display:block;margin-bottom:8px;"></i>
                                        <span style="font-weight:700;font-size:13px;color:var(--dark);display:block;">Delivery padrão</span>
                                        <span style="font-size:11px;color:var(--warning);display:block;margin-top:3px;font-weight:700;">+ <?= formatar_preco(TAXA_DELIVERY_VALOR) ?></span>
                                    </div>
                                </label>
                                <label style="cursor:pointer;">
                                    <input type="radio" name="tipo_retirada" value="delivery_expressa" style="display:none;" id="r-delivery_expressa">
                                    <div id="lbl-delivery_expressa" onclick="selecionarRetirada('delivery_expressa')"
                                         style="padding:18px 12px;border:2px solid var(--light-gray);border-radius:var(--radius-md);text-align:center;transition:all .25s;cursor:pointer;">
                                        <i class="fas fa-bolt" id="icon-delivery_expressa" style="font-size:28px;color:var(--gray-light);display:block;margin-bottom:8px;"></i>
                                        <span style="font-weight:700;font-size:13px;color:var(--dark);display:block;">Delivery express</span>
                                        <span style="font-size:11px;color:#f97316;display:block;margin-top:3px;font-weight:700;">+ <?= formatar_preco(TAXA_DELIVERY_EXPRESSA_VALOR) ?></span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Endereço -->
                        <div id="delivery-field" style="display:none;margin-bottom:18px;animation:fadeUp .3s ease;">
                            <div style="background:linear-gradient(135deg,#e6f0ff,#f0f7ff);border:1.5px solid #bfdbfe;border-radius:var(--radius-md);padding:16px;">
                                <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                                    <i class="fas fa-map-marker-alt" style="color:var(--secondary);font-size:18px;"></i>
                                    <span style="font-weight:700;color:var(--dark);font-size:14px;">Endereço de entrega *</span>
                                </div>
                                <input type="text" name="endereco_entrega" id="endereco_entrega"
                                       placeholder="Rua, número, bairro, cidade, CEP"
                                       style="background:white;border-color:#bfdbfe;">
                                <p style="font-size:11px;color:var(--gray);margin-top:8px;display:flex;align-items:center;gap:5px;">
                                    <i class="fas fa-info-circle" style="color:var(--info);"></i>
                                    Taxa de entrega: <strong><span id="taxa-entrega-text"><?= formatar_preco(TAXA_DELIVERY_VALOR) ?></span></strong> (incluída no total)
                                </p>
                            </div>
                        </div>

                        <!-- Pagamento -->
                        <div style="margin-bottom:20px;">
                            <label style="display:block;margin-bottom:12px;font-weight:700;color:var(--dark);font-size:14px;">
                                <i class="fas fa-wallet"></i> Como deseja pagar?
                            </label>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                <label style="cursor:pointer;">
                                    <input type="radio" name="forma_pagamento" value="presencial" style="display:none;" id="p-presencial">
                                    <div id="lbl-presencial" onclick="selecionarPagamento('presencial')"
                                         style="padding:16px 10px;border:2px solid var(--primary);border-radius:var(--radius-md);text-align:center;background:rgba(0,135,90,.07);transition:all .25s;cursor:pointer;">
                                        <i class="fas fa-money-bill-wave" id="icon-presencial" style="font-size:26px;color:var(--primary);display:block;margin-bottom:7px;"></i>
                                        <span id="txt-presencial" style="font-weight:700;font-size:13px;color:var(--dark);display:block;">Na retirada</span>
                                        <span id="sub-presencial" style="font-size:10px;color:var(--gray);display:block;margin-top:2px;">Dinheiro ou cartão</span>
                                    </div>
                                </label>
                                <label style="cursor:pointer;">
                                    <input type="radio" name="forma_pagamento" value="app" checked style="display:none;" id="p-app">
                                    <div id="lbl-app" onclick="selecionarPagamento('app')"
                                         style="padding:16px 10px;border:2px solid var(--light-gray);border-radius:var(--radius-md);text-align:center;transition:all .25s;cursor:pointer;">
                                        <i class="fas fa-mobile-screen-button" id="icon-app" style="font-size:26px;color:var(--gray-light);display:block;margin-bottom:7px;"></i>
                                        <span style="font-weight:700;font-size:13px;color:var(--dark);display:block;">No aplicativo</span>
                                        <span style="font-size:10px;color:var(--gray);display:block;margin-top:2px;">Pix ou cartão online</span>
                                    </div>
                                </label>
                            </div>
                            <div id="app-field" style="display:none;margin-top:12px;animation:fadeUp .3s ease;">
                                <div style="background:linear-gradient(135deg,#f5f3ff,#ede9fe);border:1.5px solid #c4b5fd;border-radius:var(--radius-md);padding:14px;display:flex;align-items:center;gap:12px;">
                                    <i class="fas fa-qrcode" style="font-size:28px;color:#7c3aed;flex-shrink:0;"></i>
                                    <div>
                                        <strong style="color:#4c1d95;font-size:13px;display:block;">Pagamento pelo app</strong>
                                        <span style="font-size:12px;color:#6d28d9;">Após confirmar, você será redirecionado ao Mercado Pago.</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Totais -->
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
                                <span id="valor-frete" style="color:var(--secondary);font-weight:700;">+ <?= formatar_preco(TAXA_DELIVERY_VALOR) ?></span>
                            </div>
                            <div class="checkout-line" id="linha-frete-gratis">
                                <span>Taxa de entrega</span>
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

                        <?php if ($receitaObrigatoria): ?>
                            <div class="form-group" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);padding:12px;border-radius:var(--radius-md);">
                                <label style="display:flex;align-items:center;gap:8px;font-weight:700;"><i class="fas fa-file-medical"></i> Receita obrigatória</label>
                                <input type="file" name="receita_arquivo" accept=".pdf,.jpg,.jpeg,.png" required>
                                <p style="font-size:12px;color:var(--gray);margin-top:6px;">Formatos: PDF, JPG, PNG (máx. 5MB). Obrigatório para itens controlados/antibióticos.</p>
                            </div>
                        <?php endif; ?>

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

    <?php if (!empty($itens)): ?>
        <div class="container" style="margin-top:12px;">
            <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:14px;">
                <a href="faq_clinico.php" class="btn btn-secondary" style="gap:8px;"><i class="fas fa-book-medical"></i> FAQ clínico</a>
                <a href="https://wa.me/?text=Preciso%20de%20orientacao%20farmaceutica%20sobre%20meu%20pedido" target="_blank" class="btn btn-primary" style="gap:8px;background:linear-gradient(135deg,#25D366,#128C7E);border:none;"><i class="fas fa-headset"></i> Fale com o farmacêutico</a>
                <div style="display:flex;align-items:center;gap:8px;padding:10px 14px;border:1px solid rgba(255,255,255,.08);border-radius:var(--radius-md);background:rgba(255,255,255,.03);color:#9fb4d6;">
                    <i class="fas fa-shield-halved" style="color:var(--primary);"></i> Selo de segurança: TLS 1.2 + LGPD
                </div>
            </div>

            <?php if (!empty($recs)): ?>
                <div style="margin-bottom:14px;">
                    <h4 style="font-family:'Bricolage Grotesque',sans-serif;font-size:16px;margin-bottom:10px;color:var(--white);">Sugestões para você</h4>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;">
                        <?php foreach ($recs as $rec): ?>
                            <a href="index.php?buscar=<?= urlencode($rec['nome']) ?>" class="card" style="padding:12px;border-radius:var(--radius-lg);text-decoration:none;color:var(--text);background:var(--surface2);border:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
                                <div>
                                    <div style="font-weight:800;font-size:13px;line-height:1.3;"><?= htmlspecialchars($rec['nome']) ?></div>
                                    <div style="color:var(--gray);font-size:11px;"><?= htmlspecialchars($rec['categoria'] ?? '') ?></div>
                                </div>
                                <div style="text-align:right;font-weight:800;color:var(--primary);"><?= formatar_preco($rec['preco']) ?></div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($recs_genericos)): ?>
                <div style="margin-bottom:14px;">
                    <h4 style="font-family:'Bricolage Grotesque',sans-serif;font-size:16px;margin-bottom:10px;color:var(--white);">Genéricos relacionados</h4>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;">
                        <?php foreach ($recs_genericos as $gen): ?>
                            <a href="index.php?buscar=<?= urlencode($gen['nome']) ?>" class="card" style="padding:12px;border-radius:var(--radius-lg);text-decoration:none;color:var(--text);background:rgba(0,229,160,.08);border:1px solid rgba(0,229,160,.25);display:flex;justify-content:space-between;align-items:center;">
                                <div>
                                    <div style="font-weight:800;font-size:13px;line-height:1.3;"><?= htmlspecialchars($gen['nome']) ?></div>
                                    <div style="color:#5bd3a4;font-size:11px;">Alternativa econômica</div>
                                </div>
                                <div style="text-align:right;font-weight:800;color:var(--primary);"><?= formatar_preco($gen['preco']) ?></div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div style="margin-bottom:20px;">
                <h4 style="font-family:'Bricolage Grotesque',sans-serif;font-size:16px;margin-bottom:10px;color:var(--white);">Kits prontos</h4>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;">
                    <?php foreach ($kits as $kit): ?>
                        <div class="card" style="padding:14px;border-radius:var(--radius-lg);background:var(--surface);border:1px solid var(--border);">
                            <div style="font-weight:800;font-size:14px;margin-bottom:6px;"><?= htmlspecialchars($kit['titulo']) ?></div>
                            <div style="color:var(--gray);font-size:12px;line-height:1.4;margin-bottom:8px;"><?= htmlspecialchars($kit['descricao']) ?></div>
                            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;">
                                <?php foreach ($kit['tags'] as $tag): ?>
                                    <span style="font-size:10px;padding:4px 8px;border-radius:var(--radius-full);background:var(--surface2);border:1px solid var(--border);color:var(--gray);text-transform:uppercase;letter-spacing:.3px;"><?= htmlspecialchars($tag) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <a href="index.php?buscar=<?= urlencode($kit['tags'][0]) ?>" class="btn btn-primary" style="width:100%;justify-content:center;">Ver itens</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
