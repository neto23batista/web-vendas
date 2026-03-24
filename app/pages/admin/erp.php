<?php
require_once FARMAVIDA_ROOT . '/app/core/bootstrap.php';
require_once FARMAVIDA_ROOT . '/app/core/config.php';
require_once FARMAVIDA_ROOT . '/app/core/helpers.php';
require_once FARMAVIDA_ROOT . '/services/schema_service.php';
require_once FARMAVIDA_ROOT . '/services/estoque_service.php';
require_once FARMAVIDA_ROOT . '/services/pedido_service.php';

function gerar_api_key(): string { return 'fv_' . bin2hex(random_bytes(24)); }
function validar_api_key(string $key, $conn): ?array {
    $stmt = $conn->prepare("SELECT id, nome, permissoes FROM erp_api_keys WHERE api_key = ? AND ativa = 1 LIMIT 1");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $apiKey = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $apiKey ?: null;
}

function api_possui_permissao(array $apiKey, string $permissao): bool {
    $permissoes = array_filter(array_map('trim', explode(',', (string)($apiKey['permissoes'] ?? ''))));
    if (!$permissoes) {
        $permissoes = ['read'];
    }
    return in_array($permissao, $permissoes, true);
}

function api_exigir_permissao(array $apiKey, array $permissoesAceitas): void {
    foreach ($permissoesAceitas as $permissao) {
        if (api_possui_permissao($apiKey, $permissao)) {
            return;
        }
    }

    http_response_code(403);
    echo json_encode(['erro' => 'Permissão insuficiente', 'code' => 403]);
    exit;
}

function validar_url_webhook(string $url): bool {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    $partes = parse_url($url);
    $host = strtolower($partes['host'] ?? '');
    $scheme = strtolower($partes['scheme'] ?? '');
    $isLocal = in_array($host, ['localhost', '127.0.0.1'], true);

    return $scheme === 'https' || $isLocal;
}

$is_api = isset($_GET['api']) || isset($_SERVER['HTTP_X_API_KEY']);
$componentes_erp = ['erp', 'estoque', 'pagamentos', 'nfe'];
if ($is_api) {
    if (schema_componentes_pendentes($conn, $componentes_erp)) {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'erro' => 'Existem migrações pendentes para o módulo ERP. Execute-as no painel administrativo.',
            'code' => 503,
        ]);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    $allowedOrigin = trim((string)getenv('ERP_ALLOWED_ORIGIN'));
    if ($allowedOrigin !== '') {
        header('Access-Control-Allow-Origin: ' . $allowedOrigin);
        header('Vary: Origin');
    }
    header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
    header('Access-Control-Allow-Headers: X-Api-Key, Content-Type');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

    $api_key = trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
    if ($api_key === '') { http_response_code(401); echo json_encode(['erro'=>'Envie a API key no header X-Api-Key','code'=>401]); exit; }
    $api_client = validar_api_key($api_key, $conn);
    if (!$api_client) { http_response_code(401); echo json_encode(['erro'=>'API key inválida','code'=>401]); exit; }
    if (!validar_api_key($api_key, $conn)) { http_response_code(401); echo json_encode(['erro'=>'API key inválida','code'=>401]); exit; }

    $stmt = $conn->prepare("UPDATE erp_api_keys SET ultimo_acesso = NOW() WHERE id = ?");
    $stmt->bind_param("i", $api_client['id']); $stmt->execute(); $stmt->close();

    $endpoint = $_GET['endpoint'] ?? '';
    $method   = $_SERVER['REQUEST_METHOD'];
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];

     
    $status_validos = ['pendente','preparando','pronto','entregue','cancelado'];

    switch("$method:$endpoint") {

        case 'GET:produtos':
            api_exigir_permissao($api_client, ['read']);
            $rows = $conn->query("SELECT id,nome,descricao,preco,categoria,disponivel,estoque_atual,estoque_minimo,unidade,localizacao FROM produtos ORDER BY nome")->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['data'=>$rows,'total'=>count($rows)]); break;

        case 'GET:pedidos':
            api_exigir_permissao($api_client, ['read']);
            $status = $_GET['status'] ?? '';
            $limit  = min((int)($_GET['limit'] ?? 50), 200);
            $desde  = $_GET['desde'] ?? '';

            $conds=[]; $params=[]; $types='';
             
            if ($status !== '' && in_array($status, $status_validos, true)) {
                $conds[]="p.status=?"; $params[]=$status; $types.='s';
            }
             
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
            api_exigir_permissao($api_client, ['read', 'estoque']);
            $rows = $conn->query("SELECT id,nome,categoria,estoque_atual,estoque_minimo,estoque_maximo,unidade,localizacao,CASE WHEN estoque_atual=0 THEN 'zerado' WHEN estoque_atual<=estoque_minimo THEN 'baixo' ELSE 'normal' END AS situacao FROM produtos ORDER BY estoque_atual ASC")->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['data'=>$rows,'total'=>count($rows)]); break;

        case 'GET:clientes':
            api_exigir_permissao($api_client, ['read']);
            $rows = $conn->query("SELECT id,nome,email,telefone,endereco,criado_em FROM usuarios WHERE tipo='cliente' ORDER BY nome")->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['data'=>$rows,'total'=>count($rows)]); break;

        case 'GET:financeiro':
            api_exigir_permissao($api_client, ['financeiro']);
             
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
            api_exigir_permissao($api_client, ['estoque']);
            $id_produto = (int)($body['id_produto'] ?? 0);
            $qtd        = (int)($body['quantidade']  ?? 0);
            $motivo     = substr(strip_tags($body['motivo'] ?? 'Entrada via ERP'), 0, 255);
            try {
                if (!$id_produto || $qtd <= 0) {
                    http_response_code(400);
                    echo json_encode(['erro' => 'id_produto e quantidade sao obrigatorios']);
                    break;
                }

                $movimento = estoque_registrar_entrada($conn, $id_produto, $qtd, $motivo, null);
                echo json_encode([
                    'sucesso' => true,
                    'estoque_anterior' => $movimento['antes'],
                    'estoque_novo' => $movimento['depois'],
                ]);
            } catch (RuntimeException $e) {
                http_response_code(400);
                echo json_encode(['erro' => $e->getMessage()]);
            } catch (Throwable $e) {
                http_response_code(500);
                echo json_encode(['erro' => 'Falha interna ao atualizar estoque.']);
            }
            break;
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
            api_exigir_permissao($api_client, ['write']);
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
            api_exigir_permissao($api_client, ['write']);
            $id_pedido = (int)($body['id_pedido'] ?? 0);
            $status    = $body['status'] ?? '';
            try {
                if (!$id_pedido || !in_array($status, $status_validos, true)) {
                    http_response_code(400);
                    echo json_encode(['erro' => 'id_pedido e status valido sao obrigatorios']);
                    break;
                }

                pedido_atualizar_status($conn, $id_pedido, $status, null);
                echo json_encode(['sucesso' => true, 'id' => $id_pedido, 'status' => $status]);
            } catch (RuntimeException $e) {
                http_response_code(400);
                echo json_encode(['erro' => $e->getMessage()]);
            } catch (Throwable $e) {
                http_response_code(500);
                echo json_encode(['erro' => 'Falha interna ao atualizar pedido.']);
            }
            break;
            if (!$id_pedido || !in_array($status, $status_validos, true)) { http_response_code(400); echo json_encode(['erro'=>'id_pedido e status válido são obrigatórios']); break; }
            $st=$conn->prepare("UPDATE pedidos SET status=? WHERE id=?"); $st->bind_param("si",$status,$id_pedido); $st->execute(); $st->close();
            echo json_encode(['sucesso'=>true,'id'=>$id_pedido,'status'=>$status]); break;

        default:
            http_response_code(404);
            echo json_encode(['erro'=>"Endpoint não encontrado: $method:$endpoint",'endpoints'=>['GET produtos','GET pedidos','GET estoque','GET clientes','GET financeiro','POST produtos','POST estoque/entrada','PUT pedidos/status']]);
    }
    exit;
}

 
verificar_login('dono');

if (schema_componentes_pendentes($conn, $componentes_erp)) {
    redirecionar(
        'migracoes.php',
        'Existem migrações pendentes para o módulo ERP. Execute-as antes de usar esta tela.',
        'erro'
    );
}

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
if (isset($_POST['revogar_key'])) {
    verificar_csrf();
    $id = (int)($_POST['id_key'] ?? 0);
    $stmt = $conn->prepare("UPDATE erp_api_keys SET ativa = 0 WHERE id = ?");
    $stmt->bind_param("i", $id); $stmt->execute(); $stmt->close();
    redirecionar('erp.php', "🔒 API Key revogada!");
}
if (isset($_POST['novo_webhook'])) {
    verificar_csrf();
    $evento = sanitizar_texto($_POST['evento'] ?? '');
    $url    = sanitizar_texto($_POST['url_destino'] ?? '');
    if (!validar_url_webhook($url)) {
        redirecionar('erp.php', 'Informe uma URL de webhook válida. Use HTTPS fora de localhost.', 'erro');
    }
    $stmt = $conn->prepare("INSERT INTO erp_webhooks (evento, url_destino) VALUES (?,?)");
    $stmt->bind_param("ss", $evento, $url); $stmt->execute(); $stmt->close();
    redirecionar('erp.php', "✅ Webhook configurado!");
}
if (isset($_POST['remover_webhook'])) {
    verificar_csrf();
    $id = (int)($_POST['id_webhook'] ?? 0);
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
<?php if (isset($_SESSION['erro'])): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?=htmlspecialchars($_SESSION['erro'])?></div><?php unset($_SESSION['erro']); endif; ?>

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
              <form method="POST" style="display:inline-flex;">
                <?= campo_csrf() ?>
                <input type="hidden" name="id_key" value="<?=(int)$k['id']?>">
                <button type="submit" name="revogar_key" class="btn btn-danger" style="padding:5px 10px;font-size:12px;" onclick="return confirm('Revogar?')"><i class="fas fa-ban"></i></button>
              </form>
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
        <form method="POST" style="display:inline-flex;flex-shrink:0;">
          <?= campo_csrf() ?>
          <input type="hidden" name="id_webhook" value="<?=(int)$wh['id']?>">
          <button type="submit" name="remover_webhook" class="btn btn-danger" style="padding:6px 10px;font-size:12px;" onclick="return confirm('Remover?')"><i class="fas fa-trash"></i></button>
        </form>
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
