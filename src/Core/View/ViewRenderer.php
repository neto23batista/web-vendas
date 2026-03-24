<?php

namespace FarmaVida\Core\View;

use FarmaVida\Core\Config\AppConfig;
use RuntimeException;

final class ViewRenderer
{
    public function __construct(
        private readonly string $basePath,
        private readonly AppConfig $config
    ) {
    }

    public function page(string $template, array $data = [], string $layout = 'layouts/app'): string
    {
        $content = $this->render($template, $data);
        return $this->render($layout, array_merge($data, ['content' => $content, 'config' => $this->config]));
    }

    public function render(string $template, array $data = []): string
    {
        $file = rtrim($this->basePath, '/\\') . '/' . str_replace('\\', '/', $template) . '.php';
        if (!is_file($file)) {
            throw new RuntimeException('View não encontrada: ' . $template);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string)ob_get_clean();
    }
}
