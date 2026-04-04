<?php
if (!defined('FARMAVIDA_ROOT')) { define('FARMAVIDA_ROOT', dirname(__DIR__, 2)); }
require_once FARMAVIDA_ROOT . '/app/core/bootstrap.php';
require_once FARMAVIDA_ROOT . '/app/core/config.php';
require_once FARMAVIDA_ROOT . '/app/core/helpers.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>FAQ Clínico - FarmaVida</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container" style="max-width:960px;padding:24px 0;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
            <a href="/index.php" class="logo" style="text-decoration:none;"><div class="logo-icon"><i class="fas fa-prescription-bottle-medical"></i></div> Farma<span>Vida</span></a>
            <a href="/carrinho.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar para sacola</a>
        </div>
        <h1 style="font-family:'Bricolage Grotesque',sans-serif;font-size:24px;margin-bottom:6px;">FAQ Clínico</h1>
        <p style="color:var(--gray);margin-bottom:20px;">Dúvidas rápidas sobre uso de medicamentos, receitas e segurança.</p>

        <div class="card" style="padding:16px;border-radius:var(--radius-lg);border:1px solid var(--border);background:var(--surface);margin-bottom:12px;">
            <h3 style="margin-bottom:6px;font-size:15px;">1. Preciso de receita?</h3>
            <p style="color:var(--gray);font-size:13px;">Antibióticos, controlados e medicamentos tarjados exigem receita válida. Para controlados, leve o receituário físico ao retirar.</p>
        </div>
        <div class="card" style="padding:16px;border-radius:var(--radius-lg);border:1px solid var(--border);background:var(--surface);margin-bottom:12px;">
            <h3 style="margin-bottom:6px;font-size:15px;">2. Posso substituir por genérico?</h3>
            <p style="color:var(--gray);font-size:13px;">Genéricos têm o mesmo princípio ativo e bioequivalência. Se tiver dúvida, peça ajuda no chat/WhatsApp com o nome do medicamento.</p>
        </div>
        <div class="card" style="padding:16px;border-radius:var(--radius-lg);border:1px solid var(--border);background:var(--surface);margin-bottom:12px;">
            <h3 style="margin-bottom:6px;font-size:15px;">3. Armazenamento seguro</h3>
            <p style="color:var(--gray);font-size:13px;">Mantenha em local seco, arejado e fora do alcance de crianças. Verifique validade e aspecto do produto antes de usar.</p>
        </div>
        <div class="card" style="padding:16px;border-radius:var(--radius-lg);border:1px solid var(--border);background:var(--surface);margin-bottom:12px;">
            <h3 style="margin-bottom:6px;font-size:15px;">4. Quem revisa meu pedido?</h3>
            <p style="color:var(--gray);font-size:13px;">Um farmacêutico responsável confere itens sujeitos a controle antes de liberar para separação/entrega.</p>
        </div>

        <div style="margin-top:18px;display:flex;gap:10px;flex-wrap:wrap;">
            <a href="https://wa.me/?text=Preciso%20tirar%20duvidas%20clinicas%20sobre%20meu%20pedido" target="_blank" class="btn btn-primary" style="gap:6px;"><i class="fas fa-comments"></i> Falar no WhatsApp</a>
            <a href="/carrinho.php" class="btn btn-secondary">Voltar</a>
        </div>
    </div>
</body>
</html>
