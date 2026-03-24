<?php

namespace FarmaVida\Core\Security;

final class CsrfManager
{
    public function __construct(private readonly SessionManager $session)
    {
    }

    public function token(): string
    {
        $token = (string)$this->session->get('csrf_token', '');
        if ($token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->put('csrf_token', $token);
        }

        return $token;
    }

    public function validate(?string $token): bool
    {
        return hash_equals($this->token(), (string)$token);
    }
}
