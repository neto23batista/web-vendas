<?php
require_once FARMAVIDA_ROOT . '/app/core/bootstrap.php';
require_once FARMAVIDA_ROOT . '/app/core/config.php';
require_once FARMAVIDA_ROOT . '/app/core/helpers.php';

verificar_login('dono');

// â”€â”€ ADICIONAR PRODUTO â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if (isset($_POST['adicionar_produto'])) {
    verificar_csrf();

    $nome      = sanitizar_texto($_POST['nome']      ?? '');
    $descricao = sanitizar_texto($_POST['descricao'] ?? '');
    $preco     = (float)($_POST['preco'] ?? 0);
    $categoria = sanitizar_texto($_POST['categoria'] ?? '');

    if (!isset($_FILES['imagem']) || $_FILES['imagem']['error'] == UPLOAD_ERR_NO_FILE) {
        redirecionar('gerenciar_produtos.php', 'Por favor, adicione uma imagem do produto!', 'erro');
    }

    $resultado = upload_imagem($_FILES['imagem']);
    if (!$resultado['sucesso']) {
        redirecionar('gerenciar_produtos.php', $resultado['mensagem'], 'erro');
    }
    $imagem = $resultado['caminho'];

    $stmt = $conn->prepare(
        "INSERT INTO produtos (nome, descricao, preco, categoria, imagem) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("ssdss", $nome, $descricao, $preco, $categoria, $imagem);
    $stmt->execute();
    $stmt->close();
    redirecionar('gerenciar_produtos.php', 'Produto adicionado com sucesso!');
}

// â”€â”€ EDITAR PRODUTO â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if (isset($_POST['editar_produto'])) {
    verificar_csrf();

    $id        = (int)($_POST['id'] ?? 0);
    $nome      = sanitizar_texto($_POST['nome']      ?? '');
    $descricao = sanitizar_texto($_POST['descricao'] ?? '');
    $preco     = (float)($_POST['preco'] ?? 0);
    $categoria = sanitizar_texto($_POST['categoria'] ?? '');
    $disponivel= isset($_POST['disponivel']) ? 1 : 0;

    $stmt = $conn->prepare("SELECT imagem FROM produtos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $produto = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $imagem = $produto['imagem'] ?? '';

    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] == 0) {
        $resultado = upload_imagem($_FILES['imagem']);
        if ($resultado['sucesso']) {
            if ($imagem && file_exists($imagem)) unlink($imagem);
            $imagem = $resultado['caminho'];
        }
    }

    $stmt = $conn->prepare(
        "UPDATE produtos SET nome=?, descricao=?, preco=?, categoria=?, imagem=?, disponivel=? WHERE id=?"
    );
    $stmt->bind_param("ssdssii", $nome, $descricao, $preco, $categoria, $imagem, $disponivel, $id);
    $stmt->execute();
    $stmt->close();
    redirecionar('gerenciar_produtos.php', 'Produto atualizado!');
}

// â”€â”€ DELETAR PRODUTO â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if (isset($_POST['deletar_produto'])) {
    verificar_csrf(); // token via GET para confirmaÃ§Ã£o
    $id = (int)($_POST['id'] ?? 0);

    $stmt = $conn->prepare("SELECT imagem FROM produtos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $produto = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $imagem_local = caminho_upload_local($produto['imagem'] ?? null);
    if ($imagem_local && file_exists($imagem_local)) {
        unlink($imagem_local);
    }

    $stmt = $conn->prepare("DELETE FROM produtos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    redirecionar('gerenciar_produtos.php', 'Produto removido!');
}

// â”€â”€ LISTAGEM COM BUSCA E PAGINAÃ‡ÃƒO â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$busca    = $_GET['busca'] ?? '';
$pagina   = max(1, (int)($_GET['pagina'] ?? 1));
$por_pag  = 20;
$offset   = ($pagina - 1) * $por_pag;

if ($busca !== '') {
    $stmt_count = $conn->prepare(
        "SELECT COUNT(*) as t FROM produtos WHERE nome LIKE ? OR categoria LIKE ?"
    );
    $like = "%{$busca}%";
    $stmt_count->bind_param("ss", $like, $like);
    $stmt_count->execute();
    $total_registros = (int)$stmt_count->get_result()->fetch_assoc()['t'];
    $stmt_count->close();

    $stmt = $conn->prepare(
        "SELECT * FROM produtos WHERE nome LIKE ? OR categoria LIKE ? ORDER BY nome LIMIT ? OFFSET ?"
    );
    $stmt->bind_param("ssii", $like, $like, $por_pag, $offset);
} else {
    $stmt_count = $conn->prepare("SELECT COUNT(*) as t FROM produtos");
    $stmt_count->execute();
    $total_registros = (int)$stmt_count->get_result()->fetch_assoc()['t'];
    $stmt_count->close();

    $stmt = $conn->prepare("SELECT * FROM produtos ORDER BY nome LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $por_pag, $offset);
}

$stmt->execute();
$produtos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_paginas = (int)ceil($total_registros / $por_pag);

$produto_editar = null;
if (isset($_GET['editar'])) {
    $id_edit = (int)$_GET['editar'];
    $stmt = $conn->prepare("SELECT * FROM produtos WHERE id = ?");
    $stmt->bind_param("i", $id_edit);
    $stmt->execute();
    $produto_editar = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Produtos - FarmaVida</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=1774207549">
    <style>
        .paginacao { display:flex; gap:6px; justify-content:center; margin-top:20px; flex-wrap:wrap; }
        .paginacao a, .paginacao span {
            padding:7px 14px; border-radius:var(--radius-full); font-size:13px; font-weight:600;
            border:2px solid var(--light-gray); text-decoration:none; color:var(--gray);
        }
        .paginacao a:hover { border-color:var(--primary); color:var(--primary); }
        .paginacao .atual  { background:var(--primary); border-color:var(--primary); color:white; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-container">
            <div class="logo" style="cursor:default;">
                <div class="logo-icon"><i class="fas fa-boxes"></i></div>
                Gerenciar Produtos
            </div>
            <a href="painel_dono.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
        </div>
    </div>

    <div class="container">
        <?php if (isset($_SESSION['sucesso'])): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $_SESSION['sucesso'] ?></div>
            <?php unset($_SESSION['sucesso']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['erro'])): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $_SESSION['erro'] ?></div>
            <?php unset($_SESSION['erro']); ?>
        <?php endif; ?>

        <!-- FORMULÃRIO -->
        <div class="card">
            <h2><i class="fas fa-<?= $produto_editar ? 'edit' : 'plus' ?>"></i> <?= $produto_editar ? 'Editar' : 'Adicionar' ?> Produto</h2>

            <form method="POST" enctype="multipart/form-data">
                <?= campo_csrf() ?>
                <?php if ($produto_editar): ?>
                    <input type="hidden" name="id" value="<?= (int)$produto_editar['id'] ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-pills"></i> Nome do Produto *</label>
                        <input type="text" name="nome" value="<?= htmlspecialchars($produto_editar['nome'] ?? '') ?>" required placeholder="Ex: Dipirona 500mg">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-dollar-sign"></i> PreÃ§o (R$) *</label>
                        <input type="number" name="preco" step="0.01" min="0.01"
                               value="<?= htmlspecialchars($produto_editar['preco'] ?? '') ?>" required placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Categoria *</label>
                        <select name="categoria" required>
                            <option value="">Selecione uma categoria</option>
                            <?php
                            $cats = ['Medicamentos','GenÃ©ricos','Vitaminas','Higiene Pessoal','DermocosmÃ©ticos','Infantil','Bem-Estar','Primeiros Socorros','Ortopedia'];
                            foreach ($cats as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>"
                                    <?= (isset($produto_editar) && $produto_editar['categoria'] == $c) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-image"></i> Imagem <?= $produto_editar ? '(opcional)' : '*' ?></label>
                        <input type="file" name="imagem" accept="image/jpeg,image/png,image/gif,image/webp"
                               <?= $produto_editar ? '' : 'required' ?>>
                        <?php if ($produto_editar && $produto_editar['imagem']): ?>
                            <img src="<?= htmlspecialchars($produto_editar['imagem']) ?>"
                                 alt="Preview" style="max-width:160px;border-radius:10px;margin-top:10px;">
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> DescriÃ§Ã£o *</label>
                    <textarea name="descricao" required placeholder="IndicaÃ§Ãµes, composiÃ§Ã£o, posologia..."><?= htmlspecialchars($produto_editar['descricao'] ?? '') ?></textarea>
                </div>

                <?php if ($produto_editar): ?>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="disponivel" <?= $produto_editar['disponivel'] ? 'checked' : '' ?>>
                            Produto disponÃ­vel para venda
                        </label>
                    </div>
                <?php endif; ?>

                <div style="display:flex;gap:10px;">
                    <button type="submit" name="<?= $produto_editar ? 'editar_produto' : 'adicionar_produto' ?>" class="btn btn-success">
                        <i class="fas fa-save"></i> <?= $produto_editar ? 'Atualizar' : 'Adicionar' ?>
                    </button>
                    <?php if ($produto_editar): ?>
                        <a href="gerenciar_produtos.php" class="btn btn-danger"><i class="fas fa-times"></i> Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- LISTA -->
        <div class="card">
            <h2><i class="fas fa-list"></i> Produtos Cadastrados (<?= $total_registros ?>)</h2>

            <div class="search-bar">
                <form method="GET" style="display:flex;gap:10px;width:100%;">
                    <input type="text" name="busca" placeholder="Buscar produto ou categoria..."
                           value="<?= htmlspecialchars($busca) ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                    <a href="gerenciar_produtos.php" class="btn btn-secondary"><i class="fas fa-sync"></i></a>
                </form>
            </div>

            <?php if (empty($produtos)): ?>
                <div class="empty" style="padding:40px;">
                    <i class="fas fa-pills"></i>
                    <h2>Nenhum produto encontrado</h2>
                </div>
            <?php else: ?>
                <table class="produtos-table">
                    <thead>
                        <tr>
                            <th>Imagem</th><th>Nome</th><th>Categoria</th><th>PreÃ§o</th><th>Status</th><th>AÃ§Ãµes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produtos as $p): ?>
                            <tr>
                                <td>
                                    <?php if ($p['imagem'] && file_exists($p['imagem'])): ?>
                                        <img src="<?= htmlspecialchars($p['imagem']) ?>"
                                             alt="<?= htmlspecialchars($p['nome']) ?>">
                                    <?php else: ?>
                                        <div style="width:56px;height:56px;background:var(--bg);border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;border:1px solid var(--light-gray);">
                                            <i class="fas fa-pills" style="color:var(--light-gray);"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($p['nome']) ?></strong></td>
                                <td><?= htmlspecialchars($p['categoria']) ?></td>
                                <td><strong style="color:var(--primary);"><?= formatar_preco($p['preco']) ?></strong></td>
                                <td><span class="badge badge-<?= $p['disponivel'] ? 'success' : 'danger' ?>"><?= $p['disponivel'] ? 'DisponÃ­vel' : 'IndisponÃ­vel' ?></span></td>
                                <td>
                                    <a href="?editar=<?= $p['id'] ?>" class="btn btn-warning" style="padding:8px 14px;font-size:13px;">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                     <form method="POST" style="display:inline-flex;">
                                         <?= campo_csrf() ?>
                                         <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                         <button type="submit"
                                                 name="deletar_produto"
                                                 class="btn btn-danger"
                                                 style="padding:8px 14px;font-size:13px;"
                                                 onclick="return confirm('Remover este produto?')">
                                             <i class="fas fa-trash"></i>
                                         </button>
                                     </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- PAGINAÃ‡ÃƒO -->
                <?php if ($total_paginas > 1): ?>
                <div class="paginacao">
                    <?php if ($pagina > 1): ?>
                        <a href="?pagina=<?= $pagina - 1 ?>&busca=<?= urlencode($busca) ?>">&#8592; Anterior</a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $pagina - 2); $i <= min($total_paginas, $pagina + 2); $i++): ?>
                        <?php if ($i === $pagina): ?>
                            <span class="atual"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?pagina=<?= $i ?>&busca=<?= urlencode($busca) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($pagina < $total_paginas): ?>
                        <a href="?pagina=<?= $pagina + 1 ?>&busca=<?= urlencode($busca) ?>">PrÃ³xima &#8594;</a>
                    <?php endif; ?>

                    <span style="color:var(--gray);padding:7px 0;">
                        PÃ¡gina <?= $pagina ?> de <?= $total_paginas ?>
                        (<?= $total_registros ?> produto<?= $total_registros != 1 ? 's' : '' ?>)
                    </span>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
