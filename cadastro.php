<?php
session_start();
include "config.php";
include "helpers.php";

if (isset($_SESSION['usuario'])) redirecionar('index.php');

$erro = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome      = sanitizar_texto($_POST['nome']);
    $email     = sanitizar_texto($_POST['email']);
    $senha     = $_POST['senha'];
    $confirmar = $_POST['confirmar_senha'];
    $telefone  = sanitizar_texto($_POST['telefone']);
    $endereco  = sanitizar_texto($_POST['endereco']);

    if (empty($nome) || empty($email) || empty($senha)) {
        $erro = 'Preencha todos os campos obrigatórios!';
    } elseif (!validar_email($email)) {
        $erro = 'E-mail inválido!';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha deve ter no mínimo 6 caracteres!';
    } elseif ($senha !== $confirmar) {
        $erro = 'As senhas não coincidem!';
    } else {
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $erro = 'Este e-mail já está cadastrado!';
        } else {
            $senha_hash = hash_senha($senha);
            $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, telefone, endereco) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $nome, $email, $senha_hash, $telefone, $endereco);
            if ($stmt->execute()) {
                redirecionar('login.php', 'Conta criada com sucesso! Faça login para continuar.', 'sucesso');
            } else {
                $erro = 'Erro ao cadastrar. Tente novamente!';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Conta - FarmaVida</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">
    <div class="cadastro-container">
        <a href="index.php" class="logo">
            <div class="logo-icon"><i class="fas fa-prescription-bottle-medical"></i></div>
            FarmaVida
        </a>
        <h2 class="auth-title">Criar Conta</h2>
        <p class="auth-subtitle">Cadastre-se para comprar com praticidade</p>

        <?php if ($erro): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $erro ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label><i class="fas fa-user"></i> Nome Completo *</label>
                <input type="text" name="nome" required placeholder="Seu nome completo"
                       value="<?= isset($_POST['nome']) ? htmlspecialchars($_POST['nome']) : '' ?>">
            </div>

            <div class="form-group">
                <label><i class="fas fa-envelope"></i> E-mail *</label>
                <input type="email" name="email" required placeholder="seu@email.com"
                       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
            </div>

            <div class="form-group">
                <label><i class="fas fa-phone"></i> Telefone / WhatsApp</label>
                <input type="tel" name="telefone" placeholder="(00) 00000-0000"
                       value="<?= isset($_POST['telefone']) ? htmlspecialchars($_POST['telefone']) : '' ?>">
            </div>

            <div class="form-group">
                <label><i class="fas fa-map-marker-alt"></i> Endereço</label>
                <textarea name="endereco" rows="2" placeholder="Rua, número, bairro, cidade"><?= isset($_POST['endereco']) ? htmlspecialchars($_POST['endereco']) : '' ?></textarea>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Senha * <small style="font-weight:400;color:var(--gray);">(mín. 6 chars)</small></label>
                    <input type="password" name="senha" required minlength="6" placeholder="Crie uma senha">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Confirmar Senha *</label>
                    <input type="password" name="confirmar_senha" required placeholder="Repita a senha">
                </div>
            </div>

            <button type="submit" class="btn btn-success btn-lg" style="width:100%;justify-content:center;margin-top:8px;">
                <i class="fas fa-user-plus"></i> Criar Conta
            </button>
        </form>

        <div class="links">
            <p>Já tem conta? <a href="login.php">Faça login aqui</a></p>
            <p><a href="index.php"><i class="fas fa-arrow-left"></i> Voltar à loja</a></p>
        </div>
    </div>
</body>
</html>
