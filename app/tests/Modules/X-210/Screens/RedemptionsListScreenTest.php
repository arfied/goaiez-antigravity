<?php

declare(strict_types=1);

namespace Tests\Modules\X210\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X210\Ui\RedemptionsList;
use Livewire\Livewire;
use Tests\TestCase;

class RedemptionsListScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-210.redemptions'))->assertOk();

        Livewire::test(RedemptionsList::class)->assertOk();
    }
}
