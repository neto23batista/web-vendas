<?php
// ============================================================
// MAILER â€“ FarmaVida
// ============================================================
// ConfiguraÃ§Ã£o via variÃ¡veis de ambiente ou constantes abaixo.
// Para usar SMTP (recomendado em produÃ§Ã£o):
//   MAIL_SMTP_HOST   = smtp.gmail.com
//   MAIL_SMTP_PORT   = 587
//   MAIL_SMTP_USER   = seu@email.com
//   MAIL_SMTP_PASS   = sua_senha_de_app
//   MAIL_FROM        = no-reply@farmavida.com.br
//   MAIL_FROM_NAME   = FarmaVida
// ============================================================

define('MAIL_SMTP_HOST',  getenv('MAIL_SMTP_HOST')  ?: '');
define('MAIL_SMTP_PORT',  (int)(getenv('MAIL_SMTP_PORT') ?: 587));
define('MAIL_SMTP_USER',  getenv('MAIL_SMTP_USER')  ?: '');
define('MAIL_SMTP_PASS',  getenv('MAIL_SMTP_PASS')  ?: '');
define('MAIL_FROM',       getenv('MAIL_FROM')       ?: 'no-reply@farmavida.com.br');
define('MAIL_FROM_NAME',  getenv('MAIL_FROM_NAME')  ?: 'FarmaVida');

/**
 * Envia um e-mail em HTML.
 *
 * @param  string $para     EndereÃ§o de destino
 * @param  string $assunto  Assunto
 * @param  string $corpo    Corpo HTML
 * @return bool
 */
function enviar_email(string $para, string $assunto, string $corpo): bool {
    if (!filter_var($para, FILTER_VALIDATE_EMAIL)) return false;

    // â”€â”€ Tenta PHPMailer se disponÃ­vel (composer require phpmailer/phpmailer) â”€â”€
    if (file_exists(FARMAVIDA_ROOT . '/vendor/autoload.php')) {
        require_once FARMAVIDA_ROOT . '/vendor/autoload.php';
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = MAIL_SMTP_HOST ?: 'localhost';
            $mail->SMTPAuth   = !empty(MAIL_SMTP_USER);
            $mail->Username   = MAIL_SMTP_USER;
            $mail->Password   = MAIL_SMTP_PASS;
            $mail->SMTPSecure = MAIL_SMTP_PORT == 465
                ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMIME
                : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = MAIL_SMTP_PORT;
            $mail->CharSet    = 'UTF-8';
            $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $mail->addAddress($para);
            $mail->isHTML(true);
            $mail->Subject = $assunto;
            $mail->Body    = $corpo;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $corpo));
            return $mail->send();
        } catch (\Exception $e) {
            error_log("PHPMailer error: " . $e->getMessage());
            return false;
        }
    }

    // â”€â”€ Fallback: mail() nativo â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Funciona em produÃ§Ã£o com sendmail configurado.
    // No XAMPP/Windows configure php.ini: SMTP=, smtp_port=, sendmail_from=
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
    $headers .= "Reply-To: " . MAIL_FROM . "\r\n";
    $headers .= "X-Mailer: FarmaVida/1.0\r\n";

    return @mail($para, '=?UTF-8?B?' . base64_encode($assunto) . '?=', $corpo, $headers);
}

// â”€â”€ TEMPLATES â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

function email_layout(string $titulo, string $conteudo): string {
    return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">
    <style>
        body{font-family:Arial,sans-serif;background:#f0f7ff;margin:0;padding:24px;}
        .box{max-width:560px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.1);}
        .hdr{background:linear-gradient(135deg,#00875a,#0052cc);padding:28px 32px;text-align:center;}
        .hdr h1{color:#fff;margin:0;font-size:22px;}
        .hdr small{color:rgba(255,255,255,.8);font-size:13px;}
        .body{padding:28px 32px;color:#0d1b2a;font-size:15px;line-height:1.7;}
        .btn{display:inline-block;padding:13px 28px;background:linear-gradient(135deg,#00875a,#0052cc);color:#fff;text-decoration:none;border-radius:999px;font-weight:700;margin:16px 0;}
        .ftr{background:#f0f7ff;padding:18px 32px;font-size:12px;color:#5e7491;text-align:center;}
        .tag{display:inline-block;padding:4px 12px;border-radius:999px;font-size:12px;font-weight:700;}
        table.itens{width:100%;border-collapse:collapse;margin:16px 0;}
        table.itens th{background:#f0f7ff;padding:8px 10px;text-align:left;font-size:12px;color:#5e7491;}
        table.itens td{padding:8px 10px;border-bottom:1px solid #dce8f2;font-size:13px;}
    </style></head><body>
    <div class="box">
        <div class="hdr"><h1>ðŸ’Š FarmaVida</h1><small>FarmÃ¡cia e Drogaria</small></div>
        <div class="body">' . $conteudo . '</div>
        <div class="ftr">FarmaVida Â· Av. da SaÃºde, 456 â€“ Centro Â· (17) 99999-1234<br>
        Este Ã© um e-mail automÃ¡tico, nÃ£o responda diretamente.</div>
    </div></body></html>';
}

function email_boas_vindas(string $nome): string {
    $conteudo = "<h2 style='color:#00875a;margin-top:0;'>Bem-vindo(a), " . htmlspecialchars($nome) . "! ðŸŽ‰</h2>
    <p>Sua conta na <strong>FarmaVida</strong> foi criada com sucesso.</p>
    <p>Agora vocÃª pode:</p>
    <ul>
        <li>Navegar pelo nosso catÃ¡logo completo</li>
        <li>Fazer pedidos com entrega ou retirada</li>
        <li>Acompanhar o status dos seus pedidos em tempo real</li>
    </ul>
    <p>Cuide bem da sua saÃºde â€” estamos aqui para ajudar! ðŸ’š</p>";
    return email_layout('Bem-vindo Ã  FarmaVida!', $conteudo);
}

function email_confirmacao_pedido(int $id, string $nome, array $itens, float $total, string $tipo_retirada): string {
    $itens_html = '';
    foreach ($itens as $it) {
        $sub = number_format($it['preco'] * $it['quantidade'], 2, ',', '.');
        $itens_html .= "<tr><td>{$it['quantidade']}x {$it['nome']}</td><td style='text-align:right'>R$ {$sub}</td></tr>";
    }
    $total_fmt   = number_format($total, 2, ',', '.');
    $tipo_label  = $tipo_retirada === 'delivery' ? 'ðŸï¸ Delivery' : 'ðŸª Retirada no local';

    $conteudo = "<h2 style='color:#00875a;margin-top:0;'>Pedido #$id confirmado! âœ…</h2>
    <p>OlÃ¡, <strong>" . htmlspecialchars($nome) . "</strong>! Recebemos seu pedido.</p>
    <table class='itens'>
        <tr><th>Produto</th><th style='text-align:right'>Subtotal</th></tr>
        $itens_html
        <tr><td><strong>Total</strong></td><td style='text-align:right;font-weight:700;color:#00875a;'>R$ $total_fmt</td></tr>
    </table>
    <p><strong>Entrega:</strong> $tipo_label</p>
    <p>Acompanhe o status do seu pedido no painel da sua conta.</p>";
    return email_layout("Pedido #$id confirmado â€“ FarmaVida", $conteudo);
}

function email_status_pedido(int $id, string $nome, string $status): string {
    $labels = [
        'preparando' => ['ðŸ”µ Separando seu pedido',   '#3b82f6', 'Estamos separando os produtos do seu pedido!'],
        'pronto'     => ['ðŸŸ¢ Pronto para retirada',    '#10b981', 'Seu pedido estÃ¡ pronto! Pode vir buscar ou aguarde o entregador.'],
        'entregue'   => ['âœ… Pedido entregue',          '#00875a', 'Seu pedido foi entregue. Obrigado pela preferÃªncia!'],
        'cancelado'  => ['âŒ Pedido cancelado',         '#ef4444', 'Infelizmente seu pedido foi cancelado. Entre em contato conosco se precisar de ajuda.'],
    ];
    [$titulo_st, $cor, $msg] = $labels[$status] ?? ['ðŸ“¦ Status atualizado', '#5e7491', 'O status do seu pedido foi atualizado.'];

    $conteudo = "<h2 style='color:{$cor};margin-top:0;'>{$titulo_st}</h2>
    <p>OlÃ¡, <strong>" . htmlspecialchars($nome) . "</strong>!</p>
    <p>{$msg}</p>
    <p style='font-size:13px;color:#5e7491;'>Pedido: <strong>#$id</strong></p>";
    return email_layout("Pedido #$id â€“ $titulo_st", $conteudo);
}

function email_recuperacao_senha(string $nome, string $link): string {
    $conteudo = "<h2 style='color:#00875a;margin-top:0;'>RedefiniÃ§Ã£o de senha ðŸ”</h2>
    <p>OlÃ¡, <strong>" . htmlspecialchars($nome) . "</strong>!</p>
    <p>Recebemos uma solicitaÃ§Ã£o para redefinir a senha da sua conta FarmaVida.</p>
    <p>Clique no botÃ£o abaixo para criar uma nova senha. O link Ã© vÃ¡lido por <strong>1 hora</strong>.</p>
    <p><a href='" . htmlspecialchars($link) . "' class='btn'>Redefinir minha senha</a></p>
    <p style='font-size:12px;color:#5e7491;'>Se vocÃª nÃ£o solicitou a redefiniÃ§Ã£o, ignore este e-mail. Sua senha permanece a mesma.</p>
    <p style='font-size:12px;color:#5e7491;'>Link alternativo:<br><a href='" . htmlspecialchars($link) . "' style='color:#0052cc;word-break:break-all;'>" . htmlspecialchars($link) . "</a></p>";
    return email_layout('RedefiniÃ§Ã£o de senha â€“ FarmaVida', $conteudo);
}
