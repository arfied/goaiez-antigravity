<?php

declare(strict_types=1);

namespace Tests\Modules\X163\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class DailyPricingDigestScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-163.daily-pricing-digest'))->assertOk();

        Livewire::test(\App\Modules\X163\Ui\DailyPricingDigest::class)->assertOk();
    }
}
