<?php
require_once FARMAVIDA_ROOT . '/app/core/bootstrap.php';
require_once FARMAVIDA_ROOT . '/app/core/config.php';
require_once FARMAVIDA_ROOT . '/app/core/helpers.php';
require_once FARMAVIDA_ROOT . '/services/schema_service.php';

if (isset($_SESSION['usuario'])) redirecionar('index.php');

if (schema_componentes_pendentes($conn, ['auth'])) {
    http_response_code(503);
    die('Redefinicao de senha temporariamente indisponivel. Execute as migracoes pendentes no painel administrativo.');
}

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$erro  = '';
$reset = null;

 
if ($token !== '') {
    $stmt = $conn->prepare(
        "SELECT pr.*, u.nome, u.email
         FROM password_resets pr
         JOIN usuarios u ON pr.id_usuario = u.id
         WHERE pr.token = ?
           AND pr.usado = 0
           AND pr.expira_em > NOW()"
    );
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $reset = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$reset && $token !== '') {
    $erro = 'Link inválido ou expirado. Solicite um novo link.';
} elseif ($token === '') {
    $erro = 'Token ausente. Solicite um novo link.';
}

 
$sucesso = false;
if (!$erro && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    $nova_senha = $_POST['nova_senha']      ?? '';
    $confirmar  = $_POST['confirmar_senha'] ?? '';

    if (strlen($nova_senha) < 6) {
        $erro = 'A senha deve ter no mínimo 6 caracteres.';
    } elseif ($nova_senha !== $confirmar) {
        $erro = 'As senhas não coincidem.';
    } else {
        $hash = password_hash($nova_senha, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
        $stmt->bind_param("si", $hash, $reset['id_usuario']);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE password_resets SET usado = 1 WHERE token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->close();

        $sucesso = true;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir senha - FarmaVida</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=1774207549">
</head>
<body class="auth-page">
    <div class="login-container">
        <a href="index.php" class="logo">
            <div class="logo-icon"><i class="fas fa-prescription-bottle-medical"></i></div>
            FarmaVida
        </a>
        <h2 class="auth-title">Nova senha</h2>

        <?php if ($sucesso): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Senha redefinida com sucesso!
            </div>
            <div style="text-align:center;margin-top:20px;">
                <a href="login.php" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;">
                    <i class="fas fa-sign-in-alt"></i> Fazer login agora
                </a>
            </div>

        <?php elseif ($erro && !$reset): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($erro) ?>
            </div>
            <div class="links">
                <p><a href="esqueci_senha.php"><i class="fas fa-redo"></i> Solicitar novo link</a></p>
                <p><a href="login.php"><i class="fas fa-arrow-left"></i> Voltar ao login</a></p>
            </div>

        <?php else: ?>
            <p class="auth-subtitle">
                Olá, <strong><?= htmlspecialchars($reset['nome']) ?></strong>. Defina sua nova senha abaixo.
            </p>

            <?php if ($erro): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($erro) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <?= campo_csrf() ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Nova senha <small style="font-weight:400;color:var(--gray);">(mín. 6 chars)</small></label>
                    <input type="password" name="nova_senha" required minlength="6"
                           placeholder="Crie uma senha forte" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Confirmar nova senha</label>
                    <input type="password" name="confirmar_senha" required
                           placeholder="Repita a senha" autocomplete="new-password">
                </div>

                <button type="submit" class="btn btn-success btn-lg" style="width:100%;justify-content:center;">
                    <i class="fas fa-check"></i> Salvar nova senha
                </button>
            </form>

            <div class="links">
                <p><a href="login.php"><i class="fas fa-arrow-left"></i> Voltar ao login</a></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
