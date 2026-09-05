<?php

declare(strict_types=1);

namespace Tests\Modules\CTelephony\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CTelephony\Ui\CarrierRosterHealth;
use Livewire\Livewire;
use Tests\TestCase;

class CarrierRosterHealthScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-telephony.carrier-roster-health'))->assertOk();

        Livewire::test(CarrierRosterHealth::class)->assertOk();
    }
}
