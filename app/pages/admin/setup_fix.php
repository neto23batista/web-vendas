<?php
 
 
 
 
 

 
$css_patch = <<<'MOBILECSS'

 



 
@media(max-width:700px){
   
  .header .logo > span:not(:first-child){ display:none; }
  .header .logo > button,
  .header .logo > .auto-update-badge { display:none !important; }

   
  .nav-buttons .btn > i ~ * { display:none; }

   
  .stats-grid {
    grid-template-columns: 1fr 1fr;
  }
   
  .stats-grid > .stat-card:last-child:nth-child(odd) {
    grid-column: 1 / -1;
  }

   
  .alert-estoque-inner {
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 10px !important;
  }
  .alert-estoque-inner .btn {
    margin-left: 0 !important;
    justify-content: center;
    width: 100%;
  }

   
  .pedido-header {
    flex-direction: column;
    gap: 10px;
  }

   
  .status-form {
    flex-direction: column;
  }
  .status-form select,
  .status-form a.btn,
  .status-form button {
    width: 100%;
    justify-content: center;
  }
}

@media(max-width:400px){
   
  .stats-grid {
    grid-template-columns: 1fr 1fr;
  }
  .logo { font-size:16px; }
  .logo .logo-icon { width:30px; height:30px; font-size:13px; }
}
MOBILECSS;

$css_file = FARMAVIDA_ROOT . '/style.css';
$atual = file_get_contents($css_file);

 
$atual = preg_replace('/\/\* ═+\s*MOBILE FIX PATCH.*$/s', '', $atual);
$atual = rtrim($atual) . "
" . $css_patch;
file_put_contents($css_file, $atual);

 
$painel = FARMAVIDA_ROOT . '/app/pages/admin/painel_dono.php';
$html   = file_get_contents($painel);

 
$old = <<<'OLDJS'
                    banner.innerHTML = `
                        <div style="background:linear-gradient(135deg,#fef3c7,#fde68a);border:1.5px solid #f59e0b;border-radius:var(--radius-md);padding:16px 20px;margin-bottom:24px;display:flex;align-items:flex-start;gap:14px;">
                            <i class="fas fa-triangle-exclamation" style="font-size:22px;color:#d97706;flex-shrink:0;margin-top:2px;"></i>
                            <div style="flex:1;">
                                <strong style="color:#92400e;display:block;margin-bottom:8px;">⚠️ Estoque crítico: ${data.zerados} produto(s) zerado(s), ${data.baixos} abaixo do mínimo</strong>
                                <div style="display:flex;gap:8px;flex-wrap:wrap;">${itensHtml}</div>
                            </div>
                            <a href="estoque.php" class="btn btn-warning" style="padding:8px 16px;font-size:13px;flex-shrink:0;">
                                <i class="fas fa-boxes-stacked"></i> Gerenciar Estoque
                            </a>
                        </div>`;
OLDJS;

$new = <<<'NEWJS'
                    banner.innerHTML = `
                        <div style="background:rgba(255,184,48,.1);border:1px solid rgba(255,184,48,.3);border-radius:var(--radius-md);padding:14px 16px;margin-bottom:16px;">
                            <div class="alert-estoque-inner" style="display:flex;align-items:flex-start;gap:12px;flex-wrap:wrap;">
                                <i class="fas fa-triangle-exclamation" style="font-size:18px;color:var(--warning);flex-shrink:0;margin-top:2px;"></i>
                                <div style="flex:1;min-width:180px;">
                                    <strong style="color:var(--warning);display:block;margin-bottom:6px;font-size:13px;">Estoque crítico: ${data.zerados} zerado(s), ${data.baixos} abaixo do mínimo</strong>
                                    <div style="display:flex;gap:6px;flex-wrap:wrap;">${itensHtml}</div>
                                </div>
                                <a href="estoque.php" class="btn btn-warning" style="font-size:12px;padding:0 14px;min-height:38px;flex-shrink:0;">
                                    <i class="fas fa-boxes-stacked"></i> Gerenciar Estoque
                                </a>
                            </div>
                        </div>`;
NEWJS;

if (str_contains($html, trim(explode("
", $old)[1]))) {
    $html = str_replace($old, $new, $html);
    file_put_contents($painel, $html);
    $painel_ok = true;
} else {
    $painel_ok = false;
}

 
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{background:#070d18;color:#f0f6ff;font-family:sans-serif;padding:20px 16px;min-height:100vh;}
h1{font-size:20px;color:#00e5a0;margin-bottom:16px;}
.box{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:14px;padding:16px;margin-bottom:12px;}
.ok{color:#00e5a0;font-weight:700;}
.warn{color:#ffb830;font-weight:700;}
.info{color:#8fa8c8;font-size:13px;line-height:1.8;margin-top:8px;}
.btn{display:block;text-align:center;padding:15px;background:linear-gradient(135deg,#00e5a0,#00c8ff);color:#070d18;border-radius:999px;font-weight:800;text-decoration:none;font-size:14px;margin-top:6px;}
.btn2{background:rgba(77,156,255,.12);color:#4d9cff;border:1px solid rgba(77,156,255,.3);}
ul{list-style:none;margin-top:8px;}
li{padding:4px 0;font-size:12px;color:#8fa8c8;border-bottom:1px solid rgba(255,255,255,.05);}
li:last-child{border-bottom:none;}
</style>
</head>
<body>
<h1>🔧 Mobile Fix</h1>

<div class="box">
  <p class="ok">✅ style.css — regras mobile adicionadas</p>
  <ul>
    <li>✓ Nav: botões mostram só ícone no mobile</li>
    <li>✓ Stats: 5º card ocupa linha inteira</li>
    <li>✓ Alert banner: empilha verticalmente</li>
    <li>✓ pedido-header: coluna no mobile</li>
    <li>✓ status-form: botões 100% de largura</li>
  </ul>
</div>

<div class="box">
  <?php if ($painel_ok): ?>
    <p class="ok">✅ painel_dono.php — banner de estoque corrigido</p>
  <?php else: ?>
    <p class="warn">⚠️ painel_dono.php — já estava corrigido ou padrão diferente</p>
  <?php endif; ?>
</div>

<a href="painel_dono.php" class="btn">Ver Painel Admin →</a>
<a href="index.php" class="btn btn2">Ver Loja →</a>
<p style="text-align:center;margin-top:14px;font-size:11px;color:#4d6b8a;">Apague este arquivo após usar.</p>
</body>
</html>
