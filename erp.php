<?php
session_start();
include "config.php";
include "helpers.php";

function gerar_api_key(): string { return 'fv_' . bin2hex(random_bytes(24)); }
function validar_api_key(string $key, $conn): bool {
    $stmt = $conn->prepare("SELECT id FROM erp_api_keys WHERE api_key = ? AND ativa = 1");
    $stmt->bind_param("s", $key); $stmt->execute();
    $ok = $stmt->get_result()->num_rows > 0; $stmt->close(); return $ok;
}

$conn->query("CREATE TABLE IF NOT EXISTS erp_api_keys (id INT PRIMARY KEY AUTO_INCREMENT,nome VARCHAR(100) NOT NULL,api_key VARCHAR(100) UNIQUE NOT NULL,ativa TINYINT(1) DEFAULT 1,permissoes TEXT,ultimo_acesso TIMESTAMP NULL,criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
$conn->query("CREATE TABLE IF NOT EXISTS erp_webhooks (id INT PRIMARY KEY AUTO_INCREMENT,evento VARCHAR(60) NOT NULL,url_destino VARCHAR(500) NOT NULL,ativa TINYINT(1) DEFAULT 1,tentativas INT DEFAULT 0,ultimo_disparo TIMESTAMP NULL,criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
$conn->query("CREATE TABLE IF NOT EXISTS erp_webhook_logs (id INT PRIMARY KEY AUTO_INCREMENT,id_webhook INT,evento VARCHAR(60),payload TEXT,resposta TEXT,http_status INT,criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");

// Auto-migração colunas
$cols_p = array_column($conn->query("SHOW COLUMNS FROM produtos")->fetch_all(MYSQLI_ASSOC), 'Field');
$cols_ped = array_column($conn->query("SHOW COLUMNS FROM pedidos")->fetch_all(MYSQLI_ASSOC), 'Field');
$auto = ['produtos'=>['estoque_atual'=>"ALTER TABLE produtos ADD COLUMN estoque_atual INT NOT NULL DEFAULT 0",'estoque_minimo'=>"ALTER TABLE produtos ADD COLUMN estoque_minimo INT NOT NULL DEFAULT 5",'estoque_maximo'=>"ALTER TABLE produtos ADD COLUMN estoque_maximo INT NOT NULL DEFAULT 999",'unidade'=>"ALTER TABLE produtos ADD COLUMN unidade VARCHAR(20) DEFAULT 'un'",'localizacao'=>"ALTER TABLE produtos ADD COLUMN localizacao VARCHAR(60) DEFAULT NULL",'ncm'=>"ALTER TABLE produtos ADD COLUMN ncm VARCHAR(8) DEFAULT '30049099'",'cfop'=>"ALTER TABLE produtos ADD COLUMN cfop VARCHAR(4) DEFAULT '5102'"],'pedidos'=>['forma_pagamento'=>"ALTER TABLE pedidos ADD COLUMN forma_pagamento ENUM('presencial','app') DEFAULT 'presencial'",'pagamento_status'=>"ALTER TABLE pedidos ADD COLUMN pagamento_status ENUM('pendente','aprovado','recusado','em_analise','cancelado') DEFAULT 'pendente'",'nfe_numero'=>"ALTER TABLE pedidos ADD COLUMN nfe_numero VARCHAR(9) DEFAULT NULL",'nfe_serie'=>"ALTER TABLE pedidos ADD COLUMN nfe_serie VARCHAR(3) DEFAULT '001'",'nfe_chave'=>"ALTER TABLE pedidos ADD COLUMN nfe_chave VARCHAR(45) DEFAULT NULL",'nfe_status'=>"ALTER TABLE pedidos ADD COLUMN nfe_status ENUM('pendente','emitida','cancelada') DEFAULT 'pendente'",'nfe_emitida_em'=>"ALTER TABLE pedidos ADD COLUMN nfe_emitida_em TIMESTAMP NULL"]];
foreach($auto['produtos'] as $col=>$sql){ if(!in_array($col,$cols_p)) $conn->query($sql); }
foreach($auto['pedidos']  as $col=>$sql){ if(!in_array($col,$cols_ped)) $conn->query($sql); }

// ── API REST ──────────────────────────────────────────────────
$is_api = isset($_GET['api']) || isset($_SERVER['HTTP_X_API_KEY']);
if ($is_api) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
    header('Access-Control-Allow-Headers: X-Api-Key, Content-Type');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

    $api_key = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
    if (!validar_api_key($api_key, $conn)) { http_response_code(401); echo json_encode(['erro'=>'API key inválida','code'=>401]); exit; }

    $stmt = $conn->prepare("UPDATE erp_api_keys SET ultimo_acesso = NOW() WHERE api_key = ?");
    $stmt->bind_param("s", $api_key); $stmt->execute(); $stmt->close();

    $endpoint = $_GET['endpoint'] ?? '';
    $method   = $_SERVER['REQUEST_METHOD'];
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];

    // Status válidos para pedidos
    $status_validos = ['pendente','preparando','pronto','entregue','cancelado'];

    switch("$method:$endpoint") {

        case 'GET:produtos':
            $rows = $conn->query("SELECT id,nome,descricao,preco,categoria,disponivel,estoque_atual,estoque_minimo,unidade,localizacao FROM produtos ORDER BY nome")->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['data'=>$rows,'total'=>count($rows)]); break;

        case 'GET:pedidos':
            $status = $_GET['status'] ?? '';
            $limit  = min((int)($_GET['limit'] ?? 50), 200);
            $desde  = $_GET['desde'] ?? '';

            $conds=[]; $params=[]; $types='';
            // status: whitelist
            if ($status !== '' && in_array($status, $status_validos, true)) {
                $conds[]="p.status=?"; $params[]=$status; $types.='s';
            }
            // desde: valida formato de data
            if ($desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $desde)) {
                $conds[]="p.criado_em > ?"; $params[]=$desde; $types.='s';
            }
            $where = $conds ? 'WHERE '.implode(' AND ',$conds) : '';
            $params[] = $limit; $types .= 'i';
            $sql = "SELECT p.id,p.total,p.status,p.tipo_retirada,p.forma_pagamento,p.pagamento_status,p.observacoes,p.criado_em,u.nome AS cliente,u.email,u.telefone FROM pedidos p JOIN usuarios u ON p.id_cliente=u.id $where ORDER BY p.criado_em DESC LIMIT ?";
            $st = $conn->prepare($sql); $st->bind_param($types,...$params); $st->execute();
            $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();
            foreach ($rows as &$r) {
                $id_r = (int)$r['id'];
                $si = $conn->prepare("SELECT pi.quantidade,pi.preco_unitario,pr.nome,pr.categoria FROM pedido_itens pi JOIN produtos pr ON pi.id_produto=pr.id WHERE pi.id_pedido=?");
                $si->bind_param("i",$id_r); $si->execute();
                $r['itens'] = $si->get_result()->fetch_all(MYSQLI_ASSOC); $si->close();
            }
            echo json_encode(['data'=>$rows,'total'=>count($rows)]); break;

        case 'GET:estoque':
            $rows = $conn->query("SELECT id,nome,categoria,estoque_atual,estoque_minimo,estoque_maximo,unidade,localizacao,CASE WHEN estoque_atual=0 THEN 'zerado' WHEN estoque_atual<=estoque_minimo THEN 'baixo' ELSE 'normal' END AS situacao FROM produtos ORDER BY estoque_atual ASC")->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['data'=>$rows,'total'=>count($rows)]); break;

        case 'GET:clientes':
            $rows = $conn->query("SELECT id,nome,email,telefone,endereco,criado_em FROM usuarios WHERE tipo='cliente' ORDER BY nome")->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['data'=>$rows,'total'=>count($rows)]); break;

        case 'GET:financeiro':
            // Datas: valida formato antes de usar
            $ini = $_GET['ini'] ?? date('Y-m-01');
            $fim = $_GET['fim'] ?? date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ini)) $ini = date('Y-m-01');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fim)) $fim = date('Y-m-d');

            $st = $conn->prepare("SELECT COUNT(*) AS pedidos,COALESCE(SUM(total),0) AS receita,COALESCE(AVG(total),0) AS ticket_medio,SUM(CASE WHEN status='cancelado' THEN 1 ELSE 0 END) AS cancelados FROM pedidos WHERE DATE(criado_em) BETWEEN ? AND ? AND status!='cancelado'");
            $st->bind_param("ss",$ini,$fim); $st->execute(); $resumo=$st->get_result()->fetch_assoc(); $st->close();
            $st = $conn->prepare("SELECT pr.categoria,SUM(pi.quantidade*pi.preco_unitario) AS receita,SUM(pi.quantidade) AS qtd FROM pedido_itens pi JOIN produtos pr ON pi.id_produto=pr.id JOIN pedidos p ON pi.id_pedido=p.id WHERE DATE(p.criado_em) BETWEEN ? AND ? AND p.status!='cancelado' GROUP BY pr.categoria ORDER BY receita DESC");
            $st->bind_param("ss",$ini,$fim); $st->execute(); $cats=$st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();
            echo json_encode(['periodo'=>['ini'=>$ini,'fim'=>$fim],'resumo'=>$resumo,'por_categoria'=>$cats]); break;

        case 'POST:estoque/entrada':
            $id_produto = (int)($body['id_produto'] ?? 0);
            $qtd        = (int)($body['quantidade']  ?? 0);
            $motivo     = substr(strip_tags($body['motivo'] ?? 'Entrada via ERP'), 0, 255);
            if (!$id_produto || $qtd <= 0) { http_response_code(400); echo json_encode(['erro'=>'id_produto e quantidade são obrigatórios']); break; }
            $st=$conn->prepare("SELECT estoque_atual FROM produtos WHERE id=?"); $st->bind_param("i",$id_produto); $st->execute();
            $prod=$st->get_result()->fetch_assoc(); $st->close();
            if (!$prod) { http_response_code(404); echo json_encode(['erro'=>'Produto não encontrado']); break; }
            $antes=(int)$prod['estoque_atual']; $depois=$antes+$qtd;
            $st=$conn->prepare("UPDATE produtos SET estoque_atual=? WHERE id=?"); $st->bind_param("ii",$depois,$id_produto); $st->execute(); $st->close();
            $tipo_m='entrada';
            $st=$conn->prepare("INSERT INTO movimentacoes_estoque (id_produto,tipo,quantidade,estoque_anterior,estoque_novo,motivo) VALUES (?,?,?,?,?,?)");
            $st->bind_param("isiiss",$id_produto,$tipo_m,$qtd,$antes,$depois,$motivo); $st->execute(); $st->close();
            echo json_encode(['sucesso'=>true,'estoque_anterior'=>$antes,'estoque_novo'=>$depois]); break;

        case 'POST:produtos':
            $nome      = substr(strip_tags($body['nome']      ?? ''), 0, 150);
            $preco     = (float)($body['preco']    ?? 0);
            $categoria = substr(strip_tags($body['categoria'] ?? ''), 0, 60);
            $descricao = substr(strip_tags($body['descricao'] ?? ''), 0, 1000);
            $estoque   = (int)($body['estoque_inicial'] ?? 0);
            if (!$nome || $preco <= 0) { http_response_code(400); echo json_encode(['erro'=>'nome e preco são obrigatórios']); break; }
            $st=$conn->prepare("INSERT INTO produtos (nome,descricao,preco,categoria,estoque_atual) VALUES (?,?,?,?,?)");
            $st->bind_param("ssdsi",$nome,$descricao,$preco,$categoria,$estoque); $st->execute();
            echo json_encode(['sucesso'=>true,'id'=>$conn->insert_id]); $st->close(); break;

        case 'PUT:pedidos/status':
            $id_pedido = (int)($body['id_pedido'] ?? 0);
            $status    = $body['status'] ?? '';
            if (!$id_pedido || !in_array($status, $status_validos, true)) { http_response_code(400); echo json_encode(['erro'=>'id_pedido e status válido são obrigatórios']); break; }
            $st=$conn->prepare("UPDATE pedidos SET status=? WHERE id=?"); $st->bind_param("si",$status,$id_pedido); $st->execute(); $st->close();
            echo json_encode(['sucesso'=>true,'id'=>$id_pedido,'status'=>$status]); break;

        default:
            http_response_code(404);
            echo json_encode(['erro'=>"Endpoint não encontrado: $method:$endpoint",'endpoints'=>['GET produtos','GET pedidos','GET estoque','GET clientes','GET financeiro','POST produtos','POST estoque/entrada','PUT pedidos/status']]);
    }
    exit;
}

// ── PAINEL WEB ────────────────────────────────────────────────
verificar_login('dono');

if (isset($_POST['nova_key'])) {
    verificar_csrf();
    $nome  = sanitizar_texto($_POST['nome_app'] ?? '');
    $perms = implode(',', array_filter($_POST['permissoes'] ?? ['read'], fn($p) => in_array($p,['read','write','estoque','financeiro'],true)));
    $key   = gerar_api_key();
    $stmt  = $conn->prepare("INSERT INTO erp_api_keys (nome, api_key, permissoes) VALUES (?,?,?)");
    $stmt->bind_param("sss", $nome, $key, $perms); $stmt->execute(); $stmt->close();
    $_SESSION['nova_key'] = $key;
    redirecionar('erp.php', "✅ API Key gerada com sucesso!");
}
if (isset($_GET['revogar'])) {
    $id = (int)$_GET['revogar'];
    $stmt = $conn->prepare("UPDATE erp_api_keys SET ativa = 0 WHERE id = ?");
    $stmt->bind_param("i", $id); $stmt->execute(); $stmt->close();
    redirecionar('erp.php', "🔒 API Key revogada!");
}
if (isset($_POST['novo_webhook'])) {
    verificar_csrf();
    $evento = sanitizar_texto($_POST['evento'] ?? '');
    $url    = sanitizar_texto($_POST['url_destino'] ?? '');
    $stmt = $conn->prepare("INSERT INTO erp_webhooks (evento, url_destino) VALUES (?,?)");
    $stmt->bind_param("ss", $evento, $url); $stmt->execute(); $stmt->close();
    redirecionar('erp.php', "✅ Webhook configurado!");
}
if (isset($_GET['del_webhook'])) {
    $id = (int)$_GET['del_webhook'];
    $stmt = $conn->prepare("DELETE FROM erp_webhooks WHERE id = ?");
    $stmt->bind_param("i", $id); $stmt->execute(); $stmt->close();
    redirecionar('erp.php');
}

$api_keys = $conn->query("SELECT * FROM erp_api_keys ORDER BY criado_em DESC")->fetch_all(MYSQLI_ASSOC);
$webhooks = $conn->query("SELECT * FROM erp_webhooks ORDER BY criado_em DESC")->fetch_all(MYSQLI_ASSOC);
$nova_key = $_SESSION['nova_key'] ?? null; unset($_SESSION['nova_key']);
$msg      = $_SESSION['sucesso'] ?? ''; unset($_SESSION['sucesso']);

$base_url = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https':'http')."://$_SERVER[HTTP_HOST]".dirname($_SERVER['PHP_SELF']).'/erp.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Integração ERP – FarmaVida</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="style.css?v=1774207549">
<style>
.endpoint-card{background:var(--bg);border-radius:var(--radius-md);padding:14px 16px;margin-bottom:10px;border-left:4px solid var(--primary);display:flex;align-items:center;gap:14px;}
.method-badge{padding:4px 10px;border-radius:var(--radius-full);font-size:11px;font-weight:800;color:white;min-width:48px;text-align:center;}
.method-get{background:#00875a;}.method-post{background:#0052cc;}.method-put{background:#ff8b00;}.method-delete{background:#de350b;}
.endpoint-url{font-family:'Courier New',monospace;font-size:13px;color:var(--dark);font-weight:600;}
.endpoint-desc{font-size:12px;color:var(--gray);margin-left:auto;}
.key-card{background:var(--white);border:1px solid var(--light-gray);border-radius:var(--radius-md);padding:16px;margin-bottom:12px;}
.key-val{font-family:'Courier New',monospace;font-size:12px;background:var(--bg);padding:8px 12px;border-radius:8px;word-break:break-all;cursor:pointer;}
.nova-key-banner{background:linear-gradient(135deg,#e3fcef,#d1fae5);border:1.5px solid #00875a;border-radius:var(--radius-md);padding:18px 22px;margin-bottom:20px;}
.erp-compat{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px;margin-top:12px;}
.compat-card{background:var(--white);border:1px solid var(--light-gray);border-radius:var(--radius-md);padding:14px;text-align:center;font-size:12px;font-weight:700;color:var(--dark);}
</style>
</head>
<body>
<div class="header"><div class="header-container">
  <div class="logo" style="cursor:default;"><div class="logo-icon"><i class="fas fa-plug"></i></div>Integração ERP</div>
  <div class="nav-buttons"><a href="painel_dono.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Painel</a></div>
</div></div>

<div class="container">
<?php if($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?=htmlspecialchars($msg)?></div><?php endif; ?>

<?php if($nova_key): ?>
<div class="nova-key-banner">
  <strong style="color:#065f46;display:block;margin-bottom:8px;"><i class="fas fa-key"></i> Sua nova API Key — copie agora, não será exibida novamente!</strong>
  <div class="key-val" onclick="navigator.clipboard.writeText('<?=htmlspecialchars($nova_key)?>');alert('Copiada!')"><?=htmlspecialchars($nova_key)?></div>
  <p style="font-size:12px;color:#065f46;margin-top:8px;"><i class="fas fa-info-circle"></i> Clique para copiar. Guarde em local seguro.</p>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:20px;">
  <h2><i class="fas fa-handshake"></i> Sistemas Compatíveis</h2>
  <p style="color:var(--gray);font-size:13px;">A API REST do FarmaVida conecta com qualquer sistema que suporte HTTP/JSON:</p>
  <div class="erp-compat">
    <div class="compat-card">📊 Conta Azul</div><div class="compat-card">🔵 Bling ERP</div>
    <div class="compat-card">🟡 Omie</div><div class="compat-card">🔶 Totvs</div>
    <div class="compat-card">🟠 SAP B1</div><div class="compat-card">⚙️ Zapier</div>
    <div class="compat-card">🔗 Make</div><div class="compat-card">📦 Qualquer REST</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">

  <div class="card">
    <h2><i class="fas fa-key"></i> Chaves de API</h2>
    <form method="POST" style="background:var(--bg);padding:18px;border-radius:var(--radius-md);margin-bottom:20px;">
      <?=campo_csrf()?>
      <div class="form-group"><label><i class="fas fa-tag"></i> Nome do sistema *</label><input type="text" name="nome_app" required placeholder="Ex: Conta Azul, Bling..."></div>
      <div class="form-group"><label><i class="fas fa-shield-halved"></i> Permissões</label>
        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:6px;">
          <?php foreach(['read'=>'Leitura','write'=>'Escrita','estoque'=>'Estoque','financeiro'=>'Financeiro'] as $v=>$l): ?>
            <label class="checkbox-label" style="font-size:13px;"><input type="checkbox" name="permissoes[]" value="<?=$v?>" checked> <?=$l?></label>
          <?php endforeach; ?>
        </div>
      </div>
      <button type="submit" name="nova_key" class="btn btn-success"><i class="fas fa-plus"></i> Gerar API Key</button>
    </form>
    <?php if(empty($api_keys)): ?>
      <p style="text-align:center;color:var(--gray);padding:20px;">Nenhuma chave gerada ainda.</p>
    <?php else: ?>
      <?php foreach($api_keys as $k): ?>
      <div class="key-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
          <strong><?=htmlspecialchars($k['nome'])?></strong>
          <div style="display:flex;gap:8px;align-items:center;">
            <span class="badge badge-<?=$k['ativa']?'success':'danger'?>"><?=$k['ativa']?'Ativa':'Revogada'?></span>
            <?php if($k['ativa']): ?>
              <a href="?revogar=<?=(int)$k['id']?>" class="btn btn-danger" style="padding:5px 10px;font-size:12px;" onclick="return confirm('Revogar?')"><i class="fas fa-ban"></i></a>
            <?php endif; ?>
          </div>
        </div>
        <div class="key-val" onclick="navigator.clipboard.writeText(this.textContent.trim());mostrarToast('Copiado!')" title="Clique para copiar">
          <?=$k['ativa']?htmlspecialchars($k['api_key']):'••••••••••••••••••••••••'?>
        </div>
        <div style="font-size:11px;color:var(--gray);margin-top:8px;display:flex;gap:16px;">
          <span><i class="fas fa-calendar"></i> <?=date('d/m/Y',strtotime($k['criado_em']))?></span>
          <span><i class="fas fa-clock"></i> <?=$k['ultimo_acesso']?date('d/m/Y H:i',strtotime($k['ultimo_acesso'])):'Nunca'?></span>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2><i class="fas fa-satellite-dish"></i> Webhooks</h2>
    <p style="color:var(--gray);font-size:13px;margin-bottom:16px;">Receba notificações automáticas em sua URL quando eventos ocorrerem.</p>
    <form method="POST" style="background:var(--bg);padding:18px;border-radius:var(--radius-md);margin-bottom:20px;">
      <?=campo_csrf()?>
      <div class="form-group"><label><i class="fas fa-bolt"></i> Evento *</label>
        <select name="evento" required>
          <option value="pedido.criado">Pedido Criado</option>
          <option value="pedido.status_atualizado">Status Atualizado</option>
          <option value="pedido.pago">Pagamento Confirmado</option>
          <option value="estoque.baixo">Estoque Baixo</option>
          <option value="estoque.zerado">Produto Sem Estoque</option>
          <option value="nfe.emitida">NF-e Emitida</option>
        </select>
      </div>
      <div class="form-group"><label><i class="fas fa-link"></i> URL de Destino *</label><input type="url" name="url_destino" required placeholder="https://seu-erp.com/webhook"></div>
      <button type="submit" name="novo_webhook" class="btn btn-primary"><i class="fas fa-plus"></i> Adicionar Webhook</button>
    </form>
    <?php if(empty($webhooks)): ?><p style="text-align:center;color:var(--gray);padding:20px;">Nenhum webhook configurado.</p>
    <?php else: ?>
      <?php foreach($webhooks as $wh): ?>
      <div style="background:var(--bg);border-radius:var(--radius-md);padding:12px 14px;margin-bottom:10px;border-left:3px solid var(--primary);display:flex;align-items:center;gap:12px;">
        <i class="fas fa-bolt" style="color:var(--primary);font-size:16px;flex-shrink:0;"></i>
        <div style="flex:1;min-width:0;">
          <strong style="font-size:13px;display:block;"><?=htmlspecialchars($wh['evento'])?></strong>
          <span style="font-size:11px;color:var(--gray);word-break:break-all;"><?=htmlspecialchars($wh['url_destino'])?></span>
        </div>
        <a href="?del_webhook=<?=(int)$wh['id']?>" class="btn btn-danger" style="padding:6px 10px;font-size:12px;flex-shrink:0;" onclick="return confirm('Remover?')"><i class="fas fa-trash"></i></a>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <h2><i class="fas fa-book-open"></i> Documentação da API REST</h2>
  <div class="alert alert-info" style="margin-bottom:20px;">
    <i class="fas fa-circle-info"></i>
    <div><strong>URL Base:</strong> <code style="background:#fff;padding:2px 8px;border-radius:6px;"><?=htmlspecialchars($base_url)?></code><br>
    <strong>Autenticação:</strong> Header <code style="background:#fff;padding:2px 8px;border-radius:6px;">X-Api-Key: sua_api_key</code></div>
  </div>
  <?php foreach([['GET','produtos','Listar produtos com estoque'],['GET','pedidos','Listar pedidos (?status= &limit= &desde=)'],['GET','estoque','Posição de estoque com criticidade'],['GET','clientes','Base de clientes'],['GET','financeiro','Resumo financeiro (?ini= &fim=)'],['POST','produtos','Criar produto (nome, preco, categoria)'],['POST','estoque/entrada','Entrada de estoque (id_produto, quantidade)'],['PUT','pedidos/status','Atualizar status (id_pedido, status)']] as [$m,$ep,$desc]): ?>
    <div class="endpoint-card">
      <span class="method-badge method-<?=strtolower($m)?>"><?=$m?></span>
      <span class="endpoint-url">?api=1&endpoint=<?=$ep?></span>
      <span class="endpoint-desc"><?=$desc?></span>
    </div>
  <?php endforeach; ?>
  <div style="margin-top:20px;background:var(--bg);padding:16px;border-radius:var(--radius-md);">
    <strong style="display:block;margin-bottom:10px;font-size:14px;"><i class="fas fa-code"></i> Exemplo – cURL</strong>
    <pre style="font-size:12px;color:#065f46;overflow-x:auto;white-space:pre-wrap;">curl -H "X-Api-Key: fv_sua_chave" "<?=htmlspecialchars($base_url)?>?api=1&endpoint=pedidos&status=pendente"</pre>
  </div>
</div>
</div>

<script>
function mostrarToast(msg){const t=document.createElement('div');t.className='toast';t.innerHTML=`<i class="fas fa-check-circle"></i> ${msg}`;document.body.appendChild(t);setTimeout(()=>{t.style.opacity='0';setTimeout(()=>t.remove(),300);},2000);}
</script>
</body>
</html>
