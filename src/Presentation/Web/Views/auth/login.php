<div class="login-container">
    <a href="index.php" class="logo">
        <div class="logo-icon"><i class="fas fa-prescription-bottle-medical"></i></div>
        FarmaVida
    </a>
    <h2 class="auth-title">Entrar</h2>
    <p class="auth-subtitle">Acesse sua conta para continuar comprando.</p>

    <?php if (!empty($messages['success'])): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($messages['success']) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$blocked): ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> E-mail</label>
                <input type="email" name="email" required autocomplete="email" placeholder="seu@email.com" value="<?= htmlspecialchars((string)($old['email'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label style="display:flex;justify-content:space-between;align-items:center;">
                    <span><i class="fas fa-lock"></i> Senha</span>
                    <a href="esqueci_senha.php" style="font-size:12px;color:var(--primary);text-decoration:none;font-weight:600;">Esqueci minha senha</a>
                </label>
                <input type="password" name="senha" required autocomplete="current-password" placeholder="Sua senha">
            </div>
            <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;">
                <i class="fas fa-right-to-bracket"></i> Entrar
            </button>
        </form>
    <?php endif; ?>

    <div class="links">
        <p>Não tem conta? <a href="cadastro.php">Cadastre-se</a></p>
        <p><a href="index.php"><i class="fas fa-arrow-left"></i> Voltar à loja</a></p>
    </div>
</div>
