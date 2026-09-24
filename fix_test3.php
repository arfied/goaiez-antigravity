<?php
$files = [
    'app/tests/Feature/Industry/IndustryStartingPointsTest.php',
    'app/tests/Modules/X-103/SitePreviewActionTest.php',
    'app/tests/Modules/X-157/PublicBookingRouteTest.php',
    'app/tests/Modules/X-103/Screens/SiteBuildScreenTest.php'
];

foreach ($files as $file) {
    $c = file_get_contents($file);
    // Remove the industry and site_variant from provisionTenant array
    $c = preg_replace("/'industry' => \\\App\\\Enums\\\IndustryFamily::Trades->value,?\s*/", "", $c);
    $c = preg_replace("/'site_variant' => 'c',?\s*/", "", $c);
    
    // Add $biz->update(...) right after provisionTenant if needed.
    // In PublicBookingRouteTest:
    // $biz = self::provisionTenant(['owner_user_id' => $owner->id]);
    // we want to add $biz->update(['industry' => 'trades', 'site_variant' => 'c']);
    
    // We can do it broadly:
    $c = preg_replace(
        "/(self|\\\$this)->provisionTenant\((.*?)\);/",
        "$0\n        \$biz->update(['industry' => 'trades', 'site_variant' => 'c']);",
        $c
    );

    file_put_contents($file, $c);
}
