<?php

namespace FarmaVida\Application\Store;

use FarmaVida\Core\Http\Request;
use FarmaVida\Core\Security\CsrfManager;
use FarmaVida\Core\Security\FlashMessages;
use FarmaVida\Core\Security\SessionManager;
use FarmaVida\Infrastructure\Repository\ProductCatalogRepository;
use FarmaVida\Infrastructure\Services\ProductImageResolver;

final class CatalogService
{
    public function __construct(
        private readonly ProductCatalogRepository $catalog,
        private readonly ProductImageResolver $imageResolver,
        private readonly SessionManager $session,
        private readonly FlashMessages $flash,
        private readonly CsrfManager $csrf
    ) {
    }

    public function homeViewModel(Request $request): array
    {
        $userId = (int)$this->session->get('id_usuario', 0);
        $category = trim((string)$request->query('categoria', ''));
        $search = trim((string)$request->query('busca', ''));

        $products = $this->enrichProducts($this->catalog->availableProducts($category, $search));
        $featured = $this->enrichProducts($this->catalog->topProducts(6));
        $cartPreview = $userId > 0 ? $this->enrichProducts($this->catalog->cartPreview($userId)) : [];

        return [
            'pageTitle' => 'FarmaVida - Catálogo',
            'bodyClass' => 'store-home',
            'messages' => $this->flash->consume(),
            'selectedCategory' => $category,
            'searchTerm' => $search,
            'categories' => $this->categoriesWithIcons(),
            'products' => $products,
            'featuredProducts' => $featured,
            'cartCount' => $userId > 0 ? $this->catalog->cartCount($userId) : 0,
            'cartPreview' => $cartPreview,
            'isLoggedIn' => $userId > 0,
            'isClient' => $this->session->get('tipo') === 'cliente',
            'isOwner' => $this->session->get('tipo') === 'dono',
            'userName' => (string)$this->session->get('usuario', ''),
            'csrfToken' => $this->csrf->token(),
        ];
    }

    private function enrichProducts(array $products): array
    {
        $icons = $this->iconMap();
        return array_map(function (array $product) use ($icons): array {
            $product['imagem_resolvida'] = $this->imageResolver->resolve(
                $product['imagem'] ?? null,
                (string)($product['nome'] ?? 'Produto'),
                (string)($product['categoria'] ?? 'Outros')
            );
            $product['icone_categoria'] = $icons[$product['categoria'] ?? 'Outros'] ?? '📦';
            return $product;
        }, $products);
    }

    private function categoriesWithIcons(): array
    {
        $icons = $this->iconMap();
        return array_map(function (array $row) use ($icons): array {
            $category = (string)($row['categoria'] ?? '');
            return [
                'categoria' => $category,
                'icone' => $icons[$category] ?? '📦',
            ];
        }, $this->catalog->categories());
    }

    private function iconMap(): array
    {
        return [
            'Medicamentos' => '💊',
            'Genéricos' => '🔵',
            'Vitaminas' => '🌿',
            'Higiene Pessoal' => '🧴',
            'Dermocosméticos' => '✨',
            'Infantil' => '👶',
            'Bem-Estar' => '💚',
            'Primeiros Socorros' => '🩹',
            'Ortopedia' => '🦽',
            'Outros' => '📦',
        ];
    }
}
