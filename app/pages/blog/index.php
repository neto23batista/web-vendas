<?php
if (!defined('FARMAVIDA_ROOT')) { define('FARMAVIDA_ROOT', dirname(__DIR__, 2)); }
require_once FARMAVIDA_ROOT . '/app/core/bootstrap.php';
require_once FARMAVIDA_ROOT . '/app/core/config.php';

$posts = [
    [
        'slug' => 'como-usar-antibioticos-com-seguranca',
        'titulo' => 'Como usar antibióticos com segurança',
        'resumo' => 'Dicas rápidas de adesão, horários e quando procurar o médico.',
        'data' => '2026-03-01'
    ],
    [
        'slug' => 'guia-de-vitaminas-para-o-outono',
        'titulo' => 'Guia de vitaminas para o outono',
        'resumo' => 'Vitamina D, C e zinco: quando suplementar e cuidados.',
        'data' => '2026-02-12'
    ],
    [
        'slug' => 'primeiros-socorros-em-casa',
        'titulo' => 'Primeiros socorros em casa',
        'resumo' => 'O que ter no kit de emergência e como usar cada item.',
        'data' => '2026-01-20'
    ],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Blog FarmaVida</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta name="description" content="Conteúdo curto sobre saúde, uso correto de medicamentos e bem-estar.">
</head>
<body>
    <div class="container" style="max-width:900px;padding:28px 0;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
            <a href="/index.php" class="logo" style="text-decoration:none;"><div class="logo-icon"><i class="fas fa-prescription-bottle-medical"></i></div> Farma<span>Vida</span></a>
            <a href="/carrinho.php" class="btn btn-secondary"><i class="fas fa-cart-shopping"></i> Sacola</a>
        </div>
        <h1 style="font-family:'Bricolage Grotesque',sans-serif;font-size:24px;margin-bottom:6px;">Blog FarmaVida</h1>
        <p style="color:var(--gray);margin-bottom:18px;">Dicas rápidas, FAQs e boas práticas de uso.</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;">
            <?php foreach ($posts as $post): ?>
                <article class="card" style="padding:14px;border-radius:var(--radius-lg);border:1px solid var(--border);background:var(--surface);">
                    <div style="font-size:11px;color:var(--gray);margin-bottom:4px;"><?= htmlspecialchars($post['data']) ?></div>
                    <h2 style="font-size:16px;margin:0 0 6px;"><?= htmlspecialchars($post['titulo']) ?></h2>
                    <p style="color:var(--gray);font-size:13px;margin:0 0 10px;"><?= htmlspecialchars($post['resumo']) ?></p>
                    <a href="/carrinho.php?buscar=<?= urlencode($post['titulo']) ?>" class="btn btn-primary" style="justify-content:center;">Ler e ver produtos relacionados</a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
