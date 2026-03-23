<?php
require_once FARMAVIDA_ROOT . '/app/core/bootstrap.php';
require_once FARMAVIDA_ROOT . '/app/core/config.php';
require_once FARMAVIDA_ROOT . '/app/core/helpers.php';
verificar_login('dono');

function data_valida(string $d): bool {
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

$periodo  = in_array($_GET['periodo'] ?? '', ['7','15','30','90'], true) ? $_GET['periodo'] : '30';
$data_ini = date('Y-m-d', strtotime("-{$periodo} days"));
$data_fim = date('Y-m-d');
if (!empty($_GET['data_ini']) && !empty($_GET['data_fim'])) {
    $di = $_GET['data_ini']; $df = $_GET['data_fim'];
    if (data_valida($di) && data_valida($df) && $di <= $df) { $data_ini=$di; $data_fim=$df; }
}

function q($conn,string $sql,string $types,...$params):array{$st=$conn->prepare($sql);$st->bind_param($types,...$params);$st->execute();$r=$st->get_result()->fetch_all(MYSQLI_ASSOC);$st->close();return $r;}
function q1($conn,string $sql,string $types,...$params):array{$st=$conn->prepare($sql);$st->bind_param($types,...$params);$st->execute();$r=$st->get_result()->fetch_assoc()??[];$st->close();return $r;}

$kpis = q1($conn,"SELECT COUNT(*) AS total_pedidos,COALESCE(SUM(total),0) AS faturamento,COALESCE(AVG(total),0) AS ticket_medio,SUM(CASE WHEN status='cancelado' THEN 1 ELSE 0 END) AS cancelados,SUM(CASE WHEN tipo_retirada='delivery' THEN 1 ELSE 0 END) AS deliveries,COUNT(DISTINCT id_cliente) AS clientes_unicos FROM pedidos WHERE DATE(criado_em) BETWEEN ? AND ?","ss",$data_ini,$data_fim);
$fat_dia = q($conn,"SELECT DATE(criado_em) AS dia,COUNT(*) AS pedidos,SUM(total) AS total FROM pedidos WHERE DATE(criado_em) BETWEEN ? AND ? AND status!='cancelado' GROUP BY DATE(criado_em) ORDER BY dia ASC","ss",$data_ini,$data_fim);
$top_produtos = q($conn,"SELECT pr.nome,pr.categoria,SUM(pi.quantidade) AS qtd_vendida,SUM(pi.quantidade*pi.preco_unitario) AS receita FROM pedido_itens pi JOIN produtos pr ON pi.id_produto=pr.id JOIN pedidos p ON pi.id_pedido=p.id WHERE DATE(p.criado_em) BETWEEN ? AND ? AND p.status!='cancelado' GROUP BY pi.id_produto ORDER BY receita DESC LIMIT 10","ss",$data_ini,$data_fim);
$por_categoria = q($conn,"SELECT pr.categoria,SUM(pi.quantidade*pi.preco_unitario) AS receita,SUM(pi.quantidade) AS qtd FROM pedido_itens pi JOIN produtos pr ON pi.id_produto=pr.id JOIN pedidos p ON pi.id_pedido=p.id WHERE DATE(p.criado_em) BETWEEN ? AND ? AND p.status!='cancelado' GROUP BY pr.categoria ORDER BY receita DESC","ss",$data_ini,$data_fim);
$por_status  = q($conn,"SELECT status,COUNT(*) AS qtd FROM pedidos WHERE DATE(criado_em) BETWEEN ? AND ? GROUP BY status","ss",$data_ini,$data_fim);
$por_entrega = q($conn,"SELECT tipo_retirada,COUNT(*) AS qtd,SUM(total) AS total FROM pedidos WHERE DATE(criado_em) BETWEEN ? AND ? AND status!='cancelado' GROUP BY tipo_retirada","ss",$data_ini,$data_fim);
$top_clientes= q($conn,"SELECT u.nome,u.email,COUNT(p.id) AS pedidos,SUM(p.total) AS gasto FROM pedidos p JOIN usuarios u ON p.id_cliente=u.id WHERE DATE(p.criado_em) BETWEEN ? AND ? AND p.status!='cancelado' GROUP BY p.id_cliente ORDER BY gasto DESC LIMIT 8","ss",$data_ini,$data_fim);
$por_hora    = q($conn,"SELECT HOUR(criado_em) AS hora,COUNT(*) AS pedidos FROM pedidos WHERE DATE(criado_em) BETWEEN ? AND ? AND status!='cancelado' GROUP BY HOUR(criado_em) ORDER BY hora","ss",$data_ini,$data_fim);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>RelatÃ³rios â€“ FarmaVida</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=1774207549">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
    <style>
        .periodo-bar{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:14px 18px;margin-bottom:24px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;}
        .periodo-btn{padding:7px 16px;border-radius:var(--radius-full);border:1px solid var(--border);background:var(--surface);color:var(--text3);font-size:12px;font-weight:600;text-decoration:none;transition:var(--transition);white-space:nowrap;}
        .periodo-btn:hover{border-color:var(--border2);color:var(--text2);}
        .periodo-btn.active{background:rgba(0,229,160,.1);border-color:rgba(0,229,160,.3);color:var(--primary);}
        .kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:28px;}
        .kpi-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px;display:flex;align-items:center;gap:14px;transition:var(--transition);position:relative;overflow:hidden;}
        .kpi-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:var(--gradient-main);opacity:0;transition:opacity var(--transition);}
        .kpi-card:hover{border-color:var(--border2);transform:translateY(-3px);box-shadow:var(--shadow-md);}
        .kpi-card:hover::before{opacity:1;}
        .kpi-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
        .kpi-val{font-family:'Bricolage Grotesque',sans-serif;font-size:22px;font-weight:800;color:var(--text);line-height:1;margin-bottom:3px;}
        .kpi-label{font-size:11px;color:var(--text3);font-weight:600;text-transform:uppercase;letter-spacing:.4px;}
        .charts-grid{display:grid;grid-template-columns:2fr 1fr;gap:18px;margin-bottom:18px;}
        .charts-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px;margin-bottom:18px;}
        .chart-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:22px;transition:var(--transition);}
        .chart-card:hover{border-color:var(--border2);box-shadow:var(--shadow-md);}
        .chart-card h3{font-family:'Bricolage Grotesque',sans-serif;font-size:15px;font-weight:700;color:var(--text);margin-bottom:16px;display:flex;align-items:center;gap:8px;}
        .chart-card h3 i{color:var(--primary);}
        .top-table{width:100%;border-collapse:collapse;}
        .top-table th{font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;padding:8px 10px;border-bottom:1px solid var(--border);text-align:left;}
        .top-table td{padding:10px;border-bottom:1px solid var(--border);font-size:13px;color:var(--text2);}
        .top-table tr:last-child td{border-bottom:none;}
        .top-table tr:hover td{background:var(--surface2);}
        .rank-num{width:26px;height:26px;border-radius:50%;background:var(--surface2);font-weight:800;font-size:11px;color:var(--text3);display:flex;align-items:center;justify-content:center;}
        .rank-gold  {background:rgba(255,184,48,.15);color:var(--warning);}
        .rank-silver{background:rgba(255,255,255,.08);color:var(--text2);}
        .rank-bronze{background:rgba(255,120,70,.12);color:#ff7846;}
        @media(max-width:900px){.charts-grid,.charts-grid-3{grid-template-columns:1fr;}}
        @media print{.header,.periodo-bar,.nav-buttons{display:none!important;} body{background:white;color:#000;} body::before{display:none;} .chart-card,.kpi-card{border:1px solid #ddd;background:white;}}
    </style>
</head>
<body>
<div class="header">
    <div class="header-container">
        <div class="logo" style="cursor:default;"><div class="logo-icon"><i class="fas fa-chart-line"></i></div>RelatÃ³rios</div>
        <div class="nav-buttons">
            <button onclick="window.print()" class="btn btn-secondary"><i class="fas fa-print"></i></button>
            <a href="painel_dono.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Painel</a>
        </div>
    </div>
</div>

<div class="container">

<!-- PERÃODO -->
<div class="periodo-bar">
    <span style="font-size:12px;font-weight:700;color:var(--text2);margin-right:4px;"><i class="fas fa-calendar-alt" style="color:var(--primary);"></i> PerÃ­odo:</span>
    <a href="?periodo=7"  class="periodo-btn <?= $periodo=='7' &&empty($_GET['data_ini'])?'active':'' ?>">7 dias</a>
    <a href="?periodo=15" class="periodo-btn <?= $periodo=='15'&&empty($_GET['data_ini'])?'active':'' ?>">15 dias</a>
    <a href="?periodo=30" class="periodo-btn <?= ($periodo=='30'||empty($_GET['periodo']))&&empty($_GET['data_ini'])?'active':'' ?>">30 dias</a>
    <a href="?periodo=90" class="periodo-btn <?= $periodo=='90'&&empty($_GET['data_ini'])?'active':'' ?>">90 dias</a>
    <form method="GET" style="display:flex;gap:8px;align-items:center;margin-left:8px;">
        <input type="date" name="data_ini" value="<?= htmlspecialchars($data_ini) ?>" style="padding:6px 10px;font-size:12px;">
        <span style="color:var(--text3);font-size:12px;">atÃ©</span>
        <input type="date" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>" style="padding:6px 10px;font-size:12px;">
        <button type="submit" class="btn btn-primary" style="padding:7px 14px;font-size:12px;"><i class="fas fa-search"></i></button>
    </form>
    <span style="margin-left:auto;font-size:11px;color:var(--text3);"><?= date('d/m/Y',strtotime($data_ini)) ?> â€“ <?= date('d/m/Y',strtotime($data_fim)) ?></span>
</div>

<!-- KPIs -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-icon" style="background:rgba(0,229,160,.1);"><i class="fas fa-sack-dollar" style="color:var(--primary);"></i></div>
        <div><div class="kpi-val"><?= formatar_preco($kpis['faturamento']) ?></div><div class="kpi-label">Faturamento</div></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:rgba(77,156,255,.1);"><i class="fas fa-receipt" style="color:var(--secondary);"></i></div>
        <div><div class="kpi-val"><?= $kpis['total_pedidos'] ?></div><div class="kpi-label">Total Pedidos</div></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:rgba(168,85,247,.1);"><i class="fas fa-ticket" style="color:#a855f7;"></i></div>
        <div><div class="kpi-val"><?= formatar_preco($kpis['ticket_medio']) ?></div><div class="kpi-label">Ticket MÃ©dio</div></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:rgba(0,229,160,.1);"><i class="fas fa-users" style="color:var(--primary);"></i></div>
        <div><div class="kpi-val"><?= $kpis['clientes_unicos'] ?></div><div class="kpi-label">Clientes Ãšnicos</div></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:rgba(77,156,255,.1);"><i class="fas fa-motorcycle" style="color:var(--secondary);"></i></div>
        <div><div class="kpi-val"><?= $kpis['deliveries'] ?></div><div class="kpi-label">Deliveries</div></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:rgba(255,77,109,.1);"><i class="fas fa-ban" style="color:var(--danger);"></i></div>
        <div><div class="kpi-val"><?= $kpis['cancelados'] ?></div><div class="kpi-label">Cancelados</div></div>
    </div>
</div>

<!-- GRÃFICOS -->
<div class="charts-grid">
    <div class="chart-card">
        <h3><i class="fas fa-chart-line"></i> Faturamento por Dia</h3>
        <canvas id="chartFat" height="100"></canvas>
    </div>
    <div class="chart-card">
        <h3><i class="fas fa-chart-pie"></i> Status dos Pedidos</h3>
        <canvas id="chartStatus" height="220"></canvas>
    </div>
</div>
<div class="charts-grid-3">
    <div class="chart-card">
        <h3><i class="fas fa-tags"></i> Receita por Categoria</h3>
        <canvas id="chartCat" height="220"></canvas>
    </div>
    <div class="chart-card">
        <h3><i class="fas fa-clock"></i> HorÃ¡rio de Pico</h3>
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
        <?php if(empty($top_produtos)): ?><p style="color:var(--text3);font-size:13px;text-align:center;padding:32px;">Sem dados no perÃ­odo</p>
        <?php else: ?>
        <table class="top-table">
            <thead><tr><th>#</th><th>Produto</th><th>Categoria</th><th>Qtd</th><th>Receita</th></tr></thead>
            <tbody>
            <?php foreach($top_produtos as $i=>$p): ?>
            <tr>
                <td><div class="rank-num <?= $i===0?'rank-gold':($i===1?'rank-silver':($i===2?'rank-bronze':'')) ?>"><?= $i+1 ?></div></td>
                <td><strong style="color:var(--text);"><?= htmlspecialchars($p['nome']) ?></strong></td>
                <td style="font-size:11px;"><?= htmlspecialchars($p['categoria']) ?></td>
                <td style="font-weight:700;color:var(--text);"><?= $p['qtd_vendida'] ?></td>
                <td style="font-weight:700;color:var(--primary);"><?= formatar_preco($p['receita']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <div class="chart-card">
        <h3><i class="fas fa-star"></i> Melhores Clientes</h3>
        <?php if(empty($top_clientes)): ?><p style="color:var(--text3);font-size:13px;text-align:center;padding:32px;">Sem dados no perÃ­odo</p>
        <?php else: ?>
        <table class="top-table">
            <thead><tr><th>#</th><th>Cliente</th><th>Pedidos</th><th>Total</th></tr></thead>
            <tbody>
            <?php foreach($top_clientes as $i=>$c): ?>
            <tr>
                <td><div class="rank-num <?= $i===0?'rank-gold':($i===1?'rank-silver':($i===2?'rank-bronze':'')) ?>"><?= $i+1 ?></div></td>
                <td><strong style="display:block;color:var(--text);"><?= htmlspecialchars($c['nome']) ?></strong><span style="font-size:11px;color:var(--text3);"><?= htmlspecialchars($c['email']) ?></span></td>
                <td style="font-weight:700;color:var(--text);"><?= $c['pedidos'] ?></td>
                <td style="font-weight:700;color:var(--primary);"><?= formatar_preco($c['gasto']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

</div>

<script>
// Chart defaults â€” tema dark
Chart.defaults.color = '#8fa8c8';
Chart.defaults.borderColor = 'rgba(255,255,255,.06)';
Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";

const G='#00e5a0',B='#4d9cff',O='#ffb830',R='#ff4d6d',P='#a855f7',T='#00c8ff';
const CAT_COLORS=[G,B,O,P,T,R,'#f472b6','#84cc16','#06b6d4'];
const ST_COLORS={pendente:O,preparando:B,pronto:G,entregue:'#4d6b8a',cancelado:R};
const ST_LABELS={pendente:'Aguardando',preparando:'Separando',pronto:'Pronto',entregue:'Entregue',cancelado:'Cancelado'};
const SCALES={x:{ticks:{color:'#4d6b8a'},grid:{color:'rgba(255,255,255,.04)'}},y:{ticks:{color:'#4d6b8a'},grid:{color:'rgba(255,255,255,.04)'}}};

const fatDia    = <?= json_encode($fat_dia) ?>;
const porStatus = <?= json_encode($por_status) ?>;
const porCat    = <?= json_encode($por_categoria) ?>;
const porHoraRaw= <?= json_encode($por_hora) ?>;
const porEntrega= <?= json_encode($por_entrega) ?>;

// Faturamento por dia
new Chart(document.getElementById('chartFat'),{type:'line',data:{
    labels:fatDia.map(r=>{const[y,m,d]=r.dia.split('-');return d+'/'+m;}),
    datasets:[
        {label:'Faturamento (R$)',data:fatDia.map(r=>parseFloat(r.total)),borderColor:G,backgroundColor:G+'22',borderWidth:2.5,fill:true,tension:.4,pointBackgroundColor:G,pointRadius:4,yAxisID:'y'},
        {label:'Pedidos',data:fatDia.map(r=>parseInt(r.pedidos)),borderColor:B,backgroundColor:'transparent',borderWidth:2,borderDash:[6,3],tension:.4,pointBackgroundColor:B,pointRadius:3,yAxisID:'y2'}
    ]},options:{responsive:true,scales:{x:SCALES.x,y:{...SCALES.y,position:'left'},y2:{...SCALES.y,position:'right',grid:{drawOnChartArea:false}}},plugins:{legend:{position:'top',labels:{color:'#8fa8c8'}}}}});

// Status
new Chart(document.getElementById('chartStatus'),{type:'doughnut',data:{
    labels:porStatus.map(r=>ST_LABELS[r.status]||r.status),
    datasets:[{data:porStatus.map(r=>r.qtd),backgroundColor:porStatus.map(r=>ST_COLORS[r.status]||'#4d6b8a'),borderColor:'rgba(13,20,37,.8)',borderWidth:3,hoverOffset:10}]
},options:{responsive:true,cutout:'65%',plugins:{legend:{position:'bottom',labels:{color:'#8fa8c8'}}}}});

// Categoria
new Chart(document.getElementById('chartCat'),{type:'bar',data:{
    labels:porCat.map(r=>r.categoria),
    datasets:[{label:'Receita (R$)',data:porCat.map(r=>parseFloat(r.receita)),backgroundColor:CAT_COLORS.map(c=>c+'99'),borderColor:CAT_COLORS,borderWidth:1.5,borderRadius:5}]
},options:{responsive:true,indexAxis:'y',scales:{x:SCALES.x,y:SCALES.y},plugins:{legend:{display:false}}}});

// HorÃ¡rio de pico
const allHoras=Array.from({length:24},(_,i)=>i);
const hmap={};porHoraRaw.forEach(r=>{hmap[r.hora]=r.pedidos;});
const horaData=allHoras.map(h=>hmap[h]||0);
const mx=Math.max(...horaData);
new Chart(document.getElementById('chartHora'),{type:'bar',data:{
    labels:allHoras.map(h=>String(h).padStart(2,'0')+'h'),
    datasets:[{label:'Pedidos',data:horaData,backgroundColor:horaData.map(v=>v===mx&&v>0?O:B+'55'),borderColor:horaData.map(v=>v===mx&&v>0?O:B),borderWidth:1,borderRadius:4}]
},options:{responsive:true,scales:{x:SCALES.x,y:SCALES.y},plugins:{legend:{display:false}}}});

// Entrega
new Chart(document.getElementById('chartEntrega'),{type:'pie',data:{
    labels:porEntrega.map(r=>r.tipo_retirada==='delivery'?'Delivery':'Retirada'),
    datasets:[{data:porEntrega.map(r=>r.qtd),backgroundColor:[B+'cc',G+'cc'],borderColor:[B,G],borderWidth:2.5,hoverOffset:10}]
},options:{responsive:true,plugins:{legend:{position:'bottom',labels:{color:'#8fa8c8'}}}}});
</script>
</body>
</html>
