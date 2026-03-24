<?php
require_once FARMAVIDA_ROOT . '/app/core/bootstrap.php';
require_once FARMAVIDA_ROOT . '/app/core/config.php';
require_once FARMAVIDA_ROOT . '/app/core/helpers.php';

$categoria  = $_GET['categoria'] ?? '';
$busca      = $_GET['busca'] ?? '';
$usuario_id = $_SESSION['id_usuario'] ?? 0;

 
$stmt = $conn->prepare("SELECT * FROM produtos WHERE disponivel = 1 ORDER BY categoria, nome");
$stmt->execute();
$todos_produtos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

 
$categorias = $conn->query(
    "SELECT DISTINCT categoria FROM produtos WHERE disponivel=1 AND categoria IS NOT NULL AND categoria!='' ORDER BY categoria"
)->fetch_all(MYSQLI_ASSOC);

 
$mais_pedidos = $conn->query(
    "SELECT pr.id, pr.nome, pr.preco, pr.imagem, pr.categoria,
            pr.estoque_atual, pr.estoque_minimo,
            SUM(pi.quantidade) as total_vendido
     FROM pedido_itens pi
     JOIN produtos pr ON pi.id_produto = pr.id
     JOIN pedidos p ON pi.id_pedido = p.id
     WHERE pr.disponivel = 1 AND p.status != 'cancelado'
     GROUP BY pr.id
     ORDER BY total_vendido DESC
     LIMIT 6"
)->fetch_all(MYSQLI_ASSOC);

$cart_count = $usuario_id
    ? (int)$conn->query("SELECT COUNT(*) as t FROM carrinho WHERE id_cliente=$usuario_id")->fetch_assoc()['t']
    : 0;

 
$cart_items = [];
if ($usuario_id) {
    $stmt = $conn->prepare(
        "SELECT c.id, c.quantidade, p.nome, p.preco, p.imagem, p.categoria
         FROM carrinho c JOIN produtos p ON c.id_produto = p.id
         WHERE c.id_cliente = ? AND p.disponivel = 1 ORDER BY c.adicionado_em DESC LIMIT 5"
    );
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $cart_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

 
$cart_qtd = [];
if ($usuario_id) {
    $stmt = $conn->prepare(
        "SELECT c.id_produto, c.quantidade FROM carrinho c WHERE c.id_cliente = ?"
    );
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $cart_qtd[$row['id_produto']] = (int)$row['quantidade'];
    }
    $stmt->close();
}

$msg      = $_SESSION['sucesso'] ?? $_SESSION['erro'] ?? '';
$msg_tipo = isset($_SESSION['sucesso']) ? 'success' : 'error';
unset($_SESSION['sucesso'], $_SESSION['erro']);

$cat_icons = [
    'Medicamentos'       => '💊',
    'Genéricos'          => '🔵',
    'Vitaminas'          => '🌿',
    'Higiene Pessoal'    => '🧴',
    'Dermocosméticos'    => '✨',
    'Infantil'           => '👶',
    'Bem-Estar'          => '💚',
    'Primeiros Socorros' => '🩹',
    'Ortopedia'          => '🦽',
    'Outros'             => '📦',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FarmaVida — Sua Farmácia Premium</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
         


        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg: #070d18;
            --bg2: #0d1425;
            --bg3: #111c30;
            --surface: rgba(255, 255, 255, .04);
            --surface2: rgba(255, 255, 255, .07);
            --border: rgba(255, 255, 255, .08);
            --border2: rgba(255, 255, 255, .14);

            --primary: #00e5a0;
            --primary-d: #00b87f;
            --primary-g: linear-gradient(135deg, #00e5a0, #00c8ff);
            --blue: #4d9cff;
            --blue-g: linear-gradient(135deg, #4d9cff, #a855f7);
            --danger: #ff4d6d;
            --warning: #ffb830;

            --text: #f0f6ff;
            --text2: #8fa8c8;
            --text3: #4d6b8a;

            --r-sm: 10px;
            --r-md: 16px;
            --r-lg: 22px;
            --r-xl: 30px;
            --r-full: 9999px;
            --sh-glow: 0 0 40px rgba(0, 229, 160, .15);
            --sh-card: 0 8px 40px rgba(0, 0, 0, .5);
            --t: .25s cubic-bezier(.4, 0, .2, 1);

            --nav-h: 70px;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
        }

         
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.03'/%3E%3C/svg%3E");
            opacity: .4;
        }

         


        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 3px;
        }

         


        .nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: var(--nav-h);
            background: rgba(7, 13, 24, .85);
            backdrop-filter: blur(20px) saturate(180%);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            padding: 0 clamp(16px, 4vw, 48px);
            gap: 20px;
            transition: background var(--t);
        }

        .nav.scrolled {
            background: rgba(7, 13, 24, .97);
        }

        .nav-logo {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 24px;
            font-weight: 800;
            color: var(--text);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .nav-logo-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: var(--primary-g);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            box-shadow: var(--sh-glow);
        }

        .nav-logo span {
            color: var(--primary);
        }

         
        .nav-search {
            flex: 1;
            max-width: 480px;
            position: relative;
        }

        .nav-search input {
            width: 100%;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: var(--r-full);
            padding: 10px 44px 10px 18px;
            color: var(--text);
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 14px;
            outline: none;
            transition: var(--t);
        }

        .nav-search input::placeholder {
            color: var(--text3);
        }

        .nav-search input:focus {
            border-color: var(--primary);
            background: var(--surface);
            box-shadow: 0 0 0 3px rgba(0, 229, 160, .12);
        }

        .nav-search-icon {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text3);
            font-size: 14px;
            pointer-events: none;
            transition: var(--t);
        }

        .nav-search input:focus~.nav-search-icon {
            color: var(--primary);
        }

         
        .search-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            background: var(--bg2);
            border: 1px solid var(--border2);
            border-radius: var(--r-md);
            max-height: 360px;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .6);
            display: none;
            z-index: 200;
        }

        .search-dropdown.open {
            display: block;
            animation: dropDown .2s ease;
        }

        @keyframes dropDown {
            from {
                opacity: 0;
                transform: translateY(-8px)
            }

            to {
                opacity: 1;
                transform: none
            }
        }

        .search-result-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            cursor: pointer;
            transition: background var(--t);
            border-bottom: 1px solid var(--border);
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .search-result-item:hover {
            background: var(--surface2);
        }

        .search-result-img {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            object-fit: cover;
            background: var(--bg3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .search-result-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }

        .search-result-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
        }

        .search-result-price {
            font-size: 12px;
            color: var(--primary);
            font-weight: 700;
            margin-top: 2px;
        }

        .search-result-cat {
            font-size: 11px;
            color: var(--text3);
        }

        .search-no-result {
            padding: 20px;
            text-align: center;
            color: var(--text3);
            font-size: 13px;
        }

         
        .nav-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
            flex-shrink: 0;
        }

        .btn-nav {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 18px;
            border-radius: var(--r-full);
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all var(--t);
            position: relative;
            overflow: hidden;
            white-space: nowrap;
        }

        .btn-primary-nav {
            background: var(--primary-g);
            color: #070d18;
            box-shadow: 0 4px 20px rgba(0, 229, 160, .3);
        }

        .btn-primary-nav:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 229, 160, .45);
        }

        .btn-ghost {
            background: var(--surface2);
            color: var(--text2);
            border: 1px solid var(--border);
        }

        .btn-ghost:hover {
            background: var(--surface);
            color: var(--text);
            border-color: var(--border2);
        }

        .btn-danger-nav {
            background: rgba(255, 77, 109, .15);
            color: #ff4d6d;
            border: 1px solid rgba(255, 77, 109, .3);
        }

        .btn-danger-nav:hover {
            background: rgba(255, 77, 109, .25);
        }

        .cart-btn {
            position: relative;
            width: 44px;
            height: 44px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text2);
            font-size: 17px;
            cursor: pointer;
            text-decoration: none;
            transition: all var(--t);
        }

        .cart-btn:hover {
            background: var(--surface);
            color: var(--primary);
            border-color: var(--primary);
        }

        .cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary-g);
            color: #070d18;
            font-size: 10px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--bg);
            animation: badgePop .3s cubic-bezier(.34, 1.56, .64, 1);
        }

        @keyframes badgePop {
            from {
                transform: scale(0)
            }

            to {
                transform: scale(1)
            }
        }

         


        .cart-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .6);
            backdrop-filter: blur(4px);
            z-index: 2000;
            opacity: 0;
            pointer-events: none;
            transition: opacity var(--t);
        }

        .cart-overlay.open {
            opacity: 1;
            pointer-events: all;
        }

        .cart-drawer {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            width: min(400px, 95vw);
            background: var(--bg2);
            border-left: 1px solid var(--border2);
            z-index: 2001;
            display: flex;
            flex-direction: column;
            transform: translateX(100%);
            transition: transform .35s cubic-bezier(.4, 0, .2, 1);
        }

        .cart-drawer.open {
            transform: none;
        }

        .cart-drawer-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .cart-drawer-title {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .cart-drawer-close {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--surface2);
            border: none;
            cursor: pointer;
            color: var(--text2);
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--t);
        }

        .cart-drawer-close:hover {
            background: var(--danger);
            color: #fff;
        }

        .cart-drawer-items {
            flex: 1;
            overflow-y: auto;
            padding: 16px 24px;
        }

        .cart-drawer-item {
            display: flex;
            gap: 12px;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }

        .cart-drawer-item:last-child {
            border-bottom: none;
        }

        .cart-drawer-img {
            width: 54px;
            height: 54px;
            border-radius: 10px;
            object-fit: cover;
            background: var(--bg3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        .cart-drawer-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 10px;
        }

        .cart-drawer-info {
            flex: 1;
            min-width: 0;
        }

        .cart-drawer-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cart-drawer-price {
            font-size: 12px;
            color: var(--primary);
            font-weight: 700;
            margin-top: 2px;
        }

        .cart-drawer-qty {
            font-size: 11px;
            color: var(--text3);
            margin-top: 2px;
        }

        .cart-drawer-footer {
            padding: 20px 24px;
            border-top: 1px solid var(--border);
        }

        .cart-total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .cart-total-label {
            font-size: 14px;
            color: var(--text2);
        }

        .cart-total-value {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 26px;
            font-weight: 800;
            color: var(--primary);
        }

        .cart-empty-msg {
            text-align: center;
            padding: 48px 0;
            color: var(--text3);
        }

        .cart-empty-msg i {
            font-size: 48px;
            display: block;
            margin-bottom: 12px;
            opacity: .4;
        }

         


        .hero {
            position: relative;
            min-height: 92vh;
            display: flex;
            align-items: center;
            padding: calc(var(--nav-h) + 60px) clamp(16px, 6vw, 80px) 80px;
            overflow: hidden;
        }

         
        .hero-bg {
            position: absolute;
            inset: 0;
            z-index: 0;
            background: radial-gradient(ellipse 80% 60% at 60% 40%, rgba(0, 229, 160, .08) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 20% 80%, rgba(77, 156, 255, .07) 0%, transparent 60%),
                var(--bg);
        }

        .hero-grid {
            position: absolute;
            inset: 0;
            z-index: 0;
            background-image:
                linear-gradient(rgba(255, 255, 255, .025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, .025) 1px, transparent 1px);
            background-size: 48px 48px;
            mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black 30%, transparent 100%);
        }

         
        .hero-particles {
            position: absolute;
            inset: 0;
            z-index: 0;
            overflow: hidden;
        }

        .pill-float {
            position: absolute;
            width: var(--w, 12px);
            height: var(--h, 28px);
            border-radius: 50px;
            background: var(--c, rgba(0, 229, 160, .15));
            animation: floatPill var(--dur, 8s) var(--delay, 0s) ease-in-out infinite;
            transform-origin: center;
        }

        @keyframes floatPill {

            0%,
            100% {
                transform: translateY(0) rotate(var(--rot, 0deg)) scale(1);
                opacity: var(--op, .6);
            }

            33% {
                transform: translateY(-30px) rotate(calc(var(--rot, 0deg) + 12deg)) scale(1.05);
            }

            66% {
                transform: translateY(-15px) rotate(calc(var(--rot, 0deg) - 8deg)) scale(.97);
            }
        }

        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 680px;
        }

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            border-radius: var(--r-full);
            background: rgba(0, 229, 160, .1);
            border: 1px solid rgba(0, 229, 160, .25);
            color: var(--primary);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .8px;
            text-transform: uppercase;
            margin-bottom: 28px;
            animation: fadeSlideUp .6s .1s both;
        }

        .hero-eyebrow::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--primary);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
                transform: scale(1)
            }

            50% {
                opacity: .5;
                transform: scale(1.4)
            }
        }

        .hero-title {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: clamp(42px, 6vw, 80px);
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -2px;
            margin-bottom: 24px;
            animation: fadeSlideUp .6s .2s both;
        }

        .hero-title .accent {
            background: var(--primary-g);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-sub {
            font-size: 18px;
            color: var(--text2);
            line-height: 1.7;
            max-width: 500px;
            margin-bottom: 40px;
            animation: fadeSlideUp .6s .3s both;
        }

        .hero-actions {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            animation: fadeSlideUp .6s .4s both;
        }

        .hero-btn {
            padding: 14px 32px;
            border-radius: var(--r-full);
            font-size: 15px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 9px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all var(--t);
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .hero-btn-primary {
            background: var(--primary-g);
            color: #070d18;
            box-shadow: 0 8px 30px rgba(0, 229, 160, .35);
        }

        .hero-btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 40px rgba(0, 229, 160, .5);
        }

        .hero-btn-secondary {
            background: var(--surface2);
            color: var(--text);
            border: 1px solid var(--border2);
        }

        .hero-btn-secondary:hover {
            background: var(--surface);
            border-color: var(--primary);
            color: var(--primary);
        }

        .hero-stats {
            display: flex;
            gap: 36px;
            margin-top: 52px;
            animation: fadeSlideUp .6s .5s both;
            flex-wrap: wrap;
        }

        .hero-stat-num {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 32px;
            font-weight: 800;
            color: var(--text);
            line-height: 1;
        }

        .hero-stat-num span {
            color: var(--primary);
        }

        .hero-stat-label {
            font-size: 12px;
            color: var(--text3);
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

         
        .hero-visual {
            position: absolute;
            right: -5%;
            top: 50%;
            transform: translateY(-50%);
            width: min(560px, 48vw);
            z-index: 0;
            pointer-events: none;
            animation: fadeSlideUp .8s .2s both;
        }

        .hero-visual-ring {
            position: absolute;
            border-radius: 50%;
            border: 1px solid rgba(0, 229, 160, .12);
            animation: spinSlow 20s linear infinite;
        }

        @keyframes spinSlow {
            to {
                transform: rotate(360deg)
            }
        }

        @keyframes fadeSlideUp {
            from {
                opacity: 0;
                transform: translateY(28px)
            }

            to {
                opacity: 1;
                transform: none
            }
        }

        @keyframes fadeSlideLeft {
            from {
                opacity: 0;
                transform: translateX(-24px)
            }

            to {
                opacity: 1;
                transform: none
            }
        }

         


        .badges-bar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: center;
            padding: 24px clamp(16px, 4vw, 48px);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            background: rgba(0, 229, 160, .02);
            position: relative;
            z-index: 1;
        }

        .badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            border-radius: var(--r-full);
            background: var(--surface);
            border: 1px solid var(--border);
            font-size: 13px;
            font-weight: 600;
            color: var(--text2);
            transition: var(--t);
        }

        .badge-pill i {
            color: var(--primary);
        }

        .badge-pill:hover {
            background: var(--surface2);
            color: var(--text);
        }

         


        .main {
            padding: 60px clamp(16px, 4vw, 48px);
            position: relative;
            z-index: 1;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 14px;
        }

        .section-title {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: clamp(22px, 3vw, 30px);
            font-weight: 800;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title::before {
            content: '';
            width: 4px;
            height: 28px;
            border-radius: 2px;
            background: var(--primary-g);
            flex-shrink: 0;
        }

        .section-link {
            font-size: 13px;
            font-weight: 600;
            color: var(--primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: var(--t);
        }

        .section-link:hover {
            gap: 10px;
        }

         


        .filters {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 40px;
            padding: 4px;
        }

        .filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 18px;
            border-radius: var(--r-full);
            background: var(--surface);
            border: 1px solid var(--border);
            color: var(--text2);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--t);
            white-space: nowrap;
            user-select: none;
        }

        .filter-chip:hover {
            background: var(--surface2);
            color: var(--text);
            border-color: var(--border2);
        }

        .filter-chip.active {
            background: rgba(0, 229, 160, .12);
            border-color: rgba(0, 229, 160, .4);
            color: var(--primary);
            box-shadow: 0 0 20px rgba(0, 229, 160, .08);
        }

        .filter-chip .chip-count {
            background: rgba(255, 255, 255, .08);
            padding: 1px 7px;
            border-radius: 20px;
            font-size: 11px;
        }

        .filter-chip.active .chip-count {
            background: rgba(0, 229, 160, .2);
        }

         


        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 20px;
        }

         
        .product-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            overflow: hidden;
            cursor: pointer;
            transition: transform .3s cubic-bezier(.4, 0, .2, 1),
                box-shadow .3s cubic-bezier(.4, 0, .2, 1),
                border-color .3s;
            position: relative;
            will-change: transform;
            animation: cardIn .4s both;
        }

        @keyframes cardIn {
            from {
                opacity: 0;
                transform: translateY(20px) scale(.97)
            }

            to {
                opacity: 1;
                transform: none
            }
        }

        .product-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, .5), 0 0 0 1px rgba(0, 229, 160, .2);
            border-color: rgba(0, 229, 160, .25);
            z-index: 2;
        }

        .product-card.hidden {
            display: none !important;
        }

        .product-card.filtered-out {
            animation: cardOut .25s both;
        }

        @keyframes cardOut {
            to {
                opacity: 0;
                transform: scale(.95)
            }
        }

         
        .stock-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            z-index: 3;
            padding: 4px 10px;
            border-radius: var(--r-full);
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .stock-low {
            background: rgba(255, 184, 48, .15);
            color: var(--warning);
            border: 1px solid rgba(255, 184, 48, .3);
        }

        .stock-out {
            background: rgba(255, 77, 109, .15);
            color: var(--danger);
            border: 1px solid rgba(255, 77, 109, .3);
        }

        .stock-hot {
            background: rgba(0, 229, 160, .12);
            color: var(--primary);
            border: 1px solid rgba(0, 229, 160, .3);
        }

         
        .card-quick-view {
            position: absolute;
            top: 12px;
            right: 12px;
            z-index: 3;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: rgba(7, 13, 24, .7);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border2);
            color: var(--text2);
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transform: scale(.8);
            transition: all .2s;
            cursor: pointer;
        }

        .product-card:hover .card-quick-view {
            opacity: 1;
            transform: scale(1);
        }

        .card-quick-view:hover {
            background: var(--primary);
            color: #070d18;
            border-color: var(--primary);
        }

         
        .card-img-wrap {
            position: relative;
            overflow: hidden;
            height: 200px;
            background: var(--bg3);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .4s cubic-bezier(.4, 0, .2, 1);
        }

        .product-card:hover .card-img-wrap img {
            transform: scale(1.06);
        }

        .card-img-placeholder {
            font-size: 52px;
            opacity: .3;
        }

         
        .card-img-wrap::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, transparent 40%, rgba(255, 255, 255, .04) 50%, transparent 60%);
            transform: translateX(-100%);
            transition: transform .5s;
        }

        .product-card:hover .card-img-wrap::after {
            transform: translateX(100%);
        }

        .card-body {
            padding: 18px;
        }

        .card-cat {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: var(--primary);
            margin-bottom: 7px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .card-name {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
            line-height: 1.3;
            margin-bottom: 6px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .card-desc {
            font-size: 12px;
            color: var(--text3);
            line-height: 1.5;
            margin-bottom: 14px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding-top: 14px;
            border-top: 1px solid var(--border);
        }

        .card-price {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 22px;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
        }

        .card-price small {
            font-size: 12px;
            font-weight: 500;
            color: var(--text3);
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

         
        .card-add-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 18px;
            border-radius: var(--r-full);
            background: rgba(0, 229, 160, .12);
            border: 1px solid rgba(0, 229, 160, .25);
            color: var(--primary);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all var(--t);
            font-family: 'Plus Jakarta Sans', sans-serif;
            white-space: nowrap;
        }

        .card-add-btn:hover {
            background: var(--primary);
            color: #070d18;
            border-color: var(--primary);
            transform: scale(1.04);
            box-shadow: 0 6px 20px rgba(0, 229, 160, .3);
        }

        .card-add-btn:active {
            transform: scale(.97);
        }

        .card-add-btn.loading i {
            animation: spin .6s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg)
            }
        }

         
        .qty-controls {
            display: inline-flex;
            align-items: center;
            gap: 0;
            background: rgba(0, 229, 160, .08);
            border: 1px solid rgba(0, 229, 160, .25);
            border-radius: var(--r-full);
            overflow: hidden;
            height: 38px;
        }

        .qty-btn {
            width: 36px;
            height: 38px;
            border: none;
            background: transparent;
            color: var(--primary);
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--t);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qty-btn:hover {
            background: rgba(0, 229, 160, .15);
        }

        .qty-num {
            min-width: 32px;
            text-align: center;
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 15px;
            font-weight: 800;
            color: var(--text);
        }

         


        .trending-scroll {
            display: flex;
            gap: 16px;
            overflow-x: auto;
            padding-bottom: 12px;
            scroll-snap-type: x mandatory;
            scrollbar-width: none;
        }

        .trending-scroll::-webkit-scrollbar {
            display: none;
        }

        .trending-card {
            flex-shrink: 0;
            width: 200px;
            scroll-snap-align: start;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-md);
            overflow: hidden;
            cursor: pointer;
            transition: all var(--t);
        }

        .trending-card:hover {
            border-color: rgba(0, 229, 160, .3);
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, .4);
        }

        .trending-img {
            height: 120px;
            background: var(--bg3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            overflow: hidden;
        }

        .trending-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .trending-info {
            padding: 12px;
        }

        .trending-name {
            font-size: 13px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 4px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .trending-price {
            font-size: 15px;
            font-weight: 800;
            color: var(--primary);
        }

        .trending-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 10px;
            font-weight: 700;
            color: var(--warning);
            background: rgba(255, 184, 48, .1);
            padding: 2px 8px;
            border-radius: 20px;
            margin-bottom: 6px;
        }

         


        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .75);
            backdrop-filter: blur(8px);
            z-index: 3000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            pointer-events: none;
            transition: opacity .25s;
        }

        .modal-overlay.open {
            opacity: 1;
            pointer-events: all;
        }

        .modal {
            background: var(--bg2);
            border: 1px solid var(--border2);
            border-radius: var(--r-xl);
            width: 100%;
            max-width: 680px;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(.95) translateY(20px);
            transition: transform .3s cubic-bezier(.4, 0, .2, 1);
            box-shadow: 0 40px 100px rgba(0, 0, 0, .7);
        }

        .modal-overlay.open .modal {
            transform: scale(1) translateY(0);
        }

        .modal-inner {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }

        .modal-img {
            height: 340px;
            background: var(--bg3);
            border-radius: var(--r-xl) 0 0 var(--r-xl);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 72px;
            overflow: hidden;
            position: relative;
        }

        .modal-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: var(--r-xl) 0 0 var(--r-xl);
        }

        .modal-close-btn {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(7, 13, 24, .7);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border2);
            color: var(--text2);
            font-size: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--t);
        }

        .modal-close-btn:hover {
            background: var(--danger);
            color: #fff;
        }

        .modal-info {
            padding: 28px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .modal-cat {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: var(--primary);
        }

        .modal-name {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 22px;
            font-weight: 800;
            color: var(--text);
            line-height: 1.2;
        }

        .modal-desc {
            font-size: 13px;
            color: var(--text2);
            line-height: 1.7;
            flex: 1;
        }

        .modal-price {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 32px;
            font-weight: 800;
            color: var(--primary);
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        @media(max-width:600px) {
            .modal-inner {
                grid-template-columns: 1fr;
            }

            .modal-img {
                border-radius: var(--r-xl) var(--r-xl) 0 0;
                height: 220px;
            }

            .modal-img img {
                border-radius: var(--r-xl) var(--r-xl) 0 0;
            }
        }

         


        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--text3);
        }

        .empty-state i {
            font-size: 64px;
            display: block;
            margin-bottom: 16px;
            opacity: .25;
        }

        .empty-state h2 {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 24px;
            color: var(--text2);
            margin-bottom: 8px;
        }

        .alert-bar {
            margin: 0 clamp(16px, 4vw, 48px) 0;
            padding: 14px 20px;
            border-radius: var(--r-md);
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 600;
            animation: fadeSlideUp .3s both;
        }

        .alert-success {
            background: rgba(0, 229, 160, .1);
            border: 1px solid rgba(0, 229, 160, .25);
            color: var(--primary);
        }

        .alert-error {
            background: rgba(255, 77, 109, .1);
            border: 1px solid rgba(255, 77, 109, .25);
            color: var(--danger);
        }

         


        .toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toast {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            border-radius: var(--r-md);
            background: var(--bg2);
            border: 1px solid var(--border2);
            box-shadow: 0 10px 40px rgba(0, 0, 0, .5);
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            animation: toastIn .35s cubic-bezier(.34, 1.56, .64, 1);
            max-width: 320px;
        }

        .toast i {
            color: var(--primary);
            font-size: 16px;
            flex-shrink: 0;
        }

        .toast.error i {
            color: var(--danger);
        }

        @keyframes toastIn {
            from {
                opacity: 0;
                transform: translateX(60px) scale(.9)
            }

            to {
                opacity: 1;
                transform: none
            }
        }

         


        @media(max-width:768px) {
            .hero {
                min-height: auto;
                padding-bottom: 60px;
            }

            .hero-visual {
                display: none;
            }

            .hero-title {
                letter-spacing: -1px;
            }

            .hero-stats {
                gap: 24px;
            }

            .nav-search {
                display: none;
            }

            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 12px;
            }

            .card-body {
                padding: 12px;
            }

            .card-name {
                font-size: 13px;
            }

            .card-price {
                font-size: 18px;
            }

            .card-footer {
                flex-direction: column;
                align-items: stretch;
            }

            .card-add-btn {
                justify-content: center;
            }
        }

        @media(max-width:480px) {
            .products-grid {
                grid-template-columns: 1fr 1fr;
            }

            .hero-actions {
                flex-direction: column;
            }

            .hero-btn {
                justify-content: center;
            }
        }
    </style>
</head>

<body>

    
    <nav class="nav" id="navbar">
        <a href="index.php" class="nav-logo">
            <div class="nav-logo-icon">💊</div>
            Farma<span>Vida</span>
        </a>

        <div class="nav-search" id="nav-search-wrap">
            <input type="text" id="live-search" placeholder="Buscar produtos, medicamentos..."
                autocomplete="off" spellcheck="false">
            <i class="fas fa-search nav-search-icon"></i>
            <div class="search-dropdown" id="search-dropdown"></div>
        </div>

        <div class="nav-actions">
            <?php if (isset($_SESSION['usuario'])): ?>
                <?php if ($_SESSION['tipo'] == 'cliente'): ?>
                    <a href="#" class="cart-btn" id="cart-toggle-btn" title="Sacola">
                        <i class="fas fa-shopping-bag"></i>
                        <?php if ($cart_count > 0): ?>
                            <span class="cart-badge" id="cart-badge-nav"><?= $cart_count ?></span>
                        <?php else: ?>
                            <span class="cart-badge" id="cart-badge-nav" style="display:none;">0</span>
                        <?php endif; ?>
                    </a>
                    <a href="painel_cliente.php" class="btn-nav btn-ghost">
                        <i class="fas fa-user"></i>
                        <span><?= htmlspecialchars(explode(' ', $_SESSION['usuario'])[0]) ?></span>
                    </a>
                <?php else: ?>
                    <a href="painel_dono.php" class="btn-nav btn-primary-nav">
                        <i class="fas fa-tachometer-alt"></i> Painel
                    </a>
                <?php endif; ?>
                <a href="logout.php" class="btn-nav btn-danger-nav"><i class="fas fa-sign-out-alt"></i></a>
            <?php else: ?>
                <a href="login.php" class="btn-nav btn-ghost"><i class="fas fa-sign-in-alt"></i> Entrar</a>
                <a href="cadastro.php" class="btn-nav btn-primary-nav"><i class="fas fa-user-plus"></i> Cadastrar</a>
            <?php endif; ?>
        </div>
    </nav>

    
    <?php if (isset($_SESSION['usuario']) && $_SESSION['tipo'] == 'cliente'): ?>
        <div class="cart-overlay" id="cart-overlay"></div>
        <div class="cart-drawer" id="cart-drawer">
            <div class="cart-drawer-header">
                <div class="cart-drawer-title">
                    <i class="fas fa-shopping-bag" style="color:var(--primary)"></i>
                    Minha Sacola
                </div>
                <button class="cart-drawer-close" id="cart-close"><i class="fas fa-xmark"></i></button>
            </div>
            <div class="cart-drawer-items" id="cart-drawer-items">
                <?php if (empty($cart_items)): ?>
                    <div class="cart-empty-msg">
                        <i class="fas fa-shopping-bag"></i>
                        <p>Sua sacola está vazia</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($cart_items as $ci): ?>
                        <div class="cart-drawer-item">
                            <div class="cart-drawer-img">
                                <img src="<?= htmlspecialchars(url_imagem_produto($ci['imagem'] ?? null, $ci['nome'] ?? 'Produto', $ci['categoria'] ?? 'Sem categoria')) ?>" alt="">
                            </div>
                            <div class="cart-drawer-info">
                                <div class="cart-drawer-name"><?= htmlspecialchars($ci['nome']) ?></div>
                                <div class="cart-drawer-price"><?= formatar_preco($ci['preco']) ?></div>
                                <div class="cart-drawer-qty"><?= $ci['quantidade'] ?>× unidade</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="cart-drawer-footer">
                <div class="cart-total-row">
                    <span class="cart-total-label">Total estimado</span>
                    <span class="cart-total-value" id="cart-total-drawer">
                        <?php
                        $total_cart = array_sum(array_map(fn($i) => $i['preco'] * $i['quantidade'], $cart_items));
                        echo formatar_preco($total_cart);
                        ?>
                    </span>
                </div>
                <a href="carrinho.php" class="btn-nav btn-primary-nav" style="width:100%;justify-content:center;padding:14px;">
                    <i class="fas fa-shopping-cart"></i> Ver Sacola Completa
                </a>
            </div>
        </div>
    <?php endif; ?>

    
    <section class="hero" id="hero">
        <div class="hero-bg"></div>
        <div class="hero-grid"></div>

        
        <div class="hero-particles">
            <div class="pill-float" style="--w:10px;--h:24px;--c:rgba(0,229,160,.18);--dur:9s;--delay:0s;--rot:20deg;--op:.7;top:15%;left:55%;"></div>
            <div class="pill-float" style="--w:8px;--h:20px;--c:rgba(77,156,255,.15);--dur:11s;--delay:1.5s;--rot:-15deg;top:60%;left:70%;"></div>
            <div class="pill-float" style="--w:14px;--h:34px;--c:rgba(0,229,160,.12);--dur:8s;--delay:3s;--rot:45deg;top:30%;left:80%;"></div>
            <div class="pill-float" style="--w:6px;--h:16px;--c:rgba(168,85,247,.15);--dur:13s;--delay:0.5s;--rot:-30deg;top:70%;left:60%;"></div>
            <div class="pill-float" style="--w:12px;--h:28px;--c:rgba(0,229,160,.1);--dur:7s;--delay:2s;--rot:60deg;top:20%;left:90%;"></div>
            <div class="pill-float" style="--w:9px;--h:22px;--c:rgba(77,156,255,.12);--dur:10s;--delay:4s;--rot:-45deg;top:80%;left:85%;"></div>
            <div class="pill-float" style="--w:7px;--h:18px;--c:rgba(255,184,48,.1);--dur:12s;--delay:1s;--rot:10deg;top:45%;left:75%;"></div>
        </div>

        <div class="hero-content">
            <div class="hero-eyebrow">
                <span>Farmácia Premium</span>
            </div>
            <h1 class="hero-title">
                Sua saúde em<br>
                <span class="accent">boas mãos</span>
            </h1>
            <p class="hero-sub">
                Medicamentos, vitaminas, dermocosméticos e muito mais —
                com entrega rápida e orientação farmacêutica de qualidade.
            </p>
            <div class="hero-actions">
                <a href="#catalogo" class="hero-btn hero-btn-primary">
                    <i class="fas fa-pills"></i> Ver Catálogo
                </a>
                <?php if (!isset($_SESSION['usuario'])): ?>
                    <a href="cadastro.php" class="hero-btn hero-btn-secondary">
                        <i class="fas fa-user-plus"></i> Criar Conta
                    </a>
                <?php endif; ?>
            </div>
            <div class="hero-stats">
                <div>
                    <div class="hero-stat-num"><?= count($todos_produtos) ?><span>+</span></div>
                    <div class="hero-stat-label">Produtos</div>
                </div>
                <div>
                    <div class="hero-stat-num">9<span>+</span></div>
                    <div class="hero-stat-label">Categorias</div>
                </div>
                <div>
                    <div class="hero-stat-num">24<span>h</span></div>
                    <div class="hero-stat-label">Atendimento</div>
                </div>
            </div>
        </div>
    </section>

    
    <div class="badges-bar">
        <div class="badge-pill"><i class="fas fa-truck-fast"></i> Entrega Rápida</div>
        <div class="badge-pill"><i class="fas fa-shield-halved"></i> Compra Segura</div>
        <div class="badge-pill"><i class="fas fa-tag"></i> Melhores Preços</div>
        <div class="badge-pill"><i class="fas fa-user-doctor"></i> Orientação Farmacêutica</div>
        <div class="badge-pill"><i class="fas fa-rotate-left"></i> Troca Facilitada</div>
    </div>

    
    <?php if ($msg): ?>
        <div class="alert-bar alert-<?= $msg_tipo ?>" style="margin-top:28px;">
            <i class="fas fa-<?= $msg_tipo == 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    
    <main class="main" id="catalogo">

        
        <?php if (!empty($mais_pedidos)): ?>
            <section style="margin-bottom:56px;">
                <div class="section-header">
                    <div class="section-title">🔥 Mais Pedidos</div>
                    <a href="#" class="section-link" onclick="filtrarCategoria('');return false;">
                        Ver todos <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <div class="trending-scroll">
                    <?php foreach ($mais_pedidos as $p): ?>
                        <div class="trending-card" onclick="abrirModal(<?= $p['id'] ?>)">
                            <div class="trending-img">
                                <img src="<?= htmlspecialchars(url_imagem_produto($p['imagem'] ?? null, $p['nome'] ?? 'Produto', $p['categoria'] ?? 'Sem categoria')) ?>" alt="">
                            </div>
                            <div class="trending-info">
                                <div class="trending-tag"><i class="fas fa-fire"></i> Popular</div>
                                <div class="trending-name"><?= htmlspecialchars($p['nome']) ?></div>
                                <div class="trending-price"><?= formatar_preco($p['preco']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        
        <section style="margin-bottom:40px;">
            <div class="filters" id="filters">
                <div class="filter-chip active" data-cat="" onclick="filtrarCategoria('')">
                    <i class="fas fa-th-large"></i> Todos
                    <span class="chip-count"><?= count($todos_produtos) ?></span>
                </div>
                <?php
                $contagem_cat = array_count_values(array_column($todos_produtos, 'categoria'));
                foreach ($categorias as $cat):
                    $nome_cat = $cat['categoria'];
                    $qtd = $contagem_cat[$nome_cat] ?? 0;
                ?>
                    <div class="filter-chip" data-cat="<?= htmlspecialchars($nome_cat) ?>"
                        onclick="filtrarCategoria('<?= addslashes(htmlspecialchars($nome_cat)) ?>')">
                        <?= $cat_icons[$nome_cat] ?? '📦' ?>
                        <?= htmlspecialchars($nome_cat) ?>
                        <span class="chip-count"><?= $qtd ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        
        <section>
            <div class="section-header">
                <div class="section-title" id="grid-title">Todos os Produtos</div>
                <span style="font-size:13px;color:var(--text3);" id="grid-count">
                    <?= count($todos_produtos) ?> produto(s)
                </span>
            </div>

            <div class="products-grid" id="products-grid">
                <?php foreach ($todos_produtos as $i => $p):
                    $est = (int)($p['estoque_atual'] ?? 99);
                    $min = (int)($p['estoque_minimo'] ?? 5);
                    $icon = $cat_icons[$p['categoria']] ?? '📦';
                    $qtd_carrinho = $cart_qtd[$p['id']] ?? 0;
                    $stock_class = '';
                    $stock_label = '';
                    if ($est === 0) {
                        $stock_class = 'stock-out';
                        $stock_label = 'Indisponível';
                    } elseif ($est <= $min) {
                        $stock_class = 'stock-low';
                        $stock_label = 'Últimas unidades';
                    }
                ?>
                    <div class="product-card"
                        data-id="<?= $p['id'] ?>"
                        data-cat="<?= htmlspecialchars($p['categoria']) ?>"
                        data-nome="<?= strtolower(htmlspecialchars($p['nome'])) ?>"
                        data-desc="<?= strtolower(htmlspecialchars($p['descricao'])) ?>"
                        style="animation-delay:<?= min($i * 40, 400) ?>ms"
                        onclick="abrirModal(<?= $p['id'] ?>)">

                        <?php if ($stock_label): ?>
                            <div class="stock-badge <?= $stock_class ?>"><?= $stock_label ?></div>
                        <?php endif; ?>

                        <button class="card-quick-view" onclick="event.stopPropagation();abrirModal(<?= $p['id'] ?>)"
                            title="Visualização rápida">
                            <i class="fas fa-expand"></i>
                        </button>

                        <div class="card-img-wrap">
                            <img src="<?= htmlspecialchars(url_imagem_produto($p['imagem'] ?? null, $p['nome'] ?? 'Produto', $p['categoria'] ?? 'Sem categoria')) ?>"
                                alt="<?= htmlspecialchars($p['nome']) ?>"
                                loading="lazy">
                        </div>

                        <div class="card-body">
                            <div class="card-cat"><?= $icon ?> <?= htmlspecialchars($p['categoria']) ?></div>
                            <div class="card-name"><?= htmlspecialchars($p['nome']) ?></div>
                            <div class="card-desc"><?= htmlspecialchars($p['descricao']) ?></div>
                            <div class="card-footer" onclick="event.stopPropagation()">
                                <div class="card-price">
                                    <?= formatar_preco($p['preco']) ?>
                                </div>
                                <?php if (isset($_SESSION['usuario']) && $_SESSION['tipo'] == 'cliente'): ?>
                                    <?php if ($qtd_carrinho > 0): ?>
                                        <div class="qty-controls" id="qty-ctrl-<?= $p['id'] ?>">
                                            <button class="qty-btn" onclick="mudarQtd(<?= $p['id'] ?>, -1)">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <span class="qty-num" id="qty-num-<?= $p['id'] ?>"><?= $qtd_carrinho ?></span>
                                            <button class="qty-btn" onclick="mudarQtd(<?= $p['id'] ?>, 1)">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                    <?php elseif ($est > 0): ?>
                                        <button class="card-add-btn" id="btn-add-<?= $p['id'] ?>"
                                            onclick="adicionarCarrinho(<?= $p['id'] ?>, this)">
                                            <i class="fas fa-cart-plus"></i> Adicionar
                                        </button>
                                    <?php else: ?>
                                        <button class="card-add-btn" disabled
                                            style="opacity:.4;cursor:not-allowed;">
                                            Indisponível
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="login.php" class="card-add-btn">
                                        <i class="fas fa-sign-in-alt"></i> Entrar
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="empty-state" id="empty-state" style="display:none;">
                <i class="fas fa-search"></i>
                <h2>Nenhum produto encontrado</h2>
                <p>Tente outro termo ou categoria.</p>
            </div>
        </section>

    </main>

    
    <div class="modal-overlay" id="product-modal" onclick="if(event.target===this)fecharModal()">
        <div class="modal" id="modal-content">
            
        </div>
    </div>

    
    <div class="toast-container" id="toast-container"></div>

    
    <script>
        const CSRF_TOKEN = <?= json_encode(gerar_token_csrf()) ?>;
        const PRODUTOS = <?= json_encode(array_map(fn($p) => [
                                'id'        => $p['id'],
                                'nome'      => $p['nome'],
                                'descricao' => $p['descricao'],
                                'preco'     => (float)$p['preco'],
                                'categoria' => $p['categoria'],
                                'imagem'    => url_imagem_produto($p['imagem'] ?? null, $p['nome'] ?? 'Produto', $p['categoria'] ?? 'Sem categoria'),
                                'estoque'   => (int)($p['estoque_atual'] ?? 99),
                                'minimo'    => (int)($p['estoque_minimo'] ?? 5),
                            ], $todos_produtos), JSON_UNESCAPED_UNICODE) ?>;

        const CAT_ICONS = <?= json_encode($cat_icons, JSON_UNESCAPED_UNICODE) ?>;
        const IS_CLIENTE = <?= (isset($_SESSION['usuario']) && $_SESSION['tipo'] == 'cliente') ? 'true' : 'false' ?>;
        const CART_QTD = <?= json_encode($cart_qtd) ?>;
    </script>

    <script>
         


        window.addEventListener('scroll', () => {
            document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 40);
        }, {
            passive: true
        });

         


        const cartToggle = document.getElementById('cart-toggle-btn');
        const cartOverlay = document.getElementById('cart-overlay');
        const cartDrawer = document.getElementById('cart-drawer');
        const cartClose = document.getElementById('cart-close');

        function abrirDrawer() {
            cartOverlay?.classList.add('open');
            cartDrawer?.classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function fecharDrawer() {
            cartOverlay?.classList.remove('open');
            cartDrawer?.classList.remove('open');
            document.body.style.overflow = '';
        }
        cartToggle?.addEventListener('click', e => {
            e.preventDefault();
            abrirDrawer();
        });
        cartOverlay?.addEventListener('click', fecharDrawer);
        cartClose?.addEventListener('click', fecharDrawer);

         


        function toast(msg, tipo = 'success') {
            const c = document.getElementById('toast-container');
            const t = document.createElement('div');
            t.className = `toast${tipo === 'error' ? ' error' : ''}`;
            t.innerHTML = `<i class="fas fa-${tipo==='success'?'check-circle':'exclamation-circle'}"></i> ${msg}`;
            c.appendChild(t);
            setTimeout(() => {
                t.style.opacity = '0';
                t.style.transform = 'translateX(60px)';
                t.style.transition = '.3s';
                setTimeout(() => t.remove(), 310);
            }, 3000);
        }

         


        const searchInput = document.getElementById('live-search');
        const searchDropdown = document.getElementById('search-dropdown');
        let searchTimer;

        searchInput?.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                const q = searchInput.value.trim().toLowerCase();
                if (q.length < 2) {
                    searchDropdown.classList.remove('open');
                    filtrarTexto('');
                    return;
                }

                filtrarTexto(q);

                const matched = PRODUTOS.filter(p =>
                    p.nome.toLowerCase().includes(q) ||
                    p.categoria.toLowerCase().includes(q) ||
                    (p.descricao || '').toLowerCase().includes(q)
                ).slice(0, 6);

                if (matched.length === 0) {
                    searchDropdown.innerHTML = '<div class="search-no-result"><i class="fas fa-search"></i> Nenhum resultado</div>';
                } else {
                    searchDropdown.innerHTML = matched.map(p => `
                <div class="search-result-item" onclick="abrirModal(${p.id})">
                    <div class="search-result-img">
                        ${p.imagem ? `<img src="${p.imagem}" alt="">` : (CAT_ICONS[p.categoria]||'📦')}
                    </div>
                    <div>
                        <div class="search-result-name">${p.nome}</div>
                        <div class="search-result-cat">${p.categoria}</div>
                        <div class="search-result-price">R$ ${p.preco.toFixed(2).replace('.',',')}</div>
                    </div>
                </div>
            `).join('');
                }
                searchDropdown.classList.add('open');
            }, 300);
        });

        document.addEventListener('click', e => {
            if (!document.getElementById('nav-search-wrap')?.contains(e.target)) {
                searchDropdown?.classList.remove('open');
            }
        });

         


        function filtrarCategoria(cat) {
            
            document.querySelectorAll('.filter-chip').forEach(c => {
                c.classList.toggle('active', c.dataset.cat === cat);
            });

            const cards = document.querySelectorAll('.product-card');
            let visiveis = 0;
            cards.forEach((card, i) => {
                const match = !cat || card.dataset.cat === cat;
                if (match) {
                    card.classList.remove('hidden');
                    card.style.animationDelay = (i % 20 * 40) + 'ms';
                    card.style.animation = 'none';
                    void card.offsetHeight; 
                    card.style.animation = 'cardIn .35s both';
                    visiveis++;
                } else {
                    card.classList.add('hidden');
                }
            });

            document.getElementById('empty-state').style.display = visiveis === 0 ? 'block' : 'none';
            document.getElementById('grid-title').textContent = cat || 'Todos os Produtos';
            document.getElementById('grid-count').textContent = visiveis + ' produto(s)';

            
            document.getElementById('catalogo').scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }

         


        function filtrarTexto(q) {
            const cards = document.querySelectorAll('.product-card');
            let visiveis = 0;
            cards.forEach(card => {
                const match = !q ||
                    card.dataset.nome.includes(q) ||
                    card.dataset.desc.includes(q) ||
                    card.dataset.cat.toLowerCase().includes(q);
                card.classList.toggle('hidden', !match);
                if (match) visiveis++;
            });
            document.getElementById('empty-state').style.display = visiveis === 0 ? 'block' : 'none';
            document.getElementById('grid-count').textContent = visiveis + ' produto(s)';
        }

         


        function abrirModal(id) {
            const p = PRODUTOS.find(x => x.id == id);
            if (!p) return;

            const icon = CAT_ICONS[p.categoria] || '📦';
            const imgHtml = p.imagem ?
                `<img src="${p.imagem}" alt="${p.nome}">` :
                `<span style="font-size:72px">${icon}</span>`;

            const estEm = p.estoque > 0;
            const qtdCart = CART_QTD[p.id] || 0;

            let addHtml = '';
            if (IS_CLIENTE) {
                if (!estEm) {
                    addHtml = `<button class="card-add-btn" disabled style="opacity:.4;cursor:not-allowed;">Indisponível</button>`;
                } else if (qtdCart > 0) {
                    addHtml = `
                <div class="qty-controls">
                    <button class="qty-btn" onclick="mudarQtd(${p.id},-1)"><i class="fas fa-minus"></i></button>
                    <span class="qty-num" id="modal-qty-${p.id}">${qtdCart}</span>
                    <button class="qty-btn" onclick="mudarQtd(${p.id},1)"><i class="fas fa-plus"></i></button>
                </div>`;
                } else {
                    addHtml = `<button class="card-add-btn" id="modal-btn-${p.id}" onclick="adicionarCarrinho(${p.id},this)">
                <i class="fas fa-cart-plus"></i> Adicionar à Sacola
            </button>`;
                }
            } else {
                addHtml = `<a href="login.php" class="card-add-btn"><i class="fas fa-sign-in-alt"></i> Entrar para comprar</a>`;
            }

            const stockBadge = p.estoque === 0 ?
                `<span class="stock-badge stock-out">Indisponível</span>` :
                p.estoque <= p.minimo ?
                `<span class="stock-badge stock-low">Últimas unidades</span>` :
                '';

            document.getElementById('modal-content').innerHTML = `
        <div class="modal-inner">
            <div class="modal-img">
                ${imgHtml}
                <button class="modal-close-btn" onclick="fecharModal()"><i class="fas fa-xmark"></i></button>
            </div>
            <div class="modal-info">
                <div class="modal-cat">${icon} ${p.categoria}</div>
                <div class="modal-name">${p.nome}</div>
                ${stockBadge}
                <div class="modal-desc">${p.descricao || 'Sem descrição disponível.'}</div>
                <div class="modal-price">R$ ${p.preco.toFixed(2).replace('.',',')}</div>
                <div class="modal-actions">${addHtml}</div>
            </div>
        </div>`;

            document.getElementById('product-modal').classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function fecharModal() {
            document.getElementById('product-modal').classList.remove('open');
            document.body.style.overflow = '';
        }

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') fecharModal();
        });

         


        async function adicionarCarrinho(id, btn) {
            if (btn) {
                btn.disabled = true;
                const icon = btn.querySelector('i');
                if (icon) {
                    icon.className = 'fas fa-spinner';
                    btn.classList.add('loading');
                }
            }

            try {
                const fd = new FormData();
                fd.append('adicionar_carrinho', '1');
                fd.append('id_produto', id);
                fd.append('tipo_produto', 'normal');
                fd.append('quantidade', '1');
                fd.append('redirect', 'index.php');
                fd.append('csrf_token', CSRF_TOKEN);

                await fetch('carrinho.php', {
                    method: 'POST',
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    body: fd
                });

                CART_QTD[id] = (CART_QTD[id] || 0) + 1;

                
                const footer = document.querySelector(`[data-id="${id}"] .card-footer`);
                if (footer) {
                    const addBtn = footer.querySelector('.card-add-btn');
                    if (addBtn) {
                        const qtyDiv = document.createElement('div');
                        qtyDiv.className = 'qty-controls';
                        qtyDiv.id = `qty-ctrl-${id}`;
                        qtyDiv.innerHTML = `
                    <button class="qty-btn" onclick="mudarQtd(${id},-1)"><i class="fas fa-minus"></i></button>
                    <span class="qty-num" id="qty-num-${id}">1</span>
                    <button class="qty-btn" onclick="mudarQtd(${id},1)"><i class="fas fa-plus"></i></button>`;
                        addBtn.replaceWith(qtyDiv);
                    }
                }

                
                const modalBtn = document.getElementById(`modal-btn-${id}`);
                if (modalBtn) {
                    const qtyDiv = document.createElement('div');
                    qtyDiv.className = 'qty-controls';
                    qtyDiv.innerHTML = `
                <button class="qty-btn" onclick="mudarQtd(${id},-1)"><i class="fas fa-minus"></i></button>
                <span class="qty-num" id="modal-qty-${id}">1</span>
                <button class="qty-btn" onclick="mudarQtd(${id},1)"><i class="fas fa-plus"></i></button>`;
                    modalBtn.replaceWith(qtyDiv);
                }

                toast('Produto adicionado à sacola! 🛍️');
                await atualizarContadorCarrinho();
            } catch (e) {
                toast('Erro ao adicionar', 'error');
                if (btn) {
                    btn.disabled = false;
                    btn.classList.remove('loading');
                    btn.innerHTML = '<i class="fas fa-cart-plus"></i> Adicionar';
                }
            }
        }

         


        async function mudarQtd(id, delta) {
            const atual = CART_QTD[id] || 0;
            const nova = Math.max(0, atual + delta);
            CART_QTD[id] = nova;

            
            ['qty-num-' + id, 'modal-qty-' + id].forEach(elId => {
                const el = document.getElementById(elId);
                if (el) el.textContent = nova;
            });

            if (nova === 0) {
                
                ['qty-ctrl-' + id].forEach(elId => {
                    const el = document.getElementById(elId);
                    if (el) {
                        const btn = document.createElement('button');
                        btn.className = 'card-add-btn';
                        btn.id = `btn-add-${id}`;
                        btn.setAttribute('onclick', `adicionarCarrinho(${id}, this)`);
                        btn.innerHTML = '<i class="fas fa-cart-plus"></i> Adicionar';
                        el.replaceWith(btn);
                    }
                });
            }

            
            const fd = new FormData();
            fd.append('ajax_atualizar_quantidade', '1');
            fd.append('id_carrinho', id); 
            fd.append('quantidade', nova);
            try {
                await fetch('ajax_handler.php', {
                    method: 'POST',
                    body: (() => {
                        const f = new FormData();
                        f.append('action', 'atualizar_quantidade_carrinho');
                        f.append('id_carrinho', id);
                        f.append('quantidade', nova);
                        f.append('csrf_token', CSRF_TOKEN);
                        return f;
                    })()
                });
                await atualizarContadorCarrinho();
            } catch (e) {}
        }

         


        async function atualizarContadorCarrinho() {
            try {
                const data = await (await fetch('ajax_handler.php?action=contar_carrinho')).json();
                const n = data.count || 0;
                const badge = document.getElementById('cart-badge-nav');
                if (badge) {
                    badge.textContent = n;
                    badge.style.display = n > 0 ? 'flex' : 'none';
                }
            } catch (e) {}
        }

         


        document.querySelectorAll('.product-card').forEach(card => {
            card.addEventListener('mousemove', e => {
                const r = card.getBoundingClientRect();
                const x = ((e.clientX - r.left) / r.width - .5) * 8;
                const y = ((e.clientY - r.top) / r.height - .5) * 8;
                card.style.transform = `translateY(-6px) rotateX(${-y}deg) rotateY(${x}deg)`;
                card.style.transition = 'transform .1s ease';
            });
            card.addEventListener('mouseleave', () => {
                card.style.transform = '';
                card.style.transition = 'transform .4s cubic-bezier(.4,0,.2,1)';
            });
        });

         


        const observer = new IntersectionObserver(entries => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    e.target.style.opacity = '1';
                    observer.unobserve(e.target);
                }
            });
        }, {
            threshold: .1
        });

        document.querySelectorAll('.product-card').forEach((c, i) => {
            c.style.opacity = '0';
            setTimeout(() => observer.observe(c), i * 30);
        });

         


        setInterval(atualizarContadorCarrinho, 20000);
    </script>
</body>

</html>
