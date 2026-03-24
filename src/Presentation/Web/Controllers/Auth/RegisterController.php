<?php

namespace FarmaVida\Presentation\Web\Controllers\Auth;

use FarmaVida\Application\Auth\AuthService;
use FarmaVida\Core\Http\Request;
use FarmaVida\Core\Http\Response;
use FarmaVida\Core\Security\CsrfManager;
use FarmaVida\Core\Security\FlashMessages;
use FarmaVida\Core\View\ViewRenderer;

final class RegisterController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly ViewRenderer $view,
        private readonly CsrfManager $csrf,
        private readonly FlashMessages $flash
    ) {
    }

    public function handle(Request $request): Response
    {
        if ($this->auth->isAuthenticated()) {
            return Response::redirect('index.php');
        }

        $messages = $this->flash->consume();
        $error = '';

        if ($request->isPost()) {
            if (!$this->csrf->validate((string)$request->post('csrf_token', ''))) {
                $error = 'Requisição inválida.';
            } else {
                $result = $this->auth->register($request->allPost());
                if ($result['success'] ?? false) {
                    return Response::redirect((string)$result['redirect']);
                }
                $error = (string)($result['error'] ?? 'Não foi possível criar a conta.');
            }
        }

        $html = $this->view->page('auth/register', [
            'pageTitle' => 'Criar Conta - FarmaVida',
            'bodyClass' => 'auth-page',
            'messages' => $messages,
            'error' => $error,
            'csrfToken' => $this->csrf->token(),
            'old' => $request->allPost(),
        ]);

        return Response::html($html);
    }
}
