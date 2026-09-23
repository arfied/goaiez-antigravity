<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\OwnerNotification;
use App\Models\PlatformCredential;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Sms\InfobipWebhookVerifier;
use App\Services\Sms\OwnerNotifications;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    $this->biz = $this->provisionTenant(['owner_user_id' => $this->owner->id]);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);
});

it('infobip webhook verifier honours max signature age', function () {
    PlatformSetting::write('sms.webhook.max_signature_age_seconds', 1, 'test');

    config(['services.infobip.signature_scheme' => 'exchange']);

    $secret = 'test-secret';
    PlatformCredential::forceCreate([
        'key' => InfobipWebhookVerifier::CREDENTIAL,
        'value' => $secret,
        'rotated_by' => 'test',
        'rotated_at' => now(),
        'environment' => 'test',
    ]);

    $body = '{"test":true}';
    $timestamp = (time() - 2) * 1000;

    $expected = hash_hmac('sha256', $timestamp.$body, $secret);

    $request = Request::create('/webhook', 'POST', [], [], [], [
        'HTTP_'.str_replace('-', '_', strtoupper(InfobipWebhookVerifier::EXCHANGE_SIGNATURE_HEADER)) => $expected,
        'HTTP_'.str_replace('-', '_', strtoupper(InfobipWebhookVerifier::EXCHANGE_TIMESTAMP_HEADER)) => (string) $timestamp,
    ], $body);

    $verifier = app(InfobipWebhookVerifier::class);
    expect($verifier->verify($request))->toBeFalse();

    $timestampNow = time() * 1000;
    $expectedNow = hash_hmac('sha256', $timestampNow.$body, $secret);
    $requestNow = Request::create('/webhook', 'POST', [], [], [], [
        'HTTP_'.str_replace('-', '_', strtoupper(InfobipWebhookVerifier::EXCHANGE_SIGNATURE_HEADER)) => $expectedNow,
        'HTTP_'.str_replace('-', '_', strtoupper(InfobipWebhookVerifier::EXCHANGE_TIMESTAMP_HEADER)) => (string) $timestampNow,
    ], $body);

    expect($verifier->verify($requestNow))->toBeTrue();
});

it('owner notifications honours ledger limit', function () {
    PlatformSetting::write('sms.owner_notifications.ledger_limit', 1, 'test');

    OwnerNotification::factory()->count(2)->create([
        'business_id' => $this->biz->id,
        'sent_at' => now(),
    ]);

    $service = app(OwnerNotifications::class);
    $ledger = $service->ledgerFor((int) $this->biz->id, $service->ledgerLimit());
    expect($ledger['sends'])->toHaveCount(1);
});

it('owner notifications honours correlation window', function () {
    PlatformSetting::write('sms.owner_notifications.correlation_window_hours', 1, 'test');

    OwnerNotification::factory()->create([
        'business_id' => $this->biz->id,
        'sent_at' => CarbonImmutable::now()->subHours(2),
    ]);

    $latest = app(OwnerNotifications::class)->latestFor((int) $this->biz->id);
    expect($latest)->toBeNull();

    $recent = OwnerNotification::factory()->create([
        'business_id' => $this->biz->id,
        'sent_at' => CarbonImmutable::now()->subMinutes(30),
    ]);

    $latestNow = app(OwnerNotifications::class)->latestFor((int) $this->biz->id);
    expect($latestNow->id)->toBe($recent->id);
});
