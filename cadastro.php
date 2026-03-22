<?php
session_start();
include "config.php";
include "helpers.php";
include "mailer.php";

if (isset($_SESSION['usuario'])) redirecionar('index.php');

$erro = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verificar_csrf();

    $nome      = sanitizar_texto($_POST['nome']      ?? '');
    $email     = sanitizar_texto($_POST['email']     ?? '');
    $senha     = $_POST['senha']            ?? '';
    $confirmar = $_POST['confirmar_senha']  ?? '';
    $telefone  = sanitizar_texto($_POST['telefone']  ?? '');
    $endereco  = sanitizar_texto($_POST['endereco']  ?? '');
    $cpf_raw   = $_POST['cpf']              ?? '';
    $cpf       = preg_replace('/\D/', '', $cpf_raw);   // apenas dígitos

    if (empty($nome) || empty($email) || empty($senha)) {
        $erro = 'Preencha todos os campos obrigatórios!';
    } elseif (!validar_email($email)) {
        $erro = 'E-mail inválido!';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha deve ter no mínimo 6 caracteres!';
    } elseif ($senha !== $confirmar) {
        $erro = 'As senhas não coincidem!';
    } elseif (!empty($cpf) && !validar_cpf($cpf)) {
        $erro = 'CPF inválido! Verifique os números digitados.';
    } else {
        // Verificar e-mail duplicado
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $existe_email = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        // Verificar CPF duplicado (se informado)
        $existe_cpf = false;
        if (!empty($cpf)) {
            $cpf_fmt = formatar_cpf($cpf);
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE cpf = ?");
            $stmt->bind_param("s", $cpf_fmt);
            $stmt->execute();
            $existe_cpf = $stmt->get_result()->num_rows > 0;
            $stmt->close();
        }

        if ($existe_email) {
            $erro = 'Este e-mail já está cadastrado!';
        } elseif ($existe_cpf) {
            $erro = 'Este CPF já está cadastrado!';
        } else {
            $senha_hash = hash_senha($senha);
            $cpf_salvo  = !empty($cpf) ? formatar_cpf($cpf) : null;

            $stmt = $conn->prepare(
                "INSERT INTO usuarios (nome, email, senha, telefone, endereco, cpf)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("ssssss", $nome, $email, $senha_hash, $telefone, $endereco, $cpf_salvo);

            if ($stmt->execute()) {
                $stmt->close();
                // E-mail de boas-vindas (melhor esforço — não bloqueia o fluxo)
                $corpo = email_boas_vindas($nome);
                enviar_email($email, 'Bem-vindo à FarmaVida! 💊', $corpo);
                redirecionar('login.php', 'Conta criada com sucesso! Faça login para continuar.');
            } else {
                $erro = 'Erro ao cadastrar. Tente novamente!';
            }
            $stmt->close();
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
    <link rel="stylesheet" href="style.css?v=1774207549">
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
            <?= campo_csrf() ?>

            <div class="form-group">
                <label><i class="fas fa-user"></i> Nome Completo *</label>
                <input type="text" name="nome" required placeholder="Seu nome completo"
                       value="<?= isset($_POST['nome']) ? htmlspecialchars($_POST['nome']) : '' ?>">
            </div>

            <div class="form-group">
                <label><i class="fas fa-envelope"></i> E-mail *</label>
                <input type="email" name="email" required placeholder="seu@email.com"
                       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                       autocomplete="email">
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label><i class="fas fa-id-card"></i> CPF <small style="font-weight:400;color:var(--gray);">(opcional)</small></label>
                    <input type="text" name="cpf" placeholder="000.000.000-00"
                           maxlength="14" inputmode="numeric"
                           value="<?= isset($_POST['cpf']) ? htmlspecialchars($_POST['cpf']) : '' ?>"
                           oninput="mascaraCPF(this)">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Telefone / WhatsApp</label>
                    <input type="tel" name="telefone" placeholder="(00) 00000-0000"
                           value="<?= isset($_POST['telefone']) ? htmlspecialchars($_POST['telefone']) : '' ?>">
                </div>
            </div>

            <div class="form-group">
                <label><i class="fas fa-map-marker-alt"></i> Endereço</label>
                <textarea name="endereco" rows="2" placeholder="Rua, número, bairro, cidade"><?= isset($_POST['endereco']) ? htmlspecialchars($_POST['endereco']) : '' ?></textarea>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Senha * <small style="font-weight:400;color:var(--gray);">(mín. 6 chars)</small></label>
                    <input type="password" name="senha" required minlength="6"
                           placeholder="Crie uma senha" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Confirmar Senha *</label>
                    <input type="password" name="confirmar_senha" required
                           placeholder="Repita a senha" autocomplete="new-password">
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

    <script>
    function mascaraCPF(input) {
        let v = input.value.replace(/\D/g, '').substring(0, 11);
        if (v.length > 9)      v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
        else if (v.length > 6) v = v.replace(/(\d{3})(\d{3})(\d{3})/, '$1.$2.$3');
        else if (v.length > 3) v = v.replace(/(\d{3})(\d{3})/, '$1.$2');
        input.value = v;
    }
    </script>
</body>
</html>
