<?php

declare(strict_types=1);

namespace Tests\Modules\X01\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X01\Ui\PaymentRisk;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentRiskScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-01.payment-risk'))->assertOk();

        Livewire::test(PaymentRisk::class)->assertOk();
    }
}
