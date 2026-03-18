<?php
session_start();
include "config.php";
include "helpers.php";

verificar_login('dono');

if (isset($_POST['adicionar_produto'])) {
    $nome      = sanitizar_texto($_POST['nome']);
    $descricao = sanitizar_texto($_POST['descricao']);
    $preco     = (float) $_POST['preco'];
    $categoria = sanitizar_texto($_POST['categoria']);
    $imagem    = '';

    if (!isset($_FILES['imagem']) || $_FILES['imagem']['error'] == UPLOAD_ERR_NO_FILE) {
        $_SESSION['erro'] = 'Por favor, adicione uma imagem do produto!';
        redirecionar('gerenciar_produtos.php'); exit;
    }
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] == 0) {
        $resultado = upload_imagem($_FILES['imagem']);
        if ($resultado['sucesso']) { $imagem = $resultado['caminho']; }
        else { $_SESSION['erro'] = $resultado['mensagem']; redirecionar('gerenciar_produtos.php'); exit; }
    }

    $stmt = $conn->prepare("INSERT INTO produtos (nome, descricao, preco, categoria, imagem) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdss", $nome, $descricao, $preco, $categoria, $imagem);
    $stmt->execute();
    redirecionar('gerenciar_produtos.php', 'Produto adicionado com sucesso!');
}

if (isset($_POST['editar_produto'])) {
    $id        = (int) $_POST['id'];
    $nome      = sanitizar_texto($_POST['nome']);
    $descricao = sanitizar_texto($_POST['descricao']);
    $preco     = (float) $_POST['preco'];
    $categoria = sanitizar_texto($_POST['categoria']);
    $disponivel= isset($_POST['disponivel']) ? 1 : 0;

    $produto = $conn->query("SELECT imagem FROM produtos WHERE id=$id")->fetch_assoc();
    $imagem  = $produto['imagem'];

    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] == 0) {
        $resultado = upload_imagem($_FILES['imagem']);
        if ($resultado['sucesso']) {
            if ($imagem && file_exists($imagem)) unlink($imagem);
            $imagem = $resultado['caminho'];
        }
    }

    $stmt = $conn->prepare("UPDATE produtos SET nome=?, descricao=?, preco=?, categoria=?, imagem=?, disponivel=? WHERE id=?");
    $stmt->bind_param("ssdssii", $nome, $descricao, $preco, $categoria, $imagem, $disponivel, $id);
    $stmt->execute();
    redirecionar('gerenciar_produtos.php', 'Produto atualizado!');
}

if (isset($_GET['deletar'])) {
    $id = (int) $_GET['deletar'];
    $produto = $conn->query("SELECT imagem FROM produtos WHERE id=$id")->fetch_assoc();
    if ($produto['imagem'] && file_exists($produto['imagem'])) unlink($produto['imagem']);
    $conn->query("DELETE FROM produtos WHERE id=$id");
    redirecionar('gerenciar_produtos.php', 'Produto removido!');
}

$busca = $_GET['busca'] ?? '';
$where = $busca ? "WHERE nome LIKE '%$busca%' OR categoria LIKE '%$busca%'" : '';
$produtos = $conn->query("SELECT * FROM produtos $where ORDER BY nome")->fetch_all(MYSQLI_ASSOC);

$produto_editar = null;
if (isset($_GET['editar'])) {
    $produto_editar = $conn->query("SELECT * FROM produtos WHERE id=" . (int) $_GET['editar'])->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Produtos - FarmaVida</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
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

        <!-- FORMULÁRIO -->
        <div class="card">
            <h2><i class="fas fa-<?= $produto_editar ? 'edit' : 'plus' ?>"></i> <?= $produto_editar ? 'Editar' : 'Adicionar' ?> Produto</h2>

            <form method="POST" enctype="multipart/form-data">
                <?php if ($produto_editar): ?>
                    <input type="hidden" name="id" value="<?= $produto_editar['id'] ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-pills"></i> Nome do Produto *</label>
                        <input type="text" name="nome" value="<?= $produto_editar['nome'] ?? '' ?>" required placeholder="Ex: Dipirona 500mg">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-dollar-sign"></i> Preço (R$) *</label>
                        <input type="number" name="preco" step="0.01" value="<?= $produto_editar['preco'] ?? '' ?>" required placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Categoria *</label>
                        <select name="categoria" required>
                            <option value="">Selecione uma categoria</option>
                            <?php
                            $cats = ['Medicamentos','Genéricos','Vitaminas','Higiene Pessoal','Dermocosméticos','Infantil','Bem-Estar','Primeiros Socorros','Ortopedia'];
                            foreach ($cats as $c): ?>
                                <option value="<?= $c ?>" <?= (isset($produto_editar) && $produto_editar['categoria']==$c)?'selected':'' ?>><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-image"></i> Imagem <?= $produto_editar ? '(opcional)' : '*' ?></label>
                        <input type="file" name="imagem" accept="image/*" <?= $produto_editar ? '' : 'required' ?>>
                        <?php if ($produto_editar && $produto_editar['imagem']): ?>
                            <img src="<?= $produto_editar['imagem'] ?>" alt="Preview" style="max-width:160px;border-radius:10px;margin-top:10px;">
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Descrição *</label>
                    <textarea name="descricao" required placeholder="Indicações, composição, posologia..."><?= $produto_editar['descricao'] ?? '' ?></textarea>
                </div>

                <?php if ($produto_editar): ?>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="disponivel" <?= $produto_editar['disponivel'] ? 'checked' : '' ?>>
                            Produto disponível para venda
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
            <h2><i class="fas fa-list"></i> Produtos Cadastrados (<?= count($produtos) ?>)</h2>

            <div class="search-bar">
                <form method="GET" style="display:flex;gap:10px;width:100%;">
                    <input type="text" name="busca" placeholder="Buscar produto ou categoria..." value="<?= htmlspecialchars($busca) ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                    <a href="gerenciar_produtos.php" class="btn btn-secondary"><i class="fas fa-sync"></i></a>
                </form>
            </div>

            <?php if (empty($produtos)): ?>
                <div class="empty" style="padding:40px;"><i class="fas fa-pills"></i><h2>Nenhum produto cadastrado</h2></div>
            <?php else: ?>
                <table class="produtos-table">
                    <thead>
                        <tr>
                            <th>Imagem</th><th>Nome</th><th>Categoria</th><th>Preço</th><th>Status</th><th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produtos as $p): ?>
                            <tr>
                                <td>
                                    <?php if ($p['imagem'] && file_exists($p['imagem'])): ?>
                                        <img src="<?= $p['imagem'] ?>" alt="<?= $p['nome'] ?>">
                                    <?php else: ?>
                                        <div style="width:56px;height:56px;background:var(--bg);border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;border:1px solid var(--light-gray);">
                                            <i class="fas fa-pills" style="color:var(--light-gray);"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($p['nome']) ?></strong></td>
                                <td><?= htmlspecialchars($p['categoria']) ?></td>
                                <td><strong style="color:var(--primary);"><?= formatar_preco($p['preco']) ?></strong></td>
                                <td><span class="badge badge-<?= $p['disponivel'] ? 'success' : 'danger' ?>"><?= $p['disponivel'] ? 'Disponível' : 'Indisponível' ?></span></td>
                                <td>
                                    <a href="?editar=<?= $p['id'] ?>" class="btn btn-warning" style="padding:8px 14px;font-size:13px;"><i class="fas fa-edit"></i></a>
                                    <a href="?deletar=<?= $p['id'] ?>" class="btn btn-danger" style="padding:8px 14px;font-size:13px;" onclick="return confirm('Remover este produto?')"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
