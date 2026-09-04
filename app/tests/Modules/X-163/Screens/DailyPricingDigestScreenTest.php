<?php

namespace Tests\Modules\X163\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class DailyPricingDigestScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-163.daily-pricing-digest'))->assertOk();

        Livewire::test(\App\Modules\X163\Ui\DailyPricingDigest::class)->assertOk();
    }
}
