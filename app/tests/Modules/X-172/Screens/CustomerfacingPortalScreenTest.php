<?php

declare(strict_types=1);

namespace Tests\Modules\X172\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X172\Ui\CustomerfacingPortal;
use Livewire\Livewire;
use Tests\TestCase;

require_once __DIR__.'/Fixtures.php';

class CustomerfacingPortalScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        $token = Fixtures::token($biz);

        $this->get(route('x-172.customerfacing-portal', ['token' => $token]))->assertOk();

        Livewire::test(CustomerfacingPortal::class, ['token' => $token])->assertOk();
    }

    public function test_screen_refuses_an_unknown_token(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-172.customerfacing-portal', ['token' => 'distinctive-no-such-token-4621']))->assertNotFound();
    }

    public function test_a_failed_action_shows_the_error_panel_instead_of_crashing(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        $token = Fixtures::token($biz);

        Livewire::test(CustomerfacingPortal::class, ['token' => $token])
            ->set('errorMessage', 'Distinctive failure 4845')
            ->assertSee("We couldn't record that")
            ->assertSee('Distinctive failure 4845');
    }
    public function test_the_portal_token_cannot_be_overwritten_from_the_browser(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        $token = Fixtures::token($biz);

        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
        Livewire::test(CustomerfacingPortal::class, ['token' => $token])->set('token', 'forged-4851');
    }
}
