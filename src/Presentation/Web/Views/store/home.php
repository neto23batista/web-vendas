<style>
.store-shell{max-width:1280px;margin:0 auto;padding:28px 18px 70px;position:relative;z-index:1}
.store-nav{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:20px 0 32px}
.store-brand{display:flex;align-items:center;gap:12px;text-decoration:none;color:var(--text);font-family:'Bricolage Grotesque',sans-serif;font-size:28px;font-weight:800}
.store-brand-badge{width:44px;height:44px;border-radius:14px;background:var(--gradient-main);display:flex;align-items:center;justify-content:center;color:#071018;box-shadow:var(--shadow-green)}
.store-nav-actions{display:flex;flex-wrap:wrap;gap:10px;justify-content:flex-end}
.hero-panel{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(280px,.6fr);gap:24px;align-items:stretch;margin-bottom:28px}
.hero-copy,.hero-cart{background:var(--surface);border:1px solid var(--border);border-radius:24px;box-shadow:var(--shadow-lg)}
.hero-copy{padding:34px;position:relative;overflow:hidden}
.hero-copy::after{content:'';position:absolute;inset:auto -60px -80px auto;width:220px;height:220px;border-radius:50%;background:radial-gradient(circle,rgba(0,229,160,.28),transparent 70%)}
.hero-kicker{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border-radius:999px;background:rgba(0,229,160,.1);border:1px solid rgba(0,229,160,.22);color:var(--primary);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;margin-bottom:18px}
.hero-title{font-family:'Bricolage Grotesque',sans-serif;font-size:clamp(34px,5vw,58px);line-height:1.02;letter-spacing:-.04em;margin-bottom:16px}
.hero-title span{background:var(--gradient-main);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.hero-text{max-width:620px;color:var(--text2);font-size:16px;line-height:1.8;margin-bottom:24px}
.hero-actions{display:flex;flex-wrap:wrap;gap:12px}
.hero-cart{padding:24px}
.hero-cart h3{font-family:'Bricolage Grotesque',sans-serif;font-size:24px;margin-bottom:6px}
.hero-cart p{color:var(--text2);font-size:14px;margin-bottom:18px}
.cart-list{display:grid;gap:12px;margin-bottom:18px}
.cart-item{display:flex;align-items:center;gap:12px;padding:12px;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:16px}
.cart-item img{width:52px;height:52px;object-fit:cover;border-radius:12px}
.cart-item-name{font-size:14px;font-weight:700}
.cart-item-meta{font-size:12px;color:var(--text2)}
.hero-cart .btn{width:100%;justify-content:center}
.filter-bar{display:grid;grid-template-columns:minmax(0,1fr);gap:12px;margin-bottom:24px}
.search-form{display:grid;grid-template-columns:minmax(0,1fr) 190px auto auto;gap:12px}
.input-clean,.select-clean{width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:16px;color:var(--text);padding:14px 16px;font:inherit}
.chip-row{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:26px}
.chip{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:999px;background:var(--surface2);border:1px solid var(--border);color:var(--text2);text-decoration:none;font-size:14px}
.chip.active,.chip:hover{color:var(--text);border-color:rgba(0,229,160,.35);background:rgba(0,229,160,.1)}
.section-head{display:flex;justify-content:space-between;align-items:end;gap:16px;margin-bottom:16px}
.section-title{font-family:'Bricolage Grotesque',sans-serif;font-size:28px}
.section-sub{color:var(--text2);font-size:14px}
.featured-grid,.products-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:18px}
.product-card{display:flex;flex-direction:column;background:var(--surface);border:1px solid var(--border);border-radius:22px;overflow:hidden;box-shadow:var(--shadow-md)}
.product-card img{width:100%;aspect-ratio:4/3;object-fit:cover;background:var(--bg2)}
.product-body{display:grid;gap:14px;padding:18px}
.product-meta{display:flex;justify-content:space-between;align-items:center;gap:10px;color:var(--text2);font-size:13px}
.product-name{font-size:18px;font-weight:800;line-height:1.3}
.product-description{font-size:14px;color:var(--text2);line-height:1.7;min-height:70px}
.product-footer{display:flex;justify-content:space-between;align-items:center;gap:14px}
.price{font-family:'Bricolage Grotesque',sans-serif;font-size:28px;font-weight:800;color:var(--primary)}
.stock{font-size:12px;color:var(--text2)}
.actions-inline{display:flex;gap:10px;flex-wrap:wrap}
.empty-state{padding:34px;border-radius:22px;background:var(--surface);border:1px dashed var(--border2);color:var(--text2);text-align:center}
.message-stack{display:grid;gap:12px;margin-bottom:18px}
.message{padding:14px 16px;border-radius:16px;font-size:14px;font-weight:600}
.message.success{background:rgba(0,229,160,.12);border:1px solid rgba(0,229,160,.22);color:#b5ffe4}
.message.error{background:rgba(255,77,109,.12);border:1px solid rgba(255,77,109,.22);color:#ffd1da}
@media (max-width:980px){.hero-panel{grid-template-columns:1fr}.search-form{grid-template-columns:1fr}}
</style>

<div class="store-shell">
    <nav class="store-nav">
        <a href="index.php" class="store-brand">
            <span class="store-brand-badge"><i class="fas fa-prescription-bottle-medical"></i></span>
            FarmaVida
        </a>
        <div class="store-nav-actions">
            <?php if ($isOwner): ?>
                <a href="painel_dono.php" class="btn btn-primary"><i class="fas fa-chart-line"></i> Painel</a>
                <a href="logout.php" class="btn btn-secondary"><i class="fas fa-right-from-bracket"></i> Sair</a>
            <?php elseif ($isLoggedIn): ?>
                <a href="painel_cliente.php" class="btn btn-primary"><i class="fas fa-user"></i> Minha conta</a>
                <a href="carrinho.php" class="btn btn-secondary"><i class="fas fa-bag-shopping"></i> Sacola</a>
                <a href="logout.php" class="btn btn-secondary"><i class="fas fa-right-from-bracket"></i> Sair</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary"><i class="fas fa-right-to-bracket"></i> Entrar</a>
                <a href="cadastro.php" class="btn btn-secondary"><i class="fas fa-user-plus"></i> Criar conta</a>
            <?php endif; ?>
        </div>
    </nav>

    <?php if (!empty($messages['success']) || !empty($messages['error'])): ?>
        <div class="message-stack">
            <?php if (!empty($messages['success'])): ?>
                <div class="message success"><?= htmlspecialchars($messages['success']) ?></div>
            <?php endif; ?>
            <?php if (!empty($messages['error'])): ?>
                <div class="message error"><?= htmlspecialchars($messages['error']) ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <section class="hero-panel">
        <div class="hero-copy">
            <div class="hero-kicker"><i class="fas fa-bolt"></i> Camada nova</div>
            <h1 class="hero-title">Catálogo com <span>controller, service e repository</span>.</h1>
            <p class="hero-text">A vitrine pública, login e cadastro foram migrados para uma arquitetura limpa em `src/`, mantendo as URLs atuais e isolando o legado em módulos ainda não migrados.</p>
            <div class="hero-actions">
                <a href="#catalogo" class="btn btn-primary"><i class="fas fa-pills"></i> Ver produtos</a>
                <?php if ($isLoggedIn): ?>
                    <a href="painel_cliente.php" class="btn btn-secondary"><i class="fas fa-box"></i> Meus pedidos</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-secondary"><i class="fas fa-user-lock"></i> Entrar para comprar</a>
                <?php endif; ?>
            </div>
        </div>

        <aside class="hero-cart">
            <h3><?= $isLoggedIn ? 'Sua sacola' : 'Acesso rápido' ?></h3>
            <p><?= $isLoggedIn ? 'Resumo dos itens mais recentes do carrinho atual.' : 'Entre para acompanhar pedidos, comprar e gerenciar seus dados.' ?></p>

            <?php if ($isLoggedIn && !empty($cartPreview)): ?>
                <div class="cart-list">
                    <?php foreach ($cartPreview as $item): ?>
                        <div class="cart-item">
                            <img src="<?= htmlspecialchars($item['imagem_resolvida']) ?>" alt="<?= htmlspecialchars($item['nome']) ?>">
                            <div>
                                <div class="cart-item-name"><?= htmlspecialchars($item['nome']) ?></div>
                                <div class="cart-item-meta"><?= (int)$item['quantidade'] ?> unidade(s) · R$ <?= number_format((float)$item['preco'], 2, ',', '.') ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <a href="carrinho.php" class="btn btn-primary"><i class="fas fa-bag-shopping"></i> Abrir sacola (<?= (int)$cartCount ?>)</a>
            <?php elseif ($isLoggedIn): ?>
                <div class="empty-state">Sua sacola está vazia no momento.</div>
                <a href="#catalogo" class="btn btn-primary" style="margin-top:18px;"><i class="fas fa-cart-plus"></i> Escolher produtos</a>
            <?php else: ?>
                <div class="empty-state">Faça login para comprar, acompanhar pedidos e salvar seus dados.</div>
                <a href="login.php" class="btn btn-primary" style="margin-top:18px;"><i class="fas fa-right-to-bracket"></i> Entrar</a>
            <?php endif; ?>
        </aside>
    </section>

    <section id="catalogo">
        <div class="filter-bar">
            <form method="GET" class="search-form">
                <input class="input-clean" type="text" name="busca" placeholder="Buscar por nome, descrição ou categoria" value="<?= htmlspecialchars($searchTerm) ?>">
                <select class="select-clean" name="categoria">
                    <option value="">Todas as categorias</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= htmlspecialchars($category['categoria']) ?>" <?= $selectedCategory === $category['categoria'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['categoria']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Filtrar</button>
                <a class="btn btn-secondary" href="index.php"><i class="fas fa-rotate-left"></i> Limpar</a>
            </form>
        </div>

        <div class="chip-row">
            <a class="chip <?= $selectedCategory === '' ? 'active' : '' ?>" href="index.php">📦 Todos</a>
            <?php foreach ($categories as $category): ?>
                <a class="chip <?= $selectedCategory === $category['categoria'] ? 'active' : '' ?>" href="index.php?categoria=<?= urlencode($category['categoria']) ?>">
                    <?= htmlspecialchars($category['icone']) ?> <?= htmlspecialchars($category['categoria']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($featuredProducts)): ?>
            <div class="section-head">
                <div>
                    <h2 class="section-title">Mais pedidos</h2>
                    <p class="section-sub">Produtos com maior saída no momento.</p>
                </div>
            </div>
            <div class="featured-grid" style="margin-bottom:28px;">
                <?php foreach ($featuredProducts as $product): ?>
                    <article class="product-card">
                        <img src="<?= htmlspecialchars($product['imagem_resolvida']) ?>" alt="<?= htmlspecialchars($product['nome']) ?>">
                        <div class="product-body">
                            <div class="product-meta">
                                <span><?= htmlspecialchars($product['icone_categoria']) ?> <?= htmlspecialchars($product['categoria']) ?></span>
                                <span>Vendidos: <?= (int)($product['total_vendido'] ?? 0) ?></span>
                            </div>
                            <div class="product-name"><?= htmlspecialchars($product['nome']) ?></div>
                            <div class="product-footer">
                                <div class="price">R$ <?= number_format((float)$product['preco'], 2, ',', '.') ?></div>
                                <div class="stock">Estoque <?= max(0, (int)($product['estoque_atual'] ?? 0)) ?></div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="section-head">
            <div>
                <h2 class="section-title">Catálogo</h2>
                <p class="section-sub"><?= count($products) ?> produto(s) encontrado(s).</p>
            </div>
        </div>

        <?php if (empty($products)): ?>
            <div class="empty-state">Nenhum produto encontrado com esse filtro.</div>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                    <article class="product-card">
                        <img src="<?= htmlspecialchars($product['imagem_resolvida']) ?>" alt="<?= htmlspecialchars($product['nome']) ?>">
                        <div class="product-body">
                            <div class="product-meta">
                                <span><?= htmlspecialchars($product['icone_categoria']) ?> <?= htmlspecialchars($product['categoria']) ?></span>
                                <span>Estoque <?= max(0, (int)($product['estoque_atual'] ?? 0)) ?></span>
                            </div>
                            <div class="product-name"><?= htmlspecialchars($product['nome']) ?></div>
                            <div class="product-description"><?= htmlspecialchars($product['descricao'] ?? '') ?></div>
                            <div class="product-footer">
                                <div>
                                    <div class="price">R$ <?= number_format((float)$product['preco'], 2, ',', '.') ?></div>
                                    <div class="stock"><?= ((int)($product['estoque_atual'] ?? 0) <= (int)($product['estoque_minimo'] ?? 0)) ? 'Reposição recomendada' : 'Disponível' ?></div>
                                </div>
                                <div class="actions-inline">
                                    <?php if ($isClient): ?>
                                        <form method="POST" action="carrinho.php">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="adicionar_carrinho" value="1">
                                            <input type="hidden" name="id_produto" value="<?= (int)$product['id'] ?>">
                                            <input type="hidden" name="tipo_produto" value="normal">
                                            <input type="hidden" name="quantidade" value="1">
                                            <input type="hidden" name="redirect" value="index.php">
                                            <button type="submit" class="btn btn-primary"><i class="fas fa-cart-plus"></i> Adicionar</button>
                                        </form>
                                    <?php elseif ($isOwner): ?>
                                        <a href="gerenciar_produtos.php?editar=<?= (int)$product['id'] ?>" class="btn btn-secondary"><i class="fas fa-pen"></i> Editar</a>
                                    <?php else: ?>
                                        <a href="login.php" class="btn btn-primary"><i class="fas fa-right-to-bracket"></i> Entrar</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
