<?php
session_start();
include "config.php";
include "helpers.php";

verificar_login('dono');

if (isset($_POST['atualizar_status'])) {
    $id_pedido = (int) $_POST['id_pedido'];
    $status    = $_POST['status'];
    $conn->query("UPDATE pedidos SET status='$status' WHERE id=$id_pedido");
    redirecionar('painel_dono.php', 'Status atualizado!');
}

$stats = $conn->query("SELECT COUNT(DISTINCT id) as total_pedidos, SUM(total) as faturamento_total, SUM(CASE WHEN status='pendente' THEN 1 ELSE 0 END) as pedidos_pendentes FROM pedidos")->fetch_assoc();
$total_produtos = $conn->query("SELECT COUNT(*) as t FROM produtos WHERE disponivel=1")->fetch_assoc()['t'];
$total_clientes = $conn->query("SELECT COUNT(*) as t FROM usuarios WHERE tipo='cliente'")->fetch_assoc()['t'];

$pedidos = $conn->query("
    SELECT p.*, u.nome as cliente_nome, u.telefone, u.endereco
    FROM pedidos p
    JOIN usuarios u ON p.id_cliente = u.id
    ORDER BY p.criado_em DESC
    LIMIT 20
")->fetch_all(MYSQLI_ASSOC);

foreach ($pedidos as &$pedido) {
    $itens = $conn->query("SELECT pi.*, pr.nome as produto_nome FROM pedido_itens pi JOIN produtos pr ON pi.id_produto = pr.id WHERE pi.id_pedido = {$pedido['id']}")->fetch_all(MYSQLI_ASSOC);
    $pedido['itens'] = $itens; $pedido['total_itens'] = count($itens);
}
unset($pedido);

$status_cores = ['pendente'=>'#f59e0b','preparando'=>'#3b82f6','pronto'=>'#10b981','entregue'=>'#6b7280','cancelado'=>'#ef4444'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Admin - FarmaVida</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <div class="header-container">
            <div class="logo" style="cursor:default;">
                <div class="logo-icon"><i class="fas fa-prescription-bottle-medical"></i></div>
                Farma<span>Vida</span>
                <span style="font-size:13px;color:var(--gray);font-weight:500;margin-left:4px;">Admin</span>
                <button onclick="atualizarPedidos(); atualizarStats();" class="btn btn-secondary" style="margin-left:12px;padding:7px 14px;font-size:12px;">
                    <i class="fas fa-sync-alt"></i> Atualizar
                </button>
                <span class="auto-update-badge" style="margin-left:8px;"><i class="fas fa-clock"></i> 30s</span>
            </div>
            <div class="nav-buttons">
                <a href="gerenciar_produtos.php" class="btn btn-success"><i class="fas fa-boxes"></i> Produtos</a>
                <a href="limpar_pedidos_antigos.php?executar=1" class="btn btn-warning" onclick="return confirm('Limpar pedidos finalizados?')"><i class="fas fa-trash-alt"></i> Limpar</a>
                <a href="index.php" class="btn btn-secondary"><i class="fas fa-store"></i> Loja</a>
                <a href="logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <h1><i class="fas fa-tachometer-alt" style="color:var(--primary);"></i> Painel Administrativo</h1>
            <p style="color:var(--gray);">Bem-vindo, <?= htmlspecialchars($_SESSION['usuario']) ?>!</p>
        </div>

        <?php if (isset($_SESSION['sucesso'])): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $_SESSION['sucesso'] ?></div>
            <?php unset($_SESSION['sucesso']); ?>
        <?php endif; ?>

        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-receipt"></i>
                <div class="stat-numero" id="stat-total-pedidos"><?= $stats['total_pedidos'] ?></div>
                <div class="stat-label">Total de Pedidos</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-sack-dollar"></i>
                <div class="stat-numero" id="stat-faturamento" style="font-size:20px;"><?= formatar_preco($stats['faturamento_total'] ?? 0) ?></div>
                <div class="stat-label">Faturamento Total</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-clock"></i>
                <div class="stat-numero" id="stat-pendentes"><?= $stats['pedidos_pendentes'] ?></div>
                <div class="stat-label">Pedidos Pendentes</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <div class="stat-numero" id="stat-clientes"><?= $total_clientes ?></div>
                <div class="stat-label">Clientes</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-pills"></i>
                <div class="stat-numero" id="stat-produtos"><?= $total_produtos ?></div>
                <div class="stat-label">Produtos Disponíveis</div>
            </div>
        </div>

        <!-- PEDIDOS -->
        <div class="card">
            <h2><i class="fas fa-clipboard-list"></i> Pedidos Recentes</h2>

            <div id="pedidos-container">
                <?php if (empty($pedidos)): ?>
                    <div class="empty"><i class="fas fa-receipt"></i><h2>Nenhum pedido ainda</h2></div>
                <?php else: ?>
                    <?php foreach ($pedidos as $pedido): ?>
                        <div class="pedido <?= $pedido['conta_solicitada'] == 1 ? 'pedido-conta-solicitada' : '' ?>" id="pedido-<?= $pedido['id'] ?>">
                            <div class="pedido-header">
                                <div style="flex:1;">
                                    <?php if ($pedido['conta_solicitada'] == 1): ?>
                                        <div style="background:var(--warning);color:#fff;padding:7px 14px;border-radius:var(--radius-full);margin-bottom:10px;font-weight:700;display:inline-flex;align-items:center;gap:8px;font-size:13px;">
                                            <i class="fas fa-cash-register"></i> CLIENTE QUER PAGAR!
                                        </div>
                                    <?php endif; ?>
                                    <div class="pedido-numero">Pedido #<?= $pedido['id'] ?></div>
                                    <div class="pedido-info">
                                        <strong><i class="fas fa-user"></i></strong> <?= htmlspecialchars($pedido['cliente_nome']) ?><br>
                                        <?php if ($pedido['telefone']): ?><strong><i class="fas fa-phone"></i></strong> <?= htmlspecialchars($pedido['telefone']) ?><br><?php endif; ?>
                                        <?php if ($pedido['endereco']): ?><strong><i class="fas fa-map-marker-alt"></i></strong> <?= htmlspecialchars($pedido['endereco']) ?><br><?php endif; ?>
                                        <strong><i class="fas fa-calendar-alt"></i></strong> <?= date('d/m/Y H:i', strtotime($pedido['criado_em'])) ?>
                                        <?php if ($pedido['observacoes']): ?><br><strong><i class="fas fa-comment-medical"></i></strong> <?= htmlspecialchars($pedido['observacoes']) ?><?php endif; ?>
                                    </div>

                                    <div class="pedido-itens">
                                        <strong><i class="fas fa-pills"></i> Itens do Pedido:</strong>
                                        <ul class="lista-itens">
                                            <?php foreach ($pedido['itens'] as $item): ?>
                                                <li>
                                                    <span class="item-qty"><?= $item['quantidade'] ?>x</span>
                                                    <span class="item-name"><?= htmlspecialchars($item['produto_nome']) ?></span>
                                                    <span class="item-price"><?= formatar_preco($item['preco_unitario'] * $item['quantidade']) ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>

                                    <div class="pedido-total"><?= formatar_preco($pedido['total']) ?></div>
                                </div>

                                <div class="status-form">
                                    <select id="status-<?= $pedido['id'] ?>" onchange="atualizarStatus(<?= $pedido['id'] ?>, this.value)">
                                        <option value="pendente"   <?= $pedido['status']=='pendente'   ? 'selected' : '' ?>>Aguardando</option>
                                        <option value="preparando" <?= $pedido['status']=='preparando' ? 'selected' : '' ?>>Separando</option>
                                        <option value="pronto"     <?= $pedido['status']=='pronto'     ? 'selected' : '' ?>>Pronto</option>
                                        <option value="entregue"   <?= $pedido['status']=='entregue'   ? 'selected' : '' ?>>Entregue</option>
                                        <option value="cancelado"  <?= $pedido['status']=='cancelado'  ? 'selected' : '' ?>>Cancelado</option>
                                    </select>
                                    <a href="imprimir_pedido.php?id=<?= $pedido['id'] ?>" target="_blank" class="btn btn-primary" style="padding:10px 18px;text-decoration:none;">
                                        <i class="fas fa-print"></i> Nota
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function mostrarToast(msg, tipo='success') {
            const t = document.createElement('div');
            t.className = `toast ${tipo==='error'?'error':''}`;
            t.innerHTML = `<i class="fas fa-${tipo==='success'?'check':'exclamation'}-circle"></i> ${msg}`;
            document.body.appendChild(t);
            setTimeout(()=>{ t.style.opacity='0'; setTimeout(()=>t.remove(),300); }, 3000);
        }

        async function atualizarStatus(idPedido, status) {
            try {
                const fd = new FormData(); fd.append('action','atualizar_status'); fd.append('id_pedido',idPedido); fd.append('status',status);
                const data = await (await fetch('ajax_handler.php',{method:'POST',body:fd})).json();
                if (data.sucesso) { mostrarToast('Status atualizado!'); atualizarStats(); }
            } catch(e) { mostrarToast('Erro de conexão','error'); }
        }

        function formatarPreco(v) { return 'R$ '+parseFloat(v).toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.'); }
        function formatarData(d) { const dt=new Date(d); return dt.toLocaleDateString('pt-BR')+' '+dt.toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'}); }

        async function atualizarStats() {
            try {
                const data = await (await fetch('ajax_handler.php?action=buscar_stats_dono')).json();
                if (data.stats) {
                    document.getElementById('stat-total-pedidos').textContent = data.stats.total_pedidos||0;
                    document.getElementById('stat-faturamento').textContent   = formatarPreco(data.stats.faturamento_total||0);
                    document.getElementById('stat-pendentes').textContent     = data.stats.pedidos_pendentes||0;
                    document.getElementById('stat-clientes').textContent      = data.total_clientes||0;
                    document.getElementById('stat-produtos').textContent      = data.total_produtos||0;
                }
            } catch(e) {}
        }

        let pedidosAtuais = new Set([<?= implode(',', array_column($pedidos, 'id')) ?>]);

        async function atualizarPedidos() {
            try {
                const data = await (await fetch('ajax_handler.php?action=buscar_pedidos_dono')).json();
                if (!data.pedidos || !data.pedidos.length) return;
                const container = document.getElementById('pedidos-container');
                let html = ''; let novosPedidos = false;

                data.pedidos.forEach(pedido => {
                    const isNovo = !pedidosAtuais.has(pedido.id);
                    if (isNovo) { novosPedidos = true; pedidosAtuais.add(pedido.id); }

                    let itensHtml = '';
                    (pedido.itens||[]).forEach(item => {
                        itensHtml += `<li><span class="item-qty">${item.quantidade}x</span><span class="item-name">${item.produto_nome}</span><span class="item-price">${formatarPreco(item.preco_unitario*item.quantidade)}</span></li>`;
                    });

                    html += `<div class="pedido ${isNovo?'pedido-novo':''} ${pedido.conta_solicitada==1?'pedido-conta-solicitada':''}" id="pedido-${pedido.id}">
                        <div class="pedido-header">
                            <div style="flex:1;">
                                ${pedido.conta_solicitada==1?`<div style="background:var(--warning);color:#fff;padding:7px 14px;border-radius:var(--radius-full);margin-bottom:10px;font-weight:700;display:inline-flex;align-items:center;gap:8px;font-size:13px;"><i class="fas fa-cash-register"></i> CLIENTE QUER PAGAR!</div>`:''}
                                <div class="pedido-numero">Pedido #${pedido.id}</div>
                                <div class="pedido-info">
                                    <strong><i class="fas fa-user"></i></strong> ${pedido.cliente_nome}<br>
                                    ${pedido.telefone?`<strong><i class="fas fa-phone"></i></strong> ${pedido.telefone}<br>`:''}
                                    <strong><i class="fas fa-calendar-alt"></i></strong> ${formatarData(pedido.criado_em)}
                                    ${pedido.observacoes?`<br><strong><i class="fas fa-comment-medical"></i></strong> ${pedido.observacoes}`:''}
                                </div>
                                <div class="pedido-itens"><strong><i class="fas fa-pills"></i> Itens:</strong><ul class="lista-itens">${itensHtml}</ul></div>
                                <div class="pedido-total">${formatarPreco(pedido.total)}</div>
                            </div>
                            <div class="status-form">
                                <select id="status-${pedido.id}" onchange="atualizarStatus(${pedido.id}, this.value)">
                                    <option value="pendente"   ${pedido.status==='pendente'  ?'selected':''}>Aguardando</option>
                                    <option value="preparando" ${pedido.status==='preparando'?'selected':''}>Separando</option>
                                    <option value="pronto"     ${pedido.status==='pronto'    ?'selected':''}>Pronto</option>
                                    <option value="entregue"   ${pedido.status==='entregue'  ?'selected':''}>Entregue</option>
                                    <option value="cancelado"  ${pedido.status==='cancelado' ?'selected':''}>Cancelado</option>
                                </select>
                                <a href="imprimir_pedido.php?id=${pedido.id}" target="_blank" class="btn btn-primary" style="padding:10px 18px;text-decoration:none;"><i class="fas fa-print"></i> Nota</a>
                            </div>
                        </div>
                    </div>`;
                });

                container.innerHTML = html;
                if (novosPedidos) { mostrarToast('Novo pedido recebido!'); }
            } catch(e) { console.error(e); }
        }

        if ('Notification' in window && Notification.permission === 'default') Notification.requestPermission();

        setInterval(() => { atualizarPedidos(); atualizarStats(); }, 30000);
        setTimeout(() => { atualizarPedidos(); atualizarStats(); }, 1000);
    </script>
</body>
</html>
