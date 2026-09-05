<?php
$file = __DIR__.'/../app/app/Console/Commands/SurfacesGenerateCommand.php';
$content = file_get_contents($file);
$content = str_replace(
    ", \\\\App\\\\Modules\\\\{\$modClassNamespace}\\\\Ui\\\\\$class::class)",
    ", " . '($class[0] === "\\\\" ? $class : "\\\\App\\\\Modules\\\\{$modClassNamespace}\\\\Ui\\\\" . $class) . "::class)"',
    $content
);
file_put_contents($file, $content);
