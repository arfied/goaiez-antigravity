<?php

declare(strict_types=1);

namespace Tests\Modules\X156\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X156\Ui\RejectedrowsListView;
use Livewire\Livewire;
use Tests\TestCase;

class RejectedrowsListViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

                $this->get(route('x-156.rejectedrows-list'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console');

        \App\Support\Tenancy::setUser($owner->id);
        \App\Modules\X156\Models\IngestRejection::create([
            'business_id' => $biz->id,
            'rejection_reason' => 'Distinctive rejection 4648',
        ]);

        $this->get(route('x-156.rejectedrows-list'))
            ->assertOk()
            ->assertSee('Distinctive rejection 4648');

        Livewire::test(RejectedrowsListView::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-156.rejectedrows-list.admin'))->assertOk();

        Livewire::test(RejectedrowsListView::class)->assertOk();
    }
}
