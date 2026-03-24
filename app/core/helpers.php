<?php
require_once __DIR__ . '/bootstrap.php';
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
    iniciar_sessao_segura();
    if ($mensagem) {
        $_SESSION[$tipo] = $mensagem;
    }
    header("Location: $url");
    exit;
}

function verificar_login($tipo_requerido = null) {
    iniciar_sessao_segura();
    if (!isset($_SESSION['usuario'])) {
        redirecionar('login.php', 'Você precisa fazer login!', 'erro');
    }
    if ($tipo_requerido && $_SESSION['tipo'] != $tipo_requerido) {
        redirecionar('index.php', 'Acesso negado!', 'erro');
    }
}

 
function gerar_token_csrf(): string {
    iniciar_sessao_segura();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}





function verificar_csrf(): void {
    iniciar_sessao_segura();
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Requisição inválida (token CSRF ausente ou incorreto).');
    }
}





function campo_csrf(): string {
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(gerar_token_csrf(), ENT_QUOTES, 'UTF-8') . '">';
}
 

function upload_imagem($arquivo, $pasta = 'uploads/') {
    $pastaRelativa = trim(str_replace('\\', '/', $pasta), '/');
    $pastaRelativa = $pastaRelativa === '' ? 'uploads' : $pastaRelativa;
    $diretorioDestino = FARMAVIDA_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pastaRelativa);

    if (!is_dir($diretorioDestino)) {
        mkdir($diretorioDestino, 0755, true);
    }

    $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $mime_permitidos      = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));

     
    $finfo     = new finfo(FILEINFO_MIME_TYPE);
    $mime_real = $finfo->file($arquivo['tmp_name']);

    if (!in_array($extensao, $extensoes_permitidas, true) || !in_array($mime_real, $mime_permitidos, true)) {
        return ['sucesso' => false, 'mensagem' => 'Formato de imagem inválido'];
    }
    if ($arquivo['size'] > 5242880) {
        return ['sucesso' => false, 'mensagem' => 'Imagem muito grande (máx 5MB)'];
    }

    $nome = bin2hex(random_bytes(16)) . '.' . $extensao;
    $caminhoRelativo = $pastaRelativa . '/' . $nome;
    $caminhoAbsoluto = $diretorioDestino . DIRECTORY_SEPARATOR . $nome;

    if (move_uploaded_file($arquivo['tmp_name'], $caminhoAbsoluto)) {
        return ['sucesso' => true, 'caminho' => $caminhoRelativo];
    }
    return ['sucesso' => false, 'mensagem' => 'Erro ao fazer upload'];
}

function caminho_upload_local(?string $caminhoRelativo): ?string {
    if (!$caminhoRelativo) {
        return null;
    }

    $baseUploads = realpath(FARMAVIDA_ROOT . '/uploads');
    if ($baseUploads === false) {
        return null;
    }

    $caminho = realpath(FARMAVIDA_ROOT . DIRECTORY_SEPARATOR . ltrim($caminhoRelativo, '/\\'));
    if ($caminho === false) {
        return null;
    }

    $prefixo = rtrim($baseUploads, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return strncmp($caminho, $prefixo, strlen($prefixo)) === 0 ? $caminho : null;
}

function imagem_upload_disponivel(?string $caminhoRelativo): bool {
    $caminhoLocal = caminho_upload_local($caminhoRelativo);
    return $caminhoLocal !== null && is_file($caminhoLocal);
}

function texto_len(string $texto): int {
    return function_exists('mb_strlen') ? mb_strlen($texto, 'UTF-8') : strlen(utf8_decode($texto));
}

function texto_limit(string $texto, int $largura): string {
    return function_exists('mb_strimwidth')
        ? mb_strimwidth($texto, 0, $largura, '', 'UTF-8')
        : substr($texto, 0, $largura);
}

function texto_lower(string $texto): string {
    return function_exists('mb_strtolower') ? mb_strtolower($texto, 'UTF-8') : strtolower($texto);
}

function texto_upper(string $texto): string {
    return function_exists('mb_strtoupper') ? mb_strtoupper($texto, 'UTF-8') : strtoupper($texto);
}

function linhas_placeholder_produto(string $nome): array {
    $nome = trim(preg_replace('/\s+/', ' ', $nome));
    if ($nome === '') {
        return ['Produto', 'Farmacia'];
    }

    $palavras = preg_split('/\s+/', $nome) ?: [];
    $linhas = [];
    $atual = '';

    foreach ($palavras as $palavra) {
        $teste = trim($atual . ' ' . $palavra);
        if ($atual !== '' && texto_len($teste) > 18) {
            $linhas[] = $atual;
            $atual = $palavra;
            if (count($linhas) === 2) {
                break;
            }
            continue;
        }
        $atual = $teste;
    }

    if ($atual !== '' && count($linhas) < 2) {
        $linhas[] = $atual;
    }

    if (count($linhas) === 1) {
        $linhas[] = '';
    }

    return array_map(
        static fn(string $linha): string => texto_limit($linha, 20),
        array_slice($linhas, 0, 2)
    );
}

function gerar_placeholder_produto_svg(?string $nome, ?string $categoria): string {
    $nome = trim((string)$nome);
    $categoria = trim((string)$categoria);

    if ($nome === '') {
        $nome = 'Produto';
    }
    if ($categoria === '') {
        $categoria = 'Farmacia';
    }

    [$linha1, $linha2] = linhas_placeholder_produto($nome);
    $categoriaCurta = texto_limit($categoria, 24);

    $seed = sprintf('%u', crc32(texto_lower($categoria . '|' . $nome)));
    $h1 = (int)$seed % 360;
    $h2 = ($h1 + 28) % 360;
    $accent = ($h1 + 180) % 360;

    $esc = static fn(string $texto): string => htmlspecialchars($texto, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $cor1 = "hsl($h1, 72%, 54%)";
    $cor2 = "hsl($h2, 82%, 46%)";
    $corAccent = "hsl($accent, 90%, 76%)";

    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 600" role="img" aria-label="{$esc($nome)}">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="{$cor1}"/>
      <stop offset="100%" stop-color="{$cor2}"/>
    </linearGradient>
  </defs>
  <rect width="800" height="600" rx="48" fill="url(#bg)"/>
  <circle cx="678" cy="112" r="108" fill="rgba(255,255,255,.10)"/>
  <circle cx="734" cy="524" r="158" fill="rgba(255,255,255,.08)"/>
  <rect x="58" y="58" width="684" height="484" rx="34" fill="rgba(9,14,24,.12)"/>
  <text x="86" y="116" fill="rgba(255,255,255,.88)" font-family="Segoe UI, Arial, sans-serif" font-size="24" font-weight="700" letter-spacing="1.6">{$esc(texto_upper($categoriaCurta))}</text>
  <text x="86" y="238" fill="#ffffff" font-family="Segoe UI, Arial, sans-serif" font-size="52" font-weight="800">{$esc($linha1)}</text>
  <text x="86" y="300" fill="#ffffff" font-family="Segoe UI, Arial, sans-serif" font-size="52" font-weight="800">{$esc($linha2)}</text>
  <text x="86" y="496" fill="rgba(255,255,255,.82)" font-family="Segoe UI, Arial, sans-serif" font-size="22" font-weight="600">FarmaVida</text>
  <g transform="translate(534 144)">
    <rect x="0" y="0" width="176" height="176" rx="42" fill="rgba(255,255,255,.14)"/>
    <rect x="26" y="26" width="124" height="124" rx="62" fill="#ffffff" opacity=".94"/>
    <rect x="80" y="26" width="16" height="124" rx="8" fill="{$corAccent}" opacity=".95"/>
    <rect x="26" y="80" width="124" height="16" rx="8" fill="{$corAccent}" opacity=".95"/>
  </g>
</svg>
SVG;

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

function url_imagem_produto(?string $imagem, ?string $nome = null, ?string $categoria = null): string {
    if ($imagem && imagem_upload_disponivel($imagem)) {
        return $imagem;
    }

    return gerar_placeholder_produto_svg($nome, $categoria);
}
