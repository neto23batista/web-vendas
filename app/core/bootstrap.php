<?php
if (!defined('FARMAVIDA_ROOT')) {
    define('FARMAVIDA_ROOT', dirname(__DIR__, 2));
}

function enviar_headers_seguranca(): void {
    if (headers_sent()) {
        return;
    }

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

function iniciar_sessao_segura(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/; samesite=Lax', '', $https, true);
    }

    session_name('farmavida_session');
    session_start();

    $agora = time();
    $ttlRegeneracao = 1800;

    if (!isset($_SESSION['__sessao_criada_em'])) {
        $_SESSION['__sessao_criada_em'] = $agora;
    } elseif (($agora - (int)$_SESSION['__sessao_criada_em']) >= $ttlRegeneracao) {
        session_regenerate_id(true);
        $_SESSION['__sessao_criada_em'] = $agora;
    }
}

enviar_headers_seguranca();
iniciar_sessao_segura();
