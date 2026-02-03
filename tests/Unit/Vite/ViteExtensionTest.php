<?php

declare(strict_types=1);

namespace Tests\Unit\Vite;

use App\Infrastructure\Vite\Twig\ViteExtension;
use App\Infrastructure\Vite\Vite;
use PHPUnit\Framework\TestCase;
use Twig\TwigFunction;

final class ViteExtensionTest extends TestCase
{
    public function test_vite_functions_delegate_to_service(): void
    {
        $vite = new Vite([
            'public_dir' => __DIR__ . '/../../Fixtures/vite',
            'build_dir' => 'build',
            'manifest' => '.vite/manifest.json',
            'hot_file' => 'hot',
            'asset_url' => '',
        ]);

        $ext = new ViteExtension($vite);
        $funcs = $ext->getFunctions();

        /** @var TwigFunction $vite */
        $viteFunction = $funcs[0];
        /** @var TwigFunction $asset */
        $asset = $funcs[1];

        $this->assertSame($vite->tags(['resources/js/app.js']), ($viteFunction->getCallable())(['resources/js/app.js']));
        $this->assertSame($vite->asset('resources/js/app.js'), ($asset->getCallable())('resources/js/app.js'));
    }
}
