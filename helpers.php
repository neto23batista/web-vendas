<?php
function sanitizar_texto($texto) {
    return htmlspecialchars(trim($texto), ENT_QUOTES, 'UTF-8');
}

function validar_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validar_cpf(string $cpf): bool {
    $cpf = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) return false;
    for ($t = 9; $t < 11; $t++) {
        $d = 0;
        for ($c = 0; $c < $t; $c++) $d += $cpf[$c] * ($t + 1 - $c);
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) return false;
    }
    return true;
}

function formatar_cpf(string $cpf): string {
    $cpf = preg_replace('/\D/', '', $cpf);
    return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
}

function hash_senha($senha) {
    return password_hash($senha, PASSWORD_DEFAULT);
}

function verificar_senha($senha, $hash) {
    return password_verify($senha, $hash);
}

function formatar_preco($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function redirecionar($url, $mensagem = '', $tipo = 'sucesso') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if ($mensagem) {
        $_SESSION[$tipo] = $mensagem;
    }
    header("Location: $url");
    exit;
}

function verificar_login($tipo_requerido = null) {
    if (!isset($_SESSION['usuario'])) {
        redirecionar('login.php', 'Você precisa fazer login!', 'erro');
    }
    if ($tipo_requerido && $_SESSION['tipo'] != $tipo_requerido) {
        redirecionar('index.php', 'Acesso negado!', 'erro');
    }
}

// ── CSRF ─────────────────────────────────────────────────────
function gerar_token_csrf(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica o token CSRF do POST.
 * Chama die() com HTTP 403 se inválido.
 */
function verificar_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Requisição inválida (token CSRF ausente ou incorreto).');
    }
}

/**
 * Retorna o campo hidden HTML com o token CSRF.
 * Use dentro de qualquer <form> que faça POST.
 */
function campo_csrf(): string {
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(gerar_token_csrf(), ENT_QUOTES, 'UTF-8') . '">';
}
// ─────────────────────────────────────────────────────────────

function upload_imagem($arquivo, $pasta = 'uploads/') {
    if (!file_exists($pasta)) {
        mkdir($pasta, 0755, true);
    }

    $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $mime_permitidos      = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));

    // Valida extensão E mime type real (não o declarado pelo browser)
    $finfo     = new finfo(FILEINFO_MIME_TYPE);
    $mime_real = $finfo->file($arquivo['tmp_name']);

    if (!in_array($extensao, $extensoes_permitidas, true) || !in_array($mime_real, $mime_permitidos, true)) {
        return ['sucesso' => false, 'mensagem' => 'Formato de imagem inválido'];
    }
    if ($arquivo['size'] > 5242880) {
        return ['sucesso' => false, 'mensagem' => 'Imagem muito grande (máx 5MB)'];
    }

    $nome    = bin2hex(random_bytes(16)) . '.' . $extensao;
    $caminho = $pasta . $nome;

    if (move_uploaded_file($arquivo['tmp_name'], $caminho)) {
        return ['sucesso' => true, 'caminho' => $caminho];
    }
    return ['sucesso' => false, 'mensagem' => 'Erro ao fazer upload'];
}
