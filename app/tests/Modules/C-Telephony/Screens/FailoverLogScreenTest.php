<?php

declare(strict_types=1);

namespace Tests\Modules\CTelephony\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CTelephony\Actions\CarrierSendAction;
use App\Modules\CTelephony\Ui\FailoverLog;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class FailoverLogScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-telephony.failover-log'))->assertOk();

        Livewire::test(FailoverLog::class)->assertOk();
    }

    public function test_the_failover_log_shows_each_receipts_identity_and_status(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        DB::table('carrier_credentials')->insert([
            ['business_id' => $biz->id, 'carrier_name' => 'telnyx', 'api_key' => 'key_telnyx', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $res = app(CarrierSendAction::class)->handle(
            businessId: $biz->id,
            threadKey: 'conv-thread-telnyx-sticky-01',
            toPhone: '+15125550199',
            body: 'Message sequence',
            isRcs: false,
            preferredCarrier: 'telnyx'
        );

        Livewire::test(FailoverLog::class)
            ->assertSee('#'.$res['message_id'])
            ->assertSee('[sent]');
    }
}
