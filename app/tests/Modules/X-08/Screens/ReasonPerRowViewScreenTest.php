<?php

declare(strict_types=1);

namespace Tests\Modules\X08\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X08\Ui\ReasonPerRowView;
use Livewire\Livewire;
use Tests\TestCase;

class ReasonPerRowViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-08.reason-per-row'))
            ->assertOk()
            ->assertSee('Reason Per Row')
            ->assertSee('This screen is reason-per-row');

        Livewire::test(ReasonPerRowView::class)->assertOk();
    }

    public function test_403_for_other_roles(): void
    {
        $tech = User::factory()->create(['role' => UserRole::Staff]);
        $this->actingAs($tech);

        $this->get(route('x-08.reason-per-row'))->assertForbidden();
        Livewire::test(ReasonPerRowView::class)->assertForbidden();
    }
}
