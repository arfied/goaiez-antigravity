<?php

declare(strict_types=1);

namespace Tests\Modules\CBilling\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class RevenueRecoveryScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-billing.revenue-recovery'))->assertOk();

        Livewire::test(\App\Modules\CBilling\Ui\RevenueRecovery::class)->assertOk();
    }
}
