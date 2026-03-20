<?php
// ============================================================
// NF-e – NOTA FISCAL ELETRÔNICA (FarmaVida)
// ============================================================
// Geração de XML NF-e e DANFE simplificada
// Para emissão real integrar com: Focus NFe, NFe.io, Tecnospeed
// ou webservice SEFAZ diretamente via certificado digital A1/A3
// ============================================================
session_start();
include "config.php";
include "helpers.php";
verificar_login('dono');

// ── AUTO-MIGRAÇÃO: colunas NF-e em pedidos ──────────────────
$existentes = array_column($conn->query("SHOW COLUMNS FROM pedidos")->fetch_all(MYSQLI_ASSOC), 'Field');
$sqls_pedidos = [
    'nfe_numero'        => "ALTER TABLE pedidos ADD COLUMN nfe_numero VARCHAR(9) DEFAULT NULL",
    'nfe_serie'         => "ALTER TABLE pedidos ADD COLUMN nfe_serie VARCHAR(3) DEFAULT '001'",
    'nfe_chave'         => "ALTER TABLE pedidos ADD COLUMN nfe_chave VARCHAR(45) DEFAULT NULL",
    'nfe_status'        => "ALTER TABLE pedidos ADD COLUMN nfe_status ENUM('pendente','emitida','cancelada') DEFAULT 'pendente'",
    'nfe_emitida_em'    => "ALTER TABLE pedidos ADD COLUMN nfe_emitida_em TIMESTAMP NULL",
    'nfe_cancelada_em'  => "ALTER TABLE pedidos ADD COLUMN nfe_cancelada_em TIMESTAMP NULL",
    'nfe_justificativa' => "ALTER TABLE pedidos ADD COLUMN nfe_justificativa TEXT DEFAULT NULL",
];
foreach ($sqls_pedidos as $col => $sql) {
    if (!in_array($col, $existentes)) $conn->query($sql);
}

// ── AUTO-MIGRAÇÃO: colunas fiscais em produtos ───────────────
$cols_prod  = array_column($conn->query("SHOW COLUMNS FROM produtos")->fetch_all(MYSQLI_ASSOC), 'Field');
$sqls_prods = [
    'ncm'  => "ALTER TABLE produtos ADD COLUMN ncm  VARCHAR(8) DEFAULT '30049099'",
    'cfop' => "ALTER TABLE produtos ADD COLUMN cfop VARCHAR(4) DEFAULT '5102'",
    'cst'  => "ALTER TABLE produtos ADD COLUMN cst  VARCHAR(3) DEFAULT '500'",
];
foreach ($sqls_prods as $col => $sql) {
    if (!in_array($col, $cols_prod)) $conn->query($sql);
}

// ── AUTO-MIGRAÇÃO: cpf em usuarios ──────────────────────────
$cols_usr = array_column($conn->query("SHOW COLUMNS FROM usuarios")->fetch_all(MYSQLI_ASSOC), 'Field');
if (!in_array('cpf', $cols_usr)) {
    $conn->query("ALTER TABLE usuarios ADD COLUMN cpf VARCHAR(14) DEFAULT NULL AFTER telefone");
}

// ── CONFIGURAÇÕES DA EMPRESA ────────────────────────────────
// Em produção, mova para config.php ou banco de dados
$empresa = [
    'razao_social' => 'FARMAVIDA FARMÁCIA E DROGARIA LTDA',
    'nome_fantasia' => 'FarmaVida',
    'cnpj'         => '12.345.678/0001-00',
    'ie'           => '123.456.789.000',
    'crt'          => '1',  // 1=Simples Nacional, 3=Regime Normal
    'endereco'     => 'Av. da Saúde, 456',
    'numero'       => '456',
    'bairro'       => 'Centro',
    'municipio'    => 'Votuporanga',
    'uf'           => 'SP',
    'cep'          => '15500-000',
    'telefone'     => '(17) 99999-1234',
    'email'        => 'fiscal@farmavida.com.br',
    'csc'          => 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX', // CSC para NFC-e
    'id_csc'       => '000001',
];

// ── AÇÕES ──────────────────────────────────────────────────
$acao      = $_GET['acao']   ?? 'listar';
$id_pedido = (int)($_GET['id'] ?? 0);

// ── EMITIR NF-e ────────────────────────────────────────────
if ($acao === 'emitir' && $id_pedido > 0) {
    $pedido = $conn->query("
        SELECT p.*, u.nome AS cliente_nome, u.email AS cliente_email,
               u.cpf, u.telefone, u.endereco
        FROM pedidos p
        JOIN usuarios u ON p.id_cliente = u.id
        WHERE p.id = $id_pedido
    ")->fetch_assoc();

    $itens = $conn->query("
        SELECT pi.*, pr.nome, pr.categoria,
               pr.ncm, pr.cfop, pr.cst
        FROM pedido_itens pi
        JOIN produtos pr ON pi.id_produto = pr.id
        WHERE pi.id_pedido = $id_pedido
    ")->fetch_all(MYSQLI_ASSOC);

    if ($pedido) {
        $numero_nfe = str_pad($id_pedido, 9, '0', STR_PAD_LEFT);
        $serie      = '001';
        $dhEmi      = date('Y-m-d\TH:i:sP');
        $chave      = gerar_chave_nfe($empresa['uf'], $pedido, $numero_nfe, $serie);

        // Salvar NF-e no banco
        $conn->query("UPDATE pedidos SET nfe_numero='$numero_nfe', nfe_serie='$serie', nfe_chave='$chave', nfe_status='emitida', nfe_emitida_em=NOW() WHERE id=$id_pedido");

        header("Location: nfe.php?acao=danfe&id=$id_pedido");
        exit;
    }
}

// ── CANCELAR NF-e ───────────────────────────────────────────
if ($acao === 'cancelar' && $id_pedido > 0) {
    $justificativa = sanitizar_texto($_POST['justificativa'] ?? '');
    if (strlen($justificativa) < 15) {
        $_SESSION['erro'] = 'Justificativa deve ter pelo menos 15 caracteres.';
        header("Location: nfe.php?acao=danfe&id=$id_pedido"); exit;
    }
    $conn->query("UPDATE pedidos SET nfe_status='cancelada', nfe_cancelada_em=NOW(), nfe_justificativa='$justificativa' WHERE id=$id_pedido");
    header("Location: nfe.php"); exit;
}

// ── FUNÇÃO: gerar chave de acesso NF-e ─────────────────────
function gerar_chave_nfe(string $uf, array $pedido, string $numero, string $serie): string {
    $cuf   = ['SP'=>'35','RJ'=>'33','MG'=>'31'][$uf] ?? '35';
    $aamm  = date('ym', strtotime($pedido['criado_em']));
    $cnpj  = preg_replace('/\D/', '', '12345678000100');
    $mod   = '55'; // NF-e
    $nserie= str_pad($serie,   3, '0', STR_PAD_LEFT);
    $nnfe  = str_pad($numero,  9, '0', STR_PAD_LEFT);
    $tp    = '1'; // normal
    $codigo= str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
    $chave = $cuf.$aamm.$cnpj.$mod.$nserie.$nnfe.$tp.$codigo;
    // Dígito verificador (módulo 11)
    $peso  = 2; $soma = 0;
    for ($i = strlen($chave)-1; $i >= 0; $i--) {
        $soma += (int)$chave[$i] * $peso;
        $peso  = $peso == 9 ? 2 : $peso + 1;
    }
    $resto = $soma % 11;
    $dv    = ($resto < 2) ? 0 : 11 - $resto;
    return $chave . $dv;
}

// ── BUSCAR PEDIDOS PARA LISTAR ──────────────────────────────
$busca_nfe = $_GET['busca'] ?? '';
$filtro_st = $_GET['st']    ?? '';
$where = ["p.id IS NOT NULL"];
if ($busca_nfe) $where[] = "(p.id='$busca_nfe' OR u.nome LIKE '%$busca_nfe%')";
if ($filtro_st) $where[] = "p.nfe_status='$filtro_st'";
$where_sql = implode(' AND ', $where);

$pedidos_lista = $conn->query("
    SELECT p.id, p.total, p.status, p.criado_em,
           p.nfe_numero, p.nfe_serie, p.nfe_chave, p.nfe_status, p.nfe_emitida_em,
           u.nome AS cliente_nome
    FROM pedidos p
    JOIN usuarios u ON p.id_cliente = u.id
    WHERE $where_sql
    ORDER BY p.criado_em DESC
    LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

// ── DANFE: buscar pedido específico ────────────────────────
$pedido_danfe = null;
$itens_danfe  = [];
if ($acao === 'danfe' && $id_pedido > 0) {
    $pedido_danfe = $conn->query("
        SELECT p.*, u.nome AS cliente_nome, u.email AS cliente_email,
               u.cpf, u.telefone, u.endereco
        FROM pedidos p
        JOIN usuarios u ON p.id_cliente = u.id
        WHERE p.id = $id_pedido
    ")->fetch_assoc();
    $itens_danfe = $conn->query("
        SELECT pi.*, pr.nome, pr.categoria, pr.ncm, pr.cfop
        FROM pedido_itens pi
        JOIN produtos pr ON pi.id_produto = pr.id
        WHERE pi.id_pedido = $id_pedido
    ")->fetch_all(MYSQLI_ASSOC);
}

$msg = $_SESSION['sucesso'] ?? $_SESSION['erro'] ?? ''; unset($_SESSION['sucesso'], $_SESSION['erro']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NF-e – FarmaVida</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* ── DANFE ── */
        .danfe { max-width:860px; margin:0 auto; background:white; border:2px solid #000; font-family:'Courier New',monospace; font-size:11px; }
        .danfe-header { display:grid; grid-template-columns:180px 1fr 200px; border-bottom:2px solid #000; }
        .danfe-logo { padding:12px; border-right:1px solid #000; display:flex; align-items:center; justify-content:center; flex-direction:column; gap:4px; text-align:center; }
        .danfe-empresa { padding:12px; border-right:1px solid #000; }
        .danfe-chave { padding:10px; font-size:9px; }
        .danfe-section { border-bottom:1px solid #000; padding:0; }
        .danfe-section-title { background:#eee; padding:2px 8px; font-weight:bold; font-size:10px; border-bottom:1px solid #ccc; }
        .danfe-grid { display:grid; gap:0; }
        .danfe-field { padding:4px 8px; border-right:1px solid #ccc; }
        .danfe-field:last-child { border-right:none; }
        .danfe-label { font-size:8px; color:#555; display:block; text-transform:uppercase; }
        .danfe-value { font-size:11px; font-weight:bold; }
        .danfe-items table { width:100%; border-collapse:collapse; }
        .danfe-items th { background:#eee; padding:4px 6px; font-size:9px; text-transform:uppercase; text-align:left; border:1px solid #ccc; }
        .danfe-items td { padding:4px 6px; font-size:10px; border:1px solid #eee; }
        .danfe-totais { display:grid; grid-template-columns:1fr 1fr; }
        .danfe-totais-lado { padding:8px 12px; }
        .total-destaque { font-size:20px; font-weight:900; color:#000; }
        .nfe-badge { display:inline-flex; align-items:center; gap:5px; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:700; }
        .nfe-pendente  { background:#fef3c7; color:#92400e; }
        .nfe-emitida   { background:#e3fcef; color:#065f46; }
        .nfe-cancelada { background:#fee2e2; color:#991b1b; }
        .nfe-table { width:100%; border-collapse:collapse; }
        .nfe-table th { font-size:11px; font-weight:700; color:var(--gray); text-transform:uppercase; padding:10px 12px; border-bottom:2px solid var(--light-gray); text-align:left; }
        .nfe-table td { padding:12px; border-bottom:1px solid var(--light-gray); font-size:13px; vertical-align:middle; }
        .nfe-table tr:hover td { background:rgba(0,135,90,.03); }
        .config-aviso { background:linear-gradient(135deg,#fffbeb,#fef3c7); border:1.5px solid #f59e0b; border-radius:var(--radius-md); padding:16px 20px; margin-bottom:20px; }
        @media print {
            body { background:white; }
            .header, .nav-buttons, .periodo-bar, .card:not(.danfe-card), .no-print { display:none !important; }
            .danfe { border:2px solid #000 !important; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-container">
            <div class="logo" style="cursor:default;">
                <div class="logo-icon"><i class="fas fa-file-invoice"></i></div>
                Nota Fiscal Eletrônica
            </div>
            <div class="nav-buttons">
                <?php if ($acao === 'danfe'): ?>
                    <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Imprimir DANFE</button>
                    <a href="nfe.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
                <?php else: ?>
                    <a href="painel_dono.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Painel</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="container">

        <?php if ($acao === 'danfe' && $pedido_danfe): ?>
        <!-- ═══════════════════════════════════════════════════
             DANFE – Documento Auxiliar da Nota Fiscal Eletrônica
             ═══════════════════════════════════════════════════ -->
        <div style="margin-bottom:20px;" class="no-print">
            <?php if ($pedido_danfe['nfe_status'] === 'emitida'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    NF-e #<?= $pedido_danfe['nfe_numero'] ?> emitida com sucesso em <?= date('d/m/Y H:i', strtotime($pedido_danfe['nfe_emitida_em'])) ?>
                </div>
            <?php elseif ($pedido_danfe['nfe_status'] === 'cancelada'): ?>
                <div class="alert alert-error"><i class="fas fa-ban"></i> NF-e cancelada.</div>
            <?php endif; ?>
        </div>

        <div class="danfe">
            <!-- CABEÇALHO -->
            <div class="danfe-header">
                <div class="danfe-logo">
                    <strong style="font-size:18px;color:#00875a;">💊</strong>
                    <strong style="font-size:13px;"><?= $empresa['nome_fantasia'] ?></strong>
                    <span style="font-size:9px;">DANFE</span>
                </div>
                <div class="danfe-empresa">
                    <strong style="font-size:13px;"><?= $empresa['razao_social'] ?></strong><br>
                    <?= $empresa['endereco'] ?>, <?= $empresa['numero'] ?> – <?= $empresa['bairro'] ?><br>
                    <?= $empresa['municipio'] ?>/<?= $empresa['uf'] ?> – CEP: <?= $empresa['cep'] ?><br>
                    Fone: <?= $empresa['telefone'] ?><br>
                    CNPJ: <?= $empresa['cnpj'] ?> &nbsp;|&nbsp; IE: <?= $empresa['ie'] ?><br>
                    <small>CRT: <?= $empresa['crt'] === '1' ? 'Simples Nacional' : 'Regime Normal' ?></small>
                </div>
                <div class="danfe-chave">
                    <strong style="font-size:10px;">NF-e</strong><br>
                    <strong>Nº <?= $pedido_danfe['nfe_numero'] ?? '000000000' ?> – Série <?= $pedido_danfe['nfe_serie'] ?? '001' ?></strong><br><br>
                    <strong>Chave de Acesso:</strong><br>
                    <span style="word-break:break-all;font-size:9px;"><?= chunk_split($pedido_danfe['nfe_chave'] ?? str_repeat('0',44), 4, ' ') ?></span><br><br>
                    <strong>Data de Emissão:</strong><br>
                    <?= date('d/m/Y H:i', strtotime($pedido_danfe['nfe_emitida_em'] ?? $pedido_danfe['criado_em'])) ?><br><br>
                    <strong>Natureza da Operação:</strong><br>
                    VENDA A CONSUMIDOR
                </div>
            </div>

            <!-- DESTINATÁRIO -->
            <div class="danfe-section">
                <div class="danfe-section-title">DESTINATÁRIO / REMETENTE</div>
                <div class="danfe-grid" style="grid-template-columns:2fr 1fr 1fr;">
                    <div class="danfe-field"><span class="danfe-label">Nome / Razão Social</span><span class="danfe-value"><?= htmlspecialchars($pedido_danfe['cliente_nome']) ?></span></div>
                    <div class="danfe-field"><span class="danfe-label">CPF/CNPJ</span><span class="danfe-value"><?= htmlspecialchars($pedido_danfe['cpf'] ?? 'CONSUMIDOR FINAL') ?></span></div>
                    <div class="danfe-field"><span class="danfe-label">Data de Entrega</span><span class="danfe-value"><?= date('d/m/Y') ?></span></div>
                </div>
                <div class="danfe-grid" style="grid-template-columns:2fr 1fr;">
                    <div class="danfe-field"><span class="danfe-label">Endereço</span><span class="danfe-value"><?= htmlspecialchars($pedido_danfe['endereco'] ?? '—') ?></span></div>
                    <div class="danfe-field"><span class="danfe-label">Fone / E-mail</span><span class="danfe-value"><?= htmlspecialchars($pedido_danfe['telefone'] ?? $pedido_danfe['cliente_email'] ?? '—') ?></span></div>
                </div>
            </div>

            <!-- PRODUTOS -->
            <div class="danfe-section danfe-items">
                <div class="danfe-section-title">DADOS DOS PRODUTOS / SERVIÇOS</div>
                <table>
                    <thead>
                        <tr>
                            <th>#</th><th>Descrição do Produto</th><th>Categoria</th>
                            <th>Qtd</th><th>Un</th><th>Vl. Unit.</th><th>Vl. Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($itens_danfe as $i => $item): ?>
                        <tr>
                            <td><?= $i+1 ?></td>
                            <td><?= htmlspecialchars($item['nome']) ?></td>
                            <td><?= htmlspecialchars($item['categoria']) ?></td>
                            <td><?= $item['quantidade'] ?></td>
                            <td>un</td>
                            <td>R$ <?= number_format($item['preco_unitario'], 2, ',', '.') ?></td>
                            <td>R$ <?= number_format($item['preco_unitario'] * $item['quantidade'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- TOTAIS -->
            <div class="danfe-section">
                <div class="danfe-section-title">CÁLCULO DO IMPOSTO</div>
                <div class="danfe-totais">
                    <div class="danfe-totais-lado">
                        <div class="danfe-grid" style="grid-template-columns:1fr 1fr;">
                            <div class="danfe-field"><span class="danfe-label">Base de Cálculo ICMS</span><span class="danfe-value">R$ <?= number_format($pedido_danfe['total'], 2, ',', '.') ?></span></div>
                            <div class="danfe-field"><span class="danfe-label">Valor ICMS</span><span class="danfe-value">R$ 0,00</span></div>
                            <div class="danfe-field"><span class="danfe-label">Valor PIS</span><span class="danfe-value">R$ 0,00</span></div>
                            <div class="danfe-field"><span class="danfe-label">Valor COFINS</span><span class="danfe-value">R$ 0,00</span></div>
                        </div>
                    </div>
                    <div class="danfe-totais-lado" style="text-align:center;border-left:1px solid #ccc;">
                        <div class="danfe-label" style="font-size:11px;margin-bottom:4px;">VALOR TOTAL DA NOTA</div>
                        <div class="total-destaque">R$ <?= number_format($pedido_danfe['total'], 2, ',', '.') ?></div>
                    </div>
                </div>
            </div>

            <!-- INFO ADICIONAL -->
            <div class="danfe-section" style="padding:8px 12px;">
                <div class="danfe-section-title">DADOS ADICIONAIS</div>
                <p style="padding:6px 8px;font-size:10px;">
                    Pedido: #<?= $pedido_danfe['id'] ?> | 
                    <?= htmlspecialchars($pedido_danfe['observacoes'] ?? 'Venda ao consumidor final.') ?><br>
                    Documento emitido pelo sistema FarmaVida. Este documento não tem valor fiscal até autorização SEFAZ.
                </p>
            </div>
        </div><!-- /danfe -->

        <!-- AÇÕES NO DANFE -->
        <?php if ($pedido_danfe['nfe_status'] === 'emitida'): ?>
        <div style="margin-top:20px;display:flex;gap:12px;flex-wrap:wrap;" class="no-print">
            <button onclick="document.getElementById('modal-cancelar').style.display='flex'" class="btn btn-danger">
                <i class="fas fa-ban"></i> Cancelar NF-e
            </button>
        </div>

        <!-- Modal cancelar -->
        <div id="modal-cancelar" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;padding:20px;">
            <div style="background:white;border-radius:20px;padding:32px;max-width:480px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.3);">
                <h3 style="font-family:'Sora',sans-serif;color:var(--danger);margin-bottom:16px;"><i class="fas fa-ban"></i> Cancelar NF-e</h3>
                <form method="POST" action="nfe.php?acao=cancelar&id=<?= $id_pedido ?>">
                    <div class="form-group">
                        <label><i class="fas fa-comment"></i> Justificativa (mínimo 15 caracteres) *</label>
                        <textarea name="justificativa" rows="3" required minlength="15" placeholder="Motivo do cancelamento..."></textarea>
                    </div>
                    <div style="display:flex;gap:10px;">
                        <button type="submit" class="btn btn-danger"><i class="fas fa-check"></i> Confirmar Cancelamento</button>
                        <button type="button" onclick="document.getElementById('modal-cancelar').style.display='none'" class="btn btn-secondary">Voltar</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- ═══════════════════════════════════════════════════
             LISTAGEM DE NF-e
             ═══════════════════════════════════════════════════ -->

        <!-- AVISO DE CONFIGURAÇÃO -->
        <div class="config-aviso">
            <div style="display:flex;align-items:flex-start;gap:12px;">
                <i class="fas fa-circle-info" style="color:#d97706;font-size:20px;margin-top:2px;flex-shrink:0;"></i>
                <div>
                    <strong style="color:#92400e;display:block;margin-bottom:6px;">⚙️ Configuração necessária para emissão real</strong>
                    <div style="font-size:13px;color:#78350f;line-height:1.8;">
                        Para emitir NF-e com validade jurídica, configure em <strong>nfe.php</strong> as seguintes informações:<br>
                        <strong>Certificado Digital A1/A3</strong> · <strong>CNPJ e IE corretos</strong> · <strong>Integração SEFAZ</strong> (via <a href="https://focusnfe.com.br" target="_blank" style="color:#0052cc;">Focus NFe</a>, <a href="https://nfe.io" target="_blank" style="color:#0052cc;">NFe.io</a> ou <a href="https://tecnospeed.com.br" target="_blank" style="color:#0052cc;">Tecnospeed</a>)
                    </div>
                </div>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?= isset($_SESSION['erro'])?'error':'success' ?>"><i class="fas fa-circle-info"></i> <?= $msg ?></div>
        <?php endif; ?>

        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
                <h2 style="margin:0;"><i class="fas fa-file-invoice"></i> Notas Fiscais</h2>
            </div>

            <!-- Filtros -->
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;">
                <form method="GET" style="display:flex;gap:8px;flex:1;min-width:240px;">
                    <input type="text" name="busca" placeholder="🔍 Pedido ou cliente..." value="<?= htmlspecialchars($busca_nfe) ?>" style="flex:1;border-radius:var(--radius-full);">
                    <select name="st" style="border-radius:var(--radius-full);">
                        <option value="">Todos os status</option>
                        <option value="pendente"  <?= $filtro_st==='pendente' ?'selected':'' ?>>Pendente</option>
                        <option value="emitida"   <?= $filtro_st==='emitida'  ?'selected':'' ?>>Emitida</option>
                        <option value="cancelada" <?= $filtro_st==='cancelada'?'selected':'' ?>>Cancelada</option>
                    </select>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                </form>
            </div>

            <?php if (empty($pedidos_lista)): ?>
                <div class="empty" style="padding:40px;"><i class="fas fa-file-invoice"></i><h2>Nenhuma nota encontrada</h2></div>
            <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="nfe-table">
                    <thead>
                        <tr>
                            <th>Pedido</th><th>Cliente</th><th>Data</th><th>Valor</th>
                            <th>Nº NF-e</th><th>Status</th><th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pedidos_lista as $p):
                        $st = $p['nfe_status'] ?? 'pendente';
                    ?>
                        <tr>
                            <td><strong>#<?= $p['id'] ?></strong></td>
                            <td><?= htmlspecialchars($p['cliente_nome']) ?></td>
                            <td style="white-space:nowrap;color:var(--gray);font-size:12px;"><?= date('d/m/Y H:i', strtotime($p['criado_em'])) ?></td>
                            <td style="font-weight:700;color:var(--primary);"><?= formatar_preco($p['total']) ?></td>
                            <td style="font-size:12px;"><?= $p['nfe_numero'] ? $p['nfe_numero'].'/'.$p['nfe_serie'] : '—' ?></td>
                            <td>
                                <span class="nfe-badge nfe-<?= $st ?>">
                                    <i class="fas fa-<?= $st==='emitida'?'check-circle':($st==='cancelada'?'ban':'clock') ?>"></i>
                                    <?= ucfirst($st) ?>
                                </span>
                            </td>
                            <td style="white-space:nowrap;">
                                <?php if ($st === 'emitida'): ?>
                                    <a href="nfe.php?acao=danfe&id=<?= $p['id'] ?>" class="btn btn-secondary" style="padding:7px 12px;font-size:12px;"><i class="fas fa-eye"></i> Ver DANFE</a>
                                <?php elseif ($st === 'pendente'): ?>
                                    <a href="nfe.php?acao=emitir&id=<?= $p['id'] ?>" class="btn btn-success" style="padding:7px 12px;font-size:12px;" onclick="return confirm('Emitir NF-e para o Pedido #<?= $p['id'] ?>?')">
                                        <i class="fas fa-paper-plane"></i> Emitir
                                    </a>
                                <?php else: ?>
                                    <span style="font-size:12px;color:var(--gray);">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</body>
</html>