<?php

namespace Tests\Modules\X212\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class ReconciliationReportScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-212.reconciliation-report'))->assertOk();

        Livewire::test(\App\Modules\X212\Ui\ReconciliationReport::class)->assertOk();
    }
}
