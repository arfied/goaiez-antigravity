<?php
// Fix ExpirySettingsTest
$content = file_get_contents('tests/Feature/Ops/ExpirySettingsTest.php');
$content = preg_replace('/expect\(\$expiry->diffInMinutes\(now\(\)\)\)->toBe\(5\);/', 'expect(now()->diffInMinutes($expiry))->toBe(5);', $content);
$content = preg_replace('/expect\(\$item->expires_at->diffInHours\(now\(\)\)\)->toBe\(10\);/', 'expect(now()->diffInHours($item->expires_at))->toBe(10);', $content);
$content = preg_replace('/expect\(\$ticket->sla_due_at->diffInHours\(now\(\)\)\)->toBe\(5\);/', 'expect(now()->diffInHours($ticket->sla_due_at))->toBe(5);', $content);
// Fix QA ticket creation (needs person)
$content = str_replace("\$ticket = \$action->handle(\$this->biz->id, 1, 'subject');", "\$personId = app(\App\Modules\X121\Actions\PersonLookupAction::class)->create(\$this->biz->id, ['first_name' => 'John']);\n    \$ticket = \$action->handle(\$this->biz->id, \$personId, 'subject');", $content);
file_put_contents('tests/Feature/Ops/ExpirySettingsTest.php', $content);

// Fix PortalLinkSettingsTest
$content = file_get_contents('tests/Feature/Portal/PortalLinkSettingsTest.php');
$content = preg_replace('/expect\(\$link->expires_at->diffInHours\(now\(\)\)\)->toBe\(12\);/', 'expect(now()->diffInHours($link->expires_at))->toBe(12);', $content);
file_put_contents('tests/Feature/Portal/PortalLinkSettingsTest.php', $content);

// Fix CallAttributionSettingsTest
$content = file_get_contents('tests/Feature/Attribution/CallAttributionSettingsTest.php');
$content = preg_replace('/expect\(\$token->expires_at->diffInMinutes\(now\(\)\)\)->toBe\(15\);/', 'expect(now()->diffInMinutes($token->expires_at))->toBe(15);', $content);
file_put_contents('tests/Feature/Attribution/CallAttributionSettingsTest.php', $content);

// Fix CardExpirySettingsTest (remove customer_id)
$content = file_get_contents('tests/Feature/Billing/CardExpirySettingsTest.php');
$content = str_replace("'customer_id' => 1,", "", $content);
file_put_contents('tests/Feature/Billing/CardExpirySettingsTest.php', $content);
