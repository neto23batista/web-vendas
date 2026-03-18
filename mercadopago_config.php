<?php
// ============================================================
// CONFIGURAÇÃO MERCADO PAGO – FarmaVida
// ============================================================
// 1. Acesse: https://www.mercadopago.com.br/developers
// 2. Crie um aplicativo
// 3. Copie suas credenciais abaixo
// ============================================================

// --- SUAS CREDENCIAIS ---
define('MP_ACCESS_TOKEN', 'SEU_ACCESS_TOKEN_AQUI');   // Começa com APP_USR- ou TEST-
define('MP_PUBLIC_KEY',   'SUA_PUBLIC_KEY_AQUI');     // Começa com APP_USR- ou TEST-

// --- AMBIENTE ---
// 'sandbox'    → testes (use credenciais TEST-)
// 'production' → produção (use credenciais APP_USR-)
define('MP_AMBIENTE', 'sandbox');

// --- URL BASE DO SEU SITE ---
// Ex: 'https://farmavida.com.br' ou 'http://localhost/farmavida'
define('MP_BASE_URL', 'http://localhost/farmavida');

// --- URLS DE RETORNO ---
define('MP_URL_SUCESSO',   MP_BASE_URL . '/pagamento_retorno.php?status=sucesso');
define('MP_URL_FALHA',     MP_BASE_URL . '/pagamento_retorno.php?status=falha');
define('MP_URL_PENDENTE',  MP_BASE_URL . '/pagamento_retorno.php?status=pendente');
define('MP_URL_WEBHOOK',   MP_BASE_URL . '/pagamento_webhook.php');

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
            'X-Idempotency-Key: farmavida-' . uniqid(),
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

    $data = json_decode($response, true);
    $data['_http_code'] = $httpCode;
    return $data;
}
?>
