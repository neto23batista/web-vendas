<?php
require_once FARMAVIDA_ROOT . '/app/core/bootstrap.php';
require_once FARMAVIDA_ROOT . '/app/core/config.php';
require_once FARMAVIDA_ROOT . '/app/core/helpers.php';
require_once FARMAVIDA_ROOT . '/app/integrations/mailer.php';
require_once FARMAVIDA_ROOT . '/services/schema_service.php';

if (isset($_SESSION['usuario'])) redirecionar('index.php');

if (schema_componentes_pendentes($conn, ['auth'])) {
    http_response_code(503);
    die('Recuperacao de senha temporariamente indisponivel. Execute as migracoes pendentes no painel administrativo.');
}

if (false) {

// Criar tabela de tokens se nÃ£o existir
$conn->query("CREATE TABLE IF NOT EXISTS password_resets (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    id_usuario INT NOT NULL,
    token      VARCHAR(64) UNIQUE NOT NULL,
    expira_em  DATETIME NOT NULL,
    usado      TINYINT(1) DEFAULT 0,
    criado_em  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_token   (token),
    INDEX idx_usuario (id_usuario)
) ENGINE=InnoDB");

}

$mensagem = '';
$tipo_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    $email = sanitizar_texto($_POST['email'] ?? '');

    if (!validar_email($email)) {
        $mensagem = 'E-mail invÃ¡lido!';
        $tipo_msg = 'erro';
    } else {
        // Mensagem genÃ©rica independente de existir ou nÃ£o (anti-enumeraÃ§Ã£o)
        $stmt = $conn->prepare("SELECT id, nome FROM usuarios WHERE email = ? AND tipo = 'cliente'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $usuario = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($usuario) {
            // Invalida tokens anteriores
            $stmt = $conn->prepare("UPDATE password_resets SET usado = 1 WHERE id_usuario = ? AND usado = 0");
            $stmt->bind_param("i", $usuario['id']);
            $stmt->execute();
            $stmt->close();

            $token  = bin2hex(random_bytes(32));
            $expira = date('Y-m-d H:i:s', time() + 3600);

            $stmt = $conn->prepare("INSERT INTO password_resets (id_usuario, token, expira_em) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $usuario['id'], $token, $expira);
            $stmt->execute();
            $stmt->close();

            $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                  . '://' . $_SERVER['HTTP_HOST']
                  . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $link = $base . '/redefinir_senha.php?token=' . urlencode($token);

            email_redefinir_senha($email, $usuario['nome'], $link);
        }

        $mensagem = 'Se este e-mail estiver cadastrado, vocÃª receberÃ¡ as instruÃ§Ãµes em instantes. Verifique tambÃ©m a pasta de spam.';
        $tipo_msg = 'sucesso';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Esqueci minha senha - FarmaVida</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=1774207549">
</head>
<body class="auth-page">
    <div class="login-container">
        <a href="index.php" class="logo">
            <div class="logo-icon"><i class="fas fa-prescription-bottle-medical"></i></div>
            FarmaVida
        </a>
        <h2 class="auth-title">Esqueceu a senha?</h2>
        <p class="auth-subtitle">Informe seu e-mail e enviaremos um link para redefinir.</p>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipo_msg === 'sucesso' ? 'success' : 'error' ?>">
                <i class="fas fa-<?= $tipo_msg === 'sucesso' ? 'check' : 'exclamation' ?>-circle"></i>
                <?= $mensagem ?>
            </div>
        <?php endif; ?>

        <?php if ($tipo_msg !== 'sucesso'): ?>
        <form method="POST">
            <?= campo_csrf() ?>
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> E-mail cadastrado</label>
                <input type="email" name="email" required placeholder="seu@email.com"
                       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                       autocomplete="email">
            </div>
            <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;">
                <i class="fas fa-paper-plane"></i> Enviar link de redefiniÃ§Ã£o
            </button>
        </form>
        <?php endif; ?>

        <div class="links">
            <p><a href="login.php"><i class="fas fa-arrow-left"></i> Voltar ao login</a></p>
        </div>
    </div>
</body>
</html>
