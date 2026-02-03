<?php

declare(strict_types=1);

namespace App\Infrastructure\Vite\Twig;

use App\Infrastructure\Vite\Vite;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ViteExtension extends AbstractExtension
{
    public function __construct(private readonly Vite $vite) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('vite', fn(array $entries, ?string $buildDir = null, bool $inline = false) => $this->vite->tags($entries, $buildDir, $inline), ['is_safe' => ['html']]),
            new TwigFunction('vite_asset', fn(string $entry, ?string $buildDir = null) => $this->vite->asset($entry, $buildDir)),
        ];
    }
}
