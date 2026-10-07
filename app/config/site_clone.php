<?php

declare(strict_types=1);

return [
    'root' => env('SITE_CLONE_ROOT', '/home/goaiez/public_html/clones'),
    'runner' => env('SITE_CLONE_RUNNER', dirname(base_path()).'/webstudio-bridge/bin/run-clone.sh'),
];
