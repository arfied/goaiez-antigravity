<?php

declare(strict_types=1);

namespace Tests\Modules\X191\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X191\Models\LinkPlacement;
use App\Modules\X191\Ui\LinksEarned;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class LinksEarnedScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-191.links-earned'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No earned links yet.');

        Tenancy::setUser($owner->id);

        LinkPlacement::create([
            'business_id' => $biz->id,
            'pitch_id' => null,
            'placed_url' => 'https://distinctive-4607.example/resources',
            'anchor_text' => 'Distinctive anchor 4607',
            'is_active' => true,
        ]);

        Tenancy::forget();

        $this->get(route('x-191.links-earned'))
            ->assertOk()
            ->assertSee('Distinctive anchor 4607')
            ->assertSee('live')
            ->assertDontSee('No earned links yet.');

        Livewire::test(LinksEarned::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-191.links-earned.admin'))->assertOk();

        Livewire::test(LinksEarned::class)->assertOk();
    }

    public function test_control_writes_and_clears_empty_states(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::setUser($owner->id);

        $table = (new LinkPlacement)->getTable();

        Livewire::test(LinksEarned::class)
            ->set('placedUrl', 'https://example.com/placed')
            ->set('anchorText', 'example')
            ->call('recordPlacement')
            ->assertSet('placedUrl', '')
            ->assertSee('Recorded placement on');

        $this->assertDatabaseHas($table, [
            'business_id' => $biz->id,
            'placed_url' => 'https://example.com/placed',
        ]);

        $this->get(route('x-191.links-earned'))
            ->assertSee('https://example.com/placed')
            ->assertDontSee('No earned links yet.');

        $this->get(route('x-191.pitchacquire-ratio'))
            ->assertDontSee('No outreach yet.');
    }

    public function test_control_refuses_empty_input(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::setUser($owner->id);

        $table = (new LinkPlacement)->getTable();

        Livewire::test(LinksEarned::class)
            ->set('placedUrl', '')
            ->set('anchorText', 'example')
            ->call('recordPlacement')
            ->assertSet('error', 'Placed URL is required.');

        $this->assertDatabaseMissing($table, [
            'anchor_text' => 'example',
        ]);
    }
}
