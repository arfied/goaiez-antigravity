<?php

declare(strict_types=1);

namespace Tests\Modules\X08\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X08\Ui\RiskListView;
use Livewire\Livewire;
use Tests\TestCase;

class RiskListViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-08.risk-list'))->assertOk();

        Livewire::test(RiskListView::class)->assertOk();
    }
}
