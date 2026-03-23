<?php
// ============================================================
// CONFIGURAÇÃO MERCADO PAGO – FarmaVida
// ============================================================
// 1. Acesse: https://www.mercadopago.com.br/developers
// 2. Crie um aplicativo
// 3. Preencha suas credenciais abaixo (ou use variáveis de
//    ambiente: MP_ACCESS_TOKEN e MP_PUBLIC_KEY)
// ============================================================

// --- SUAS CREDENCIAIS ---
// Prefira variáveis de ambiente a colocar tokens em código:
//   putenv("MP_ACCESS_TOKEN=APP_USR-xxx"); no .env ou no servidor
define('MP_ACCESS_TOKEN', getenv('MP_ACCESS_TOKEN') ?: 'SEU_ACCESS_TOKEN_AQUI');
define('MP_PUBLIC_KEY',   getenv('MP_PUBLIC_KEY')   ?: 'SUA_PUBLIC_KEY_AQUI');

// --- AMBIENTE ---
// 'sandbox'    → testes (use credenciais TEST-)
// 'production' → produção (use credenciais APP_USR-)
define('MP_AMBIENTE', getenv('MP_AMBIENTE') ?: 'sandbox');

// --- URL BASE DETECTADA AUTOMATICAMENTE ---
// Funciona em localhost E em produção sem precisar alterar nada.
// Se precisar forçar um domínio específico, defina a variável de
// ambiente MP_BASE_URL no servidor.
if (getenv('MP_BASE_URL')) {
    define('MP_BASE_URL', rtrim(getenv('MP_BASE_URL'), '/'));
} else {
    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basepath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    define('MP_BASE_URL', "{$scheme}://{$host}{$basepath}");
}

// --- URLS DE RETORNO ---
define('MP_URL_SUCESSO',  MP_BASE_URL . '/pagamento_retorno.php');
define('MP_URL_FALHA',    MP_BASE_URL . '/pagamento_retorno.php');
define('MP_URL_PENDENTE', MP_BASE_URL . '/pagamento_retorno.php');
define('MP_URL_WEBHOOK',  MP_BASE_URL . '/pagamento_webhook.php');

// --- API BASE ---
define('MP_API_URL', 'https://api.mercadopago.com');

// ============================================================
// FUNÇÃO AUXILIAR: chamada cURL para a API do Mercado Pago
// ============================================================
function mp_request(string $method, string $endpoint, array $body = []): array {
    $url = MP_API_URL . $endpoint;

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . MP_ACCESS_TOKEN,
            'Content-Type: application/json',
            'X-Idempotency-Key: farmavida-' . bin2hex(random_bytes(8)),
        ],
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['erro' => true, 'mensagem' => 'Erro cURL: ' . $error, 'code' => 0];
    }

    $data = json_decode($response, true) ?? [];
    $data['_http_code'] = $httpCode;
    return $data;
}
