<?php

declare(strict_types=1);

namespace Tests\Modules\X176;

use App\Models\Business;
use App\Modules\X103\Models\Page;
use App\Modules\X108\Models\Appointment;
use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X157\Models\EdgeZone;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * [G8-15] Event Auto-Sync
 */
final class EventSchemaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_rendered_schema_contains_events_when_appointments_present(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Event Tenant']);
        \App\Support\Tenancy::set((int) $biz->id);
        
        $page = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home']);
        $zone = app(\App\Modules\X157\Actions\EdgeProvisionAction::class)->handle($biz->id, 'event1.example.com', true);

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
        $html = \Illuminate\Support\Facades\Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        $this->assertStringContainsString('"@type":"Event"', $html);
        $this->assertStringContainsString('Drain Cleaning', $html);
    }

    public function test_rendered_schema_has_no_events_when_no_appointments(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Event Tenant 2']);
        \App\Support\Tenancy::set((int) $biz->id);
        
        $page = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home']);
        $zone = app(\App\Modules\X157\Actions\EdgeProvisionAction::class)->handle($biz->id, 'event2.example.com', true);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $page->id,
            commitId: 'commit_124',
            businessName: 'My Biz 2'
        );

        $this->assertEquals('deployed', $res['status']);
        $html = \Illuminate\Support\Facades\Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        $this->assertStringNotContainsString('"@type":"Event"', $html);
    }
}
