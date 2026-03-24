<?php

namespace FarmaVida\Presentation\Web\Controllers\Auth;

use FarmaVida\Application\Auth\AuthService;
use FarmaVida\Core\Http\Request;
use FarmaVida\Core\Http\Response;

final class LogoutController
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function handle(Request $request): Response
    {
        $this->auth->logout();
        return Response::redirect('index.php');
    }
}
