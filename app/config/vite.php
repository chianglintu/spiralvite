<?php

declare(strict_types=1);

return [
    'public_dir' => directory('public'),
    'build_dir' => 'build',
    'manifest' => '.vite/manifest.json',
    'hot_file' => 'hot',
    'asset_url' => env('ASSET_URL', ''),
];
