<?php

declare(strict_types=1);

use App\Models\PlatformMailSend;
use App\Models\PlatformSetting;
use App\Services\Mail\GooglePushTokenVerifier;
use App\Services\Mail\MailQuota;
use App\Services\Mail\SnsSubscriptions;
use Illuminate\Support\Facades\Cache;

it('sns subscriptions honours pending ttl', function () {
    PlatformSetting::write('mail.sns.pending_ttl_seconds', 7777, 'test');

    Cache::shouldReceive('put')->once()->withArgs(function ($key, $val, $ttl) {
        return $ttl === 7777;
    });

    app(SnsSubscriptions::class)->record('arn:aws:sns:us-east-1:123:foo', 'https://sns.us-east-1.amazonaws.com/');
});

it('mail quota honours alert quiet seconds', function () {
    PlatformSetting::write('mail.quota.alert_quiet_seconds', 7777, 'test');
    PlatformSetting::write('mail.daily_send_ceiling.array', 10, 'test');
    PlatformSetting::write('mail.ceiling_alert_percent', 10, 'test');

    config(['mail.default' => 'array']);

    PlatformMailSend::forceCreate([
        'mailer' => 'array',
        'sending_account' => 'test@example.com',
        'sent_at' => now(),
    ]);

    Cache::shouldReceive('add')->once()->withArgs(function ($key, $val, $ttl) {
        return $ttl === 7777;
    })->andReturn(true);

    app(MailQuota::class)->record();
});

it('google push token verifier honours certificate ttl', function () {
    PlatformSetting::write('mail.google_push.certificate_ttl_seconds', 7777, 'test');

    expect(app(GooglePushTokenVerifier::class)->certificateTtlSeconds())->toBe(7777);
});
