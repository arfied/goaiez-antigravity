<?php

namespace Tests\Modules\CSms\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class PernumberComplaintMonitoringScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-sms.pernumber-complaint-monitoring'))->assertOk();

        Livewire::test(\App\Modules\CSms\Ui\PernumberComplaintMonitoring::class)->assertOk();
    }
}
