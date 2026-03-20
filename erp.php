<?php
// ============================================================
// INTEGRAÇÃO ERP – FarmaVida
// Expõe endpoints REST para sistemas de gestão externos
// Compatível com: Conta Azul, Bling, Totvs, SAP B1, Omie
// ============================================================
session_start();
include "config.php";
include "helpers.php";

// ── API KEY (gerada automaticamente, salva no banco) ─────────
function gerar_api_key(): string {
    return 'fv_' . bin2hex(random_bytes(24));
}

function validar_api_key(string $key, $conn): bool {
    $key = $conn->real_escape_string($key);
    $r   = $conn->query("SELECT id FROM erp_api_keys WHERE api_key='$key' AND ativa=1");
    return $r && $r->num_rows > 0;
}

// ── CRIAR TABELAS SE NÃO EXISTIREM ──────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS erp_api_keys (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    api_key VARCHAR(100) UNIQUE NOT NULL,
    ativa TINYINT(1) DEFAULT 1,
    permissoes TEXT,
    ultimo_acesso TIMESTAMP NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$conn->query("CREATE TABLE IF NOT EXISTS erp_webhooks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    evento VARCHAR(60) NOT NULL,
    url_destino VARCHAR(500) NOT NULL,
    ativa TINYINT(1) DEFAULT 1,
    tentativas INT DEFAULT 0,
    ultimo_disparo TIMESTAMP NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$conn->query("CREATE TABLE IF NOT EXISTS erp_webhook_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_webhook INT,
    evento VARCHAR(60),
    payload TEXT,
    resposta TEXT,
    http_status INT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

// ── AUTO-MIGRAÇÃO: colunas de estoque se não existirem ──────
$cols_p = array_column($conn->query("SHOW COLUMNS FROM produtos")->fetch_all(MYSQLI_ASSOC), 'Field');
$cols_pedidos = array_column($conn->query("SHOW COLUMNS FROM pedidos")->fetch_all(MYSQLI_ASSOC), 'Field');
$auto_cols = [
    'produtos' => [
        'estoque_atual'  => "ALTER TABLE produtos ADD COLUMN estoque_atual INT NOT NULL DEFAULT 0",
        'estoque_minimo' => "ALTER TABLE produtos ADD COLUMN estoque_minimo INT NOT NULL DEFAULT 5",
        'estoque_maximo' => "ALTER TABLE produtos ADD COLUMN estoque_maximo INT NOT NULL DEFAULT 999",
        'unidade'        => "ALTER TABLE produtos ADD COLUMN unidade VARCHAR(20) DEFAULT 'un'",
        'localizacao'    => "ALTER TABLE produtos ADD COLUMN localizacao VARCHAR(60) DEFAULT NULL",
        'ncm'            => "ALTER TABLE produtos ADD COLUMN ncm VARCHAR(8) DEFAULT '30049099'",
        'cfop'           => "ALTER TABLE produtos ADD COLUMN cfop VARCHAR(4) DEFAULT '5102'",
    ],
    'pedidos' => [
        'forma_pagamento'  => "ALTER TABLE pedidos ADD COLUMN forma_pagamento ENUM('presencial','app') DEFAULT 'presencial'",
        'pagamento_status' => "ALTER TABLE pedidos ADD COLUMN pagamento_status ENUM('pendente','aprovado','recusado','em_analise','cancelado') DEFAULT 'pendente'",
        'nfe_numero'       => "ALTER TABLE pedidos ADD COLUMN nfe_numero VARCHAR(9) DEFAULT NULL",
        'nfe_serie'        => "ALTER TABLE pedidos ADD COLUMN nfe_serie VARCHAR(3) DEFAULT '001'",
        'nfe_chave'        => "ALTER TABLE pedidos ADD COLUMN nfe_chave VARCHAR(45) DEFAULT NULL",
        'nfe_status'       => "ALTER TABLE pedidos ADD COLUMN nfe_status ENUM('pendente','emitida','cancelada') DEFAULT 'pendente'",
        'nfe_emitida_em'   => "ALTER TABLE pedidos ADD COLUMN nfe_emitida_em TIMESTAMP NULL",
    ],
];
foreach ($auto_cols['produtos'] as $col => $sql) {
    if (!in_array($col, $cols_p)) $conn->query($sql);
}
foreach ($auto_cols['pedidos'] as $col => $sql) {
    if (!in_array($col, $cols_pedidos)) $conn->query($sql);
}

// ── DETECTAR SE É REQUISIÇÃO API (JSON) ─────────────────────
$is_api = (isset($_GET['api']) || isset($_SERVER['HTTP_X_API_KEY']));

if ($is_api) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
    header('Access-Control-Allow-Headers: X-Api-Key, Content-Type');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

    $api_key = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
    if (!validar_api_key($api_key, $conn)) {
        http_response_code(401);
        echo json_encode(['erro' => 'API key inválida ou inativa', 'code' => 401]);
        exit;
    }

    // Atualizar último acesso
    $key_esc = $conn->real_escape_string($api_key);
    $conn->query("UPDATE erp_api_keys SET ultimo_acesso=NOW() WHERE api_key='$key_esc'");

    $endpoint = $_GET['endpoint'] ?? '';
    $method   = $_SERVER['REQUEST_METHOD'];
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];

    switch ("$method:$endpoint") {

        // GET /produtos – listar produtos com estoque
        case 'GET:produtos':
            $rows = $conn->query("
                SELECT id, nome, descricao, preco, categoria, disponivel,
                       estoque_atual, estoque_minimo, unidade, localizacao
                FROM produtos ORDER BY nome
            ")->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['data' => $rows, 'total' => count($rows)]);
            break;

        // GET /pedidos – listar pedidos com filtros
        case 'GET:pedidos':
            $status = $_GET['status'] ?? '';
            $limit  = min((int)($_GET['limit'] ?? 50), 200);
            $desde  = $_GET['desde'] ?? '';
            $wh = ['1=1'];
            if ($status) $wh[] = "p.status='" . $conn->real_escape_string($status) . "'";
            if ($desde)  $wh[] = "p.criado_em > '" . $conn->real_escape_string($desde) . "'";
            $rows = $conn->query("
                SELECT p.id, p.total, p.status, p.tipo_retirada, p.forma_pagamento,
                       p.pagamento_status, p.observacoes, p.criado_em,
                       u.nome AS cliente, u.email, u.telefone, u.cpf
                FROM pedidos p
                JOIN usuarios u ON p.id_cliente = u.id
                WHERE " . implode(' AND ', $wh) . "
                ORDER BY p.criado_em DESC LIMIT $limit
            ")->fetch_all(MYSQLI_ASSOC);
            foreach ($rows as &$r) {
                $id = $r['id'];
                $r['itens'] = $conn->query("
                    SELECT pi.quantidade, pi.preco_unitario, pr.nome, pr.categoria
                    FROM pedido_itens pi JOIN produtos pr ON pi.id_produto=pr.id
                    WHERE pi.id_pedido=$id
                ")->fetch_all(MYSQLI_ASSOC);
            }
            echo json_encode(['data' => $rows, 'total' => count($rows)]);
            break;

        // GET /estoque – posição de estoque
        case 'GET:estoque':
            $rows = $conn->query("
                SELECT p.id, p.nome, p.categoria, p.estoque_atual, p.estoque_minimo,
                       p.estoque_maximo, p.unidade, p.localizacao,
                       CASE WHEN p.estoque_atual=0 THEN 'zerado'
                            WHEN p.estoque_atual<=p.estoque_minimo THEN 'baixo'
                            ELSE 'normal' END AS situacao
                FROM produtos p ORDER BY p.estoque_atual ASC
            ")->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['data' => $rows, 'total' => count($rows)]);
            break;

        // GET /clientes – base de clientes
        case 'GET:clientes':
            $rows = $conn->query("
                SELECT id, nome, email, telefone, cpf, endereco, criado_em
                FROM usuarios WHERE tipo='cliente' ORDER BY nome
            ")->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['data' => $rows, 'total' => count($rows)]);
            break;

        // GET /financeiro – resumo financeiro
        case 'GET:financeiro':
            $ini = $_GET['ini'] ?? date('Y-m-01');
            $fim = $_GET['fim'] ?? date('Y-m-d');
            $r   = $conn->query("
                SELECT COUNT(*) AS pedidos, COALESCE(SUM(total),0) AS receita,
                       COALESCE(AVG(total),0) AS ticket_medio,
                       SUM(CASE WHEN status='cancelado' THEN 1 ELSE 0 END) AS cancelados
                FROM pedidos
                WHERE DATE(criado_em) BETWEEN '$ini' AND '$fim' AND status!='cancelado'
            ")->fetch_assoc();
            $cats = $conn->query("
                SELECT pr.categoria, SUM(pi.quantidade*pi.preco_unitario) AS receita
                FROM pedido_itens pi
                JOIN produtos pr ON pi.id_produto=pr.id
                JOIN pedidos p ON pi.id_pedido=p.id
                WHERE DATE(p.criado_em) BETWEEN '$ini' AND '$fim' AND p.status!='cancelado'
                GROUP BY pr.categoria ORDER BY receita DESC
            ")->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['periodo'=>['ini'=>$ini,'fim'=>$fim], 'resumo'=>$r, 'por_categoria'=>$cats]);
            break;

        // POST /estoque/entrada – dar entrada via ERP
        case 'POST:estoque/entrada':
            $id_produto = (int)($body['id_produto'] ?? 0);
            $qtd        = (int)($body['quantidade']  ?? 0);
            $motivo     = $body['motivo'] ?? 'Entrada via ERP';
            if (!$id_produto || $qtd <= 0) { http_response_code(400); echo json_encode(['erro'=>'id_produto e quantidade são obrigatórios']); break; }
            $prod = $conn->query("SELECT estoque_atual FROM produtos WHERE id=$id_produto")->fetch_assoc();
            if (!$prod) { http_response_code(404); echo json_encode(['erro'=>'Produto não encontrado']); break; }
            $antes  = (int)$prod['estoque_atual'];
            $depois = $antes + $qtd;
            $conn->query("UPDATE produtos SET estoque_atual=$depois WHERE id=$id_produto");
            $tipo_mov = 'entrada';
            $mot_esc = $conn->real_escape_string($motivo);
            $conn->query("INSERT INTO movimentacoes_estoque (id_produto,tipo,quantidade,estoque_anterior,estoque_novo,motivo) VALUES ($id_produto,'$tipo_mov',$qtd,$antes,$depois,'$mot_esc')");
            echo json_encode(['sucesso'=>true, 'estoque_anterior'=>$antes, 'estoque_novo'=>$depois]);
            break;

        // POST /produtos – criar produto via ERP
        case 'POST:produtos':
            $nome      = $conn->real_escape_string($body['nome'] ?? '');
            $preco     = (float)($body['preco'] ?? 0);
            $categoria = $conn->real_escape_string($body['categoria'] ?? '');
            $descricao = $conn->real_escape_string($body['descricao'] ?? '');
            $estoque   = (int)($body['estoque_inicial'] ?? 0);
            if (!$nome || $preco <= 0) { http_response_code(400); echo json_encode(['erro'=>'nome e preco são obrigatórios']); break; }
            $conn->query("INSERT INTO produtos (nome,descricao,preco,categoria,estoque_atual) VALUES ('$nome','$descricao',$preco,'$categoria',$estoque)");
            $new_id = $conn->insert_id;
            echo json_encode(['sucesso'=>true, 'id'=>$new_id]);
            break;

        // PUT /pedidos/status – atualizar status via ERP
        case 'PUT:pedidos/status':
            $id_pedido = (int)($body['id_pedido'] ?? 0);
            $status    = $conn->real_escape_string($body['status'] ?? '');
            $allowed   = ['pendente','preparando','pronto','entregue','cancelado'];
            if (!$id_pedido || !in_array($status, $allowed)) { http_response_code(400); echo json_encode(['erro'=>'id_pedido e status válido são obrigatórios']); break; }
            $conn->query("UPDATE pedidos SET status='$status' WHERE id=$id_pedido");
            echo json_encode(['sucesso'=>true, 'id'=>$id_pedido, 'status'=>$status]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['erro' => "Endpoint não encontrado: $method:$endpoint", 'endpoints_disponiveis' => [
                'GET produtos','GET pedidos','GET estoque','GET clientes','GET financeiro',
                'POST produtos','POST estoque/entrada','PUT pedidos/status'
            ]]);
    }
    exit;
}

// ── PAINEL WEB (interface admin) ─────────────────────────────
verificar_login('dono');

// Ações do painel
if (isset($_POST['nova_key'])) {
    $nome  = sanitizar_texto($_POST['nome_app']);
    $perms = implode(',', $_POST['permissoes'] ?? ['read']);
    $key   = gerar_api_key();
    $stmt  = $conn->prepare("INSERT INTO erp_api_keys (nome, api_key, permissoes) VALUES (?,?,?)");
    $stmt->bind_param("sss", $nome, $key, $perms);
    $stmt->execute();
    $_SESSION['nova_key'] = $key;
    redirecionar('erp.php', "✅ API Key gerada com sucesso!");
}

if (isset($_GET['revogar'])) {
    $id = (int)$_GET['revogar'];
    $conn->query("UPDATE erp_api_keys SET ativa=0 WHERE id=$id");
    redirecionar('erp.php', "🔒 API Key revogada!");
}

if (isset($_POST['novo_webhook'])) {
    $evento = sanitizar_texto($_POST['evento']);
    $url    = sanitizar_texto($_POST['url_destino']);
    $conn->query("INSERT INTO erp_webhooks (evento, url_destino) VALUES ('$evento', '$url')");
    redirecionar('erp.php', "✅ Webhook configurado!");
}

if (isset($_GET['del_webhook'])) {
    $conn->query("DELETE FROM erp_webhooks WHERE id=" . (int)$_GET['del_webhook']);
    redirecionar('erp.php');
}

$api_keys   = $conn->query("SELECT * FROM erp_api_keys ORDER BY criado_em DESC")->fetch_all(MYSQLI_ASSOC);
$webhooks   = $conn->query("SELECT * FROM erp_webhooks ORDER BY criado_em DESC")->fetch_all(MYSQLI_ASSOC);
$nova_key   = $_SESSION['nova_key'] ?? null; unset($_SESSION['nova_key']);
$msg        = $_SESSION['sucesso'] ?? ''; unset($_SESSION['sucesso']);

$base_url   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http') . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']) . '/erp.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Integração ERP – FarmaVida</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .endpoint-card { background:var(--bg); border-radius:var(--radius-md); padding:14px 16px; margin-bottom:10px; border-left:4px solid var(--primary); display:flex; align-items:center; gap:14px; }
        .method-badge { padding:4px 10px; border-radius:var(--radius-full); font-size:11px; font-weight:800; color:white; min-width:48px; text-align:center; }
        .method-get    { background:#00875a; }
        .method-post   { background:#0052cc; }
        .method-put    { background:#ff8b00; }
        .method-delete { background:#de350b; }
        .endpoint-url { font-family:'Courier New',monospace; font-size:13px; color:var(--dark); font-weight:600; }
        .endpoint-desc { font-size:12px; color:var(--gray); margin-left:auto; }
        .key-card { background:var(--white); border:1px solid var(--light-gray); border-radius:var(--radius-md); padding:16px; margin-bottom:12px; }
        .key-val { font-family:'Courier New',monospace; font-size:12px; background:var(--bg); padding:8px 12px; border-radius:8px; word-break:break-all; cursor:pointer; }
        .nova-key-banner { background:linear-gradient(135deg,#e3fcef,#d1fae5); border:1.5px solid #00875a; border-radius:var(--radius-md); padding:18px 22px; margin-bottom:20px; }
        .erp-compat { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:12px; margin-top:12px; }
        .compat-card { background:var(--white); border:1px solid var(--light-gray); border-radius:var(--radius-md); padding:14px; text-align:center; font-size:12px; font-weight:700; color:var(--dark); }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-container">
            <div class="logo" style="cursor:default;">
                <div class="logo-icon"><i class="fas fa-plug"></i></div>
                Integração ERP
            </div>
            <div class="nav-buttons">
                <a href="painel_dono.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Painel</a>
            </div>
        </div>
    </div>

    <div class="container">

        <?php if ($msg): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div>
        <?php endif; ?>

        <?php if ($nova_key): ?>
            <div class="nova-key-banner">
                <strong style="color:#065f46;display:block;margin-bottom:8px;"><i class="fas fa-key"></i> Sua nova API Key — copie agora, não será exibida novamente!</strong>
                <div class="key-val" onclick="navigator.clipboard.writeText('<?= $nova_key ?>');alert('Copiada!')">
                    <?= $nova_key ?>
                </div>
                <p style="font-size:12px;color:#065f46;margin-top:8px;"><i class="fas fa-info-circle"></i> Clique na chave para copiar. Guarde em local seguro.</p>
            </div>
        <?php endif; ?>

        <!-- COMPATIBILIDADE -->
        <div class="card" style="margin-bottom:20px;">
            <h2><i class="fas fa-handshake"></i> Sistemas Compatíveis</h2>
            <p style="color:var(--gray);font-size:13px;margin-bottom:4px;">A API REST do FarmaVida pode ser conectada a qualquer sistema que suporte integração por HTTP/JSON:</p>
            <div class="erp-compat">
                <div class="compat-card">📊 Conta Azul</div>
                <div class="compat-card">🔵 Bling ERP</div>
                <div class="compat-card">🟡 Omie</div>
                <div class="compat-card">🔶 Totvs</div>
                <div class="compat-card">🟠 SAP B1</div>
                <div class="compat-card">🟢 Netsuite</div>
                <div class="compat-card">⚙️ Zapier</div>
                <div class="compat-card">🔗 Make</div>
                <div class="compat-card">📦 Qualquer REST</div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">

            <!-- API KEYS -->
            <div class="card">
                <h2><i class="fas fa-key"></i> Chaves de API</h2>

                <form method="POST" style="background:var(--bg);padding:18px;border-radius:var(--radius-md);margin-bottom:20px;">
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Nome do sistema integrado *</label>
                        <input type="text" name="nome_app" required placeholder="Ex: Conta Azul, Bling, Power BI...">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-shield-halved"></i> Permissões</label>
                        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:6px;">
                            <?php foreach(['read'=>'Leitura','write'=>'Escrita','estoque'=>'Estoque','financeiro'=>'Financeiro'] as $v=>$l): ?>
                                <label class="checkbox-label" style="font-size:13px;">
                                    <input type="checkbox" name="permissoes[]" value="<?= $v ?>" checked> <?= $l ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button type="submit" name="nova_key" class="btn btn-success"><i class="fas fa-plus"></i> Gerar API Key</button>
                </form>

                <?php if (empty($api_keys)): ?>
                    <p style="text-align:center;color:var(--gray);padding:20px;">Nenhuma chave gerada ainda.</p>
                <?php else: ?>
                    <?php foreach ($api_keys as $k): ?>
                        <div class="key-card">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                                <strong><?= htmlspecialchars($k['nome']) ?></strong>
                                <div style="display:flex;gap:8px;align-items:center;">
                                    <span class="badge badge-<?= $k['ativa']?'success':'danger' ?>"><?= $k['ativa']?'Ativa':'Revogada' ?></span>
                                    <?php if ($k['ativa']): ?>
                                        <a href="?revogar=<?= $k['id'] ?>" class="btn btn-danger" style="padding:5px 10px;font-size:12px;" onclick="return confirm('Revogar esta chave?')"><i class="fas fa-ban"></i></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="key-val" onclick="navigator.clipboard.writeText(this.textContent.trim());mostrarToast('Copiado!')" title="Clique para copiar">
                                <?= $k['ativa'] ? htmlspecialchars($k['api_key']) : '••••••••••••••••••••••••' ?>
                            </div>
                            <div style="font-size:11px;color:var(--gray);margin-top:8px;display:flex;gap:16px;">
                                <span><i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($k['criado_em'])) ?></span>
                                <span><i class="fas fa-clock"></i> <?= $k['ultimo_acesso'] ? date('d/m/Y H:i', strtotime($k['ultimo_acesso'])) : 'Nunca acessada' ?></span>
                                <span><i class="fas fa-shield-halved"></i> <?= htmlspecialchars($k['permissoes']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- WEBHOOKS -->
            <div class="card">
                <h2><i class="fas fa-satellite-dish"></i> Webhooks</h2>
                <p style="color:var(--gray);font-size:13px;margin-bottom:16px;">Configure URLs para receber notificações automáticas quando eventos acontecerem.</p>

                <form method="POST" style="background:var(--bg);padding:18px;border-radius:var(--radius-md);margin-bottom:20px;">
                    <div class="form-group">
                        <label><i class="fas fa-bolt"></i> Evento *</label>
                        <select name="evento" required>
                            <option value="pedido.criado">Pedido Criado</option>
                            <option value="pedido.status_atualizado">Status de Pedido Atualizado</option>
                            <option value="pedido.pago">Pagamento Confirmado</option>
                            <option value="estoque.baixo">Estoque Baixo</option>
                            <option value="estoque.zerado">Produto Sem Estoque</option>
                            <option value="nfe.emitida">NF-e Emitida</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-link"></i> URL de Destino *</label>
                        <input type="url" name="url_destino" required placeholder="https://seu-erp.com/webhook">
                    </div>
                    <button type="submit" name="novo_webhook" class="btn btn-primary"><i class="fas fa-plus"></i> Adicionar Webhook</button>
                </form>

                <?php if (empty($webhooks)): ?>
                    <p style="text-align:center;color:var(--gray);padding:20px;">Nenhum webhook configurado.</p>
                <?php else: ?>
                    <?php foreach ($webhooks as $wh): ?>
                        <div style="background:var(--bg);border-radius:var(--radius-md);padding:12px 14px;margin-bottom:10px;border-left:3px solid var(--primary);display:flex;align-items:center;gap:12px;">
                            <i class="fas fa-bolt" style="color:var(--primary);font-size:16px;flex-shrink:0;"></i>
                            <div style="flex:1;min-width:0;">
                                <strong style="font-size:13px;display:block;"><?= htmlspecialchars($wh['evento']) ?></strong>
                                <span style="font-size:11px;color:var(--gray);word-break:break-all;"><?= htmlspecialchars($wh['url_destino']) ?></span>
                            </div>
                            <a href="?del_webhook=<?= $wh['id'] ?>" class="btn btn-danger" style="padding:6px 10px;font-size:12px;flex-shrink:0;" onclick="return confirm('Remover?')"><i class="fas fa-trash"></i></a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- DOCUMENTAÇÃO API -->
        <div class="card">
            <h2><i class="fas fa-book-open"></i> Documentação da API REST</h2>
            <div class="alert alert-info" style="margin-bottom:20px;">
                <i class="fas fa-circle-info"></i>
                <div>
                    <strong>URL Base:</strong> <code style="background:#fff;padding:2px 8px;border-radius:6px;"><?= $base_url ?></code><br>
                    <strong>Autenticação:</strong> Header <code style="background:#fff;padding:2px 8px;border-radius:6px;">X-Api-Key: sua_api_key</code>
                </div>
            </div>

            <?php
            $endpoints = [
                ['GET',  'produtos',          'Listar todos os produtos com estoque'],
                ['GET',  'pedidos',           'Listar pedidos (?status=pendente &limit=50 &desde=2024-01-01)'],
                ['GET',  'estoque',           'Posição atual do estoque com status de criticidade'],
                ['GET',  'clientes',          'Base completa de clientes'],
                ['GET',  'financeiro',        'Resumo financeiro (?ini=2024-01-01 &fim=2024-12-31)'],
                ['POST', 'produtos',          'Criar produto (body: nome, preco, categoria, estoque_inicial)'],
                ['POST', 'estoque/entrada',   'Entrada de estoque (body: id_produto, quantidade, motivo)'],
                ['PUT',  'pedidos/status',    'Atualizar status do pedido (body: id_pedido, status)'],
            ];
            foreach ($endpoints as [$m, $ep, $desc]): ?>
                <div class="endpoint-card">
                    <span class="method-badge method-<?= strtolower($m) ?>"><?= $m ?></span>
                    <span class="endpoint-url">/erp.php?api=1&endpoint=<?= $ep ?></span>
                    <span class="endpoint-desc"><?= $desc ?></span>
                </div>
            <?php endforeach; ?>

            <div style="margin-top:20px;background:var(--bg);padding:16px;border-radius:var(--radius-md);">
                <strong style="display:block;margin-bottom:10px;color:var(--dark);font-size:14px;"><i class="fas fa-code"></i> Exemplo – cURL</strong>
                <pre style="font-size:12px;color:#065f46;overflow-x:auto;white-space:pre-wrap;">curl -H "X-Api-Key: fv_sua_chave_aqui" \
  "<?= $base_url ?>?api=1&endpoint=pedidos&status=pendente"</pre>
            </div>
        </div>

    </div>

    <script>
        function mostrarToast(msg) {
            const t = document.createElement('div');
            t.className='toast'; t.innerHTML=`<i class="fas fa-check-circle"></i> ${msg}`;
            document.body.appendChild(t);
            setTimeout(()=>{ t.style.opacity='0'; setTimeout(()=>t.remove(),300); },2000);
        }
    </script>
</body>
</html>