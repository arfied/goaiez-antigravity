<?php

declare(strict_types=1);

namespace Tests\Modules\X207\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X207\Models\PushPrompt;
use App\Modules\X207\Ui\OneConfirmonceToggle;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class OneConfirmonceToggleScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-207.one-confirmonce-toggle'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No push prompts configured.');

        Tenancy::setUser($owner->id);
        PushPrompt::create([
            'business_id' => $biz->id,
            'prompt_title' => 'Distinctive Prompt 4497',
            'prompt_body' => 'Distinctive body 4497',
            'is_active' => true,
        ]);
        Tenancy::forget();

        $this->get(route('x-207.one-confirmonce-toggle'))
            ->assertOk()
            ->assertSee('Distinctive Prompt 4497')
            ->assertSee('[active]')
            ->assertDontSee('No push prompts configured.');

        Livewire::test(OneConfirmonceToggle::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-207.one-confirmonce-toggle.admin'))->assertOk();

        Livewire::test(OneConfirmonceToggle::class)->assertOk();
    }
}
