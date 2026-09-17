<?php

declare(strict_types=1);

namespace Tests\Modules\X177;

use App\Models\Location;
use App\Modules\X177\Actions\GbpPostAction;
use App\Modules\X177\Models\GbpConnection;
use App\Modules\X177\Models\GbpPost;
use App\Modules\X177\Models\GbpStateLog;
use App\Modules\X177\Ui\GbpCard;
use App\Modules\X177\Ui\SuspensionriskEventsFleetwide;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class X177ScreensTest extends TestCase
{
    protected int $bizId;

    protected function setUp(): void
    {
        parent::setUp();
        $biz = TestCase::provisionTenant(['name' => 'GBP Tenant', 'currency' => 'USD']);
        $this->bizId = $biz->id;
        Tenancy::set($this->bizId);
        GbpConnection::where('business_id', $this->bizId)->delete();
    }

    public function test_gbp_card_mount_and_empty(): void
    {
        Livewire::test(GbpCard::class, ['businessId' => $this->bizId])
            ->assertOk()
            ->assertSee('Google Business Profile')
            ->assertSee('Connect Google');
    }

    public function test_gbp_card_sample_state(): void
    {
        Livewire::test(GbpCard::class, ['businessId' => $this->bizId])
            ->call('toggleSample')
            ->assertOk()
            ->assertSee('Profile is suspended')
            ->assertSee('Summer sale started today');
    }

    public function test_gbp_card_latest_post_and_suspended_state(): void
    {
        $loc = Location::factory()->create(['business_id' => $this->bizId]);
        $conn = GbpConnection::create([
            'location_id' => $loc->id,
            'business_id' => $this->bizId,
            'account_ref' => '123',
            'external_label' => 'Main St Store',
            'profile_status' => 'suspended',
        ]);

        GbpPost::create([
            'business_id' => $this->bizId,
            'connection_id' => $conn->id,
            'content' => 'This is the latest post text',
        ]);

        Livewire::test(GbpCard::class, ['businessId' => $this->bizId])
            ->assertSee('Profile is suspended')
            ->assertSee('This is the latest post text');
    }

    public function test_gbp_card_poll_state_action(): void
    {
        $loc = Location::factory()->create(['business_id' => $this->bizId]);
        $conn = GbpConnection::create([
            'location_id' => $loc->id,
            'business_id' => $this->bizId,
            'account_ref' => '123',
            'external_label' => 'Main St Store',
            'profile_status' => 'suspended',
        ]);

        Livewire::test(GbpCard::class, ['businessId' => $this->bizId])
            ->call('pollState', $conn->id);

        $this->assertEquals(1, GbpStateLog::where('connection_id', $conn->id)->count());
    }

    public function test_suspension_risk_mount_and_empty(): void
    {
        Livewire::test(SuspensionriskEventsFleetwide::class, ['businessId' => $this->bizId])
            ->assertOk()
            ->assertSee('No suspension risks on record');
    }

    public function test_suspension_risk_lists_flagged_post(): void
    {
        $loc = Location::factory()->create(['business_id' => $this->bizId]);
        $conn = GbpConnection::create([
            'location_id' => $loc->id,
            'business_id' => $this->bizId,
            'account_ref' => '123',
            'external_label' => 'Main St Store',
            'profile_status' => 'active',
        ]);

        app(GbpPostAction::class)->post($this->bizId, $conn->id, 'Guaranteed ranking #1 today');

        Livewire::test(SuspensionriskEventsFleetwide::class, ['businessId' => $this->bizId])
            ->assertOk()
            ->assertSee('Risk flagged')
            ->assertSee('Guaranteed ranking #1 today');
    }

    public function test_suspension_risk_poll_action(): void
    {
        $loc = Location::factory()->create(['business_id' => $this->bizId]);
        $conn = GbpConnection::create([
            'location_id' => $loc->id,
            'business_id' => $this->bizId,
            'account_ref' => '123',
            'external_label' => 'Main St Store',
            'profile_status' => 'active',
        ]);

        $initialLogCount = GbpStateLog::count();

        Livewire::test(SuspensionriskEventsFleetwide::class, ['businessId' => $this->bizId])
            ->call('pollState', $conn->id);

        $this->assertEquals($initialLogCount + 1, GbpStateLog::count());
    }

    public function test_suspension_risk_sample_state(): void
    {
        $before = GbpStateLog::count();
        Livewire::test(SuspensionriskEventsFleetwide::class, ['businessId' => $this->bizId])
            ->call('toggleSample')
            ->assertOk()
            ->assertSee('Risk flagged')
            ->assertSee('Suspension detected')
            ->call('pollState', 9991)
            ->assertOk();
        $this->assertEquals($before, GbpStateLog::count());
    }
}
