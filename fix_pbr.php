<?php
$c = file_get_contents('app/tests/Modules/X-157/PublicBookingRouteTest.php');
$c = str_replace(
    "\$biz = self::provisionTenant(['owner_user_id' => \$owner->id, ]);",
    "\$biz = self::provisionTenant(['owner_user_id' => \$owner->id]);\n        \$biz->update(['industry' => \App\Enums\IndustryFamily::Trades->value, 'site_variant' => 'c']);",
    $c
);
file_put_contents('app/tests/Modules/X-157/PublicBookingRouteTest.php', $c);
