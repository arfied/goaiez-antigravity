<?php

// Fix ExpirySettingsTest
$content = file_get_contents('tests/Feature/Ops/ExpirySettingsTest.php');
$content = preg_replace('/expect\(now\(\)->diffInMinutes\(\$expiry\)\)->toBe\(5\);/', 'expect($expiry->toDateTimeString())->toBe(now()->addMinutes(5)->toDateTimeString());', $content);
$content = preg_replace('/expect\(now\(\)->diffInHours\(\$item->expires_at\)\)->toBe\(10\);/', 'expect($item->expires_at->toDateTimeString())->toBe(now()->addHours(10)->toDateTimeString());', $content);
$content = preg_replace('/expect\(now\(\)->diffInHours\(\$ticket->sla_due_at\)\)->toBe\(5\);/', 'expect($ticket->sla_due_at->toDateTimeString())->toBe(now()->addHours(5)->toDateTimeString());', $content);
file_put_contents('tests/Feature/Ops/ExpirySettingsTest.php', $content);

// Fix PortalLinkSettingsTest
$content = file_get_contents('tests/Feature/Portal/PortalLinkSettingsTest.php');
$content = preg_replace('/expect\(now\(\)->diffInHours\(\$link->expires_at\)\)->toBe\(12\);/', 'expect($link->expires_at->toDateTimeString())->toBe(now()->addHours(12)->toDateTimeString());', $content);
file_put_contents('tests/Feature/Portal/PortalLinkSettingsTest.php', $content);

// Fix CallAttributionSettingsTest
$content = file_get_contents('tests/Feature/Attribution/CallAttributionSettingsTest.php');
$content = preg_replace('/expect\(now\(\)->diffInMinutes\(\$token->expires_at\)\)->toBe\(15\);/', 'expect($token->expires_at->toDateTimeString())->toBe(now()->addMinutes(15)->toDateTimeString());', $content);
file_put_contents('tests/Feature/Attribution/CallAttributionSettingsTest.php', $content);
