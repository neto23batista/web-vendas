<?php

namespace FarmaVida\Presentation\Web\Controllers\Auth;

use FarmaVida\Application\Auth\AuthService;
use FarmaVida\Core\Http\Request;
use FarmaVida\Core\Http\Response;
use FarmaVida\Core\Security\CsrfManager;
use FarmaVida\Core\Security\FlashMessages;
use FarmaVida\Core\View\ViewRenderer;

final class LoginController
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
            return Response::redirect($this->auth->redirectPathForCurrentUser());
        }

        $messages = $this->flash->consume();
        $status = $this->auth->loginStatus();
        $error = $status['message'];

        if ($request->isPost() && !$status['blocked']) {
            if (!$this->csrf->validate((string)$request->post('csrf_token', ''))) {
                $error = 'Requisição inválida.';
            } else {
                $result = $this->auth->attemptLogin(
                    (string)$request->post('email', ''),
                    (string)$request->post('senha', '')
                );
                if ($result['success'] ?? false) {
                    return Response::redirect((string)$result['redirect']);
                }
                $error = (string)($result['error'] ?? 'Não foi possível entrar.');
            }
        }

        $html = $this->view->page('auth/login', [
            'pageTitle' => 'Entrar - FarmaVida',
            'bodyClass' => 'auth-page',
            'messages' => $messages,
            'error' => $error,
            'blocked' => $status['blocked'],
            'csrfToken' => $this->csrf->token(),
            'old' => $request->allPost(),
        ]);

        return Response::html($html);
    }
}
