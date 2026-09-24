<?php
$c = file_get_contents('app/tests/Modules/X-103/SitePreviewActionTest.php');
$c = str_replace(
    "\$biz = self::provisionTenant([]);",
    "\$biz = self::provisionTenant([]);\n        \$biz->update(['industry' => \App\Enums\IndustryFamily::Trades->value]);",
    $c
);
file_put_contents('app/tests/Modules/X-103/SitePreviewActionTest.php', $c);
