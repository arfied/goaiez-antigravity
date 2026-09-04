<?php

declare(strict_types=1);

namespace Tests\Modules\X177;

use App\Modules\X177\Models\GbpConnection;
use App\Modules\X177\Models\GbpPost;
use App\Modules\X177\Models\GbpStateLog;
use App\Modules\X177\Ui\GbpCard;
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
        $conn = GbpConnection::create([
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
        $conn = GbpConnection::create([
            'business_id' => $this->bizId,
            'account_ref' => '123',
            'external_label' => 'Main St Store',
            'profile_status' => 'suspended',
        ]);

        Livewire::test(GbpCard::class, ['businessId' => $this->bizId])
            ->call('pollState', $conn->id);

        $this->assertEquals(1, GbpStateLog::where('connection_id', $conn->id)->count());
    }
}
