<?php

declare(strict_types=1);

use App\Enums\DataClassification;
use App\Enums\UserRole;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Warehouse\L1Derivation;
use App\Services\Warehouse\Replayer;
use App\Services\Warehouse\WarehouseRetention;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    /** @var TestCase $this */
    $this->biz = $this->provisionTenant(['owner_user_id' => $this->owner->id]);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);
});

it('classifies a session as bot at a lowered threshold', function () {
    PlatformSetting::write('warehouse.bot_threshold', 20, 'test');

    $line = [
        'business_id' => $this->biz->id,
        'data_class' => DataClassification::Pii->value,
        'received_at' => CarbonImmutable::now()->toIso8601String(),
        'schema_version' => 2,
        'payload' => json_encode([
            'device' => ['webdriver' => true],
            'events' => [
                ['event_id' => Str::uuid()->toString(), 'type' => 'pageview', 'occurred_at' => '2026-08-10T09:15:29Z'],
            ],
        ]),
    ];
    $rows = L1Derivation::rows($line, 'path');
    $this->assertTrue($rows[0]['is_bot']);
});

it('attributes or does not attribute at a shortened window', function () {
    PlatformSetting::write('warehouse.attribution_window_days', 10, 'test');

    $sid1 = Str::uuid()->toString();
    $sid2 = Str::uuid()->toString();
    $anonId = Str::uuid()->toString();
    $day1 = CarbonImmutable::parse('2026-08-01T10:00:00.000Z');
    $day15 = CarbonImmutable::parse('2026-08-15T10:00:00.000Z');

    $defaults = [
        'duration_s' => 0,
        'active_s' => 0,
        'conversions' => 0,
        'is_engaged' => false,
        'is_bot' => false,
        'entry_page_path' => '/',
        'exit_page_path' => '/',
        'device_type' => 'desktop',
        'consent_state' => 'granted',
    ];

    DB::table('l2_fact_session')->insert(array_merge($defaults, [
        'session_id' => $sid1,
        'business_id' => $this->biz->id,
        'anonymous_id' => $anonId,
        'started_at' => $day1,
        'ended_at' => $day1,
        'first_received_at' => $day1,
        'source_key' => 'google / cpc',
        'pageviews' => 1,
        'is_new' => true,
    ]));

    DB::table('l2_fact_session')->insert(array_merge($defaults, [
        'session_id' => $sid2,
        'business_id' => $this->biz->id,
        'anonymous_id' => $anonId,
        'started_at' => $day15,
        'ended_at' => $day15,
        'first_received_at' => $day15,
        'source_key' => '(direct)',
        'pageviews' => 1,
        'is_new' => false,
    ]));

    DB::table('l1_events')->insert([
        'event_id' => Str::uuid()->toString(),
        'business_id' => $this->biz->id,
        'data_class' => 'pii',
        'event_type' => 'form_submitted',
        'consent_state' => 'granted',
        'anonymous_id' => $anonId,
        'session_id' => $sid2,
        'occurred_at' => $day15,
        'received_at' => $day15,
        'is_bot' => false,
        'bot_probability' => 0,
        'page_path' => '/contact',
        'page_host' => 'example.com',
        'device_type' => 'desktop',
        'properties' => '[]',
        'l0_path' => 'path',
        'schema_version' => 2,
    ]);

    $replayer = app(Replayer::class);
    $reflection = new ReflectionMethod($replayer, 'rebuildFactConversion');
    $reflection->setAccessible(true);
    $reflection->invoke($replayer, $this->biz->id, CarbonImmutable::parse('2026-08-01'), CarbonImmutable::parse('2026-08-16'));

    $conversions = DB::table('l2_fact_conversion')
        ->where('business_id', $this->biz->id)
        ->get();

    $this->assertCount(1, $conversions);
    $this->assertEquals('(direct)', $conversions[0]->attributed_source_key);
});

it('returns written retention days', function () {
    PlatformSetting::write('warehouse.l2_retention_days', 400, 'test');
    $this->assertEquals(400, WarehouseRetention::l2RetentionDays());
});
