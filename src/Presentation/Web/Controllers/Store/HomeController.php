<?php

namespace FarmaVida\Presentation\Web\Controllers\Store;

use FarmaVida\Application\Store\CatalogService;
use FarmaVida\Core\Http\Request;
use FarmaVida\Core\Http\Response;
use FarmaVida\Core\View\ViewRenderer;

final class HomeController
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly ViewRenderer $view
    ) {
    }

    public function handle(Request $request): Response
    {
        $html = $this->view->page('store/home', $this->catalog->homeViewModel($request));
        return Response::html($html);
    }
}
