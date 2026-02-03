<?php

declare(strict_types=1);

namespace App\Infrastructure\Vite;

final class Vite
{
    private array $config;
    private array $manifestCache = [];
    private array $manifestMtime = [];

    public function __construct(array $config)
    {
        $this->config = $config + [
            'public_dir' => '',
            'build_dir' => 'build',
            'manifest' => '.vite/manifest.json',
            'hot_file' => 'hot',
            'asset_url' => '',
        ];
    }

    public function tags(array $entries, ?string $buildDir = null, bool $inline = false): string
    {
        $buildDir = $buildDir ?? $this->config['build_dir'];
        if ($this->isDev()) {
            return $this->devTags($entries, $buildDir);
        }

        $manifest = $this->manifest($buildDir);
        [$scripts, $styles, $preloads] = $this->resolveProdAssets($manifest, $entries, $buildDir, $inline);

        return $this->renderTags($scripts, $styles, $preloads);
    }

    public function asset(string $entry, ?string $buildDir = null): string
    {
        $buildDir = $buildDir ?? $this->config['build_dir'];
        $manifest = $this->manifest($buildDir);
        if (!isset($manifest[$entry]['file'])) {
            throw new \RuntimeException("Vite entry not found: {$entry}");
        }
        return $this->assetUrl($buildDir, $manifest[$entry]['file']);
    }

    private function isDev(): bool
    {
        return \is_file($this->hotPath());
    }

    private function hotPath(): string
    {
        return $this->config['public_dir'] . '/' . $this->config['hot_file'];
    }

    private function manifestPath(string $buildDir): string
    {
        return $this->config['public_dir'] . '/' . $buildDir . '/' . $this->config['manifest'];
    }

    private function manifest(string $buildDir): array
    {
        $path = $this->manifestPath($buildDir);
        if (!\is_file($path)) {
            throw new \RuntimeException("Vite manifest not found: {$path}. Run npm run build.");
        }
        $mtime = \filemtime($path) ?: 0;
        if (!isset($this->manifestCache[$buildDir]) || ($this->manifestMtime[$buildDir] ?? 0) !== $mtime) {
            $this->manifestCache[$buildDir] = \json_decode((string) \file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $this->manifestMtime[$buildDir] = $mtime;
        }
        return $this->manifestCache[$buildDir];
    }

    private function resolveProdAssets(array $manifest, array $entries, string $buildDir, bool $inline): array
    {
        $scripts = [];
        $styles = [];
        $preloads = [];

        foreach ($entries as $entry) {
            if (!isset($manifest[$entry])) {
                throw new \RuntimeException("Vite entry not found: {$entry}");
            }
            $chunk = $manifest[$entry];
            $file = $chunk['file'] ?? null;
            if ($file === null) {
                continue;
            }
            if (\str_ends_with($file, '.css')) {
                $styles[] = $this->assetRef($buildDir, $file, $inline, true);
            } else {
                $scripts[] = $this->assetRef($buildDir, $file, $inline, false);
            }

            foreach ($chunk['css'] ?? [] as $cssFile) {
                $styles[] = $this->assetRef($buildDir, $cssFile, $inline, true);
            }
            foreach ($chunk['imports'] ?? [] as $importKey) {
                if (isset($manifest[$importKey]['file'])) {
                    $preloads[] = $this->assetUrl($buildDir, $manifest[$importKey]['file']);
                }
            }
        }

        $styles = array_values(array_unique($styles));
        $scripts = array_values(array_unique($scripts));
        $preloads = array_values(array_unique($preloads));

        return [$scripts, $styles, $preloads];
    }

    private function assetUrl(string $buildDir, string $file): string
    {
        $prefix = rtrim($this->config['asset_url'], '/');
        return $prefix . '/' . trim($buildDir . '/' . $file, '/');
    }

    private function assetRef(string $buildDir, string $file, bool $inline, bool $isCss): string
    {
        if (!$inline) {
            return $this->assetUrl($buildDir, $file);
        }
        $path = $this->config['public_dir'] . '/' . $buildDir . '/' . $file;
        if (!\is_file($path)) {
            throw new \RuntimeException("Vite asset not found: {$path}");
        }
        return (string) \file_get_contents($path);
    }

    private function renderTags(array $scripts, array $styles, array $preloads): string
    {
        $tags = [];
        foreach ($preloads as $href) {
            $tags[] = '<link rel="modulepreload" href="' . $href . '">';
        }
        foreach ($styles as $style) {
            if (\str_starts_with($style, '/*') || \str_contains($style, '{')) {
                $tags[] = '<style>' . $style . '</style>';
            } else {
                $tags[] = '<link rel="stylesheet" href="' . $style . '">';
            }
        }
        foreach ($scripts as $script) {
            if (\str_contains($script, 'console') || \str_contains($script, 'import')) {
                $tags[] = '<script type="module">' . $script . '</script>';
            } else {
                $tags[] = '<script type="module" src="' . $script . '"></script>';
            }
        }
        return implode("\n", $tags);
    }

    private function devTags(array $entries, string $buildDir): string
    {
        $hot = trim((string) \file_get_contents($this->hotPath()));
        $tags = ['<script type="module" src="' . $hot . '/@vite/client"></script>'];
        foreach ($entries as $entry) {
            $url = rtrim($hot, '/') . '/' . ltrim($entry, '/');
            if (\str_ends_with($entry, '.css')) {
                $tags[] = '<link rel="stylesheet" href="' . $url . '">';
            } else {
                $tags[] = '<script type="module" src="' . $url . '"></script>';
            }
        }
        return implode("\n", $tags);
    }
}
