<?php

declare(strict_types=1);

namespace Tests\Modules\X124\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X124\Ui\PreviewCard;
use Livewire\Livewire;
use Tests\TestCase;

class PreviewCardScreenTest extends TestCase
{
    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-124.preview-card.admin'))->assertOk();

        Livewire::test(PreviewCard::class)->assertOk();
    }

    public function test_screen_renders_for_tenant(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::Owner]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-124.preview-card'))
            ->assertOk()
            ->assertDontSee('this screen is planned in');

        Livewire::actingAs($user)
            ->test(PreviewCard::class, ['businessId' => $biz->id])
            ->call('load')
            ->assertSee('Will execute');
    }
}
