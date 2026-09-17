<?php

declare(strict_types=1);

namespace Tests\Modules\X119\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X119\Ui\FactFreshnessPer;
use Livewire\Livewire;
use Tests\TestCase;

class FactFreshnessPerScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-119.fact-freshness-per'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('Nothing recorded yet.');

        \App\Support\Tenancy::setUser($owner->id);
        \Illuminate\Support\Facades\DB::table('facts')->insert([
            'business_id' => $biz->id,
            'key' => 'distinctive.fact_4645',
            'value' => 'Distinctive value 4645',
            'is_valid' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \App\Support\Tenancy::forget();

        $this->get(route('x-119.fact-freshness-per'))
            ->assertOk()
            ->assertSee('distinctive.fact_4645')
            ->assertSee('Distinctive value 4645')
            ->assertDontSee('Nothing recorded yet.');

        Livewire::test(FactFreshnessPer::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-119.fact-freshness-per.admin'))->assertOk();

        Livewire::test(FactFreshnessPer::class)->assertOk();
    }
}
