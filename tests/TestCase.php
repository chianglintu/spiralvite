<?php

declare(strict_types=1);

namespace Tests;

use Spiral\Config\ConfiguratorInterface;
use Spiral\Config\Patch\Set;
use Spiral\Core\Container;
use Spiral\Testing\TestableKernelInterface;
use Spiral\Testing\TestCase as BaseTestCase;
use Spiral\Translator\TranslatorInterface;
use Tests\App\TestKernel;

class TestCase extends BaseTestCase
{
    public function createAppInstance(Container $container = new Container()): TestableKernelInterface
    {
        return TestKernel::create(
            directories: $this->defineDirectories(
                $this->rootDirectory(),
            ),
            container: $container,
        );
    }

    public function rootDirectory(): string
    {
        return __DIR__ . '/..';
    }

    public function defineDirectories(string $root): array
    {
        return [
            'root' => $root,
        ];
    }

    protected function setUp(): void
    {
        $this->beforeBooting(static function (ConfiguratorInterface $config): void {
            if ($config->exists('session')) {
                $config->modify('session', new Set('handler', null));
            }

            $config->modify('vite', new Set('public_dir', __DIR__ . '/Fixtures/vite'));
            $config->modify('vite', new Set('build_dir', 'build'));
            $config->modify('vite', new Set('manifest', '.vite/manifest.json'));
            $config->modify('vite', new Set('hot_file', 'hot'));
            $config->modify('vite', new Set('asset_url', ''));
        });

        parent::setUp();

        $container = $this->getContainer();

        if ($container->has(TranslatorInterface::class)) {
            $container->get(TranslatorInterface::class)->setLocale('en');
        }
    }

    protected function tearDown(): void
    {
        // Uncomment this line if you want to clean up runtime directory.
        // $this->cleanUpRuntimeDirectory();
    }
}
