<?php
session_start();
include "config.php";
include "helpers.php";
verificar_login('dono');

$id_admin = $_SESSION['id_usuario'];

// ── AUTO-MIGRAÇÃO: colunas de estoque e tabela de movimentações ─
$cols_prod = array_column($conn->query("SHOW COLUMNS FROM produtos")->fetch_all(MYSQLI_ASSOC), 'Field');
$estoque_cols = [
    'estoque_atual'  => "ALTER TABLE produtos ADD COLUMN estoque_atual INT NOT NULL DEFAULT 0 AFTER disponivel",
    'estoque_minimo' => "ALTER TABLE produtos ADD COLUMN estoque_minimo INT NOT NULL DEFAULT 5",
    'estoque_maximo' => "ALTER TABLE produtos ADD COLUMN estoque_maximo INT NOT NULL DEFAULT 999",
    'unidade'        => "ALTER TABLE produtos ADD COLUMN unidade VARCHAR(20) DEFAULT 'un'",
    'localizacao'    => "ALTER TABLE produtos ADD COLUMN localizacao VARCHAR(60) DEFAULT NULL",
];
foreach ($estoque_cols as $col => $sql) {
    if (!in_array($col, $cols_prod)) $conn->query($sql);
}

// Tabela de movimentações
$conn->query("CREATE TABLE IF NOT EXISTS movimentacoes_estoque (
    id                  INT PRIMARY KEY AUTO_INCREMENT,
    id_produto          INT NOT NULL,
    tipo                ENUM('entrada','saida','ajuste','transferencia_out','transferencia_in') NOT NULL,
    quantidade          INT NOT NULL,
    estoque_anterior    INT NOT NULL DEFAULT 0,
    estoque_novo        INT NOT NULL DEFAULT 0,
    motivo              VARCHAR(255) DEFAULT NULL,
    id_pedido           INT DEFAULT NULL,
    id_usuario          INT DEFAULT NULL,
    localizacao_origem  VARCHAR(60) DEFAULT NULL,
    localizacao_destino VARCHAR(60) DEFAULT NULL,
    criado_em           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_produto) REFERENCES produtos(id) ON DELETE CASCADE
) ENGINE=InnoDB");

// ============================================================
// LIMPEZA AUTOMÁTICA: remove movimentações com mais de 30 dias
// Executa uma vez por dia usando controle por sessão
// ============================================================
if (empty($_SESSION['estoque_limpeza_data']) || $_SESSION['estoque_limpeza_data'] !== date('Y-m-d')) {
    $removidos = $conn->query("
        DELETE FROM movimentacoes_estoque
        WHERE criado_em < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $_SESSION['estoque_limpeza_data'] = date('Y-m-d');

    // Log opcional
    @mkdir('logs', 0755, true);
    $qtd_removida = $conn->affected_rows;
    if ($qtd_removida > 0) {
        file_put_contents(
            'logs/estoque_limpeza.log',
            date('Y-m-d H:i:s') . " | $qtd_removida movimentação(ões) com +30 dias removida(s)\n",
            FILE_APPEND
        );
    }
}

// ============================================================
// AÇÕES DE MOVIMENTAÇÃO
// ============================================================

// --- ENTRADA (Check-in) ---
if (isset($_POST['acao_entrada'])) {
    $id_produto = (int)$_POST['id_produto'];
    $qtd        = max(1, (int)$_POST['quantidade']);
    $motivo     = sanitizar_texto($_POST['motivo'] ?? 'Entrada de estoque');

    $prod = $conn->query("SELECT estoque_atual FROM produtos WHERE id=$id_produto")->fetch_assoc();
    if ($prod) {
        $antes = (int)$prod['estoque_atual'];
        $depois = $antes + $qtd;
        $conn->query("UPDATE produtos SET estoque_atual=$depois WHERE id=$id_produto");
        if ($depois > 0) $conn->query("UPDATE produtos SET disponivel=1 WHERE id=$id_produto AND disponivel=0");
        $tipo_mov = 'entrada';
        $stmt = $conn->prepare("INSERT INTO movimentacoes_estoque (id_produto,tipo,quantidade,estoque_anterior,estoque_novo,motivo,id_usuario) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("isiissi", $id_produto, $tipo_mov, $qtd, $antes, $depois, $motivo, $id_admin);
        $stmt->execute();
        redirecionar('estoque.php', "✅ Entrada de $qtd unidade(s) registrada com sucesso!");
    }
}

// --- SAÍDA (Check-out manual) ---
if (isset($_POST['acao_saida'])) {
    $id_produto = (int)$_POST['id_produto'];
    $qtd        = max(1, (int)$_POST['quantidade']);
    $motivo     = sanitizar_texto($_POST['motivo'] ?? 'Saída de estoque');

    $prod = $conn->query("SELECT estoque_atual FROM produtos WHERE id=$id_produto")->fetch_assoc();
    if ($prod) {
        $antes = (int)$prod['estoque_atual'];
        $depois = max(0, $antes - $qtd);
        $conn->query("UPDATE produtos SET estoque_atual=$depois WHERE id=$id_produto");
        if ($depois === 0) $conn->query("UPDATE produtos SET disponivel=0 WHERE id=$id_produto");
        $tipo_mov = 'saida';
        $stmt = $conn->prepare("INSERT INTO movimentacoes_estoque (id_produto,tipo,quantidade,estoque_anterior,estoque_novo,motivo,id_usuario) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("isiissi", $id_produto, $tipo_mov, $qtd, $antes, $depois, $motivo, $id_admin);
        $stmt->execute();
        redirecionar('estoque.php', "✅ Saída de $qtd unidade(s) registrada com sucesso!");
    }
}

// --- AJUSTE (correção de inventário) ---
if (isset($_POST['acao_ajuste'])) {
    $id_produto  = (int)$_POST['id_produto'];
    $novo_estoque = max(0, (int)$_POST['novo_estoque']);
    $motivo      = sanitizar_texto($_POST['motivo'] ?? 'Ajuste de inventário');

    $prod = $conn->query("SELECT estoque_atual FROM produtos WHERE id=$id_produto")->fetch_assoc();
    if ($prod) {
        $antes = (int)$prod['estoque_atual'];
        $diff  = abs($novo_estoque - $antes);
        $conn->query("UPDATE produtos SET estoque_atual=$novo_estoque WHERE id=$id_produto");
        if ($novo_estoque === 0) $conn->query("UPDATE produtos SET disponivel=0 WHERE id=$id_produto");
        elseif ($antes === 0 && $novo_estoque > 0) $conn->query("UPDATE produtos SET disponivel=1 WHERE id=$id_produto");
        $tipo_mov = 'ajuste';
        $stmt = $conn->prepare("INSERT INTO movimentacoes_estoque (id_produto,tipo,quantidade,estoque_anterior,estoque_novo,motivo,id_usuario) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("isiissi", $id_produto, $tipo_mov, $diff, $antes, $novo_estoque, $motivo, $id_admin);
        $stmt->execute();
        redirecionar('estoque.php', "✅ Estoque ajustado para $novo_estoque unidade(s)!");
    }
}

// --- TRANSFERÊNCIA (entre localizações) ---
if (isset($_POST['acao_transferencia'])) {
    $id_produto  = (int)$_POST['id_produto'];
    $qtd         = max(1, (int)$_POST['quantidade']);
    $loc_origem  = sanitizar_texto($_POST['localizacao_origem']);
    $loc_destino = sanitizar_texto($_POST['localizacao_destino']);
    $motivo      = sanitizar_texto($_POST['motivo'] ?? "Transferência: $loc_origem → $loc_destino");

    $prod = $conn->query("SELECT estoque_atual FROM produtos WHERE id=$id_produto")->fetch_assoc();
    if ($prod) {
        $antes = (int)$prod['estoque_atual'];
        // Estoque total não muda, mas registramos para rastreabilidade
        $stmt = $conn->prepare("INSERT INTO movimentacoes_estoque (id_produto,tipo,quantidade,estoque_anterior,estoque_novo,motivo,id_usuario,localizacao_origem,localizacao_destino) VALUES (?,?,?,?,?,?,?,?,?)");
        $tipo_t = 'transferencia_out';
        $stmt->bind_param("isiississ", $id_produto, $tipo_t, $qtd, $antes, $antes, $motivo, $id_admin, $loc_origem, $loc_destino);
        $stmt->execute();
        // Atualizar localização no produto
        $conn->query("UPDATE produtos SET localizacao='$loc_destino' WHERE id=$id_produto");
        redirecionar('estoque.php', "✅ Transferência de $qtd unidade(s) registrada: $loc_origem → $loc_destino!");
    }
}

// --- ATUALIZAR LIMITES ---
if (isset($_POST['acao_limites'])) {
    $id_produto   = (int)$_POST['id_produto'];
    $est_min      = max(0, (int)$_POST['estoque_minimo']);
    $est_max      = max(1, (int)$_POST['estoque_maximo']);
    $unidade      = sanitizar_texto($_POST['unidade'] ?? 'un');
    $localizacao  = sanitizar_texto($_POST['localizacao'] ?? '');
    $conn->query("UPDATE produtos SET estoque_minimo=$est_min, estoque_maximo=$est_max, unidade='$unidade', localizacao='$localizacao' WHERE id=$id_produto");
    redirecionar('estoque.php', "✅ Configurações atualizadas!");
}

// ============================================================
// LEITURA DE DADOS
// ============================================================
$filtro_cat   = $_GET['categoria'] ?? '';
$filtro_alerta = $_GET['alerta']   ?? '';
$busca        = $_GET['busca']     ?? '';

$where = ["1=1"];
if ($filtro_cat)    $where[] = "p.categoria = '" . $conn->real_escape_string($filtro_cat) . "'";
if ($filtro_alerta === 'baixo')   $where[] = "p.estoque_atual > 0 AND p.estoque_atual <= p.estoque_minimo";
if ($filtro_alerta === 'zerado')  $where[] = "p.estoque_atual = 0";
if ($filtro_alerta === 'ok')      $where[] = "p.estoque_atual > p.estoque_minimo";
if ($busca) $where[] = "p.nome LIKE '%" . $conn->real_escape_string($busca) . "%'";

$where_sql = implode(" AND ", $where);

$produtos = $conn->query("
    SELECT p.*
    FROM produtos p
    WHERE $where_sql
    ORDER BY p.estoque_atual ASC, p.nome ASC
")->fetch_all(MYSQLI_ASSOC);

// Estatísticas
$stats_est = $conn->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN estoque_atual = 0 THEN 1 ELSE 0 END) as zerados,
        SUM(CASE WHEN estoque_atual > 0 AND estoque_atual <= estoque_minimo THEN 1 ELSE 0 END) as baixos,
        SUM(CASE WHEN estoque_atual > estoque_minimo THEN 1 ELSE 0 END) as normais,
        SUM(estoque_atual) as total_unidades
    FROM produtos
")->fetch_assoc();

// Últimas 50 movimentações
$filtro_mov_tipo = $_GET['mov_tipo'] ?? '';
$filtro_mov_prod = isset($_GET['mov_produto']) ? (int)$_GET['mov_produto'] : 0;
$mov_where = ["1=1"];
if ($filtro_mov_tipo) $mov_where[] = "m.tipo = '" . $conn->real_escape_string($filtro_mov_tipo) . "'";
if ($filtro_mov_prod) $mov_where[] = "m.id_produto = $filtro_mov_prod";
$mov_where_sql = implode(" AND ", $mov_where);

$movimentacoes = $conn->query("
    SELECT m.*, p.nome AS produto_nome, p.unidade, u.nome AS usuario_nome
    FROM movimentacoes_estoque m
    JOIN produtos p ON m.id_produto = p.id
    LEFT JOIN usuarios u ON m.id_usuario = u.id
    WHERE $mov_where_sql
    ORDER BY m.criado_em DESC
    LIMIT 60
")->fetch_all(MYSQLI_ASSOC);

$categorias = $conn->query("SELECT DISTINCT categoria FROM produtos WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria")->fetch_all(MYSQLI_ASSOC);
$msg = $_SESSION['sucesso'] ?? ''; unset($_SESSION['sucesso']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Controle de Estoque – FarmaVida</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* ---- STOCK STATUS BADGES ---- */
        .est-badge { display:inline-flex; align-items:center; gap:5px; padding:4px 12px; border-radius:var(--radius-full); font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; }
        .est-ok      { background:#e3fcef; color:#065f46; }
        .est-baixo   { background:#fef3c7; color:#92400e; }
        .est-zerado  { background:#fee2e2; color:#991b1b; }

        /* ---- STOCK TABLE ---- */
        .estoque-table { width:100%; border-collapse:collapse; }
        .estoque-table th { background:var(--bg); font-size:12px; font-weight:700; color:var(--gray); text-transform:uppercase; letter-spacing:.6px; padding:12px 14px; text-align:left; border-bottom:2px solid var(--light-gray); white-space:nowrap; }
        .estoque-table td { padding:14px; border-bottom:1px solid var(--light-gray); font-size:13px; vertical-align:middle; }
        .estoque-table tr:hover td { background:rgba(0,135,90,.03); }
        .estoque-table tr.row-zerado td { background:rgba(239,68,68,.03); }
        .estoque-table tr.row-baixo  td { background:rgba(245,158,11,.03); }

        /* ---- STOCK BAR ---- */
        .stock-bar-wrap { width:100px; }
        .stock-bar { height:6px; background:var(--light-gray); border-radius:4px; overflow:hidden; }
        .stock-bar-fill { height:100%; border-radius:4px; transition:.3s; }

        /* ---- MOV TYPE BADGES ---- */
        .mov-entrada        { background:#e3fcef; color:#065f46; }
        .mov-saida          { background:#fee2e2; color:#991b1b; }
        .mov-ajuste         { background:#e6f0ff; color:#1e40af; }
        .mov-transferencia_out,
        .mov-transferencia_in { background:#f5f3ff; color:#4c1d95; }

        /* ---- MODAL ---- */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(13,27,42,.55); backdrop-filter:blur(4px); z-index:2000; align-items:center; justify-content:center; padding:20px; }
        .modal-overlay.open { display:flex; animation:fadeIn .2s ease; }
        .modal-box { background:var(--white); border-radius:var(--radius-xl); padding:32px; width:100%; max-width:480px; box-shadow:var(--shadow-xl); position:relative; animation:scaleIn .25s ease; }
        .modal-box h3 { font-family:'Sora',sans-serif; font-size:20px; font-weight:800; color:var(--dark); margin-bottom:20px; display:flex; align-items:center; gap:10px; }
        .modal-close { position:absolute; top:18px; right:18px; background:var(--bg); border:none; border-radius:50%; width:34px; height:34px; font-size:16px; cursor:pointer; color:var(--gray); display:flex; align-items:center; justify-content:center; transition:var(--transition); }
        .modal-close:hover { background:var(--danger); color:white; }

        /* ---- TABS ---- */
        .mov-tabs { display:flex; gap:6px; margin-bottom:24px; flex-wrap:wrap; }
        .mov-tab { padding:9px 18px; border:2px solid var(--light-gray); background:var(--white); border-radius:var(--radius-full); font-size:13px; font-weight:600; cursor:pointer; transition:var(--transition); color:var(--gray); text-decoration:none; }
        .mov-tab:hover, .mov-tab.active { border-color:var(--primary); background:var(--primary); color:white; }

        /* ---- STATS MINI ---- */
        .est-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:16px; margin-bottom:28px; }
        .est-stat { background:var(--white); border-radius:var(--radius-lg); padding:20px; box-shadow:var(--shadow-sm); border:1px solid var(--light-gray); text-align:center; transition:var(--transition); cursor:pointer; text-decoration:none; }
        .est-stat:hover { box-shadow:var(--shadow-md); transform:translateY(-3px); }
        .est-stat-num { font-family:'Sora',sans-serif; font-size:36px; font-weight:800; line-height:1; margin-bottom:6px; }
        .est-stat-label { font-size:12px; font-weight:600; color:var(--gray); text-transform:uppercase; letter-spacing:.5px; }

        /* ---- ALERT BANNER ---- */
        .alert-estoque { background:linear-gradient(135deg,#fef3c7,#fde68a); border:1.5px solid #f59e0b; border-radius:var(--radius-md); padding:14px 18px; display:flex; align-items:center; gap:14px; margin-bottom:24px; }
        .alert-estoque i { font-size:22px; color:#d97706; flex-shrink:0; }

        @media(max-width:768px) {
            .estoque-table { font-size:12px; }
            .estoque-table th, .estoque-table td { padding:10px 8px; }
            .stock-bar-wrap { display:none; }
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <div class="header">
        <div class="header-container">
            <div class="logo" style="cursor:default;">
                <div class="logo-icon"><i class="fas fa-boxes-stacked"></i></div>
                Controle de Estoque
            </div>
            <div class="nav-buttons">
                <a href="painel_dono.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Painel</a>
                <a href="gerenciar_produtos.php" class="btn btn-secondary"><i class="fas fa-pills"></i> Produtos</a>
                <a href="logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </div>

    <div class="container">

        <?php if ($msg): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div>
        <?php endif; ?>

        <!-- ALERTA DE ESTOQUE CRÍTICO -->
        <?php if ($stats_est['zerados'] > 0 || $stats_est['baixos'] > 0): ?>
            <div class="alert-estoque">
                <i class="fas fa-triangle-exclamation"></i>
                <div style="flex:1;">
                    <strong style="color:#92400e;display:block;margin-bottom:3px;">Atenção: produtos com estoque crítico</strong>
                    <span style="font-size:13px;color:#78350f;">
                        <?php if ($stats_est['zerados'] > 0): ?>
                            <strong><?= $stats_est['zerados'] ?></strong> produto(s) sem estoque &nbsp;·&nbsp;
                        <?php endif; ?>
                        <?php if ($stats_est['baixos'] > 0): ?>
                            <strong><?= $stats_est['baixos'] ?></strong> produto(s) abaixo do mínimo
                        <?php endif; ?>
                    </span>
                </div>
                <a href="estoque.php?alerta=zerado" class="btn btn-warning" style="padding:8px 16px;font-size:13px;">Ver produtos</a>
            </div>
        <?php endif; ?>

        <!-- STATS -->
        <div class="est-stats">
            <a href="estoque.php" class="est-stat">
                <div class="est-stat-num" style="color:var(--dark);"><?= $stats_est['total'] ?></div>
                <div class="est-stat-label">Total de Produtos</div>
            </a>
            <a href="estoque.php?alerta=ok" class="est-stat">
                <div class="est-stat-num" style="color:var(--primary);"><?= $stats_est['normais'] ?></div>
                <div class="est-stat-label">🟢 Estoque Normal</div>
            </a>
            <a href="estoque.php?alerta=baixo" class="est-stat">
                <div class="est-stat-num" style="color:#d97706;"><?= $stats_est['baixos'] ?></div>
                <div class="est-stat-label">🟡 Estoque Baixo</div>
            </a>
            <a href="estoque.php?alerta=zerado" class="est-stat">
                <div class="est-stat-num" style="color:var(--danger);"><?= $stats_est['zerados'] ?></div>
                <div class="est-stat-label">🔴 Sem Estoque</div>
            </a>
            <div class="est-stat" style="cursor:default;">
                <div class="est-stat-num" style="color:var(--secondary);"><?= number_format($stats_est['total_unidades'],0,'.','.') ?></div>
                <div class="est-stat-label">Unidades em Estoque</div>
            </div>
        </div>

        <!-- FILTROS + AÇÕES -->
        <div class="card" style="margin-bottom:20px;padding:20px 24px;">
            <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
                <form method="GET" style="display:flex;gap:10px;flex:1;flex-wrap:wrap;">
                    <input type="text" name="busca" placeholder="🔍 Buscar produto..." value="<?= htmlspecialchars($busca) ?>" style="flex:1;min-width:180px;padding:10px 16px;border-radius:var(--radius-full);">
                    <select name="categoria" style="padding:10px 16px;border-radius:var(--radius-full);min-width:160px;">
                        <option value="">Todas as categorias</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?= htmlspecialchars($c['categoria']) ?>" <?= $filtro_cat===$c['categoria']?'selected':'' ?>><?= htmlspecialchars($c['categoria']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="alerta" style="padding:10px 16px;border-radius:var(--radius-full);min-width:150px;">
                        <option value="">Todos os status</option>
                        <option value="ok"     <?= $filtro_alerta==='ok'    ?'selected':'' ?>>🟢 Normal</option>
                        <option value="baixo"  <?= $filtro_alerta==='baixo' ?'selected':'' ?>>🟡 Estoque baixo</option>
                        <option value="zerado" <?= $filtro_alerta==='zerado'?'selected':'' ?>>🔴 Sem estoque</option>
                    </select>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                    <a href="estoque.php" class="btn btn-secondary"><i class="fas fa-sync"></i></a>
                </form>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button onclick="abrirModal('modal-entrada')" class="btn btn-success"><i class="fas fa-arrow-down"></i> Entrada</button>
                    <button onclick="abrirModal('modal-saida')"   class="btn btn-danger" style="background:linear-gradient(135deg,#ef4444,#dc2626);"><i class="fas fa-arrow-up"></i> Saída</button>
                    <button onclick="abrirModal('modal-ajuste')"  class="btn btn-info"><i class="fas fa-sliders"></i> Ajuste</button>
                    <button onclick="abrirModal('modal-transf')"  class="btn btn-warning"><i class="fas fa-arrows-left-right"></i> Transferir</button>
                </div>
            </div>
        </div>

        <!-- TABELA DE PRODUTOS -->
        <div class="card" style="padding:0;overflow:hidden;">
            <div style="padding:20px 24px 0;">
                <h2 style="margin-bottom:4px;"><i class="fas fa-table-list"></i> Produtos (<?= count($produtos) ?>)</h2>
                <p style="color:var(--gray);font-size:13px;margin-bottom:16px;">Clique em <strong>ações</strong> para movimentar o estoque de um produto específico.</p>
            </div>
            <div style="overflow-x:auto;">
                <table class="estoque-table">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Categoria</th>
                            <th style="text-align:center;">Estoque Atual</th>
                            <th style="text-align:center;">Mín.</th>
                            <th>Unid.</th>
                            <th>Localização</th>
                            <th>Status</th>
                            <th>Barra</th>
                            <th style="text-align:center;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($produtos as $p):
                        $s   = (int)$p['estoque_atual'];
                        $min = (int)$p['estoque_minimo'];
                        $max = max(1, (int)$p['estoque_maximo']);
                        $pct = min(100, round($s / $max * 100));
                        if ($s === 0)       { $cls='row-zerado'; $badge='est-zerado'; $lbl='Sem estoque';   $cor='#ef4444'; }
                        elseif ($s <= $min) { $cls='row-baixo';  $badge='est-baixo';  $lbl='Estoque baixo'; $cor='#f59e0b'; }
                        else                { $cls=''; $badge='est-ok'; $lbl='Normal'; $cor='#10b981'; }
                    ?>
                        <tr class="<?= $cls ?>">
                            <td>
                                <strong style="display:block;color:var(--dark);font-size:13px;"><?= htmlspecialchars($p['nome']) ?></strong>
                            </td>
                            <td><span style="font-size:11px;color:var(--gray);"><?= htmlspecialchars($p['categoria']) ?></span></td>
                            <td style="text-align:center;">
                                <span style="font-family:'Sora',sans-serif;font-size:22px;font-weight:800;color:<?= $cor ?>;"><?= $s ?></span>
                            </td>
                            <td style="text-align:center;color:var(--gray);font-size:13px;"><?= $min ?></td>
                            <td style="color:var(--gray);font-size:12px;"><?= htmlspecialchars($p['unidade'] ?? 'un') ?></td>
                            <td style="color:var(--gray);font-size:12px;"><?= htmlspecialchars($p['localizacao'] ?? '—') ?></td>
                            <td><span class="est-badge <?= $badge ?>"><?= $lbl ?></span></td>
                            <td>
                                <div class="stock-bar-wrap">
                                    <div class="stock-bar">
                                        <div class="stock-bar-fill" style="width:<?= $pct ?>%;background:<?= $cor ?>;"></div>
                                    </div>
                                    <span style="font-size:10px;color:var(--gray);"><?= $pct ?>%</span>
                                </div>
                            </td>
                            <td style="text-align:center;white-space:nowrap;">
                                <button onclick="abrirModalProduto('modal-entrada', <?= $p['id'] ?>, '<?= addslashes($p['nome']) ?>')" class="btn btn-success" style="padding:6px 10px;font-size:12px;" title="Entrada"><i class="fas fa-arrow-down"></i></button>
                                <button onclick="abrirModalProduto('modal-saida',   <?= $p['id'] ?>, '<?= addslashes($p['nome']) ?>')" class="btn btn-danger"  style="padding:6px 10px;font-size:12px;background:linear-gradient(135deg,#ef4444,#dc2626);" title="Saída"><i class="fas fa-arrow-up"></i></button>
                                <button onclick="abrirModalAjuste(<?= $p['id'] ?>, '<?= addslashes($p['nome']) ?>', <?= $s ?>, <?= $min ?>, <?= $p['estoque_maximo'] ?>, '<?= addslashes($p['unidade']??'un') ?>', '<?= addslashes($p['localizacao']??'') ?>')" class="btn btn-info" style="padding:6px 10px;font-size:12px;" title="Ajustar/Config."><i class="fas fa-sliders"></i></button>
                                <a href="estoque.php?mov_produto=<?= $p['id'] ?>#historico" class="btn btn-secondary" style="padding:6px 10px;font-size:12px;" title="Ver histórico"><i class="fas fa-clock-rotate-left"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($produtos)): ?>
                        <tr><td colspan="9" style="text-align:center;padding:48px;color:var(--gray);">Nenhum produto encontrado</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- HISTÓRICO DE MOVIMENTAÇÕES -->
        <div class="card" id="historico">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:4px;">
                <h2 style="margin:0;"><i class="fas fa-clock-rotate-left"></i> Histórico de Movimentações</h2>
                <span style="display:inline-flex;align-items:center;gap:7px;padding:5px 14px;background:#f0f7ff;border:1px solid #bfdbfe;border-radius:var(--radius-full);font-size:12px;font-weight:600;color:#1e40af;">
                    <i class="fas fa-calendar-xmark"></i> Registros mantidos por 30 dias
                </span>
            </div>
            <p style="color:var(--gray);font-size:13px;margin-bottom:20px;">Movimentações com mais de 30 dias são removidas automaticamente.</p>
            <div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:center;">
                <div class="mov-tabs">
                    <a href="estoque.php#historico" class="mov-tab <?= !$filtro_mov_tipo?'active':'' ?>">Todos</a>
                    <a href="estoque.php?mov_tipo=entrada#historico"          class="mov-tab <?= $filtro_mov_tipo==='entrada'?'active':'' ?>">Entradas</a>
                    <a href="estoque.php?mov_tipo=saida#historico"            class="mov-tab <?= $filtro_mov_tipo==='saida'?'active':'' ?>">Saídas</a>
                    <a href="estoque.php?mov_tipo=ajuste#historico"           class="mov-tab <?= $filtro_mov_tipo==='ajuste'?'active':'' ?>">Ajustes</a>
                    <a href="estoque.php?mov_tipo=transferencia_out#historico" class="mov-tab <?= $filtro_mov_tipo==='transferencia_out'?'active':'' ?>">Transferências</a>
                </div>
                <?php if ($filtro_mov_prod): ?>
                    <?php $nprod = $conn->query("SELECT nome FROM produtos WHERE id=$filtro_mov_prod")->fetch_assoc(); ?>
                    <span style="font-size:13px;color:var(--gray);display:flex;align-items:center;gap:8px;">
                        Filtrando: <strong><?= htmlspecialchars($nprod['nome'] ?? '—') ?></strong>
                        <a href="estoque.php#historico" style="color:var(--danger);"><i class="fas fa-xmark"></i></a>
                    </span>
                <?php endif; ?>
            </div>

            <?php if (empty($movimentacoes)): ?>
                <div class="empty" style="padding:40px;"><i class="fas fa-inbox"></i><h2>Nenhuma movimentação</h2></div>
            <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="estoque-table">
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Tipo</th>
                            <th>Produto</th>
                            <th style="text-align:center;">Qtd</th>
                            <th style="text-align:center;">Antes</th>
                            <th style="text-align:center;">Depois</th>
                            <th>Motivo</th>
                            <th>Usuário</th>
                            <th>Pedido</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($movimentacoes as $m):
                        $tipo_labels = ['entrada'=>'Entrada','saida'=>'Saída','ajuste'=>'Ajuste','transferencia_out'=>'Transferência','transferencia_in'=>'Transf. Chegada'];
                        $tipo_icons  = ['entrada'=>'arrow-down','saida'=>'arrow-up','ajuste'=>'sliders','transferencia_out'=>'arrows-left-right','transferencia_in'=>'arrows-left-right'];
                    ?>
                        <tr>
                            <td style="white-space:nowrap;color:var(--gray);font-size:12px;"><?= date('d/m/Y H:i', strtotime($m['criado_em'])) ?></td>
                            <td>
                                <span class="est-badge mov-<?= $m['tipo'] ?>">
                                    <i class="fas fa-<?= $tipo_icons[$m['tipo']] ?? 'circle' ?>"></i>
                                    <?= $tipo_labels[$m['tipo']] ?? $m['tipo'] ?>
                                </span>
                            </td>
                            <td style="font-size:13px;"><strong><?= htmlspecialchars($m['produto_nome']) ?></strong></td>
                            <td style="text-align:center;font-weight:800;font-size:16px;color:var(--dark);"><?= $m['quantidade'] ?> <span style="font-size:11px;color:var(--gray);"><?= htmlspecialchars($m['unidade']) ?></span></td>
                            <td style="text-align:center;color:var(--gray);"><?= $m['estoque_anterior'] ?></td>
                            <td style="text-align:center;font-weight:700;color:<?= $m['estoque_novo'] > $m['estoque_anterior'] ? 'var(--primary)' : ($m['estoque_novo'] < $m['estoque_anterior'] ? 'var(--danger)' : 'var(--gray)') ?>;"><?= $m['estoque_novo'] ?></td>
                            <td style="font-size:12px;color:var(--gray);max-width:200px;">
                                <?= htmlspecialchars($m['motivo'] ?? '—') ?>
                                <?php if ($m['localizacao_origem']): ?>
                                    <br><span style="color:var(--secondary);"><?= htmlspecialchars($m['localizacao_origem']) ?> → <?= htmlspecialchars($m['localizacao_destino']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:12px;color:var(--gray);"><?= htmlspecialchars($m['usuario_nome'] ?? 'Sistema') ?></td>
                            <td style="font-size:12px;">
                                <?php if ($m['id_pedido']): ?>
                                    <a href="imprimir_pedido.php?id=<?= $m['id_pedido'] ?>" target="_blank" style="color:var(--primary);font-weight:700;">#<?= $m['id_pedido'] ?></a>
                                <?php else: ?>
                                    <span style="color:var(--light-gray);">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div><!-- /container -->

    <!-- ============================
         MODAIS
    ============================= -->

    <!-- MODAL ENTRADA -->
    <div class="modal-overlay" id="modal-entrada">
        <div class="modal-box">
            <button class="modal-close" onclick="fecharModal('modal-entrada')"><i class="fas fa-xmark"></i></button>
            <h3 style="color:var(--primary);"><i class="fas fa-arrow-down"></i> Registrar Entrada</h3>
            <form method="POST">
                <div class="form-group">
                    <label><i class="fas fa-pills"></i> Produto *</label>
                    <select name="id_produto" id="sel-entrada" required>
                        <option value="">Selecione o produto...</option>
                        <?php foreach ($conn->query("SELECT id,nome FROM produtos ORDER BY nome") as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-hashtag"></i> Quantidade *</label>
                    <input type="number" name="quantidade" min="1" value="1" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-comment"></i> Motivo / Nota fiscal</label>
                    <input type="text" name="motivo" placeholder="Ex: Compra NF 00123 – Fornecedor X">
                </div>
                <button type="submit" name="acao_entrada" class="btn btn-success btn-lg" style="width:100%;justify-content:center;">
                    <i class="fas fa-check"></i> Confirmar Entrada
                </button>
            </form>
        </div>
    </div>

    <!-- MODAL SAÍDA -->
    <div class="modal-overlay" id="modal-saida">
        <div class="modal-box">
            <button class="modal-close" onclick="fecharModal('modal-saida')"><i class="fas fa-xmark"></i></button>
            <h3 style="color:var(--danger);"><i class="fas fa-arrow-up"></i> Registrar Saída</h3>
            <form method="POST">
                <div class="form-group">
                    <label><i class="fas fa-pills"></i> Produto *</label>
                    <select name="id_produto" id="sel-saida" required>
                        <option value="">Selecione o produto...</option>
                        <?php foreach ($conn->query("SELECT id,nome,estoque_atual FROM produtos ORDER BY nome") as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?> (<?= $p['estoque_atual'] ?> em estoque)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-hashtag"></i> Quantidade *</label>
                    <input type="number" name="quantidade" min="1" value="1" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-comment"></i> Motivo</label>
                    <input type="text" name="motivo" placeholder="Ex: Perda, vencimento, uso interno...">
                </div>
                <button type="submit" name="acao_saida" class="btn btn-danger btn-lg" style="width:100%;justify-content:center;">
                    <i class="fas fa-check"></i> Confirmar Saída
                </button>
            </form>
        </div>
    </div>

    <!-- MODAL AJUSTE / CONFIGURAÇÕES -->
    <div class="modal-overlay" id="modal-ajuste">
        <div class="modal-box">
            <button class="modal-close" onclick="fecharModal('modal-ajuste')"><i class="fas fa-xmark"></i></button>
            <h3 style="color:var(--secondary);"><i class="fas fa-sliders"></i> Ajuste / Configurações</h3>
            <p id="ajuste-produto-nome" style="color:var(--gray);font-size:13px;margin:-10px 0 16px;"></p>
            <form method="POST">
                <input type="hidden" name="id_produto" id="ajuste-id">
                <!-- TABS dentro do modal -->
                <div style="display:flex;gap:6px;margin-bottom:20px;">
                    <button type="button" onclick="ajusteTab('inventario')" id="tab-inv" class="btn btn-primary" style="padding:8px 16px;font-size:13px;">📦 Inventário</button>
                    <button type="button" onclick="ajusteTab('config')"     id="tab-cfg" class="btn btn-secondary" style="padding:8px 16px;font-size:13px;">⚙️ Configurações</button>
                </div>

                <!-- Aba inventário -->
                <div id="tab-inventario">
                    <div class="form-group">
                        <label><i class="fas fa-hashtag"></i> Novo Estoque (quantidade real) *</label>
                        <input type="number" name="novo_estoque" id="ajuste-novo-estoque" min="0" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-comment"></i> Motivo</label>
                        <input type="text" name="motivo" placeholder="Ex: Contagem física, inventário mensal...">
                    </div>
                    <button type="submit" name="acao_ajuste" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;">
                        <i class="fas fa-check"></i> Salvar Ajuste
                    </button>
                </div>

                <!-- Aba configurações -->
                <div id="tab-config" style="display:none;">
                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-arrow-down-1-9"></i> Estoque Mínimo</label>
                            <input type="number" name="estoque_minimo" id="cfg-min" min="0" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-arrow-up-9-1"></i> Estoque Máximo</label>
                            <input type="number" name="estoque_maximo" id="cfg-max" min="1" required>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-ruler"></i> Unidade</label>
                            <select name="unidade" id="cfg-unidade">
                                <option value="un">Unidade (un)</option>
                                <option value="cx">Caixa (cx)</option>
                                <option value="fr">Frasco (fr)</option>
                                <option value="bl">Blister (bl)</option>
                                <option value="cp">Comprimido (cp)</option>
                                <option value="ml">Mililitro (ml)</option>
                                <option value="g">Gramas (g)</option>
                                <option value="kg">Kilograma (kg)</option>
                                <option value="par">Par</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-location-dot"></i> Localização</label>
                            <input type="text" name="localizacao" id="cfg-loc" placeholder="Ex: Prateleira A3, Geladeira...">
                        </div>
                    </div>
                    <button type="submit" name="acao_limites" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;">
                        <i class="fas fa-save"></i> Salvar Configurações
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL TRANSFERÊNCIA -->
    <div class="modal-overlay" id="modal-transf">
        <div class="modal-box">
            <button class="modal-close" onclick="fecharModal('modal-transf')"><i class="fas fa-xmark"></i></button>
            <h3 style="color:#7c3aed;"><i class="fas fa-arrows-left-right"></i> Transferência</h3>
            <form method="POST">
                <div class="form-group">
                    <label><i class="fas fa-pills"></i> Produto *</label>
                    <select name="id_produto" id="sel-transf" required>
                        <option value="">Selecione o produto...</option>
                        <?php foreach ($conn->query("SELECT id,nome,localizacao FROM produtos ORDER BY nome") as $p): ?>
                            <option value="<?= $p['id'] ?>" data-loc="<?= htmlspecialchars($p['localizacao'] ?? '') ?>"><?= htmlspecialchars($p['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-hashtag"></i> Quantidade *</label>
                    <input type="number" name="quantidade" min="1" value="1" required>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-location-dot"></i> Localização Origem</label>
                        <input type="text" name="localizacao_origem" id="transf-origem" placeholder="Ex: Depósito" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-location-crosshairs"></i> Localização Destino</label>
                        <input type="text" name="localizacao_destino" placeholder="Ex: Prateleira B2" required>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-comment"></i> Observação</label>
                    <input type="text" name="motivo" placeholder="Motivo da transferência...">
                </div>
                <button type="submit" name="acao_transferencia" class="btn btn-lg" style="width:100%;justify-content:center;background:linear-gradient(135deg,#7c3aed,#4c1d95);color:white;">
                    <i class="fas fa-check"></i> Registrar Transferência
                </button>
            </form>
        </div>
    </div>

    <script>
        /* ---- MODAL ---- */
        function abrirModal(id) {
            document.getElementById(id).classList.add('open');
            document.body.style.overflow = 'hidden';
        }
        function fecharModal(id) {
            document.getElementById(id).classList.remove('open');
            document.body.style.overflow = '';
        }
        // Fechar ao clicar fora
        document.querySelectorAll('.modal-overlay').forEach(m => {
            m.addEventListener('click', e => { if (e.target === m) fecharModal(m.id); });
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.open').forEach(m => fecharModal(m.id));
        });

        /* ---- PRÉ-SELECIONAR PRODUTO ---- */
        function abrirModalProduto(modalId, prodId, prodNome) {
            abrirModal(modalId);
            const sel = { 'modal-entrada': 'sel-entrada', 'modal-saida': 'sel-saida' }[modalId];
            if (sel) {
                const el = document.getElementById(sel);
                if (el) el.value = prodId;
            }
        }

        function abrirModalAjuste(id, nome, estoqueAtual, min, max, unidade, loc) {
            abrirModal('modal-ajuste');
            document.getElementById('ajuste-id').value           = id;
            document.getElementById('ajuste-produto-nome').textContent = nome;
            document.getElementById('ajuste-novo-estoque').value  = estoqueAtual;
            document.getElementById('cfg-min').value              = min;
            document.getElementById('cfg-max').value              = max;
            document.getElementById('cfg-loc').value              = loc;
            const selUn = document.getElementById('cfg-unidade');
            if (selUn) [...selUn.options].forEach(o => o.selected = o.value === unidade);
            ajusteTab('inventario');
        }

        function ajusteTab(tab) {
            document.getElementById('tab-inventario').style.display = tab === 'inventario' ? 'block' : 'none';
            document.getElementById('tab-config').style.display      = tab === 'config'     ? 'block' : 'none';
            document.getElementById('tab-inv').className = 'btn ' + (tab === 'inventario' ? 'btn-primary' : 'btn-secondary') + ' ' + document.getElementById('tab-inv').className.split(' btn-')[2] ?? '';
            document.getElementById('tab-inv').className = tab === 'inventario' ? 'btn btn-primary' : 'btn btn-secondary';
            document.getElementById('tab-inv').style.padding = '8px 16px';
            document.getElementById('tab-inv').style.fontSize = '13px';
            document.getElementById('tab-cfg').className = tab === 'config' ? 'btn btn-primary' : 'btn btn-secondary';
            document.getElementById('tab-cfg').style.padding = '8px 16px';
            document.getElementById('tab-cfg').style.fontSize = '13px';
        }

        /* ---- PREENCHER ORIGEM NA TRANSFERÊNCIA ---- */
        document.getElementById('sel-transf')?.addEventListener('change', function() {
            const sel = this.options[this.selectedIndex];
            const loc = sel.getAttribute('data-loc') || '';
            document.getElementById('transf-origem').value = loc;
        });
    </script>
</body>
</html>