<div class="cadastro-container">
    <a href="index.php" class="logo">
        <div class="logo-icon"><i class="fas fa-prescription-bottle-medical"></i></div>
        FarmaVida
    </a>
    <h2 class="auth-title">Criar conta</h2>
    <p class="auth-subtitle">Cadastre-se para comprar com rapidez.</p>

    <?php if (!empty($messages['success'])): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($messages['success']) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <div class="form-group">
            <label><i class="fas fa-user"></i> Nome completo</label>
            <input type="text" name="nome" required placeholder="Seu nome completo" value="<?= htmlspecialchars((string)($old['nome'] ?? '')) ?>">
        </div>

        <div class="form-group">
            <label><i class="fas fa-envelope"></i> E-mail</label>
            <input type="email" name="email" required autocomplete="email" placeholder="seu@email.com" value="<?= htmlspecialchars((string)($old['email'] ?? '')) ?>">
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label><i class="fas fa-id-card"></i> CPF</label>
                <input type="text" name="cpf" inputmode="numeric" maxlength="14" placeholder="000.000.000-00" value="<?= htmlspecialchars((string)($old['cpf'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label><i class="fas fa-phone"></i> Telefone</label>
                <input type="text" name="telefone" placeholder="(00) 00000-0000" value="<?= htmlspecialchars((string)($old['telefone'] ?? '')) ?>">
            </div>
        </div>

        <div class="form-group">
            <label><i class="fas fa-map-marker-alt"></i> Endereço</label>
            <textarea name="endereco" rows="2" placeholder="Rua, número, bairro, cidade"><?= htmlspecialchars((string)($old['endereco'] ?? '')) ?></textarea>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Senha</label>
                <input type="password" name="senha" required minlength="6" autocomplete="new-password" placeholder="Crie uma senha">
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Confirmar senha</label>
                <input type="password" name="confirmar_senha" required autocomplete="new-password" placeholder="Repita a senha">
            </div>
        </div>

        <button type="submit" class="btn btn-success btn-lg" style="width:100%;justify-content:center;">
            <i class="fas fa-user-plus"></i> Criar conta
        </button>
    </form>

    <div class="links">
        <p>Já tem conta? <a href="login.php">Faça login</a></p>
        <p><a href="index.php"><i class="fas fa-arrow-left"></i> Voltar à loja</a></p>
    </div>
</div>
