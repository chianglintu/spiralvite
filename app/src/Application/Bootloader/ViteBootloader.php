<?php

declare(strict_types=1);

namespace App\Application\Bootloader;

use App\Infrastructure\Vite\Vite;
use App\Infrastructure\Vite\Twig\ViteExtension;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Config\ConfigsInterface;
use Spiral\Twig\Bootloader\TwigBootloader;

final class ViteBootloader extends Bootloader
{
    protected const SINGLETONS = [
        Vite::class => [self::class, 'vite'],
    ];

    public function __construct(private readonly ConfiguratorInterface $config) {}

    public function init(TwigBootloader $twig): void
    {
        $this->config->setDefaults('vite', require directory('config') . 'vite.php');
        $twig->addExtension(ViteExtension::class);
    }

    private function vite(ConfigsInterface $configs): Vite
    {
        return new Vite($configs->get('vite'));
    }
}
