<?php

declare(strict_types=1);

namespace Tests\Modules\X176;

use App\Modules\X103\Models\Page;
use App\Modules\X108\Models\Appointment;
use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X157\Actions\EdgeProvisionAction;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * [G8-15] Event Auto-Sync
 */
final class EventSchemaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_rendered_schema_contains_events_when_appointments_present(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Event Tenant']);
        Tenancy::set((int) $biz->id);

        $page = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home']);
        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'event1.example.com', true);

        Appointment::create([
            'business_id' => $biz->id,
            'service_name' => 'Drain Cleaning',
            'start_time' => now()->addDays(2),
            'end_time' => now()->addDays(2)->addHours(2),
            'is_member' => false,
        ]);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $page->id,
            commitId: 'commit_123',
            businessName: 'My Biz'
        );

        $this->assertEquals('deployed', $res['status']);
        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        $this->assertStringContainsString('"@type":"Event"', $html);

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $json = json_decode($matches[1], true);

        $this->assertArrayHasKey('event', $json);
        $events = isset($json['event']['@type']) ? [$json['event']] : $json['event'];
        $eventNames = array_column($events, 'name');

        $this->assertContains('Drain Cleaning', $eventNames);
    }

    public function test_rendered_schema_has_no_events_when_no_appointments(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Event Tenant 2']);
        Tenancy::set((int) $biz->id);

        $page = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home']);
        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'event2.example.com', true);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $page->id,
            commitId: 'commit_124',
            businessName: 'My Biz 2'
        );

        $this->assertEquals('deployed', $res['status']);
        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        $this->assertStringNotContainsString('"@type":"Event"', $html);
    }
}
