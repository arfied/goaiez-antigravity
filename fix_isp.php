<?php
$c = file_get_contents('app/tests/Feature/Industry/IndustryStartingPointsTest.php');
$c = str_replace(
    "\$biz->update([]);",
    "\$biz->update(['industry' => \App\Enums\IndustryFamily::Trades->value, 'site_variant' => 'c']);",
    $c
);
file_put_contents('app/tests/Feature/Industry/IndustryStartingPointsTest.php', $c);
