<?php

namespace Tests\Modules\X01\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class PaymentRiskScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-01.payment-risk'))->assertOk();

        Livewire::test(\App\Modules\X01\Ui\PaymentRisk::class)->assertOk();
    }
}
