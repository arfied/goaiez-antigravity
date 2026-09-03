<?php
$content = file_get_contents('app/app/Modules/X-103/Domain/SiteEngine.php');
$content = str_replace("'ssl_installed' => true,", "'ssl_enabled' => true,", $content);
file_put_contents('app/app/Modules/X-103/Domain/SiteEngine.php', $content);
