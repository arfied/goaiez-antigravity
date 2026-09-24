<?php
$files = [
    'app/tests/Feature/Industry/IndustryStartingPointsTest.php',
    'app/tests/Modules/X-103/SitePreviewActionTest.php',
    'app/tests/Modules/X-157/PublicBookingRouteTest.php'
];

foreach ($files as $file) {
    $c = file_get_contents($file);
    $c = str_replace(
        "['industry' => 'trades']",
        "['industry' => \App\Enums\IndustryFamily::Trades->value]",
        $c
    );
    $c = str_replace(
        "['owner_user_id' => \$owner->id, 'industry' => 'trades', 'site_variant' => 'c']",
        "['owner_user_id' => \$owner->id, 'industry' => \App\Enums\IndustryFamily::Trades->value, 'site_variant' => 'c']",
        $c
    );
    $c = str_replace(
        "\$deploy->deploy_hash",
        "\$deploy['deploy_hash']",
        $c
    );
    
    file_put_contents($file, $c);
}
