<?php
session_start();
include "config.php";
include "helpers.php";
verificar_login('dono');

$id_admin = (int)$_SESSION['id_usuario'];

// ── AUTO-MIGRAÇÃO ─────────────────────────────────────────────
$cols_prod = array_column($conn->query("SHOW COLUMNS FROM produtos")->fetch_all(MYSQLI_ASSOC), 'Field');
foreach ([
    'estoque_atual'  => "ALTER TABLE produtos ADD COLUMN estoque_atual INT NOT NULL DEFAULT 0 AFTER disponivel",
    'estoque_minimo' => "ALTER TABLE produtos ADD COLUMN estoque_minimo INT NOT NULL DEFAULT 5",
    'estoque_maximo' => "ALTER TABLE produtos ADD COLUMN estoque_maximo INT NOT NULL DEFAULT 999",
    'unidade'        => "ALTER TABLE produtos ADD COLUMN unidade VARCHAR(20) DEFAULT 'un'",
    'localizacao'    => "ALTER TABLE produtos ADD COLUMN localizacao VARCHAR(60) DEFAULT NULL",
] as $col => $sql) { if (!in_array($col, $cols_prod)) $conn->query($sql); }

$conn->query("CREATE TABLE IF NOT EXISTS movimentacoes_estoque (
    id INT PRIMARY KEY AUTO_INCREMENT, id_produto INT NOT NULL,
    tipo ENUM('entrada','saida','ajuste','transferencia_out','transferencia_in') NOT NULL,
    quantidade INT NOT NULL, estoque_anterior INT NOT NULL DEFAULT 0, estoque_novo INT NOT NULL DEFAULT 0,
    motivo VARCHAR(255) DEFAULT NULL, id_pedido INT DEFAULT NULL, id_usuario INT DEFAULT NULL,
    localizacao_origem VARCHAR(60) DEFAULT NULL, localizacao_destino VARCHAR(60) DEFAULT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_produto) REFERENCES produtos(id) ON DELETE CASCADE
) ENGINE=InnoDB");

// Limpeza automática 30 dias
if (empty($_SESSION['estoque_limpeza']) || $_SESSION['estoque_limpeza'] !== date('Y-m-d')) {
    $conn->query("DELETE FROM movimentacoes_estoque WHERE criado_em < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $_SESSION['estoque_limpeza'] = date('Y-m-d');
}

// ── ENTRADA ──────────────────────────────────────────────────
if (isset($_POST['acao_entrada'])) {
    $id = (int)$_POST['id_produto']; $qtd = max(1,(int)$_POST['quantidade']);
    $motivo = sanitizar_texto($_POST['motivo'] ?? 'Entrada de estoque');
    $stmt = $conn->prepare("SELECT estoque_atual FROM produtos WHERE id=?");
    $stmt->bind_param("i",$id); $stmt->execute(); $prod=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if ($prod) {
        $antes=$prod['estoque_atual']; $depois=$antes+$qtd; $tipo='entrada';
        $stmt=$conn->prepare("UPDATE produtos SET estoque_atual=? WHERE id=?"); $stmt->bind_param("ii",$depois,$id); $stmt->execute(); $stmt->close();
        $stmt=$conn->prepare("UPDATE produtos SET disponivel=1 WHERE id=? AND disponivel=0"); $stmt->bind_param("i",$id); $stmt->execute(); $stmt->close();
        $stmt=$conn->prepare("INSERT INTO movimentacoes_estoque (id_produto,tipo,quantidade,estoque_anterior,estoque_novo,motivo,id_usuario) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("isiissi",$id,$tipo,$qtd,$antes,$depois,$motivo,$id_admin); $stmt->execute(); $stmt->close();
        redirecionar('estoque.php',"✅ Entrada de $qtd unidade(s) registrada!");
    }
}

// ── SAÍDA ────────────────────────────────────────────────────
if (isset($_POST['acao_saida'])) {
    $id = (int)$_POST['id_produto']; $qtd = max(1,(int)$_POST['quantidade']);
    $motivo = sanitizar_texto($_POST['motivo'] ?? 'Saída de estoque');
    $stmt=$conn->prepare("SELECT estoque_atual FROM produtos WHERE id=?"); $stmt->bind_param("i",$id); $stmt->execute();
    $prod=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if ($prod) {
        $antes=$prod['estoque_atual']; $depois=max(0,$antes-$qtd); $tipo='saida';
        $stmt=$conn->prepare("UPDATE produtos SET estoque_atual=? WHERE id=?"); $stmt->bind_param("ii",$depois,$id); $stmt->execute(); $stmt->close();
        if ($depois===0) { $stmt=$conn->prepare("UPDATE produtos SET disponivel=0 WHERE id=?"); $stmt->bind_param("i",$id); $stmt->execute(); $stmt->close(); }
        $stmt=$conn->prepare("INSERT INTO movimentacoes_estoque (id_produto,tipo,quantidade,estoque_anterior,estoque_novo,motivo,id_usuario) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("isiissi",$id,$tipo,$qtd,$antes,$depois,$motivo,$id_admin); $stmt->execute(); $stmt->close();
        redirecionar('estoque.php',"✅ Saída de $qtd unidade(s) registrada!");
    }
}

// ── AJUSTE ───────────────────────────────────────────────────
if (isset($_POST['acao_ajuste'])) {
    $id=(int)$_POST['id_produto']; $novo=max(0,(int)$_POST['novo_estoque']);
    $motivo=sanitizar_texto($_POST['motivo']??'Ajuste de inventário');
    $stmt=$conn->prepare("SELECT estoque_atual FROM produtos WHERE id=?"); $stmt->bind_param("i",$id); $stmt->execute();
    $prod=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if ($prod) {
        $antes=$prod['estoque_atual']; $diff=abs($novo-$antes); $tipo='ajuste';
        $stmt=$conn->prepare("UPDATE produtos SET estoque_atual=? WHERE id=?"); $stmt->bind_param("ii",$novo,$id); $stmt->execute(); $stmt->close();
        if ($novo===0){$stmt=$conn->prepare("UPDATE produtos SET disponivel=0 WHERE id=?");$stmt->bind_param("i",$id);$stmt->execute();$stmt->close();}
        elseif($antes===0&&$novo>0){$stmt=$conn->prepare("UPDATE produtos SET disponivel=1 WHERE id=?");$stmt->bind_param("i",$id);$stmt->execute();$stmt->close();}
        $stmt=$conn->prepare("INSERT INTO movimentacoes_estoque (id_produto,tipo,quantidade,estoque_anterior,estoque_novo,motivo,id_usuario) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("isiissi",$id,$tipo,$diff,$antes,$novo,$motivo,$id_admin); $stmt->execute(); $stmt->close();
        redirecionar('estoque.php',"✅ Estoque ajustado para $novo unidade(s)!");
    }
}

// ── TRANSFERÊNCIA ─────────────────────────────────────────────
if (isset($_POST['acao_transferencia'])) {
    $id=(int)$_POST['id_produto']; $qtd=max(1,(int)$_POST['quantidade']);
    $orig=sanitizar_texto($_POST['localizacao_origem']??''); $dest=sanitizar_texto($_POST['localizacao_destino']??'');
    $motivo=sanitizar_texto($_POST['motivo']??"Transferência: $orig → $dest");
    $stmt=$conn->prepare("SELECT estoque_atual FROM produtos WHERE id=?"); $stmt->bind_param("i",$id); $stmt->execute();
    $prod=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if ($prod) {
        $antes=(int)$prod['estoque_atual']; $tipo='transferencia_out';
        $stmt=$conn->prepare("INSERT INTO movimentacoes_estoque (id_produto,tipo,quantidade,estoque_anterior,estoque_novo,motivo,id_usuario,localizacao_origem,localizacao_destino) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("isiississ",$id,$tipo,$qtd,$antes,$antes,$motivo,$id_admin,$orig,$dest); $stmt->execute(); $stmt->close();
        $stmt=$conn->prepare("UPDATE produtos SET localizacao=? WHERE id=?"); $stmt->bind_param("si",$dest,$id); $stmt->execute(); $stmt->close();
        redirecionar('estoque.php',"✅ Transferência: $orig → $dest!");
    }
}

// ── LIMITES ───────────────────────────────────────────────────
if (isset($_POST['acao_limites'])) {
    $id=(int)$_POST['id_produto']; $min=max(0,(int)$_POST['estoque_minimo']); $max=max(1,(int)$_POST['estoque_maximo']);
    $unidade=sanitizar_texto($_POST['unidade']??'un'); $loc=sanitizar_texto($_POST['localizacao']??'');
    $stmt=$conn->prepare("UPDATE produtos SET estoque_minimo=?,estoque_maximo=?,unidade=?,localizacao=? WHERE id=?");
    $stmt->bind_param("iissi",$min,$max,$unidade,$loc,$id); $stmt->execute(); $stmt->close();
    redirecionar('estoque.php',"✅ Configurações atualizadas!");
}

// ── LEITURA ───────────────────────────────────────────────────
$filtro_cat=$_GET['categoria']??''; $filtro_alerta=$_GET['alerta']??''; $busca=$_GET['busca']??'';
$tipos_validos=['baixo','zerado','ok'];
$conds=[];$params=[];$types='';
if($filtro_cat!==''){$conds[]="p.categoria=?";$params[]=$filtro_cat;$types.='s';}
if($filtro_alerta==='baixo') $conds[]="p.estoque_atual>0 AND p.estoque_atual<=p.estoque_minimo";
elseif($filtro_alerta==='zerado') $conds[]="p.estoque_atual=0";
elseif($filtro_alerta==='ok') $conds[]="p.estoque_atual>p.estoque_minimo";
if($busca!==''){$conds[]="p.nome LIKE ?";$params[]="%$busca%";$types.='s';}
$where=$conds?'WHERE '.implode(' AND ',$conds):'';
$sql="SELECT p.* FROM produtos p $where ORDER BY p.estoque_atual ASC,p.nome ASC";
if($params){$st=$conn->prepare($sql);$st->bind_param($types,...$params);$st->execute();$produtos=$st->get_result()->fetch_all(MYSQLI_ASSOC);$st->close();}
else{$produtos=$conn->query($sql)->fetch_all(MYSQLI_ASSOC);}

$stats=$conn->query("SELECT COUNT(*) as total,SUM(CASE WHEN estoque_atual=0 THEN 1 ELSE 0 END) as zerados,SUM(CASE WHEN estoque_atual>0 AND estoque_atual<=estoque_minimo THEN 1 ELSE 0 END) as baixos,SUM(CASE WHEN estoque_atual>estoque_minimo THEN 1 ELSE 0 END) as normais,SUM(estoque_atual) as total_unidades FROM produtos")->fetch_assoc();

$tipos_mov_ok=['entrada','saida','ajuste','transferencia_out','transferencia_in'];
$fmtipo=$_GET['mov_tipo']??''; $fmprod=(int)($_GET['mov_produto']??0);
$mc=[];$mp=[];$mt='';
if($fmtipo!==''&&in_array($fmtipo,$tipos_mov_ok,true)){$mc[]="m.tipo=?";$mp[]=$fmtipo;$mt.='s';}
if($fmprod>0){$mc[]="m.id_produto=?";$mp[]=$fmprod;$mt.='i';}
$mw=$mc?'WHERE '.implode(' AND ',$mc):'';
$sql_mov="SELECT m.*,p.nome AS produto_nome,p.unidade,u.nome AS usuario_nome FROM movimentacoes_estoque m JOIN produtos p ON m.id_produto=p.id LEFT JOIN usuarios u ON m.id_usuario=u.id $mw ORDER BY m.criado_em DESC LIMIT 60";
if($mp){$st=$conn->prepare($sql_mov);$st->bind_param($mt,...$mp);$st->execute();$movimentacoes=$st->get_result()->fetch_all(MYSQLI_ASSOC);$st->close();}
else{$movimentacoes=$conn->query($sql_mov)->fetch_all(MYSQLI_ASSOC);}

$categorias=$conn->query("SELECT DISTINCT categoria FROM produtos WHERE categoria IS NOT NULL AND categoria!='' ORDER BY categoria")->fetch_all(MYSQLI_ASSOC);
$produtos_sel=$conn->query("SELECT id,nome,estoque_atual,localizacao FROM produtos ORDER BY nome")->fetch_all(MYSQLI_ASSOC);
$msg=$_SESSION['sucesso']??''; unset($_SESSION['sucesso']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Controle de Estoque – FarmaVida</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=1774207549">
    <style>
        .est-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:var(--radius-full);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;}
        .est-ok     {background:rgba(0,229,160,.1);color:var(--primary);border:1px solid rgba(0,229,160,.2);}
        .est-baixo  {background:rgba(255,184,48,.1);color:var(--warning);border:1px solid rgba(255,184,48,.2);}
        .est-zerado {background:rgba(255,77,109,.1);color:var(--danger);border:1px solid rgba(255,77,109,.2);}
        .est-table{width:100%;border-collapse:collapse;}
        .est-table th{background:var(--surface2);font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;padding:11px 14px;text-align:left;border-bottom:1px solid var(--border);white-space:nowrap;}
        .est-table td{padding:13px 14px;border-bottom:1px solid var(--border);font-size:13px;color:var(--text2);vertical-align:middle;}
        .est-table tr:hover td{background:var(--surface2);}
        .est-table tr.row-zerado td{background:rgba(255,77,109,.03);}
        .est-table tr.row-baixo  td{background:rgba(255,184,48,.03);}
        .stock-bar-wrap{width:90px;}
        .stock-bar{height:5px;background:var(--border);border-radius:3px;overflow:hidden;}
        .stock-bar-fill{height:100%;border-radius:3px;transition:.3s;}
        .mov-entrada      {background:rgba(0,229,160,.1);color:var(--primary);}
        .mov-saida        {background:rgba(255,77,109,.1);color:var(--danger);}
        .mov-ajuste       {background:rgba(77,156,255,.1);color:var(--secondary);}
        .mov-transferencia_out,.mov-transferencia_in{background:rgba(168,85,247,.1);color:#a855f7;}
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);backdrop-filter:blur(8px);z-index:2000;align-items:center;justify-content:center;padding:20px;}
        .modal-overlay.open{display:flex;animation:fadeIn .2s ease;}
        .mov-tab{display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:var(--radius-full);background:var(--surface);border:1px solid var(--border);color:var(--text3);font-size:12px;font-weight:600;cursor:pointer;text-decoration:none;transition:var(--transition);white-space:nowrap;}
        .mov-tab:hover,.mov-tab.active{background:rgba(0,229,160,.1);border-color:rgba(0,229,160,.3);color:var(--primary);}
        .est-stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px;text-align:center;transition:var(--transition);text-decoration:none;display:block;}
        .est-stat-card:hover{transform:translateY(-3px);border-color:var(--border2);box-shadow:var(--shadow-md);}
        .est-stat-num{font-family:'Bricolage Grotesque',sans-serif;font-size:34px;font-weight:800;line-height:1;margin-bottom:5px;}
        .est-stat-label{font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;}
    </style>
</head>
<body>
<div class="header">
    <div class="header-container">
        <a href="painel_dono.php" class="logo"><div class="logo-icon"><i class="fas fa-boxes-stacked"></i></div>Estoque</a>
        <div class="nav-buttons">
            <a href="painel_dono.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Painel</a>
            <a href="gerenciar_produtos.php" class="btn btn-secondary"><i class="fas fa-pills"></i></a>
            <a href="logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </div>
</div>

<div class="container">

<?php if($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div><?php endif; ?>

<!-- ALERTA CRÍTICO -->
<?php if($stats['zerados']>0||$stats['baixos']>0): ?>
<div class="alert alert-warning" style="margin-bottom:24px;">
    <i class="fas fa-triangle-exclamation"></i>
    <div>
        <strong>Estoque crítico: </strong>
        <?php if($stats['zerados']>0): ?><strong><?= $stats['zerados'] ?></strong> produto(s) zerado(s) &nbsp;·&nbsp;<?php endif; ?>
        <?php if($stats['baixos']>0): ?><strong><?= $stats['baixos'] ?></strong> abaixo do mínimo<?php endif; ?>
    </div>
    <a href="estoque.php?alerta=zerado" class="btn btn-warning" style="margin-left:auto;padding:6px 14px;font-size:12px;">Ver</a>
</div>
<?php endif; ?>

<!-- STATS -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:28px;">
    <a href="estoque.php" class="est-stat-card"><div class="est-stat-num" style="color:var(--text);"><?= $stats['total'] ?></div><div class="est-stat-label">Total</div></a>
    <a href="estoque.php?alerta=ok"     class="est-stat-card"><div class="est-stat-num" style="color:var(--primary);"><?= $stats['normais'] ?></div><div class="est-stat-label">🟢 Normal</div></a>
    <a href="estoque.php?alerta=baixo"  class="est-stat-card"><div class="est-stat-num" style="color:var(--warning);"><?= $stats['baixos'] ?></div><div class="est-stat-label">🟡 Baixo</div></a>
    <a href="estoque.php?alerta=zerado" class="est-stat-card"><div class="est-stat-num" style="color:var(--danger);"><?= $stats['zerados'] ?></div><div class="est-stat-label">🔴 Zerado</div></a>
    <div class="est-stat-card" style="cursor:default;"><div class="est-stat-num" style="color:var(--secondary);"><?= number_format($stats['total_unidades'],0,'.','.') ?></div><div class="est-stat-label">Unidades</div></div>
</div>

<!-- FILTROS + AÇÕES -->
<div class="card" style="padding:18px 22px;margin-bottom:20px;">
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <form method="GET" style="display:flex;gap:8px;flex:1;flex-wrap:wrap;">
            <input type="text" name="busca" placeholder="🔍 Buscar produto..." value="<?= htmlspecialchars($busca) ?>" style="flex:1;min-width:160px;">
            <select name="categoria">
                <option value="">Todas categorias</option>
                <?php foreach($categorias as $c): ?><option value="<?= htmlspecialchars($c['categoria']) ?>" <?= $filtro_cat===$c['categoria']?'selected':'' ?>><?= htmlspecialchars($c['categoria']) ?></option><?php endforeach; ?>
            </select>
            <select name="alerta">
                <option value="">Todos status</option>
                <option value="ok"     <?= $filtro_alerta==='ok'    ?'selected':'' ?>>🟢 Normal</option>
                <option value="baixo"  <?= $filtro_alerta==='baixo' ?'selected':'' ?>>🟡 Baixo</option>
                <option value="zerado" <?= $filtro_alerta==='zerado'?'selected':'' ?>>🔴 Zerado</option>
            </select>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
            <a href="estoque.php" class="btn btn-secondary"><i class="fas fa-sync"></i></a>
        </form>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <button onclick="abrirModal('modal-entrada')" class="btn btn-success"><i class="fas fa-arrow-down"></i> Entrada</button>
            <button onclick="abrirModal('modal-saida')"   class="btn btn-danger" ><i class="fas fa-arrow-up"></i> Saída</button>
            <button onclick="abrirModal('modal-ajuste')"  class="btn btn-info"   ><i class="fas fa-sliders"></i> Ajuste</button>
            <button onclick="abrirModal('modal-transf')"  class="btn btn-warning"><i class="fas fa-arrows-left-right"></i> Transf.</button>
        </div>
    </div>
</div>

<!-- TABELA -->
<div class="card" style="padding:0;overflow:hidden;margin-bottom:28px;">
    <div style="padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
        <h2 style="margin:0;"><i class="fas fa-table-list"></i> Produtos (<?= count($produtos) ?>)</h2>
    </div>
    <div style="overflow-x:auto;">
        <table class="est-table">
            <thead><tr><th>Produto</th><th>Categoria</th><th style="text-align:center;">Atual</th><th style="text-align:center;">Mín.</th><th>Unid.</th><th>Local.</th><th>Status</th><th>Barra</th><th style="text-align:center;">Ações</th></tr></thead>
            <tbody>
            <?php foreach($produtos as $p):
                $s=(int)$p['estoque_atual'];$min=(int)$p['estoque_minimo'];$max=max(1,(int)$p['estoque_maximo']);
                $pct=min(100,round($s/$max*100));
                if($s===0){$cls='row-zerado';$badge='est-zerado';$lbl='Zerado';$cor='#ff4d6d';}
                elseif($s<=$min){$cls='row-baixo';$badge='est-baixo';$lbl='Baixo';$cor='#ffb830';}
                else{$cls='';$badge='est-ok';$lbl='Normal';$cor='#00e5a0';}
            ?>
            <tr class="<?= $cls ?>">
                <td><strong style="color:var(--text);"><?= htmlspecialchars($p['nome']) ?></strong></td>
                <td style="font-size:11px;color:var(--text3);"><?= htmlspecialchars($p['categoria']) ?></td>
                <td style="text-align:center;"><span style="font-family:'Bricolage Grotesque',sans-serif;font-size:22px;font-weight:800;color:<?= $cor ?>;"><?= $s ?></span></td>
                <td style="text-align:center;"><?= $min ?></td>
                <td style="font-size:12px;"><?= htmlspecialchars($p['unidade']??'un') ?></td>
                <td style="font-size:12px;color:var(--text3);"><?= htmlspecialchars($p['localizacao']??'—') ?></td>
                <td><span class="est-badge <?= $badge ?>"><?= $lbl ?></span></td>
                <td><div class="stock-bar-wrap"><div class="stock-bar"><div class="stock-bar-fill" style="width:<?= $pct ?>%;background:<?= $cor ?>;"></div></div><span style="font-size:10px;color:var(--text3);"><?= $pct ?>%</span></div></td>
                <td style="text-align:center;white-space:nowrap;">
                    <button onclick="abrirModalProduto('modal-entrada',<?= $p['id'] ?>)" class="btn btn-success" style="padding:5px 9px;font-size:12px;"><i class="fas fa-arrow-down"></i></button>
                    <button onclick="abrirModalProduto('modal-saida',<?= $p['id'] ?>)"   class="btn btn-danger"  style="padding:5px 9px;font-size:12px;"><i class="fas fa-arrow-up"></i></button>
                    <button onclick="abrirModalAjuste(<?= $p['id'] ?>,'<?= addslashes(htmlspecialchars($p['nome'])) ?>',<?= $s ?>,<?= $min ?>,<?= (int)$p['estoque_maximo'] ?>,'<?= addslashes(htmlspecialchars($p['unidade']??'un')) ?>','<?= addslashes(htmlspecialchars($p['localizacao']??'')) ?>')" class="btn btn-info" style="padding:5px 9px;font-size:12px;"><i class="fas fa-sliders"></i></button>
                    <a href="estoque.php?mov_produto=<?= $p['id'] ?>#historico" class="btn btn-secondary" style="padding:5px 9px;font-size:12px;"><i class="fas fa-clock-rotate-left"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($produtos)): ?><tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text3);">Nenhum produto encontrado</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- HISTÓRICO -->
<div class="card" id="historico">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <h2 style="margin:0;"><i class="fas fa-clock-rotate-left"></i> Histórico</h2>
        <span style="font-size:12px;color:var(--text3);padding:4px 12px;background:var(--surface2);border-radius:var(--radius-full);border:1px solid var(--border);">
            <i class="fas fa-calendar-xmark"></i> Mantido por 30 dias
        </span>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;">
        <a href="estoque.php#historico"                            class="mov-tab <?= !$fmtipo?'active':'' ?>">Todos</a>
        <a href="estoque.php?mov_tipo=entrada#historico"           class="mov-tab <?= $fmtipo==='entrada'?'active':'' ?>">↓ Entradas</a>
        <a href="estoque.php?mov_tipo=saida#historico"             class="mov-tab <?= $fmtipo==='saida'?'active':'' ?>">↑ Saídas</a>
        <a href="estoque.php?mov_tipo=ajuste#historico"            class="mov-tab <?= $fmtipo==='ajuste'?'active':'' ?>">⚖ Ajustes</a>
        <a href="estoque.php?mov_tipo=transferencia_out#historico" class="mov-tab <?= $fmtipo==='transferencia_out'?'active':'' ?>">⇄ Transf.</a>
        <?php if($fmprod): ?>
        <?php $np=$conn->query("SELECT nome FROM produtos WHERE id=$fmprod")->fetch_assoc(); ?>
        <span style="font-size:12px;color:var(--text3);display:flex;align-items:center;gap:6px;padding:7px 14px;background:var(--surface2);border-radius:var(--radius-full);">
            <?= htmlspecialchars($np['nome']??'') ?> <a href="estoque.php#historico" style="color:var(--danger);">✕</a>
        </span>
        <?php endif; ?>
    </div>
    <?php if(empty($movimentacoes)): ?>
        <div class="empty" style="padding:40px;"><i class="fas fa-inbox"></i><h2>Nenhuma movimentação</h2></div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table class="est-table">
            <thead><tr><th>Data/Hora</th><th>Tipo</th><th>Produto</th><th style="text-align:center;">Qtd</th><th style="text-align:center;">Antes</th><th style="text-align:center;">Depois</th><th>Motivo</th><th>Usuário</th><th>Pedido</th></tr></thead>
            <tbody>
            <?php
            $tlabels=['entrada'=>'Entrada','saida'=>'Saída','ajuste'=>'Ajuste','transferencia_out'=>'Transf.','transferencia_in'=>'Chegada'];
            foreach($movimentacoes as $m): ?>
            <tr>
                <td style="white-space:nowrap;font-size:12px;"><?= date('d/m/Y H:i',strtotime($m['criado_em'])) ?></td>
                <td><span class="est-badge mov-<?= $m['tipo'] ?>"><?= $tlabels[$m['tipo']]??$m['tipo'] ?></span></td>
                <td><strong style="color:var(--text);"><?= htmlspecialchars($m['produto_nome']) ?></strong></td>
                <td style="text-align:center;font-weight:800;font-size:16px;color:var(--text);"><?= $m['quantidade'] ?> <span style="font-size:10px;color:var(--text3);"><?= htmlspecialchars($m['unidade']) ?></span></td>
                <td style="text-align:center;"><?= $m['estoque_anterior'] ?></td>
                <td style="text-align:center;font-weight:700;color:<?= $m['estoque_novo']>$m['estoque_anterior']?'var(--primary)':($m['estoque_novo']<$m['estoque_anterior']?'var(--danger)':'var(--text3)') ?>;"><?= $m['estoque_novo'] ?></td>
                <td style="font-size:12px;max-width:180px;"><?= htmlspecialchars($m['motivo']??'—') ?><?php if($m['localizacao_origem']): ?><br><span style="color:var(--secondary);font-size:11px;"><?= htmlspecialchars($m['localizacao_origem']) ?> → <?= htmlspecialchars($m['localizacao_destino']) ?></span><?php endif; ?></td>
                <td style="font-size:12px;color:var(--text3);"><?= htmlspecialchars($m['usuario_nome']??'Sistema') ?></td>
                <td style="font-size:12px;"><?php if($m['id_pedido']): ?><a href="imprimir_pedido.php?id=<?= (int)$m['id_pedido'] ?>" target="_blank" style="color:var(--primary);font-weight:700;">#<?= (int)$m['id_pedido'] ?></a><?php else: ?><span style="color:var(--border2);">—</span><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
</div>

<!-- MODAIS -->
<?php
$sel_opts='';
foreach($produtos_sel as $ps) $sel_opts.='<option value="'.(int)$ps['id'].'" data-loc="'.htmlspecialchars($ps['localizacao']??'').'">'.htmlspecialchars($ps['nome']).' ('.(int)$ps['estoque_atual'].' un)</option>';
?>
<div class="modal-overlay" id="modal-entrada"><div class="modal-box">
    <button class="modal-close" onclick="fecharModal('modal-entrada')"><i class="fas fa-xmark"></i></button>
    <h3 style="color:var(--primary);font-family:'Bricolage Grotesque',sans-serif;"><i class="fas fa-arrow-down"></i> Registrar Entrada</h3>
    <form method="POST"><?= campo_csrf() ?>
        <div class="form-group"><label>Produto *</label><select name="id_produto" id="sel-entrada" required><option value="">Selecione...</option><?= $sel_opts ?></select></div>
        <div class="form-group"><label>Quantidade *</label><input type="number" name="quantidade" min="1" value="1" required></div>
        <div class="form-group"><label>Motivo / NF</label><input type="text" name="motivo" placeholder="Ex: Compra NF 00123"></div>
        <button type="submit" name="acao_entrada" class="btn btn-success btn-lg" style="width:100%;justify-content:center;"><i class="fas fa-check"></i> Confirmar Entrada</button>
    </form>
</div></div>

<div class="modal-overlay" id="modal-saida"><div class="modal-box">
    <button class="modal-close" onclick="fecharModal('modal-saida')"><i class="fas fa-xmark"></i></button>
    <h3 style="color:var(--danger);font-family:'Bricolage Grotesque',sans-serif;"><i class="fas fa-arrow-up"></i> Registrar Saída</h3>
    <form method="POST"><?= campo_csrf() ?>
        <div class="form-group"><label>Produto *</label><select name="id_produto" id="sel-saida" required><option value="">Selecione...</option><?= $sel_opts ?></select></div>
        <div class="form-group"><label>Quantidade *</label><input type="number" name="quantidade" min="1" value="1" required></div>
        <div class="form-group"><label>Motivo</label><input type="text" name="motivo" placeholder="Ex: Vencimento, perda..."></div>
        <button type="submit" name="acao_saida" class="btn btn-danger btn-lg" style="width:100%;justify-content:center;"><i class="fas fa-check"></i> Confirmar Saída</button>
    </form>
</div></div>

<div class="modal-overlay" id="modal-ajuste"><div class="modal-box">
    <button class="modal-close" onclick="fecharModal('modal-ajuste')"><i class="fas fa-xmark"></i></button>
    <h3 style="color:var(--secondary);font-family:'Bricolage Grotesque',sans-serif;"><i class="fas fa-sliders"></i> Ajuste / Config</h3>
    <p id="ajuste-nome" style="color:var(--text3);font-size:13px;margin:-10px 0 16px;"></p>
    <form method="POST"><?= campo_csrf() ?><input type="hidden" name="id_produto" id="ajuste-id">
        <div style="display:flex;gap:6px;margin-bottom:18px;">
            <button type="button" onclick="ajusteTab('inv')" id="tab-inv" class="btn btn-primary" style="padding:7px 14px;font-size:12px;">📦 Inventário</button>
            <button type="button" onclick="ajusteTab('cfg')" id="tab-cfg" class="btn btn-secondary" style="padding:7px 14px;font-size:12px;">⚙️ Config</button>
        </div>
        <div id="tab-inv-body">
            <div class="form-group"><label>Novo Estoque *</label><input type="number" name="novo_estoque" id="ajuste-estoque" min="0" required></div>
            <div class="form-group"><label>Motivo</label><input type="text" name="motivo" placeholder="Contagem física..."></div>
            <button type="submit" name="acao_ajuste" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;"><i class="fas fa-check"></i> Salvar Ajuste</button>
        </div>
        <div id="tab-cfg-body" style="display:none;">
            <div class="form-grid">
                <div class="form-group"><label>Estoque Mínimo</label><input type="number" name="estoque_minimo" id="cfg-min" min="0" required></div>
                <div class="form-group"><label>Estoque Máximo</label><input type="number" name="estoque_maximo" id="cfg-max" min="1" required></div>
            </div>
            <div class="form-grid">
                <div class="form-group"><label>Unidade</label>
                    <select name="unidade" id="cfg-und"><option value="un">un</option><option value="cx">cx</option><option value="fr">fr</option><option value="bl">bl</option><option value="cp">cp</option><option value="ml">ml</option><option value="g">g</option><option value="kg">kg</option></select>
                </div>
                <div class="form-group"><label>Localização</label><input type="text" name="localizacao" id="cfg-loc" placeholder="Ex: Prateleira A3"></div>
            </div>
            <button type="submit" name="acao_limites" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;"><i class="fas fa-save"></i> Salvar Config</button>
        </div>
    </form>
</div></div>

<div class="modal-overlay" id="modal-transf"><div class="modal-box">
    <button class="modal-close" onclick="fecharModal('modal-transf')"><i class="fas fa-xmark"></i></button>
    <h3 style="color:#a855f7;font-family:'Bricolage Grotesque',sans-serif;"><i class="fas fa-arrows-left-right"></i> Transferência</h3>
    <form method="POST"><?= campo_csrf() ?>
        <div class="form-group"><label>Produto *</label><select name="id_produto" id="sel-transf" required><option value="">Selecione...</option><?= $sel_opts ?></select></div>
        <div class="form-group"><label>Quantidade *</label><input type="number" name="quantidade" min="1" value="1" required></div>
        <div class="form-grid">
            <div class="form-group"><label>Origem</label><input type="text" name="localizacao_origem" id="transf-orig" required placeholder="Ex: Depósito"></div>
            <div class="form-group"><label>Destino</label><input type="text" name="localizacao_destino" required placeholder="Ex: Prateleira B2"></div>
        </div>
        <div class="form-group"><label>Observação</label><input type="text" name="motivo" placeholder="Motivo..."></div>
        <button type="submit" name="acao_transferencia" class="btn btn-lg" style="width:100%;justify-content:center;background:linear-gradient(135deg,#a855f7,#7c3aed);color:#fff;">
            <i class="fas fa-check"></i> Registrar Transferência
        </button>
    </form>
</div></div>

<script>
function abrirModal(id){document.getElementById(id).classList.add('open');document.body.style.overflow='hidden';}
function fecharModal(id){document.getElementById(id).classList.remove('open');document.body.style.overflow='';}
document.querySelectorAll('.modal-overlay').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)fecharModal(m.id);}));
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('.modal-overlay.open').forEach(m=>fecharModal(m.id));});
function abrirModalProduto(mid,id){abrirModal(mid);const sel=document.getElementById({'modal-entrada':'sel-entrada','modal-saida':'sel-saida'}[mid]);if(sel)sel.value=id;}
function abrirModalAjuste(id,nome,est,min,max,und,loc){
    abrirModal('modal-ajuste');
    document.getElementById('ajuste-id').value=id;
    document.getElementById('ajuste-nome').textContent=nome;
    document.getElementById('ajuste-estoque').value=est;
    document.getElementById('cfg-min').value=min;
    document.getElementById('cfg-max').value=max;
    document.getElementById('cfg-loc').value=loc;
    const s=document.getElementById('cfg-und');if(s)[...s.options].forEach(o=>o.selected=o.value===und);
    ajusteTab('inv');
}
function ajusteTab(t){
    document.getElementById('tab-inv-body').style.display=t==='inv'?'block':'none';
    document.getElementById('tab-cfg-body').style.display=t==='cfg'?'block':'none';
    document.getElementById('tab-inv').className='btn '+(t==='inv'?'btn-primary':'btn-secondary');
    document.getElementById('tab-inv').style.cssText='padding:7px 14px;font-size:12px;';
    document.getElementById('tab-cfg').className='btn '+(t==='cfg'?'btn-primary':'btn-secondary');
    document.getElementById('tab-cfg').style.cssText='padding:7px 14px;font-size:12px;';
}
document.getElementById('sel-transf')?.addEventListener('change',function(){
    document.getElementById('transf-orig').value=this.options[this.selectedIndex].getAttribute('data-loc')||'';
});
</script>
</body>
</html>
