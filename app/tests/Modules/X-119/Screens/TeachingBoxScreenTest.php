<?php

declare(strict_types=1);

namespace Tests\Modules\X119\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X119\Ui\TeachingBox;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class TeachingBoxScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-119.teaching-box'))->assertOk();

        Livewire::test(TeachingBox::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-119.teaching-box.admin'))->assertOk();

        Livewire::test(TeachingBox::class)->assertOk();
    }

    public function test_a_real_get_renders_the_owner_shell(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-119.teaching-box'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertSee('Teach a fact')
            ->assertDontSee('Internal Platform Console');
    }

    public function test_teaching_writes_a_valid_fact_for_this_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::actingAs($owner)->test(TeachingBox::class)
            ->set('key', 'distinctive.fact_7731')
            ->set('value', 'Distinctive value 7731')
            ->call('teach')
            ->assertHasNoErrors();

        $fact = DB::table('facts')->where('business_id', $biz->id)->where('key', 'distinctive.fact_7731')->first();
        $this->assertNotNull($fact);
        $this->assertEquals('Distinctive value 7731', $fact->value);
        $this->assertEquals(1, $fact->is_valid);
        $this->assertEquals(1, $fact->version);

        $this->get(route('x-119.fact-freshness-per'))->assertSee('distinctive.fact_7731');
    }

    public function test_an_empty_key_is_refused_and_writes_nothing(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        DB::table('facts')->delete(); // Ensure it's clean for the test

        Livewire::actingAs($owner)->test(TeachingBox::class)
            ->set('key', '')
            ->set('value', 'x')
            ->call('teach')
            ->assertHasErrors(['key']);

        $this->assertEquals(0, DB::table('facts')->count());
    }

    public function test_another_tenants_fact_does_not_show_on_this_tenants_freshness(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::actingAs($owner)->test(TeachingBox::class)
            ->set('key', 'distinctive.fact_7731')
            ->set('value', 'Distinctive value 7731')
            ->call('teach')
            ->assertHasNoErrors();

        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);

        Tenancy::setUser((int) $ownerB->id);
        Tenancy::set((int) $bizB->id);

        $this->actingAs($ownerB)->get(route('x-119.fact-freshness-per'))
            ->assertDontSee('distinctive.fact_7731');
    }
}
