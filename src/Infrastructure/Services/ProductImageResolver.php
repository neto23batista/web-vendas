<?php

namespace FarmaVida\Infrastructure\Services;

use FarmaVida\Core\Config\AppConfig;

final class ProductImageResolver
{
    public function __construct(private readonly AppConfig $config)
    {
    }

    public function resolve(?string $image, string $name, string $category): string
    {
        if ($image !== null && $image !== '' && $this->isUploadAvailable($image)) {
            return $image;
        }

        return $this->placeholder($name, $category);
    }

    private function isUploadAvailable(string $relativePath): bool
    {
        $baseUploads = realpath($this->config->rootPath() . '/uploads');
        if ($baseUploads === false) {
            return false;
        }

        $candidate = realpath($this->config->rootPath() . '/' . ltrim($relativePath, '/\\'));
        if ($candidate === false) {
            return false;
        }

        $prefix = rtrim($baseUploads, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return strncmp($candidate, $prefix, strlen($prefix)) === 0 && is_file($candidate);
    }

    private function placeholder(string $name, string $category): string
    {
        $name = trim($name) !== '' ? trim($name) : 'Produto';
        $category = trim($category) !== '' ? trim($category) : 'Farmácia';
        $seed = sprintf('%u', crc32(mb_strtolower($category . '|' . $name, 'UTF-8')));
        $h1 = (int)$seed % 360;
        $h2 = ($h1 + 26) % 360;
        $accent = ($h1 + 180) % 360;
        $line1 = $this->limit($name, 18);
        $categoryShort = $this->limit($category, 22);
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 600" role="img" aria-label="{$esc($name)}">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="hsl({$h1}, 72%, 54%)"/>
      <stop offset="100%" stop-color="hsl({$h2}, 82%, 46%)"/>
    </linearGradient>
  </defs>
  <rect width="800" height="600" rx="48" fill="url(#bg)"/>
  <circle cx="678" cy="112" r="108" fill="rgba(255,255,255,.10)"/>
  <circle cx="734" cy="524" r="158" fill="rgba(255,255,255,.08)"/>
  <rect x="58" y="58" width="684" height="484" rx="34" fill="rgba(9,14,24,.12)"/>
  <text x="86" y="116" fill="rgba(255,255,255,.88)" font-family="Segoe UI, Arial, sans-serif" font-size="24" font-weight="700" letter-spacing="1.6">{$esc(mb_strtoupper($categoryShort, 'UTF-8'))}</text>
  <text x="86" y="238" fill="#ffffff" font-family="Segoe UI, Arial, sans-serif" font-size="52" font-weight="800">{$esc($line1)}</text>
  <text x="86" y="496" fill="rgba(255,255,255,.82)" font-family="Segoe UI, Arial, sans-serif" font-size="22" font-weight="600">FarmaVida</text>
  <g transform="translate(534 144)">
    <rect x="0" y="0" width="176" height="176" rx="42" fill="rgba(255,255,255,.14)"/>
    <rect x="26" y="26" width="124" height="124" rx="62" fill="#ffffff" opacity=".94"/>
    <rect x="80" y="26" width="16" height="124" rx="8" fill="hsl({$accent}, 90%, 76%)" opacity=".95"/>
    <rect x="26" y="80" width="124" height="16" rx="8" fill="hsl({$accent}, 90%, 76%)" opacity=".95"/>
  </g>
</svg>
SVG;

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private function limit(string $value, int $length): string
    {
        return function_exists('mb_strimwidth')
            ? mb_strimwidth($value, 0, $length, '', 'UTF-8')
            : substr($value, 0, $length);
    }
}
