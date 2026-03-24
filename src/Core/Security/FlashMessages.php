<?php

namespace FarmaVida\Core\Security;

final class FlashMessages
{
    public function __construct(private readonly SessionManager $session)
    {
    }

    public function success(string $message): void
    {
        $this->session->put('sucesso', $message);
    }

    public function error(string $message): void
    {
        $this->session->put('erro', $message);
    }

    public function consume(): array
    {
        return [
            'success' => $this->session->pull('sucesso'),
            'error' => $this->session->pull('erro'),
        ];
    }
}
