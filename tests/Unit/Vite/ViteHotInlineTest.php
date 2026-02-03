<?php

declare(strict_types=1);

namespace Tests\Unit\Vite;

use App\Infrastructure\Vite\Vite;
use PHPUnit\Framework\TestCase;

final class ViteHotInlineTest extends TestCase
{
    public function test_renders_dev_tags_from_hot_file(): void
    {
        file_put_contents(__DIR__ . '/../../Fixtures/vite/hot', "http://localhost:5173\n");

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
        ]);

        $this->assertStringContainsString('<script type="module" src="http://localhost:5173/@vite/client"></script>', $html);
        $this->assertStringContainsString('<script type="module" src="http://localhost:5173/resources/js/app.js"></script>', $html);
        $this->assertStringContainsString('<link rel="stylesheet" href="http://localhost:5173/resources/css/tailwind.css">', $html);
    }

    public function test_inline_mode_embeds_assets(): void
    {
        @unlink(__DIR__ . '/../../Fixtures/vite/hot');

        $vite = new Vite([
            'public_dir' => __DIR__ . '/../../Fixtures/vite',
            'build_dir' => 'build',
            'manifest' => '.vite/manifest.json',
            'hot_file' => 'hot',
            'asset_url' => '',
        ]);

        $html = $vite->tags(['resources/js/app.js'], null, true);

        $this->assertStringContainsString('<style>.app { color: red; }</style>', $html);
        $this->assertStringContainsString("<script type=\"module\">console.log('app');</script>", $html);
    }
}
