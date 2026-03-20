<?php
session_start();
include "config.php";
include "helpers.php";
verificar_login('dono');

// ── PERÍODO ─────────────────────────────────────────────────
$periodo  = $_GET['periodo'] ?? '30';   // dias
$data_ini = date('Y-m-d', strtotime("-{$periodo} days"));
$data_fim = date('Y-m-d');

if (!empty($_GET['data_ini']) && !empty($_GET['data_fim'])) {
    $data_ini = $_GET['data_ini'];
    $data_fim = $_GET['data_fim'];
}

// ── KPIs ─────────────────────────────────────────────────────
$kpis = $conn->query("
    SELECT
        COUNT(*)                                              AS total_pedidos,
        COALESCE(SUM(total), 0)                              AS faturamento,
        COALESCE(AVG(total), 0)                              AS ticket_medio,
        SUM(CASE WHEN status = 'cancelado' THEN 1 ELSE 0 END) AS cancelados,
        SUM(CASE WHEN tipo_retirada = 'delivery' THEN 1 ELSE 0 END) AS deliveries,
        COUNT(DISTINCT id_cliente)                           AS clientes_unicos
    FROM pedidos
    WHERE DATE(criado_em) BETWEEN '$data_ini' AND '$data_fim'
")->fetch_assoc();

// ── FATURAMENTO POR DIA ───────────────────────────────────────
$fat_dia = $conn->query("
    SELECT DATE(criado_em) AS dia, COUNT(*) AS pedidos, SUM(total) AS total
    FROM pedidos
    WHERE DATE(criado_em) BETWEEN '$data_ini' AND '$data_fim'
      AND status != 'cancelado'
    GROUP BY DATE(criado_em)
    ORDER BY dia ASC
")->fetch_all(MYSQLI_ASSOC);

// ── TOP 10 PRODUTOS ───────────────────────────────────────────
$top_produtos = $conn->query("
    SELECT pr.nome, pr.categoria,
           SUM(pi.quantidade)                          AS qtd_vendida,
           SUM(pi.quantidade * pi.preco_unitario)      AS receita
    FROM pedido_itens pi
    JOIN produtos pr ON pi.id_produto = pr.id
    JOIN pedidos p   ON pi.id_pedido  = p.id
    WHERE DATE(p.criado_em) BETWEEN '$data_ini' AND '$data_fim'
      AND p.status != 'cancelado'
    GROUP BY pi.id_produto
    ORDER BY receita DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// ── VENDAS POR CATEGORIA ──────────────────────────────────────
$por_categoria = $conn->query("
    SELECT pr.categoria,
           SUM(pi.quantidade * pi.preco_unitario) AS receita,
           SUM(pi.quantidade)                     AS qtd
    FROM pedido_itens pi
    JOIN produtos pr ON pi.id_produto = pr.id
    JOIN pedidos p   ON pi.id_pedido  = p.id
    WHERE DATE(p.criado_em) BETWEEN '$data_ini' AND '$data_fim'
      AND p.status != 'cancelado'
    GROUP BY pr.categoria
    ORDER BY receita DESC
")->fetch_all(MYSQLI_ASSOC);

// ── STATUS DOS PEDIDOS ────────────────────────────────────────
$por_status = $conn->query("
    SELECT status, COUNT(*) AS qtd
    FROM pedidos
    WHERE DATE(criado_em) BETWEEN '$data_ini' AND '$data_fim'
    GROUP BY status
")->fetch_all(MYSQLI_ASSOC);

// ── FORMAS DE ENTREGA ─────────────────────────────────────────
$por_entrega = $conn->query("
    SELECT tipo_retirada, COUNT(*) AS qtd, SUM(total) AS total
    FROM pedidos
    WHERE DATE(criado_em) BETWEEN '$data_ini' AND '$data_fim'
      AND status != 'cancelado'
    GROUP BY tipo_retirada
")->fetch_all(MYSQLI_ASSOC);

// ── TOP CLIENTES ──────────────────────────────────────────────
$top_clientes = $conn->query("
    SELECT u.nome, u.email,
           COUNT(p.id)  AS pedidos,
           SUM(p.total) AS gasto
    FROM pedidos p
    JOIN usuarios u ON p.id_cliente = u.id
    WHERE DATE(p.criado_em) BETWEEN '$data_ini' AND '$data_fim'
      AND p.status != 'cancelado'
    GROUP BY p.id_cliente
    ORDER BY gasto DESC
    LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

// ── HORÁRIO DE PICO ───────────────────────────────────────────
$por_hora = $conn->query("
    SELECT HOUR(criado_em) AS hora, COUNT(*) AS pedidos
    FROM pedidos
    WHERE DATE(criado_em) BETWEEN '$data_ini' AND '$data_fim'
      AND status != 'cancelado'
    GROUP BY HOUR(criado_em)
    ORDER BY hora
")->fetch_all(MYSQLI_ASSOC);

// Helpers JS
function json_col(array $rows, string $col): string {
    return json_encode(array_column($rows, $col));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios de Vendas – FarmaVida</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
    <style>
        /* ═══════════════════════════════════════════
           KEYFRAMES
        ═══════════════════════════════════════════ */
        @keyframes fadeSlideDown {
            from { opacity:0; transform:translateY(-28px); }
            to   { opacity:1; transform:translateY(0); }
        }
        @keyframes fadeSlideUp {
            from { opacity:0; transform:translateY(32px); }
            to   { opacity:1; transform:translateY(0); }
        }
        @keyframes fadeSlideLeft {
            from { opacity:0; transform:translateX(-32px); }
            to   { opacity:1; transform:translateX(0); }
        }
        @keyframes fadeSlideRight {
            from { opacity:0; transform:translateX(32px); }
            to   { opacity:1; transform:translateX(0); }
        }
        @keyframes scalePopIn {
            0%   { opacity:0; transform:scale(.7); }
            70%  { transform:scale(1.06); }
            100% { opacity:1; transform:scale(1); }
        }
        @keyframes shimmer {
            0%   { background-position:-600px 0; }
            100% { background-position:600px 0; }
        }
        @keyframes iconFloat {
            0%,100% { transform:translateY(0) rotate(0deg); }
            25%     { transform:translateY(-5px) rotate(-4deg); }
            75%     { transform:translateY(-3px) rotate(3deg); }
        }
        @keyframes pulseGlow {
            0%,100% { box-shadow:0 0 0 0 rgba(0,135,90,.4); }
            50%     { box-shadow:0 0 0 10px rgba(0,135,90,0); }
        }
        @keyframes barGrow {
            from { transform:scaleY(0); transform-origin:bottom; }
            to   { transform:scaleY(1); transform-origin:bottom; }
        }
        @keyframes rowSlideIn {
            from { opacity:0; transform:translateX(-16px); }
            to   { opacity:1; transform:translateX(0); }
        }
        @keyframes headlineReveal {
            from { opacity:0; letter-spacing:6px; }
            to   { opacity:1; letter-spacing:normal; }
        }
        @keyframes borderGrow {
            from { width:0; }
            to   { width:100%; }
        }
        @keyframes spinOnce {
            from { transform:rotate(0deg); }
            to   { transform:rotate(360deg); }
        }
        @keyframes countUp {
            from { opacity:0; transform:translateY(10px); }
            to   { opacity:1; transform:translateY(0); }
        }
        @keyframes ripple {
            0%   { transform:scale(0); opacity:.4; }
            100% { transform:scale(2.5); opacity:0; }
        }

        /* ═══════════════════════════════════════════
           HEADER ANIMATION
        ═══════════════════════════════════════════ */
        .header { animation:fadeSlideDown .5s cubic-bezier(.22,1,.36,1) both; }
        .logo   { animation:fadeSlideLeft .6s .1s cubic-bezier(.22,1,.36,1) both; }
        .nav-buttons { animation:fadeSlideRight .6s .15s cubic-bezier(.22,1,.36,1) both; }
        .logo .logo-icon { animation:spinOnce .7s .2s ease both; }

        /* ═══════════════════════════════════════════
           PERIODO BAR
        ═══════════════════════════════════════════ */
        .periodo-bar {
            background:var(--white); border-radius:var(--radius-lg); padding:16px 20px;
            box-shadow:var(--shadow-sm); margin-bottom:24px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;
            border:1px solid var(--light-gray);
            animation:fadeSlideDown .55s .2s cubic-bezier(.22,1,.36,1) both;
            position:relative; overflow:hidden;
        }
        .periodo-bar::after {
            content:''; position:absolute; bottom:0; left:0; height:2px;
            background:var(--gradient-main);
            animation:borderGrow .8s .8s ease both;
        }
        .periodo-btn {
            padding:8px 18px; border:2px solid var(--light-gray); border-radius:var(--radius-full);
            font-size:13px; font-weight:600; color:var(--gray); text-decoration:none;
            transition:all .25s cubic-bezier(.4,0,.2,1);
            position:relative; overflow:hidden;
        }
        .periodo-btn::before {
            content:''; position:absolute; inset:50%; border-radius:50%;
            background:rgba(255,255,255,.3); transform:translate(-50%,-50%) scale(0);
            transition:transform .5s ease;
        }
        .periodo-btn:hover::before { transform:translate(-50%,-50%) scale(3); }
        .periodo-btn:hover, .periodo-btn.active {
            border-color:var(--primary); background:var(--primary); color:white;
            transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,135,90,.3);
        }

        /* ═══════════════════════════════════════════
           KPI GRID & CARDS
        ═══════════════════════════════════════════ */
        .kpi-grid {
            display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
            gap:16px; margin-bottom:28px;
        }
        .kpi-card {
            background:var(--white); border-radius:var(--radius-lg); padding:22px 20px;
            box-shadow:var(--shadow-md); border:1px solid var(--light-gray);
            display:flex; align-items:center; gap:16px;
            opacity:0;                          /* hidden until JS fires */
            transition:transform .3s cubic-bezier(.4,0,.2,1), box-shadow .3s ease;
            position:relative; overflow:hidden; cursor:default;
        }
        .kpi-card.visible { animation:scalePopIn .55s cubic-bezier(.22,1,.36,1) forwards; }
        .kpi-card:hover {
            box-shadow:var(--shadow-lg), 0 0 0 2px var(--primary-light);
            transform:translateY(-5px) scale(1.02);
        }
        /* shimmer sweep on hover */
        .kpi-card::before {
            content:''; position:absolute; top:0; left:-100%; width:60%; height:100%;
            background:linear-gradient(90deg,transparent,rgba(255,255,255,.6),transparent);
            transition:none;
        }
        .kpi-card:hover::before { left:150%; transition:left .55s ease; }

        .kpi-icon {
            width:48px; height:48px; border-radius:14px;
            display:flex; align-items:center; justify-content:center;
            font-size:20px; flex-shrink:0;
            transition:transform .3s cubic-bezier(.4,0,.2,1);
        }
        .kpi-card:hover .kpi-icon { animation:iconFloat .8s ease; }
        .kpi-val {
            font-family:'Sora',sans-serif; font-size:24px; font-weight:800;
            color:var(--dark); line-height:1; margin-bottom:4px;
        }
        .kpi-label {
            font-size:12px; color:var(--gray); font-weight:600;
            text-transform:uppercase; letter-spacing:.5px;
        }

        /* ═══════════════════════════════════════════
           CHART CARDS
        ═══════════════════════════════════════════ */
        .charts-grid   { display:grid; grid-template-columns:2fr 1fr; gap:20px; margin-bottom:20px; }
        .charts-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:20px; margin-bottom:20px; }

        .chart-card {
            background:var(--white); border-radius:var(--radius-lg); padding:24px;
            box-shadow:var(--shadow-md); border:1px solid var(--light-gray);
            transition:transform .3s ease, box-shadow .3s ease;
        }
        .chart-card:hover {
            box-shadow:var(--shadow-xl);
            transform:translateY(-4px);
        }
        .chart-card h3 {
            font-family:'Sora',sans-serif; font-size:16px; font-weight:700;
            color:var(--dark); margin-bottom:18px;
            display:flex; align-items:center; gap:8px;
        }
        .chart-card h3 i { color:var(--primary); transition:transform .3s ease; }
        .chart-card:hover h3 i { animation:spinOnce .5s ease; }

        /* ═══════════════════════════════════════════
           TOP TABLE ROWS
        ═══════════════════════════════════════════ */
        .top-table { width:100%; border-collapse:collapse; }
        .top-table th {
            font-size:11px; font-weight:700; color:var(--gray);
            text-transform:uppercase; letter-spacing:.5px;
            padding:8px 10px; border-bottom:2px solid var(--light-gray); text-align:left;
        }
        .top-table td {
            padding:10px; border-bottom:1px solid var(--light-gray); font-size:13px;
            opacity:0;
        }
        .top-table tbody tr.row-visible td {
            animation:rowSlideIn .4s cubic-bezier(.22,1,.36,1) forwards;
        }
        .top-table tr:last-child td { border-bottom:none; }
        .top-table tr { transition:background .2s ease; }
        .top-table tr:hover td { background:rgba(0,135,90,.05); }

        /* ═══════════════════════════════════════════
           RANK BADGES
        ═══════════════════════════════════════════ */
        .rank-num {
            width:28px; height:28px; border-radius:50%; background:var(--bg);
            font-weight:800; font-size:12px; color:var(--gray);
            display:flex; align-items:center; justify-content:center;
            transition:transform .2s ease;
        }
        .top-table tr:hover .rank-num { transform:scale(1.2); }
        .rank-num.gold   { background:#fef3c7; color:#d97706; box-shadow:0 0 0 2px #f59e0b44; }
        .rank-num.silver { background:#f1f5f9; color:#64748b; box-shadow:0 0 0 2px #94a3b844; }
        .rank-num.bronze { background:#fef2ec; color:#c2410c; box-shadow:0 0 0 2px #fb923c44; }

        /* ═══════════════════════════════════════════
           EXPORT BUTTON
        ═══════════════════════════════════════════ */
        .export-btn {
            padding:9px 18px; border:2px solid var(--primary); border-radius:var(--radius-full);
            color:var(--primary); background:transparent; font-size:13px; font-weight:600;
            cursor:pointer; text-decoration:none;
            display:inline-flex; align-items:center; gap:7px;
            transition:all .25s cubic-bezier(.4,0,.2,1);
            position:relative; overflow:hidden;
        }
        .export-btn:hover {
            background:var(--primary); color:white;
            transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,135,90,.35);
        }
        .export-btn:hover i { animation:spinOnce .4s ease; }

        /* ═══════════════════════════════════════════
           SECTION TITLE REVEAL (container)
        ═══════════════════════════════════════════ */
        .container { position:relative; }

        /* ═══════════════════════════════════════════
           RESPONSIVE
        ═══════════════════════════════════════════ */
        @media(max-width:900px) {
            .charts-grid, .charts-grid-3 { grid-template-columns:1fr; }
        }
        @media print {
            .periodo-bar, .nav-buttons, .header .btn, .export-btn { display:none !important; }
            .chart-card, .kpi-card { break-inside:avoid; box-shadow:none; border:1px solid #ddd; opacity:1 !important; transform:none !important; }
            .top-table td { opacity:1 !important; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-container">
            <div class="logo" style="cursor:default;">
                <div class="logo-icon"><i class="fas fa-chart-line"></i></div>
                Relatórios de Vendas
            </div>
            <div class="nav-buttons">
                <button onclick="window.print()" class="export-btn"><i class="fas fa-print"></i> Imprimir</button>
                <a href="painel_dono.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Painel</a>
            </div>
        </div>
    </div>

    <div class="container">

        <!-- PERÍODO -->
        <div class="periodo-bar">
            <span style="font-size:13px;font-weight:700;color:var(--dark);margin-right:4px;"><i class="fas fa-calendar-alt" style="color:var(--primary);"></i> Período:</span>
            <a href="?periodo=7"  class="periodo-btn <?= $periodo=='7'  && empty($_GET['data_ini'])?'active':'' ?>">7 dias</a>
            <a href="?periodo=15" class="periodo-btn <?= $periodo=='15' && empty($_GET['data_ini'])?'active':'' ?>">15 dias</a>
            <a href="?periodo=30" class="periodo-btn <?= ($periodo=='30'||empty($_GET['periodo'])) && empty($_GET['data_ini'])?'active':'' ?>">30 dias</a>
            <a href="?periodo=90" class="periodo-btn <?= $periodo=='90' && empty($_GET['data_ini'])?'active':'' ?>">90 dias</a>
            <form method="GET" style="display:flex;gap:8px;align-items:center;margin-left:8px;">
                <input type="date" name="data_ini" value="<?= htmlspecialchars($data_ini) ?>" style="padding:7px 12px;border-radius:var(--radius-full);font-size:13px;border:2px solid var(--light-gray);">
                <span style="color:var(--gray);font-size:13px;">até</span>
                <input type="date" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>" style="padding:7px 12px;border-radius:var(--radius-full);font-size:13px;border:2px solid var(--light-gray);">
                <button type="submit" class="btn btn-primary" style="padding:8px 16px;font-size:13px;"><i class="fas fa-search"></i></button>
            </form>
            <span style="margin-left:auto;font-size:12px;color:var(--gray);">
                <?= date('d/m/Y', strtotime($data_ini)) ?> – <?= date('d/m/Y', strtotime($data_fim)) ?>
            </span>
        </div>

        <!-- KPIs -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#e3fcef;"><i class="fas fa-sack-dollar" style="color:#00875a;"></i></div>
                <div>
                    <div class="kpi-val"><?= formatar_preco($kpis['faturamento']) ?></div>
                    <div class="kpi-label">Faturamento</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#e6f0ff;"><i class="fas fa-receipt" style="color:#0052cc;"></i></div>
                <div>
                    <div class="kpi-val"><?= $kpis['total_pedidos'] ?></div>
                    <div class="kpi-label">Total de Pedidos</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#f5f3ff;"><i class="fas fa-ticket" style="color:#7c3aed;"></i></div>
                <div>
                    <div class="kpi-val"><?= formatar_preco($kpis['ticket_medio']) ?></div>
                    <div class="kpi-label">Ticket Médio</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#e3fcef;"><i class="fas fa-users" style="color:#00875a;"></i></div>
                <div>
                    <div class="kpi-val"><?= $kpis['clientes_unicos'] ?></div>
                    <div class="kpi-label">Clientes Únicos</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#e6f0ff;"><i class="fas fa-motorcycle" style="color:#0052cc;"></i></div>
                <div>
                    <div class="kpi-val"><?= $kpis['deliveries'] ?></div>
                    <div class="kpi-label">Deliveries</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#ffebe6;"><i class="fas fa-ban" style="color:#de350b;"></i></div>
                <div>
                    <div class="kpi-val"><?= $kpis['cancelados'] ?></div>
                    <div class="kpi-label">Cancelados</div>
                </div>
            </div>
        </div>

        <!-- GRÁFICO FATURAMENTO + STATUS -->
        <div class="charts-grid">
            <div class="chart-card">
                <h3><i class="fas fa-chart-line"></i> Faturamento por Dia</h3>
                <canvas id="chartFaturamento" height="100"></canvas>
            </div>
            <div class="chart-card">
                <h3><i class="fas fa-chart-pie"></i> Status dos Pedidos</h3>
                <canvas id="chartStatus" height="220"></canvas>
            </div>
        </div>

        <!-- CATEGORIA + HORA DE PICO + ENTREGA -->
        <div class="charts-grid-3">
            <div class="chart-card">
                <h3><i class="fas fa-tags"></i> Receita por Categoria</h3>
                <canvas id="chartCategoria" height="220"></canvas>
            </div>
            <div class="chart-card">
                <h3><i class="fas fa-clock"></i> Horário de Pico</h3>
                <canvas id="chartHora" height="220"></canvas>
            </div>
            <div class="chart-card">
                <h3><i class="fas fa-truck-fast"></i> Tipo de Entrega</h3>
                <canvas id="chartEntrega" height="220"></canvas>
            </div>
        </div>

        <!-- TOP PRODUTOS + TOP CLIENTES -->
        <div class="charts-grid">
            <div class="chart-card">
                <h3><i class="fas fa-trophy"></i> Top 10 Produtos</h3>
                <?php if (empty($top_produtos)): ?>
                    <p style="color:var(--gray);font-size:13px;text-align:center;padding:32px 0;">Sem dados no período</p>
                <?php else: ?>
                <table class="top-table">
                    <thead>
                        <tr><th>#</th><th>Produto</th><th>Categoria</th><th>Qtd</th><th>Receita</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($top_produtos as $i => $p): ?>
                        <tr>
                            <td>
                                <div class="rank-num <?= $i===0?'gold':($i===1?'silver':($i===2?'bronze':'')) ?>"><?= $i+1 ?></div>
                            </td>
                            <td><strong><?= htmlspecialchars($p['nome']) ?></strong></td>
                            <td style="color:var(--gray);"><?= htmlspecialchars($p['categoria']) ?></td>
                            <td style="font-weight:700;"><?= $p['qtd_vendida'] ?></td>
                            <td style="font-weight:700;color:var(--primary);"><?= formatar_preco($p['receita']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            <div class="chart-card">
                <h3><i class="fas fa-star"></i> Melhores Clientes</h3>
                <?php if (empty($top_clientes)): ?>
                    <p style="color:var(--gray);font-size:13px;text-align:center;padding:32px 0;">Sem dados no período</p>
                <?php else: ?>
                <table class="top-table">
                    <thead>
                        <tr><th>#</th><th>Cliente</th><th>Pedidos</th><th>Total Gasto</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($top_clientes as $i => $c): ?>
                        <tr>
                            <td><div class="rank-num <?= $i===0?'gold':($i===1?'silver':($i===2?'bronze':'')) ?>"><?= $i+1 ?></div></td>
                            <td>
                                <strong style="display:block;"><?= htmlspecialchars($c['nome']) ?></strong>
                                <span style="font-size:11px;color:var(--gray);"><?= htmlspecialchars($c['email']) ?></span>
                            </td>
                            <td style="font-weight:700;"><?= $c['pedidos'] ?></td>
                            <td style="font-weight:700;color:var(--primary);"><?= formatar_preco($c['gasto']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /container -->

    <script>
    /* ── DATA ──────────────────────────────────────────────── */
    const fatDia     = <?= json_encode($fat_dia) ?>;
    const porStatus  = <?= json_encode($por_status) ?>;
    const porCat     = <?= json_encode($por_categoria) ?>;
    const porHoraRaw = <?= json_encode($por_hora) ?>;
    const porEntrega = <?= json_encode($por_entrega) ?>;

    /* ── COLOURS ───────────────────────────────────────────── */
    const G = '#00875a', B = '#0052cc', O = '#ff8b00',
          R = '#de350b', P = '#7c3aed', T = '#0694a2';
    const CAT_COLORS = [G,B,O,P,T,R,'#f472b6','#84cc16','#06b6d4'];
    const ST_COLOR   = {pendente:'#f59e0b',preparando:'#3b82f6',pronto:G,entregue:'#6b7280',cancelado:R};
    const ST_LABEL   = {pendente:'Aguardando',preparando:'Separando',pronto:'Pronto',entregue:'Entregue',cancelado:'Cancelado'};

    /* ── SHARED OPTIONS ────────────────────────────────────── */
    const SCALES = {
        x: { ticks:{color:'#5e7491'}, grid:{color:'#e8f0f7'} },
        y: { ticks:{color:'#5e7491'}, grid:{color:'#e8f0f7'} }
    };

    /* ── 1. FATURAMENTO POR DIA ────────────────────────────── */
    (function() {
        const canvas = document.getElementById('chartFaturamento');
        if (!canvas) return;
        const labels = fatDia.map(r => { const [y,m,d]=r.dia.split('-'); return d+'/'+m; });
        new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels.length ? labels : ['Sem dados'],
                datasets: [
                    {
                        label: 'Faturamento (R$)',
                        data: fatDia.map(r => parseFloat(r.total)),
                        borderColor: G, backgroundColor: G+'33',
                        borderWidth: 3, fill: true, tension: 0.4,
                        pointBackgroundColor: G, pointRadius: 5, pointHoverRadius: 8
                    },
                    {
                        label: 'Pedidos',
                        data: fatDia.map(r => parseInt(r.pedidos)),
                        borderColor: B, backgroundColor: 'transparent',
                        borderWidth: 2, borderDash: [6,3], tension: 0.4,
                        pointBackgroundColor: B, pointRadius: 4, yAxisID: 'y2'
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    x: SCALES.x,
                    y:  { ...SCALES.y, position: 'left' },
                    y2: { ...SCALES.y, position: 'right', grid: { drawOnChartArea: false } }
                },
                plugins: { legend: { position: 'top' } }
            }
        });
    })();

    /* ── 2. STATUS DOS PEDIDOS ─────────────────────────────── */
    (function() {
        const canvas = document.getElementById('chartStatus');
        if (!canvas) return;
        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: porStatus.map(r => ST_LABEL[r.status] || r.status),
                datasets: [{
                    data: porStatus.map(r => r.qtd),
                    backgroundColor: porStatus.map(r => ST_COLOR[r.status] || '#ccc'),
                    borderWidth: 3, borderColor: '#fff', hoverOffset: 10
                }]
            },
            options: {
                responsive: true, cutout: '65%',
                plugins: { legend: { position: 'bottom' } }
            }
        });
    })();

    /* ── 3. RECEITA POR CATEGORIA ──────────────────────────── */
    (function() {
        const canvas = document.getElementById('chartCategoria');
        if (!canvas) return;
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: porCat.map(r => r.categoria),
                datasets: [{
                    label: 'Receita (R$)',
                    data: porCat.map(r => parseFloat(r.receita)),
                    backgroundColor: CAT_COLORS.map(c => c+'bb'),
                    borderColor: CAT_COLORS,
                    borderWidth: 2, borderRadius: 6
                }]
            },
            options: {
                responsive: true, indexAxis: 'y',
                scales: { x: SCALES.x, y: SCALES.y },
                plugins: { legend: { display: false } }
            }
        });
    })();

    /* ── 4. HORÁRIO DE PICO ────────────────────────────────── */
    (function() {
        const canvas = document.getElementById('chartHora');
        if (!canvas) return;
        const all  = Array.from({length:24}, (_,i) => i);
        const hmap = {};
        porHoraRaw.forEach(r => { hmap[r.hora] = r.pedidos; });
        const data = all.map(h => hmap[h] || 0);
        const mx   = Math.max(...data);
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: all.map(h => String(h).padStart(2,'0')+'h'),
                datasets: [{
                    label: 'Pedidos',
                    data: data,
                    backgroundColor: data.map(v => v === mx && v > 0 ? O : B+'66'),
                    borderColor:     data.map(v => v === mx && v > 0 ? O : B),
                    borderWidth: 1, borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                scales: { x: SCALES.x, y: SCALES.y },
                plugins: { legend: { display: false } }
            }
        });
    })();

    /* ── 5. TIPO DE ENTREGA ────────────────────────────────── */
    (function() {
        const canvas = document.getElementById('chartEntrega');
        if (!canvas) return;
        new Chart(canvas, {
            type: 'pie',
            data: {
                labels: porEntrega.map(r => r.tipo_retirada === 'delivery' ? 'Delivery' : 'Retirada'),
                datasets: [{
                    data: porEntrega.map(r => r.qtd),
                    backgroundColor: [B+'cc', G+'cc'],
                    borderColor: [B, G],
                    borderWidth: 3, hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    })();

    /* ── KPI COUNTER ANIMATION ─────────────────────────────── */
    function animateCounter(el) {
        const raw  = el.textContent.trim();
        const isR$ = raw.startsWith('R$');
        const num  = parseFloat(raw.replace(/[^0-9,]/g,'').replace(',','.')) || 0;
        let step = 0; const steps = 50;
        const timer = setInterval(() => {
            step++;
            const p = step / steps;
            const e = 1 - Math.pow(2, -10 * p);
            const v = num * e;
            el.textContent = isR$
                ? 'R$ ' + v.toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.')
                : Math.round(v).toLocaleString('pt-BR');
            if (step >= steps) clearInterval(timer);
        }, 1200 / steps);
    }

    /* ── KPI CARD REVEAL ───────────────────────────────────── */
    const kpiObs = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'none';
            const val = entry.target.querySelector('.kpi-val');
            if (val && !entry.target.dataset.counted) {
                entry.target.dataset.counted = '1';
                setTimeout(() => animateCounter(val), 200);
            }
            kpiObs.unobserve(entry.target);
        });
    }, { threshold: 0.2 });

    document.querySelectorAll('.kpi-card').forEach((el, i) => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = `opacity .4s ${i*0.07}s ease, transform .4s ${i*0.07}s ease`;
        kpiObs.observe(el);
    });

    /* ── PERIOD BUTTON RIPPLE ──────────────────────────────── */
    document.querySelectorAll('.periodo-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const r = document.createElement('span');
            const sz = this.offsetWidth * 2;
            r.style.cssText = `position:absolute;border-radius:50%;background:rgba(255,255,255,.4);
                width:${sz}px;height:${sz}px;left:${e.offsetX-sz/2}px;top:${e.offsetY-sz/2}px;
                animation:ripple .5s ease forwards;pointer-events:none;`;
            this.appendChild(r);
            setTimeout(() => r.remove(), 520);
        });
    });
    </script>

</body>
</html>