# Vite Laravel 风格集成 Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** 在 Spiral + Twig 中实现类似 Laravel 的 Vite 资产注入体验，支持 dev/hot、manifest、多入口、inline 输出。

**Architecture:** Vite 负责构建并输出 `public/build` + manifest；后端提供 `Vite` 服务解析 hot/manifest 并生成 HTML 标签；Twig 扩展提供 `vite()`/`vite_asset()` 函数统一调用。

**Tech Stack:** PHP 8.x、Spiral Framework、Twig、Vite 7、PHPUnit。

---

### Task 1: 添加测试夹具与生产态标签测试

**Files:**
- Create: `tests/Fixtures/vite/build/.vite/manifest.json`
- Create: `tests/Fixtures/vite/build/assets/app-abc123.js`
- Create: `tests/Fixtures/vite/build/assets/app-abc123.css`
- Create: `tests/Fixtures/vite/build/assets/vendor-xyz.js`
- Create: `tests/Fixtures/vite/build/assets/tailwind-111.css`
- Create: `tests/Fixtures/vite/build/assets/fonts-222.css`
- Create: `tests/Unit/Vite/ViteManifestTest.php`

**Step 1: 写入夹具（manifest + 产物文件）**

`tests/Fixtures/vite/build/.vite/manifest.json`:
```json
{
  "resources/js/app.js": {
    "file": "assets/app-abc123.js",
    "css": ["assets/app-abc123.css"],
    "imports": ["_vendor-xyz.js"]
  },
  "_vendor-xyz.js": {
    "file": "assets/vendor-xyz.js"
  },
  "resources/css/tailwind.css": {
    "file": "assets/tailwind-111.css",
    "isEntry": true
  },
  "resources/css/fonts.css": {
    "file": "assets/fonts-222.css",
    "isEntry": true
  }
}
```

`tests/Fixtures/vite/build/assets/app-abc123.js`:
```js
console.log('app');
```

`tests/Fixtures/vite/build/assets/app-abc123.css`:
```css
.app { color: red; }
```

`tests/Fixtures/vite/build/assets/vendor-xyz.js`:
```js
console.log('vendor');
```

`tests/Fixtures/vite/build/assets/tailwind-111.css`:
```css
body { background: #fff; }
```

`tests/Fixtures/vite/build/assets/fonts-222.css`:
```css
@font-face { font-family: Test; src: url('/fonts/test.woff2'); }
```

**Step 2: 写失败测试**

`tests/Unit/Vite/ViteManifestTest.php`:
```php
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
```

**Step 3: 运行测试确认失败**
Run: `composer test -- tests/Unit/Vite/ViteManifestTest.php`
Expected: FAIL（找不到 `App\Infrastructure\Vite\Vite` 或方法未实现）

**Step 4: 提交**
```bash
git add tests/Fixtures/vite tests/Unit/Vite/ViteManifestTest.php
git commit -m "test: add manifest fixtures for vite"
```

---

### Task 2: 实现 Vite 服务（生产态）

**Files:**
- Create: `app/src/Infrastructure/Vite/Vite.php`

**Step 1: 写最小实现使 Task 1 通过**

`app/src/Infrastructure/Vite/Vite.php`:
```php
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
```

**Step 2: 运行测试确认通过**
Run: `composer test -- tests/Unit/Vite/ViteManifestTest.php`
Expected: PASS

**Step 3: 提交**
```bash
git add app/src/Infrastructure/Vite/Vite.php
git commit -m "feat: add vite manifest resolver"
```

---

### Task 3: Dev/hot 与 inline 行为测试 + 实现

**Files:**
- Create: `tests/Fixtures/vite/hot`
- Create: `tests/Unit/Vite/ViteHotInlineTest.php`
- Modify: `app/src/Infrastructure/Vite/Vite.php`

**Step 1: 写失败测试**

`tests/Fixtures/vite/hot`:
```
http://localhost:5173
```

`tests/Unit/Vite/ViteHotInlineTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Vite;

use App\Infrastructure\Vite\Vite;
use PHPUnit\Framework\TestCase;

final class ViteHotInlineTest extends TestCase
{
    public function test_renders_dev_tags_from_hot_file(): void
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
```

**Step 2: 运行测试确认失败**
Run: `composer test -- tests/Unit/Vite/ViteHotInlineTest.php`
Expected: FAIL

**Step 3: 最小实现（修正 inline 判断与 dev 逻辑）**
- 在 `renderTags()` 中用明确标记判断 inline（避免通过内容猜测）。
- 调整 `assetRef()` 返回值为 `['type' => 'inline'|'url', 'value' => string]`，并在 `renderTags()` 使用。

**Step 4: 运行测试确认通过**
Run: `composer test -- tests/Unit/Vite/ViteHotInlineTest.php`
Expected: PASS

**Step 5: 提交**
```bash
git add app/src/Infrastructure/Vite/Vite.php tests/Fixtures/vite/hot tests/Unit/Vite/ViteHotInlineTest.php
git commit -m "test: cover hot and inline vite tags"
```

---

### Task 4: Twig 扩展与配置

**Files:**
- Create: `app/config/vite.php`
- Create: `app/src/Infrastructure/Vite/Twig/ViteExtension.php`
- Create: `app/src/Application/Bootloader/ViteBootloader.php`
- Modify: `app/src/Application/Kernel.php`
- Create: `tests/Unit/Vite/ViteExtensionTest.php`

**Step 1: 写失败测试**

`tests/Unit/Vite/ViteExtensionTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Vite;

use App\Infrastructure\Vite\Twig\ViteExtension;
use PHPUnit\Framework\TestCase;
use Twig\TwigFunction;

final class ViteExtensionTest extends TestCase
{
    public function test_vite_functions_delegate_to_service(): void
    {
        $fake = new class {
            public function tags(array $entries, ?string $buildDir = null, bool $inline = false): string
            {
                return 'tags:' . implode(',', $entries) . ':' . ($buildDir ?? 'build') . ':' . ($inline ? '1' : '0');
            }
            public function asset(string $entry, ?string $buildDir = null): string
            {
                return 'asset:' . $entry . ':' . ($buildDir ?? 'build');
            }
        };

        $ext = new ViteExtension($fake);
        $funcs = $ext->getFunctions();

        /** @var TwigFunction $vite */
        $vite = $funcs[0];
        /** @var TwigFunction $asset */
        $asset = $funcs[1];

        $this->assertSame('tags:resources/js/app.js:build:0', ($vite->getCallable())(['resources/js/app.js']));
        $this->assertSame('asset:resources/js/app.js:build', ($asset->getCallable())('resources/js/app.js'));
    }
}
```

**Step 2: 运行测试确认失败**
Run: `composer test -- tests/Unit/Vite/ViteExtensionTest.php`
Expected: FAIL（类不存在）

**Step 3: 实现配置与扩展**

`app/config/vite.php`:
```php
<?php

declare(strict_types=1);

return [
    'public_dir' => directory('public'),
    'build_dir' => 'build',
    'manifest' => '.vite/manifest.json',
    'hot_file' => 'hot',
    'asset_url' => env('ASSET_URL', ''),
];
```

`app/src/Infrastructure/Vite/Twig/ViteExtension.php`:
```php
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
```

`app/src/Application/Bootloader/ViteBootloader.php`:
```php
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
```

**Step 4: 注册 Bootloader**

`app/src/Application/Kernel.php` 在 `defineAppBootloaders()` 中加入：
```php
Bootloader\ViteBootloader::class,
```

**Step 5: 运行测试确认通过**
Run: `composer test -- tests/Unit/Vite/ViteExtensionTest.php`
Expected: PASS

**Step 6: 提交**
```bash
git add app/config/vite.php app/src/Infrastructure/Vite/Twig/ViteExtension.php app/src/Application/Bootloader/ViteBootloader.php app/src/Application/Kernel.php tests/Unit/Vite/ViteExtensionTest.php
git commit -m "feat: add vite twig extension"
```

---

### Task 5: 更新 Vite 构建配置与模板

**Files:**
- Modify: `vite.config.js`
- Modify: `package.json`
- Modify: `app/views/layout/base.twig`
- Modify: `.gitignore`

**Step 1: 更新 Vite 配置**

`vite.config.js`:
```js
import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/vite'
import fs from 'node:fs'
import path from 'node:path'

function hotFilePlugin() {
  let config
  return {
    name: 'spiral-hot-file',
    configResolved(resolved) {
      config = resolved
    },
    configureServer(server) {
      const hotPath = path.resolve(config.root, 'public', 'hot')
      const url = server.resolvedUrls?.local?.[0] ?? `http://localhost:${server.config.server.port}`
      fs.writeFileSync(hotPath, url)
      server.httpServer?.once('close', () => {
        if (fs.existsSync(hotPath)) fs.unlinkSync(hotPath)
      })
    },
    buildEnd() {
      const hotPath = path.resolve(config.root, 'public', 'hot')
      if (fs.existsSync(hotPath)) fs.unlinkSync(hotPath)
    },
  }
}

export default defineConfig({
  plugins: [
    tailwindcss(),
    hotFilePlugin(),
  ],
  build: {
    outDir: 'public/build',
    manifest: true,
    assetsDir: 'assets',
    rollupOptions: {
      input: [
        'resources/js/app.js',
        'resources/css/tailwind.css',
        'resources/css/fonts.css',
      ],
    },
  },
})
```

**Step 2: 更新脚本**

`package.json`:
```json
{
  "scripts": {
    "dev": "vite",
    "build": "vite build"
  },
  ...
}
```

**Step 3: 模板注入 Vite 入口**

`app/views/layout/base.twig`:
```twig
{% block styles %}
    {{ vite([
        'resources/js/app.js',
        'resources/css/tailwind.css',
        'resources/css/fonts.css',
    ]) }}
{% endblock %}
```

**Step 4: 忽略构建产物**

`.gitignore` 追加：
```
/public/build/
/public/hot
```

**Step 5: 运行测试**
Run: `composer test`
Expected: PASS（允许 phpunit.xml 警告）

**Step 6: 提交**
```bash
git add vite.config.js package.json app/views/layout/base.twig .gitignore
git commit -m "feat: wire vite assets into templates"
```

---

### Task 6: 运行全量测试

**Files:**
- None

**Step 1: 全量测试**
Run: `composer test`
Expected: PASS（允许 phpunit.xml 警告）

**Step 2: 提交（如有）**
若无变更则跳过提交。

