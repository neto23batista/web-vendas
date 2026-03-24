<?php

namespace FarmaVida\Infrastructure\Services;

final class MailerGateway
{
    public function __construct()
    {
        require_once FARMAVIDA_ROOT . '/app/integrations/mailer.php';
    }

    public function sendWelcome(string $email, string $name): void
    {
        if (!function_exists('email_boas_vindas') || !function_exists('enviar_email')) {
            return;
        }

        @enviar_email($email, 'Bem-vindo à FarmaVida!', email_boas_vindas($name));
    }
}
