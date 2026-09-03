<?php
$content = file_get_contents('app/app/Modules/C-Reviews/ModuleServiceProvider.php');
$content = preg_replace('/\\\\App\\\\Modules.\\d\\d\\\\Events\\\\JobCompleted::class/', '\\\\App\\\\Modules\\\\X171\\\\Events\\\\JobCompleted::class', $content);
file_put_contents('app/app/Modules/C-Reviews/ModuleServiceProvider.php', $content);
