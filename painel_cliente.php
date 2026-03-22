<?php
session_start();
include "config.php";
include "helpers.php";

verificar_login('cliente');

$id_cliente = (int)$_SESSION['id_usuario'];

if (isset($_POST['atualizar_dados'])) {
    verificar_csrf();

    $nome     = sanitizar_texto($_POST['nome']     ?? '');
    $telefone = sanitizar_texto($_POST['telefone'] ?? '');
    $endereco = sanitizar_texto($_POST['endereco'] ?? '');

    $stmt = $conn->prepare("UPDATE usuarios SET nome=?, telefone=?, endereco=? WHERE id=?");
    $stmt->bind_param("sssi", $nome, $telefone, $endereco, $id_cliente);
    $stmt->execute();
    $stmt->close();

    $_SESSION['usuario'] = $nome;
    redirecionar('painel_cliente.php', 'Dados atualizados com sucesso!');
}

$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id_cliente);
$stmt->execute();
$cliente = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare(
    "SELECT p.*, COUNT(pi.id) as total_itens
     FROM pedidos p
     LEFT JOIN pedido_itens pi ON p.id = pi.id_pedido
     WHERE p.id_cliente = ?
       AND p.status NOT IN ('entregue', 'cancelado')
     GROUP BY p.id
     ORDER BY p.criado_em DESC"
);
$stmt->bind_param("i", $id_cliente);
$stmt->execute();
$pedidos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$status_cores  = ['pendente'=>'#f59e0b','preparando'=>'#3b82f6','pronto'=>'#10b981','entregue'=>'#6b7280','cancelado'=>'#ef4444'];
$status_labels = ['pendente'=>'AGUARDANDO','preparando'=>'SEPARANDO','pronto'=>'PRONTO','entregue'=>'ENTREGUE','cancelado'=>'CANCELADO'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minha Conta - FarmaVida</title>
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
                <a href="index.php" class="btn btn-secondary"><i class="fas fa-store"></i> Loja</a>
                <a href="carrinho.php" class="btn btn-primary">
                    <i class="fas fa-shopping-bag"></i> Sacola
                    <span class="badge-num" id="cart-count" style="display:none;"></span>
                </a>
                <a href="logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Sair</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="card" style="background:var(--gradient-main);color:white;">
            <h1 style="color:white;margin-bottom:6px;"><i class="fas fa-user-circle"></i> Olá, <?= htmlspecialchars($_SESSION['usuario']) ?>!</h1>
            <p style="opacity:.9;">Gerencie seus pedidos e dados pessoais</p>
        </div>

        <?php if (isset($_SESSION['sucesso'])): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $_SESSION['sucesso'] ?></div>
            <?php unset($_SESSION['sucesso']); ?>
        <?php endif; ?>

        <div class="card">
            <div class="tabs">
                <button class="tab active" onclick="mudarTab('pedidos', this)">
                    <i class="fas fa-clipboard-list"></i> Meus Pedidos Ativos
                    <span class="auto-update-badge"><i class="fas fa-sync-alt"></i> Auto</span>
                </button>
                <button class="tab" onclick="mudarTab('dados', this)">
                    <i class="fas fa-user-edit"></i> Meus Dados
                </button>
            </div>

            <!-- ABA PEDIDOS -->
            <div id="pedidos" class="tab-content active">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Pedidos entregues e cancelados são ocultados automaticamente para manter seu painel organizado.
                </div>
                <div id="pedidos-container">
                    <?php if (empty($pedidos)): ?>
                        <div class="empty">
                            <i class="fas fa-clipboard"></i>
                            <h2>Nenhum pedido ativo</h2>
                            <p>Navegue pela nossa farmácia e faça seu pedido!</p>
                            <a href="index.php" class="btn btn-primary"><i class="fas fa-pills"></i> Ver Produtos</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pedidos as $pedido): ?>
                            <div class="pedido" id="pedido-<?= $pedido['id'] ?>">
                                <div class="pedido-header">
                                    <div>
                                        <div class="pedido-numero">Pedido #<?= $pedido['id'] ?></div>
                                        <div class="pedido-info">
                                            <i class="fas fa-calendar-alt"></i>
                                            <?= date('d/m/Y H:i', strtotime($pedido['criado_em'])) ?>
                                            &nbsp;|&nbsp;
                                            <i class="fas fa-box"></i> <?= $pedido['total_itens'] ?> item(ns)
                                        </div>
                                    </div>
                                    <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;">
                                        <span class="status-badge" style="background:<?= $status_cores[$pedido['status']] ?>">
                                            <?= $status_labels[$pedido['status']] ?>
                                        </span>
                                        <?php if ($pedido['status'] === 'pronto'): ?>
                                            <button onclick="pedirConta(<?= $pedido['id'] ?>)" class="btn btn-success" style="padding:8px 16px;font-size:13px;">
                                                <i class="fas fa-cash-register"></i> Pagar
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($pedido['observacoes']): ?>
                                    <div class="pedido-info" style="margin-top:10px;padding:10px;background:var(--bg);border-radius:8px;">
                                        <i class="fas fa-comment-medical"></i> <strong>Observações:</strong> <?= htmlspecialchars($pedido['observacoes']) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="pedido-total"><?= formatar_preco($pedido['total']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ABA DADOS -->
            <div id="dados" class="tab-content">
                <form method="POST">
                    <?= campo_csrf() ?>
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Nome Completo</label>
                        <input type="text" name="nome" value="<?= htmlspecialchars($cliente['nome']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> E-mail</label>
                        <input type="email" value="<?= htmlspecialchars($cliente['email']) ?>" disabled style="background:var(--bg);color:var(--gray);">
                        <small style="color:var(--gray);font-size:12px;">O e-mail não pode ser alterado</small>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Telefone / WhatsApp</label>
                        <input type="tel" name="telefone" value="<?= htmlspecialchars($cliente['telefone'] ?? '') ?>" placeholder="(00) 00000-0000">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Endereço de Entrega</label>
                        <textarea name="endereco" rows="3" placeholder="Rua, número, bairro, cidade"><?= htmlspecialchars($cliente['endereco'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" name="atualizar_dados" class="btn btn-success">
                        <i class="fas fa-save"></i> Salvar Alterações
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const statusCores  = { pendente:'#f59e0b', preparando:'#3b82f6', pronto:'#10b981', entregue:'#6b7280', cancelado:'#ef4444' };
        const statusLabels = { pendente:'AGUARDANDO', preparando:'SEPARANDO', pronto:'PRONTO', entregue:'ENTREGUE', cancelado:'CANCELADO' };

        let statusAtual = {};
        <?php foreach ($pedidos as $pedido): ?>
            statusAtual[<?= $pedido['id'] ?>] = '<?= $pedido['status'] ?>';
        <?php endforeach; ?>

        function mudarTab(tab, btn) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(tab).classList.add('active');
        }

        function mostrarToast(msg) {
            const t = document.createElement('div');
            t.className = 'toast'; t.innerHTML = `<i class="fas fa-info-circle"></i> ${msg}`;
            document.body.appendChild(t);
            setTimeout(() => { t.style.opacity='0'; setTimeout(()=>t.remove(),300); }, 4000);
        }

        function formatarPreco(v) { return 'R$ ' + parseFloat(v).toFixed(2).replace('.', ','); }
        function formatarData(d) { const dt=new Date(d); return dt.toLocaleDateString('pt-BR')+' '+dt.toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'}); }

        async function atualizarPedidos() {
            try {
                const data = await (await fetch('ajax_handler.php?action=buscar_pedidos_cliente')).json();
                if (!data.pedidos) return;
                const container = document.getElementById('pedidos-container');
                const ativos = data.pedidos.filter(p => p.status !== 'entregue' && p.status !== 'cancelado');
                if (ativos.length === 0) {
                    container.innerHTML = `<div class="empty"><i class="fas fa-clipboard"></i><h2>Nenhum pedido ativo</h2><p>Navegue pela farmácia!</p><a href="index.php" class="btn btn-primary"><i class="fas fa-pills"></i> Ver Produtos</a></div>`;
                    return;
                }
                let html = '';
                ativos.forEach(p => {
                    const statusAntigo = statusAtual[p.id];
                    if (statusAntigo && statusAntigo !== p.status) mostrarToast(`Pedido #${p.id}: ${statusLabels[p.status]}`);
                    statusAtual[p.id] = p.status;
                    html += `<div class="pedido" id="pedido-${p.id}">
                        <div class="pedido-header">
                            <div>
                                <div class="pedido-numero">Pedido #${p.id}</div>
                                <div class="pedido-info"><i class="fas fa-calendar-alt"></i> ${formatarData(p.criado_em)} | <i class="fas fa-box"></i> ${p.total_itens} item(ns)</div>
                            </div>
                            <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;">
                                <span class="status-badge" style="background:${statusCores[p.status]}">${statusLabels[p.status]}</span>
                                ${p.status === 'pronto' ? `<button onclick="pedirConta(${p.id})" class="btn btn-success" style="padding:8px 16px;font-size:13px;"><i class="fas fa-cash-register"></i> Pagar</button>` : ''}
                            </div>
                        </div>
                        ${p.observacoes ? `<div class="pedido-info" style="margin-top:10px;padding:10px;background:var(--bg);border-radius:8px;"><i class="fas fa-comment-medical"></i> <strong>Obs:</strong> ${p.observacoes}</div>` : ''}
                        <div class="pedido-total">${formatarPreco(p.total)}</div>
                    </div>`;
                });
                container.innerHTML = html;
            } catch(e) { console.error(e); }
        }

        async function atualizarContadorCarrinho() {
            try {
                const data = await (await fetch('ajax_handler.php?action=contar_carrinho')).json();
                const badge = document.getElementById('cart-count');
                if (data.count > 0) { badge.textContent = data.count; badge.style.display = 'flex'; }
                else badge.style.display = 'none';
            } catch(e) {}
        }

        async function pedirConta(idPedido) {
            if (!confirm('Deseja solicitar o pagamento deste pedido?')) return;
            try {
                const fd = new FormData(); fd.append('action','pedir_conta'); fd.append('id_pedido',idPedido);
                const data = await (await fetch('ajax_handler.php', {method:'POST',body:fd})).json();
                if (data.sucesso) { mostrarToast('✅ Pagamento solicitado! O farmacêutico vai te atender.'); atualizarPedidos(); }
            } catch(e) {}
        }

        setInterval(() => { atualizarPedidos(); atualizarContadorCarrinho(); }, 30000);
        atualizarContadorCarrinho();
    </script>
</body>
</html>
