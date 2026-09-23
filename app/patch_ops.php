<?php
$content = file_get_contents('tests/Feature/Ops/ExpirySettingsTest.php');
// let's manually write the class path
$content = preg_replace('/app\(\\\\App\\\\Modules.*\)->create/', 'app(\\\\App\\\\Modules\\\\X121\\\\Actions\\\\PersonLookupAction::class)->create', $content);
file_put_contents('tests/Feature/Ops/ExpirySettingsTest.php', $content);
