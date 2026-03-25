<?php

$mpAccessToken = trim((string)(getenv('MP_ACCESS_TOKEN') ?: ''));
$mpPublicKey = trim((string)(getenv('MP_PUBLIC_KEY') ?: ''));
$mpAmbiente = strtolower(trim((string)(getenv('MP_AMBIENTE') ?: 'sandbox')));

if (!in_array($mpAmbiente, ['sandbox', 'production'], true)) {
    $mpAmbiente = 'sandbox';
}

define('MP_ACCESS_TOKEN', $mpAccessToken);
define('MP_PUBLIC_KEY', $mpPublicKey);
define('MP_AMBIENTE', $mpAmbiente);

if (MP_AMBIENTE === 'production' && MP_ACCESS_TOKEN === '') {
    throw new RuntimeException('MP_ACCESS_TOKEN não configurado para produção.');
}

if (getenv('MP_BASE_URL')) {
    define('MP_BASE_URL', rtrim((string)getenv('MP_BASE_URL'), '/'));
} else {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basepath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    define('MP_BASE_URL', "{$scheme}://{$host}{$basepath}");
}

define('MP_URL_SUCESSO', MP_BASE_URL . '/pagamento_retorno.php');
define('MP_URL_FALHA', MP_BASE_URL . '/pagamento_retorno.php');
define('MP_URL_PENDENTE', MP_BASE_URL . '/pagamento_retorno.php');
define('MP_URL_WEBHOOK', MP_BASE_URL . '/pagamento_webhook.php');

define('MP_API_URL', 'https://api.mercadopago.com');

function mp_configuracao_valida(): bool
{
    return MP_ACCESS_TOKEN !== '';
}

function mp_request(string $method, string $endpoint, array $body = []): array
{
    if (!mp_configuracao_valida()) {
        return [
            'erro' => true,
            'mensagem' => 'Mercado Pago não configurado. Defina MP_ACCESS_TOKEN.',
            'code' => 0,
            'configuracao' => true,
        ];
    }

    $url = MP_API_URL . $endpoint;

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
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
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['erro' => true, 'mensagem' => 'Erro cURL: ' . $error, 'code' => 0];
    }

    $data = json_decode($response, true) ?? [];
    $data['_http_code'] = $httpCode;
    return $data;
}
