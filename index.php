<?php
session_status() === PHP_SESSION_NONE && session_start();
include "config.php";
include_once "helpers.php";

$categoria   = $_GET['categoria'] ?? '';
$busca       = $_GET['busca'] ?? '';
$usuario_id  = $_SESSION['id_usuario'] ?? 0;

$where  = ["disponivel = 1"];
$params = [];
if ($categoria) { $where[] = "categoria = ?"; $params[] = $categoria; }
if ($busca) {
    $where[] = "(nome LIKE ? OR descricao LIKE ?)";
    $b = "%$busca%";
    $params[] = $b; $params[] = $b;
}

$sql  = "SELECT * FROM produtos WHERE " . implode(" AND ", $where) . " ORDER BY categoria, nome";
$stmt = $conn->prepare($sql);
if ($params) { $stmt->bind_param(str_repeat('s', count($params)), ...$params); }
$stmt->execute();
$produtos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$produtos_por_categoria = [];
foreach ($produtos as $p) {
    $cat = $p['categoria'] ?: 'Outros';
    $produtos_por_categoria[$cat][] = $p;
}

$categorias = $conn->query("SELECT DISTINCT categoria FROM produtos WHERE disponivel=1 AND categoria IS NOT NULL AND categoria!='' ORDER BY categoria")->fetch_all(MYSQLI_ASSOC);
$cart_count = $usuario_id ? $conn->query("SELECT COUNT(*) as t FROM carrinho WHERE id_cliente=$usuario_id")->fetch_assoc()['t'] : 0;

$msg = $_SESSION['sucesso'] ?? $_SESSION['erro'] ?? '';
$msg_tipo = isset($_SESSION['sucesso']) ? 'success' : 'error';
unset($_SESSION['sucesso'], $_SESSION['erro']);

$cat_icons = [
    'Medicamentos'       => 'pills',
    'Vitaminas'          => 'capsules',
    'Higiene Pessoal'    => 'pump-soap',
    'Dermocosméticos'    => 'spa',
    'Infantil'           => 'baby',
    'Bem-Estar'          => 'heart-pulse',
    'Primeiros Socorros' => 'kit-medical',
    'Ortopedia'          => 'wheelchair',
    'Genéricos'          => 'tablet-alt',
    'Outros'             => 'box',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FarmaVida – Sua Farmácia Online</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- HEADER -->
    <div class="header">
        <div class="header-container">
            <a href="index.php" class="logo">
                <div class="logo-icon"><i class="fas fa-prescription-bottle-medical"></i></div>
                Farma<span>Vida</span>
            </a>
            <div class="nav-buttons">
                <?php if (isset($_SESSION['usuario'])): ?>
                    <?php if ($_SESSION['tipo'] == 'cliente'): ?>
                        <a href="carrinho.php" class="btn btn-primary carrinho-badge" style="position:relative;">
                            <i class="fas fa-shopping-bag"></i> Sacola
                            <?php if ($cart_count > 0): ?>
                                <span class="badge-num" id="cart-badge"><?= $cart_count ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="painel_cliente.php" class="btn btn-secondary"><i class="fas fa-user"></i> Minha Conta</a>
                    <?php else: ?>
                        <a href="painel_dono.php" class="btn btn-primary"><i class="fas fa-tachometer-alt"></i> Painel Admin</a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i></a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-secondary"><i class="fas fa-sign-in-alt"></i> Entrar</a>
                    <a href="cadastro.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> Cadastrar</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- HERO -->
        <div class="hero">
            <div class="hero-content">
                <h1>Sua saúde em <span>boas mãos</span> 💊</h1>
                <p>Medicamentos, cosméticos, vitaminas e muito mais — com entrega rápida.</p>
                <div class="hero-badges">
                    <div class="hero-badge"><i class="fas fa-truck-fast"></i> Entrega Rápida</div>
                    <div class="hero-badge"><i class="fas fa-shield-halved"></i> Compra Segura</div>
                    <div class="hero-badge"><i class="fas fa-tag"></i> Melhores Preços</div>
                    <div class="hero-badge"><i class="fas fa-user-doctor"></i> Orientação Farmacêutica</div>
                </div>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_tipo ?>">
                <i class="fas fa-<?= $msg_tipo == 'success' ? 'check' : 'exclamation' ?>-circle"></i> <?= $msg ?>
            </div>
        <?php endif; ?>

        <!-- FILTERS BAR -->
        <div class="filters-bar">
            <a href="index.php" class="filter-btn <?= !$categoria ? 'active' : '' ?>">
                <i class="fas fa-th-large"></i> Todos
            </a>
            <?php foreach ($categorias as $cat): ?>
                <a href="?categoria=<?= urlencode($cat['categoria']) ?>"
                   class="filter-btn <?= $categoria == $cat['categoria'] ? 'active' : '' ?>">
                    <?= htmlspecialchars($cat['categoria']) ?>
                </a>
            <?php endforeach; ?>

            <form method="GET" style="display:flex;gap:8px;flex:1;min-width:220px;">
                <?php if ($categoria): ?>
                    <input type="hidden" name="categoria" value="<?= htmlspecialchars($categoria) ?>">
                <?php endif; ?>
                <input type="text" name="busca" class="search-input"
                       placeholder="🔍 Buscar produtos, medicamentos..."
                       value="<?= htmlspecialchars($busca) ?>">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
            </form>
        </div>

        <!-- PRODUTOS -->
        <?php if (empty($produtos)): ?>
            <div class="card empty">
                <i class="fas fa-search"></i>
                <h2>Nenhum produto encontrado</h2>
                <p>Tente buscar por outro nome ou categoria</p>
                <a href="index.php" class="btn btn-primary"><i class="fas fa-sync"></i> Ver Todos</a>
            </div>
        <?php else: ?>
            <?php foreach ($produtos_por_categoria as $cat => $prods): ?>
                <?php $icon = $cat_icons[$cat] ?? 'box'; ?>
                <div class="categoria-section">
                    <div class="categoria-title">
                        <span class="categoria-icon"><i class="fas fa-<?= $icon ?>"></i></span>
                        <?= htmlspecialchars($cat) ?>
                    </div>

                    <div class="produtos-grid">
                        <?php foreach ($prods as $p): ?>
                            <div class="produto-card" style="position:relative;">
                                <div style="position:relative;overflow:hidden;">
                                    <?php if ($p['imagem'] && file_exists($p['imagem'])): ?>
                                        <img src="<?= htmlspecialchars($p['imagem']) ?>"
                                             alt="<?= htmlspecialchars($p['nome']) ?>"
                                             class="produto-imagem">
                                    <?php else: ?>
                                        <div class="produto-imagem" style="display:flex;align-items:center;justify-content:center;background:var(--bg);">
                                            <i class="fas fa-<?= $icon ?>" style="font-size:52px;color:var(--light-gray);"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="produto-info">
                                    <span class="produto-categoria">
                                        <i class="fas fa-<?= $icon ?>"></i> <?= htmlspecialchars($p['categoria']) ?>
                                    </span>
                                    <div class="produto-nome"><?= htmlspecialchars($p['nome']) ?></div>
                                    <div class="produto-descricao"><?= htmlspecialchars($p['descricao']) ?></div>

                                    <div class="produto-footer">
                                        <div class="produto-preco">R$ <?= number_format($p['preco'], 2, ',', '.') ?></div>

                                        <?php if (isset($_SESSION['usuario']) && $_SESSION['tipo'] == 'cliente'): ?>
                                            <button type="button"
                                                    class="btn btn-success add-btn"
                                                    onclick="adicionarCarrinho(<?= $p['id'] ?>, this)">
                                                <i class="fas fa-cart-plus"></i> Adicionar
                                            </button>
                                        <?php else: ?>
                                            <a href="login.php" class="btn btn-primary">
                                                <i class="fas fa-sign-in-alt"></i> Entrar
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- FLOATING CART -->
    <?php if (isset($_SESSION['usuario']) && $_SESSION['tipo'] == 'cliente'): ?>
        <div class="floating-cart" id="floating-cart" style="<?= $cart_count > 0 ? '' : 'display:none;' ?>">
            <a href="carrinho.php">
                <i class="fas fa-shopping-bag"></i>
                <span class="count" id="floating-count"><?= $cart_count ?></span>
            </a>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['usuario']) && $_SESSION['tipo'] == 'cliente'): ?>
    <script>
        function mostrarToast(mensagem, tipo = 'success') {
            document.querySelectorAll('.toast').forEach(t => t.remove());
            const toast = document.createElement('div');
            toast.className = `toast ${tipo === 'error' ? 'error' : ''}`;
            toast.innerHTML = `<i class="fas fa-${tipo === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${mensagem}`;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(20px)';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        async function adicionarCarrinho(idProduto, btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            try {
                const formData = new FormData();
                formData.append('adicionar_carrinho', '1');
                formData.append('id_produto', idProduto);
                formData.append('tipo_produto', 'normal');
                formData.append('quantidade', '1');
                formData.append('redirect', 'index.php');

                await fetch('carrinho.php', { method: 'POST', body: formData });

                btn.innerHTML = '<i class="fas fa-check"></i> Adicionado!';
                btn.style.background = 'var(--gradient-success)';
                mostrarToast('Produto adicionado à sacola!');
                await atualizarContador();

                setTimeout(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-cart-plus"></i> Adicionar';
                    btn.style.background = '';
                }, 2200);
            } catch (e) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-cart-plus"></i> Adicionar';
                mostrarToast('Erro ao adicionar', 'error');
            }
        }

        async function atualizarContador() {
            try {
                const response = await fetch('ajax_handler.php?action=contar_carrinho');
                const data = await response.json();
                const badge = document.getElementById('cart-badge');
                const floatingCart = document.getElementById('floating-cart');
                const floatingCount = document.getElementById('floating-count');

                if (data.count > 0) {
                    if (badge) { badge.textContent = data.count; badge.style.display = 'flex'; }
                    if (floatingCart) { floatingCart.style.display = 'block'; floatingCount.textContent = data.count; }
                } else {
                    if (badge) badge.style.display = 'none';
                    if (floatingCart) floatingCart.style.display = 'none';
                }
            } catch (e) {}
        }

        setInterval(atualizarContador, 15000);
    </script>
    <?php endif; ?>
</body>
</html>
