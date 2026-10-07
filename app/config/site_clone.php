<?php

declare(strict_types=1);

return [
    'root' => env('SITE_CLONE_ROOT', '/home/goaiez/public_html/clones'),
    'runner' => env('SITE_CLONE_RUNNER', dirname(base_path()).'/webstudio-bridge/bin/run-clone.sh'),
    'builder_origin' => env('SITE_CLONE_BUILDER_ORIGIN', 'https://wstd.dev:5174'),
    'builder_insecure_tls' => env('SITE_CLONE_BUILDER_INSECURE_TLS', false),
];
