<?php
function sanitizar_texto($texto) {
    return htmlspecialchars(trim($texto), ENT_QUOTES, 'UTF-8');
}

function validar_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
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

function upload_imagem($arquivo, $pasta = 'uploads/') {
    if (!file_exists($pasta)) {
        mkdir($pasta, 0777, true);
    }
    $extensoes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extensao, $extensoes)) {
        return ['sucesso' => false, 'mensagem' => 'Formato de imagem inválido'];
    }
    if ($arquivo['size'] > 5242880) {
        return ['sucesso' => false, 'mensagem' => 'Imagem muito grande (máx 5MB)'];
    }
    $nome = uniqid() . '.' . $extensao;
    $caminho = $pasta . $nome;
    if (move_uploaded_file($arquivo['tmp_name'], $caminho)) {
        return ['sucesso' => true, 'caminho' => $caminho];
    }
    return ['sucesso' => false, 'mensagem' => 'Erro ao fazer upload'];
}
?>
