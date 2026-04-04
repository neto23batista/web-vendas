<?php
require_once FARMAVIDA_ROOT . '/app/core/bootstrap.php';
require_once FARMAVIDA_ROOT . '/app/core/config.php';
require_once FARMAVIDA_ROOT . '/app/core/helpers.php';
require_once FARMAVIDA_ROOT . '/services/schema_service.php';
require_once FARMAVIDA_ROOT . '/services/pedido_service.php';

verificar_login('dono');

 
if (isset($_POST['atualizar_status'])) {
    verificar_csrf();
    $id_pedido = (int)$_POST['id_pedido'];
    $status    = $_POST['status'] ?? '';
    $validos   = ['pendente', 'preparando', 'pronto', 'entregue', 'cancelado'];
    if (in_array($status, $validos, true)) {
        try {
            pedido_atualizar_status($conn, $id_pedido, $status, (int)($_SESSION['id_usuario'] ?? 0) ?: null);
        } catch (Throwable $e) {
            redirecionar('painel_dono.php', $e->getMessage(), 'erro');
        }
    }
    redirecionar('painel_dono.php', 'Status atualizado!');
}

 
$migracoes_pendentes = schema_listar_migracoes_pendentes($conn);
$tem_migracoes_pendentes = !empty($migracoes_pendentes);

$stats = $conn->query(
    "SELECT COUNT(DISTINCT id) as total_pedidos,
            SUM(total) as faturamento_total,
            SUM(CASE WHEN status='pendente' THEN 1 ELSE 0 END) as pedidos_pendentes
     FROM pedidos"
)->fetch_assoc();

$total_produtos = $conn->query(
    "SELECT COUNT(*) as t FROM produtos WHERE disponivel=1"
)->fetch_assoc()['t'];

$total_clientes = $conn->query(
    "SELECT COUNT(*) as t FROM usuarios WHERE tipo='cliente'"
)->fetch_assoc()['t'];

 
$pagina  = max(1, (int)($_GET['pagina'] ?? 1));
$por_pag = 20;
$offset  = ($pagina - 1) * $por_pag;

$total_pedidos_db = (int)$conn->query("SELECT COUNT(*) as t FROM pedidos")->fetch_assoc()['t'];
$total_paginas    = (int)ceil($total_pedidos_db / $por_pag);

$stmt = $conn->prepare(
    "SELECT p.*, u.nome as cliente_nome, u.telefone, u.endereco
     FROM pedidos p
     JOIN usuarios u ON p.id_cliente = u.id
     ORDER BY p.criado_em DESC
     LIMIT ? OFFSET ?"
);
$stmt->bind_param("ii", $por_pag, $offset);
$stmt->execute();
$pedidos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($pedidos as &$pedido) {
    $stmt_i = $conn->prepare(
        "SELECT pi.*, pr.nome as produto_nome
         FROM pedido_itens pi
         JOIN produtos pr ON pi.id_produto = pr.id
         WHERE pi.id_pedido = ?"
    );
    $stmt_i->bind_param("i", $pedido['id']);
    $stmt_i->execute();
    $itens = $stmt_i->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_i->close();
    $pedido['itens']       = $itens;
    $pedido['total_itens'] = count($itens);
}
unset($pedido);

$status_cores = ['pendente'=>'#f59e0b','preparando'=>'#3b82f6','pronto'=>'#10b981','entregue'=>'#00e5a0','cancelado'=>'#ef4444'];
$status_fluxo = ['pendente', 'preparando', 'pronto', 'entregue', 'cancelado'];

function pedido_status_label_admin(string $status, bool $is_delivery): string {
    return match ($status) {
        'pendente' => 'Aguardando',
        'preparando' => 'Separando',
        'pronto' => $is_delivery ? 'Saiu p/ entrega' : 'Pronto p/ retirada',
        'entregue' => $is_delivery ? 'Entregue' : 'Retirado',
        'cancelado' => 'Cancelado',
        default => 'Aguardando',
    };
}

function pedido_status_icon_admin(string $status, bool $is_delivery): string {
    return match ($status) {
        'pendente' => 'hourglass-half',
        'preparando' => 'box-open',
        'pronto' => $is_delivery ? 'truck-fast' : 'store',
        'entregue' => 'circle-check',
        'cancelado' => 'ban',
        default => 'hourglass-half',
    };
}

function renderizar_status_form_admin(int $pedido_id, string $status, bool $is_delivery, array $status_fluxo): string {
    ob_start();
    ?>
    <div class="status-form" data-pedido-id="<?= $pedido_id ?>" data-status="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>" data-delivery="<?= $is_delivery ? '1' : '0' ?>">
        <div class="status-current status-<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>" data-status-display>
            <span class="status-current-icon"><i class="fas fa-<?= htmlspecialchars(pedido_status_icon_admin($status, $is_delivery), ENT_QUOTES, 'UTF-8') ?>"></i></span>
            <span class="status-current-copy">
                <span class="status-current-label"><?= htmlspecialchars(pedido_status_label_admin($status, $is_delivery), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="status-current-sub">Status atual do pedido</span>
            </span>
            <span class="status-current-state">Atual</span>
        </div>
        <div class="status-actions">
            <?php foreach ($status_fluxo as $opcao): ?>
                <button type="button"
                        class="status-action status-<?= htmlspecialchars($opcao, ENT_QUOTES, 'UTF-8') ?> <?= $status === $opcao ? 'is-active' : '' ?>"
                        onclick="atualizarStatus(<?= $pedido_id ?>, '<?= htmlspecialchars($opcao, ENT_QUOTES, 'UTF-8') ?>', this)">
                    <i class="fas fa-<?= htmlspecialchars(pedido_status_icon_admin($opcao, $is_delivery), ENT_QUOTES, 'UTF-8') ?>"></i>
                    <span><?= htmlspecialchars(pedido_status_label_admin($opcao, $is_delivery), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
            <?php endforeach; ?>
        </div>
        <div class="status-feedback" data-status-feedback></div>
        <a href="imprimir_pedido.php?id=<?= $pedido_id ?>" target="_blank"
           class="btn btn-primary" style="padding:10px 18px;text-decoration:none;">
            <i class="fas fa-print"></i> Nota
        </a>
    </div>
    <?php
    return (string)ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Admin - FarmaVida</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .paginacao { display:flex; gap:6px; justify-content:center; margin-top:20px; flex-wrap:wrap; }
        .paginacao a, .paginacao span {
            padding:7px 14px; border-radius:var(--radius-full); font-size:13px; font-weight:600;
            border:1px solid var(--border); text-decoration:none; color:var(--text2);
        }
        .paginacao a:hover { border-color:var(--primary); color:var(--primary); }
        .paginacao .atual  { background:var(--primary); border-color:var(--primary); color:white; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-container">
            <div class="logo" style="cursor:default;">
                <div class="logo-icon"><i class="fas fa-prescription-bottle-medical"></i></div>
                Farma<span>Vida</span>
                <span style="font-size:13px;color:var(--text2);font-weight:500;margin-left:4px;">Admin</span>
                <button onclick="atualizarPedidos(); atualizarStats();" class="btn btn-secondary"
                        style="margin-left:12px;padding:7px 14px;font-size:12px;">
                    <i class="fas fa-sync-alt"></i> Atualizar
                </button>
                <span class="auto-update-badge" style="margin-left:8px;"><i class="fas fa-clock"></i> 30s</span>
            </div>
            <div class="nav-buttons">
                <a href="migracoes.php"           class="btn btn-secondary"><i class="fas fa-database"></i> Migrações<?= $tem_migracoes_pendentes ? ' (' . count($migracoes_pendentes) . ')' : '' ?></a>
                <a href="relatorios.php"          class="btn btn-info"     ><i class="fas fa-chart-line"></i> Relatórios</a>
                <a href="nfe.php"                 class="btn btn-secondary"><i class="fas fa-file-invoice"></i> NF-e</a>
                <a href="erp.php"                 class="btn btn-secondary"><i class="fas fa-plug"></i> ERP</a>
                <a href="gerenciar_produtos.php"  class="btn btn-success"  ><i class="fas fa-boxes"></i> Produtos</a>
                <a href="estoque.php"             class="btn btn-primary"  id="btn-estoque" style="position:relative;">
                    <i class="fas fa-boxes-stacked"></i> Estoque
                    <span class="badge-num" id="estoque-alert-badge" style="display:none;background:#f59e0b;"></span>
                </a>
                <form method="POST" action="limpar_pedidos_antigos.php" style="display:inline-flex;">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="executar" value="1">
                    <button type="submit" class="btn btn-warning" onclick="return confirm('Limpar pedidos finalizados?')">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </form>
                <a href="index.php"   class="btn btn-secondary"><i class="fas fa-store"></i></a>
                <a href="logout.php"  class="btn btn-danger"   ><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <h1><i class="fas fa-tachometer-alt" style="color:var(--primary);"></i> Painel Administrativo</h1>
            <p style="color:var(--text2);">Bem-vindo, <?= htmlspecialchars($_SESSION['usuario']) ?>!</p>
        </div>

        <div id="estoque-alert-banner" style="display:none;"></div>

        <?php if (isset($_SESSION['sucesso'])): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $_SESSION['sucesso'] ?></div>
            <?php unset($_SESSION['sucesso']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['erro'])): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $_SESSION['erro'] ?></div>
            <?php unset($_SESSION['erro']); ?>
        <?php endif; ?>

        <?php if ($tem_migracoes_pendentes): ?>
            <div class="alert alert-warning">
                <i class="fas fa-database"></i>
                Existem <?= count($migracoes_pendentes) ?> Migrações pendentes. Execute-as em <a href="migracoes.php" style="font-weight:700;">Migrações</a> antes de abrir os módulos dependentes.
            </div>
        <?php endif; ?>

        
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
                <div class="stat-label">Produtos DisponÃ­veis</div>
            </div>
        </div>

        
        <div class="card">
            <h2><i class="fas fa-clipboard-list"></i> Pedidos</h2>

            <div id="pedidos-lista">
                <?php if (empty($pedidos)): ?>
                    <div class="empty"><i class="fas fa-receipt"></i><h2>Nenhum pedido ainda</h2></div>
                <?php else: ?>
                    <?php foreach ($pedidos as $pedido): ?>
                        <div class="pedido <?= $pedido['conta_solicitada'] == 1 ? 'pedido-conta-solicitada' : '' ?>"
                             data-status="<?= htmlspecialchars($pedido['status'], ENT_QUOTES, 'UTF-8') ?>"
                             id="pedido-<?= $pedido['id'] ?>">
                            <div class="pedido-header">
                                <div style="flex:1;">
                                    <?php if ($pedido['conta_solicitada'] == 1): ?>
                                        <div style="background:var(--warning);color:#fff;padding:7px 14px;border-radius:var(--radius-full);margin-bottom:10px;font-weight:700;display:inline-flex;align-items:center;gap:8px;font-size:13px;">
                                            <i class="fas fa-cash-register"></i> CLIENTE QUER PAGAR!
                                        </div>
                                    <?php endif; ?>
                                    <div class="pedido-numero">Pedido #<?= $pedido['id'] ?></div>
                                    <?php $is_delivery = strpos($pedido['observacoes'] ?? '', 'DELIVERY') !== false; ?>
                                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap;">
                                        <span style="display:inline-flex;align-items:center;gap:6px;padding:3px 12px;border-radius:20px;font-size:11px;font-weight:700;<?= $is_delivery ? 'background:rgba(77,156,255,.12);color:var(--secondary);' : 'background:rgba(0,229,160,.1);color:var(--primary);' ?>">
                                            <i class="fas fa-<?= $is_delivery ? 'motorcycle' : 'store-alt' ?>"></i>
                                            <?= $is_delivery ? 'Delivery' : 'Retirada no Local' ?>
                                        </span>
                                        <?php if (($pedido['forma_pagamento'] ?? '') === 'app'): ?>
                                            <?php
                                            $pg_cores = ['aprovado'=>['#059669','circle-check','Pago'],'em_analise'=>['#d97706','clock','Em anÃ¡lise'],'recusado'=>['#dc2626','circle-xmark','Recusado'],'cancelado'=>['#6b7280','ban','Cancelado'],'pendente'=>['#d97706','clock','Aguard. pag.']];
                                            $pg = $pg_cores[$pedido['pagamento_status'] ?? 'pendente'] ?? $pg_cores['pendente'];
                                            ?>
                                            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 12px;border-radius:20px;font-size:11px;font-weight:700;background:<?= $pg[0] ?>1a;color:<?= $pg[0] ?>;">
                                                <i class="fas fa-<?= $pg[1] ?>"></i> <?= $pg[2] ?> Â· MP
                                            </span>
                                        <?php endif; ?>
                                    </div>
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

                                <?= renderizar_status_form_admin((int)$pedido['id'], (string)$pedido['status'], $is_delivery, $status_fluxo) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div id="pedidos-paginacao">
                <?php if ($total_paginas > 1): ?>
                <div class="paginacao">
                    <?php if ($pagina > 1): ?>
                        <a href="?pagina=<?= $pagina - 1 ?>">&#8592; Anterior</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $pagina - 2); $i <= min($total_paginas, $pagina + 2); $i++): ?>
                        <?php if ($i === $pagina): ?>
                            <span class="atual"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?pagina=<?= $i ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($pagina < $total_paginas): ?>
                        <a href="?pagina=<?= $pagina + 1 ?>">PrÃ³xima &#8594;</a>
                    <?php endif; ?>
                    <span style="color:var(--text2);padding:7px 0;">
                        PÃ¡gina <?= $pagina ?> de <?= $total_paginas ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        const CSRF_TOKEN = <?= json_encode(gerar_token_csrf()) ?>;
        const STATUS_FLOW = ['pendente', 'preparando', 'pronto', 'entregue', 'cancelado'];
        const PEDIDOS_PAGINA_INICIAL = <?= (int)$pagina ?>;
        const PEDIDOS_POR_PAGINA = <?= (int)$por_pag ?>;

        let paginaAtualPedidos = PEDIDOS_PAGINA_INICIAL;
        let pedidosAtuais = new Set([<?= implode(',', array_column($pedidos, 'id')) ?>]);

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function statusLabel(status, isDelivery) {
            switch (status) {
                case 'pendente': return 'Aguardando';
                case 'preparando': return 'Separando';
                case 'pronto': return isDelivery ? 'Saiu p/ entrega' : 'Pronto p/ retirada';
                case 'entregue': return isDelivery ? 'Entregue' : 'Retirado';
                case 'cancelado': return 'Cancelado';
                default: return 'Aguardando';
            }
        }

        function statusIcon(status, isDelivery) {
            switch (status) {
                case 'pendente': return 'hourglass-half';
                case 'preparando': return 'box-open';
                case 'pronto': return isDelivery ? 'truck-fast' : 'store';
                case 'entregue': return 'circle-check';
                case 'cancelado': return 'ban';
                default: return 'hourglass-half';
            }
        }

        function renderStatusFormMarkup(idPedido, status, isDelivery, feedback = '', feedbackType = '') {
            const safeFeedback = escapeHtml(feedback);
            const actions = STATUS_FLOW.map((option) => `
                <button type="button"
                        class="status-action status-${option} ${status === option ? 'is-active' : ''}"
                        onclick="atualizarStatus(${idPedido}, '${option}', this)">
                    <i class="fas fa-${statusIcon(option, isDelivery)}"></i>
                    <span>${statusLabel(option, isDelivery)}</span>
                </button>
            `).join('');

            return `
                <div class="status-current status-${status}" data-status-display>
                    <span class="status-current-icon"><i class="fas fa-${statusIcon(status, isDelivery)}"></i></span>
                    <span class="status-current-copy">
                        <span class="status-current-label">${statusLabel(status, isDelivery)}</span>
                        <span class="status-current-sub">Status atual do pedido</span>
                    </span>
                    <span class="status-current-state">Atual</span>
                </div>
                <div class="status-actions">${actions}</div>
                <div class="status-feedback ${feedbackType ? `is-${feedbackType}` : ''}" data-status-feedback>${safeFeedback}</div>
                <a href="imprimir_pedido.php?id=${idPedido}" target="_blank"
                   class="btn btn-primary" style="padding:10px 18px;text-decoration:none;">
                    <i class="fas fa-print"></i> Nota
                </a>
            `;
        }

        function renderStatusFormContainer(idPedido, status, isDelivery, feedback = '', feedbackType = '') {
            return `
                <div class="status-form" data-pedido-id="${idPedido}" data-status="${escapeHtml(status)}" data-delivery="${isDelivery ? '1' : '0'}">
                    ${renderStatusFormMarkup(idPedido, status, isDelivery, feedback, feedbackType)}
                </div>
            `;
        }

        function setStatusFormState(form, status, feedback = '', feedbackType = '') {
            if (!form) return;
            const idPedido = Number(form.dataset.pedidoId || '0');
            const isDelivery = form.dataset.delivery === '1';
            form.dataset.status = status;
            form.innerHTML = renderStatusFormMarkup(idPedido, status, isDelivery, feedback, feedbackType);
            const pedido = form.closest('.pedido');
            if (pedido) {
                pedido.dataset.status = status;
            }
        }

        function setStatusLoading(form, loading) {
            if (!form) return;
            form.classList.toggle('is-loading', loading);
            form.querySelectorAll('.status-action').forEach((button) => {
                button.disabled = loading;
            });
            const feedback = form.querySelector('[data-status-feedback]');
            if (feedback && loading) {
                feedback.className = 'status-feedback';
                feedback.textContent = 'Atualizando status...';
            }
        }

        function mostrarToast(msg, tipo = 'success') {
            const t = document.createElement('div');
            const icon = tipo === 'success' ? 'check' : 'exclamation';
            t.className = `toast ${tipo === 'error' ? 'error' : ''}`;
            t.innerHTML = `<i class="fas fa-${icon}-circle"></i><span></span>`;
            t.querySelector('span').textContent = msg;
            document.body.appendChild(t);
            setTimeout(() => {
                t.style.opacity = '0';
                setTimeout(() => t.remove(), 300);
            }, 3000);
        }

        async function atualizarStatus(idPedido, status, button) {
            const form = button ? button.closest('.status-form') : document.querySelector(`.status-form[data-pedido-id="${idPedido}"]`);
            if (!form) return;

            const statusAnterior = form.dataset.status || '';
            if (statusAnterior === status) return;

            setStatusLoading(form, true);

            try {
                const fd = new FormData();
                fd.append('action', 'atualizar_status');
                fd.append('id_pedido', idPedido);
                fd.append('status', status);
                fd.append('csrf_token', CSRF_TOKEN);
                const resposta = await fetch('ajax_handler.php', { method: 'POST', body: fd });
                const data = await resposta.json();
                if (data.sucesso) {
                    setStatusFormState(form, status, 'Status atualizado com sucesso.', 'success');
                    mostrarToast('Status atualizado!');
                    atualizarStats();
                } else {
                    setStatusFormState(form, statusAnterior, data.erro || 'Erro ao atualizar o status.', 'error');
                    mostrarToast(data.erro || 'Erro', 'error');
                }
            } catch (e) {
                setStatusFormState(form, statusAnterior, 'Erro de conexao ao atualizar o status.', 'error');
                mostrarToast('Erro de conexao', 'error');
            } finally {
                setStatusLoading(form, false);
            }
        }

        function formatarPreco(v) {
            return 'R$ ' + Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function formatarData(d) {
            const dt = new Date(d);
            if (Number.isNaN(dt.getTime())) {
                return escapeHtml(d);
            }
            return dt.toLocaleDateString('pt-BR') + ' ' + dt.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        }

        function isDeliveryPedido(pedido) {
            return String((pedido && pedido.observacoes) ? pedido.observacoes : '').includes('DELIVERY');
        }

        function renderPagamentoBadge(pedido) {
            if (String(pedido.forma_pagamento ?? '') !== 'app') {
                return '';
            }

            const statusPagamento = String(pedido.pagamento_status ?? 'pendente');
            const map = {
                aprovado: ['#059669', 'circle-check', 'Pago'],
                em_analise: ['#d97706', 'clock', 'Em anÃ¡lise'],
                recusado: ['#dc2626', 'circle-xmark', 'Recusado'],
                cancelado: ['#6b7280', 'ban', 'Cancelado'],
                pendente: ['#d97706', 'clock', 'Aguard. pag.'],
            };
            const pg = map[statusPagamento] || map.pendente;

            return `
                <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 12px;border-radius:20px;font-size:11px;font-weight:700;background:${pg[0]}1a;color:${pg[0]};">
                    <i class="fas fa-${pg[1]}"></i> ${pg[2]} Â· MP
                </span>
            `;
        }

        function renderItensPedido(itens) {
            return (itens || []).map((item) => `
                <li>
                    <span class="item-qty">${Number(item.quantidade)}x</span>
                    <span class="item-name">${escapeHtml(item.produto_nome)}</span>
                    <span class="item-price">${formatarPreco(Number(item.preco_unitario || 0) * Number(item.quantidade || 0))}</span>
                </li>
            `).join('');
        }

        function renderPedidoCard(pedido, isNovo = false) {
            const idPedido = Number(pedido.id || 0);
            const status = String(pedido.status || 'pendente');
            const isDelivery = isDeliveryPedido(pedido);
            const contaSolicitada = Number(pedido.conta_solicitada || 0) === 1;
            const deliveryBadgeStyle = isDelivery
                ? 'background:rgba(77,156,255,.12);color:var(--secondary);'
                : 'background:rgba(0,229,160,.1);color:var(--primary);';

            return `
                <div class="pedido ${isNovo ? 'pedido-novo' : ''} ${contaSolicitada ? 'pedido-conta-solicitada' : ''}" data-status="${escapeHtml(status)}" id="pedido-${idPedido}">
                    <div class="pedido-header">
                        <div style="flex:1;">
                            ${contaSolicitada ? `<div style="background:var(--warning);color:#fff;padding:7px 14px;border-radius:var(--radius-full);margin-bottom:10px;font-weight:700;display:inline-flex;align-items:center;gap:8px;font-size:13px;"><i class="fas fa-cash-register"></i> CLIENTE QUER PAGAR!</div>` : ''}
                            <div class="pedido-numero">Pedido #${idPedido}</div>
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap;">
                                <span style="display:inline-flex;align-items:center;gap:6px;padding:3px 12px;border-radius:20px;font-size:11px;font-weight:700;${deliveryBadgeStyle}">
                                    <i class="fas fa-${isDelivery ? 'motorcycle' : 'store-alt'}"></i>
                                    ${isDelivery ? 'Delivery' : 'Retirada no Local'}
                                </span>
                                ${renderPagamentoBadge(pedido)}
                            </div>
                            <div class="pedido-info">
                                <strong><i class="fas fa-user"></i></strong> ${escapeHtml(pedido.cliente_nome)}<br>
                                ${pedido.telefone ? `<strong><i class="fas fa-phone"></i></strong> ${escapeHtml(pedido.telefone)}<br>` : ''}
                                ${pedido.endereco ? `<strong><i class="fas fa-map-marker-alt"></i></strong> ${escapeHtml(pedido.endereco)}<br>` : ''}
                                <strong><i class="fas fa-calendar-alt"></i></strong> ${formatarData(pedido.criado_em)}
                                ${pedido.observacoes ? `<br><strong><i class="fas fa-comment-medical"></i></strong> ${escapeHtml(pedido.observacoes)}` : ''}
                            </div>
                            <div class="pedido-itens">
                                <strong><i class="fas fa-pills"></i> Itens do Pedido:</strong>
                                <ul class="lista-itens">${renderItensPedido(pedido.itens)}</ul>
                            </div>
                            <div class="pedido-total">${formatarPreco(pedido.total)}</div>
                        </div>
                        ${renderStatusFormContainer(idPedido, status, isDelivery)}
                    </div>
                </div>
            `;
        }

        function renderPaginacao(meta) {
            if (!meta || Number(meta.total_paginas || 0) <= 1) {
                return '';
            }

            const pagina = Number(meta.pagina || 1);
            const totalPaginas = Number(meta.total_paginas || 1);
            const inicio = Math.max(1, pagina - 2);
            const fim = Math.min(totalPaginas, pagina + 2);
            let html = '<div class="paginacao">';

            if (pagina > 1) {
                html += `<a href="?pagina=${pagina - 1}">&#8592; Anterior</a>`;
            }

            for (let i = inicio; i <= fim; i += 1) {
                if (i === pagina) {
                    html += `<span class="atual">${i}</span>`;
                } else {
                    html += `<a href="?pagina=${i}">${i}</a>`;
                }
            }

            if (pagina < totalPaginas) {
                html += `<a href="?pagina=${pagina + 1}">PrÃ³xima &#8594;</a>`;
            }

            html += `<span style="color:var(--text2);padding:7px 0;">PÃ¡gina ${pagina} de ${totalPaginas}</span>`;
            html += '</div>';
            return html;
        }

        function atualizarUrlPaginacao(pagina) {
            const url = new URL(window.location.href);
            if (pagina > 1) {
                url.searchParams.set('pagina', String(pagina));
            } else {
                url.searchParams.delete('pagina');
            }
            window.history.replaceState({}, '', url);
        }

        async function atualizarStats() {
            try {
                const data = await (await fetch('ajax_handler.php?action=buscar_stats_dono')).json();
                if (data.stats) {
                    document.getElementById('stat-total-pedidos').textContent = data.stats.total_pedidos || 0;
                    document.getElementById('stat-faturamento').textContent = formatarPreco(data.stats.faturamento_total || 0);
                    document.getElementById('stat-pendentes').textContent = data.stats.pedidos_pendentes || 0;
                    document.getElementById('stat-clientes').textContent = data.total_clientes || 0;
                    document.getElementById('stat-produtos').textContent = data.total_produtos || 0;
                }
            } catch (e) {}
        }

        async function atualizarPedidos() {
            try {
                const resposta = await fetch(`ajax_handler.php?action=buscar_pedidos_dono&pagina=${paginaAtualPedidos}&limite=${PEDIDOS_POR_PAGINA}`);
                const data = await resposta.json();
                if (data.erro) {
                    return;
                }

                const pedidos = Array.isArray(data.pedidos) ? data.pedidos : [];
                const meta = data.paginacao || null;
                if (meta && Number(meta.pagina || 0) > 0) {
                    paginaAtualPedidos = Number(meta.pagina);
                    atualizarUrlPaginacao(paginaAtualPedidos);
                }

                const lista = document.getElementById('pedidos-lista');
                const paginacao = document.getElementById('pedidos-paginacao');
                const idsPaginaAtual = new Set(pedidos.map((pedido) => Number(pedido.id)));
                const houveNovoPedido = paginaAtualPedidos === 1 && pedidos.some((pedido) => !pedidosAtuais.has(Number(pedido.id)));

                if (pedidos.length === 0) {
                    lista.innerHTML = '<div class="empty"><i class="fas fa-receipt"></i><h2>Nenhum pedido ainda</h2></div>';
                } else {
                    lista.innerHTML = pedidos
                        .map((pedido) => renderPedidoCard(pedido, paginaAtualPedidos === 1 && !pedidosAtuais.has(Number(pedido.id))))
                        .join('');
                }

                pedidosAtuais = idsPaginaAtual;
                paginacao.innerHTML = renderPaginacao(meta);

                if (houveNovoPedido) {
                    mostrarToast('Novo pedido recebido!');
                }
            } catch (e) {
                console.error(e);
            }
        }

        async function verificarEstoque() {
            try {
                const data = await (await fetch('ajax_handler.php?action=alertas_estoque')).json();
                const banner = document.getElementById('estoque-alert-banner');
                const badge = document.getElementById('estoque-alert-badge');
                const total = (data.zerados || 0) + (data.baixos || 0);

                if (total > 0) {
                    badge.textContent = total;
                    badge.style.display = 'flex';
                    let itensHtml = '';
                    (data.criticos || []).forEach((p) => {
                        const cor = p.estoque_atual === 0 ? '#dc2626' : '#d97706';
                        itensHtml += `<span style="background:${cor}22;color:${cor};padding:2px 10px;border-radius:20px;font-size:12px;font-weight:700;white-space:nowrap;">${escapeHtml(p.nome)}: ${Number(p.estoque_atual)} un</span>`;
                    });
                    banner.style.display = 'block';
                    banner.innerHTML = `
                        <div style="background:rgba(255,184,48,.1);border:1px solid rgba(255,184,48,.3);border-radius:var(--radius-md);padding:14px 16px;margin-bottom:16px;">
                            <div class="alert-estoque-inner" style="display:flex;align-items:flex-start;gap:10px;flex-wrap:wrap;">
                                <i class="fas fa-triangle-exclamation" style="font-size:18px;color:var(--warning);flex-shrink:0;margin-top:2px;"></i>
                                <div style="flex:1;min-width:160px;">
                                    <strong style="color:var(--warning);display:block;margin-bottom:6px;font-size:13px;">Estoque crÃ­tico: ${data.zerados} zerado(s), ${data.baixos} abaixo do mÃ­nimo</strong>
                                    <div style="display:flex;gap:6px;flex-wrap:wrap;">${itensHtml}</div>
                                </div>
                                <a href="estoque.php" class="btn btn-warning" style="font-size:12px;padding:0 14px;min-height:38px;flex-shrink:0;">
                                    <i class="fas fa-boxes-stacked"></i> Gerenciar Estoque
                                </a>
                            </div>
                        </div>`;
                } else {
                    badge.style.display = 'none';
                    banner.style.display = 'none';
                }
            } catch (e) {}
        }

        setInterval(() => { atualizarPedidos(); atualizarStats(); verificarEstoque(); }, 30000);
        setTimeout(() => { atualizarPedidos(); atualizarStats(); verificarEstoque(); }, 1000);
    </script>
</body>
</html>


