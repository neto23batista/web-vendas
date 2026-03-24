<?php
require_once FARMAVIDA_ROOT . '/app/core/bootstrap.php';
require_once FARMAVIDA_ROOT . '/app/core/config.php';
require_once FARMAVIDA_ROOT . '/app/core/helpers.php';

if (isset($_SESSION['usuario'])) {
    redirecionar($_SESSION['tipo'] == 'dono' ? 'painel_dono.php' : 'index.php');
}

$erro = '';

if (!isset($_SESSION['login_tentativas']))   $_SESSION['login_tentativas']   = 0;
if (!isset($_SESSION['login_bloqueio_ate'])) $_SESSION['login_bloqueio_ate'] = 0;

$bloqueado = $_SESSION['login_bloqueio_ate'] > time();
if ($bloqueado) {
    $restante = ceil(($_SESSION['login_bloqueio_ate'] - time()) / 60);
    $erro = "Muitas tentativas. Tente novamente em {$restante} minuto(s).";
}

if (!$bloqueado && $_SERVER['REQUEST_METHOD'] == 'POST') {
    verificar_csrf();

    $email = sanitizar_texto($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (empty($email) || empty($senha)) {
        $erro = 'Preencha todos os campos!';
    } else {
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $usuario = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($usuario && verificar_senha($senha, $usuario['senha'])) {
            session_regenerate_id(true);
            $_SESSION['__sessao_criada_em'] = time();
            $_SESSION['login_tentativas']   = 0;
            $_SESSION['login_bloqueio_ate'] = 0;
            $_SESSION['id_usuario']         = $usuario['id'];
            $_SESSION['usuario']            = $usuario['nome'];
            $_SESSION['tipo']               = $usuario['tipo'];
            redirecionar(
                $usuario['tipo'] == 'dono' ? 'painel_dono.php' : 'index.php',
                'Bem-vindo de volta, ' . $usuario['nome'] . '!'
            );
        }

        $_SESSION['login_tentativas']++;
        if ($_SESSION['login_tentativas'] >= 5) {
            $_SESSION['login_bloqueio_ate'] = time() + 900;
            $_SESSION['login_tentativas']   = 0;
            $erro = 'Muitas tentativas. Conta bloqueada por 15 minutos.';
        } else {
            $restantes = 5 - $_SESSION['login_tentativas'];
            $erro = "E-mail ou senha incorretos! ({$restantes} tentativa(s) restante(s))";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar - FarmaVida</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=1774207549">
</head>
<body class="auth-page">
    <div class="login-container">
        <a href="index.php" class="logo">
            <div class="logo-icon"><i class="fas fa-prescription-bottle-medical"></i></div>
            FarmaVida
        </a>
        <h2 class="auth-title">Bem-vindo de volta!</h2>
        <p class="auth-subtitle">Entre na sua conta para fazer compras</p>

        <?php if ($erro): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= $erro ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['sucesso'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $_SESSION['sucesso'] ?>
            </div>
            <?php unset($_SESSION['sucesso']); ?>
        <?php endif; ?>

        <?php if (!$bloqueado): ?>
        <form method="POST">
            <?= campo_csrf() ?>
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> E-mail</label>
                <input type="email" name="email" required placeholder="seu@email.com"
                       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                       autocomplete="email">
            </div>
            <div class="form-group">
                <label style="display:flex;justify-content:space-between;align-items:center;">
                    <span><i class="fas fa-lock"></i> Senha</span>
                    <a href="esqueci_senha.php"
                       style="font-size:12px;color:var(--primary);text-decoration:none;font-weight:500;">
                        Esqueci minha senha
                    </a>
                </label>
                <input type="password" name="senha" required placeholder="Sua senha"
                       autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;">
                <i class="fas fa-sign-in-alt"></i> Entrar
            </button>
        </form>
        <?php endif; ?>

        <div class="links">
            <p>Não tem conta? <a href="cadastro.php">Cadastre-se grátis</a></p>
            <p><a href="index.php"><i class="fas fa-arrow-left"></i> Voltar à loja</a></p>
        </div>
    </div>
</body>
</html>
