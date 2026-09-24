<?php
$files = [
    'app/tests/Feature/Industry/IndustryStartingPointsTest.php',
    'app/tests/Modules/X-103/SitePreviewActionTest.php',
    'app/tests/Modules/X-103/Screens/SiteBuildScreenTest.php',
    'app/tests/Modules/X-157/PublicBookingRouteTest.php'
];

foreach ($files as $file) {
    $content = file_get_contents($file);
    // Replace IndustryStartingPoint::create([
    //     'family' => \App\Enums\IndustryFamily::Trades->value,
    //     'palette'
    // with IndustryStartingPoint::updateOrCreate(['family' => \App\Enums\IndustryFamily::Trades->value], [
    //     'palette'

    // Need a simple str_replace
    $content = str_replace(
        "'family' => \\App\\Enums\\IndustryFamily::Trades->value,",
        "",
        $content
    );
    $content = str_replace(
        "IndustryStartingPoint::create([",
        "IndustryStartingPoint::updateOrCreate(['family' => \\App\\Enums\\IndustryFamily::Trades->value], [",
        $content
    );
    
    // In IndustryStartingPointsTest, it's just IndustryFamily::Trades->value
    $content = str_replace(
        "'family' => IndustryFamily::Trades->value,",
        "",
        $content
    );
    $content = preg_replace(
        "/IndustryStartingPoint::create\(\[/",
        "IndustryStartingPoint::updateOrCreate(['family' => IndustryFamily::Trades->value], [",
        $content
    );

    file_put_contents($file, $content);
}
