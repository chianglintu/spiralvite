<?php

declare(strict_types=1);

namespace Tests\Unit\Vite;

use App\Infrastructure\Vite\Vite;
use PHPUnit\Framework\TestCase;

final class ViteManifestTest extends TestCase
{
    public function test_renders_prod_tags_from_manifest(): void
    {
        $vite = new Vite([
            'public_dir' => __DIR__ . '/../../Fixtures/vite',
            'build_dir' => 'build',
            'manifest' => '.vite/manifest.json',
            'hot_file' => 'hot',
            'asset_url' => '',
        ]);

        $html = $vite->tags([
            'resources/js/app.js',
            'resources/css/tailwind.css',
            'resources/css/fonts.css',
        ]);

        $this->assertStringContainsString('<script type="module" src="/build/assets/app-abc123.js"></script>', $html);
        $this->assertStringContainsString('<link rel="stylesheet" href="/build/assets/app-abc123.css">', $html);
        $this->assertStringContainsString('<link rel="modulepreload" href="/build/assets/vendor-xyz.js">', $html);
        $this->assertStringContainsString('<link rel="stylesheet" href="/build/assets/tailwind-111.css">', $html);
        $this->assertStringContainsString('<link rel="stylesheet" href="/build/assets/fonts-222.css">', $html);
    }
}
