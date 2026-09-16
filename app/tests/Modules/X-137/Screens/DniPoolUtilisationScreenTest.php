<?php

declare(strict_types=1);

namespace Tests\Modules\X137\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X137\Models\CallToken;
use App\Modules\X137\Ui\DniPoolUtilisation;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class DniPoolUtilisationScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-137.dni-pool-utilisation'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No pool numbers yet.')
            ->assertSee('0 numbers in the pool · 0 allocated · 0% in use')
            ->assertSee('Fallback: none set');

        DB::statement("SET app.business_id = '{$biz->id}'");
        DB::table('dni_pool_numbers')->insert([
            ['business_id' => $biz->id, 'phone_number' => '+15125554521'],
            ['business_id' => $biz->id, 'phone_number' => '+15125554522']
        ]);
        DB::table('dni_pool_settings')->insert([
            'business_id' => $biz->id, 'fallback_number' => '+15125554520'
        ]);
        
        CallToken::create([
            'business_id' => $biz->id,
            'visitor_session_token' => 'visitor-token-1',
            'allocated_number' => '+15125554521',
            'campaign_source' => 'distinctive_campaign_4540',
            'whisper_text' => 'Call from distinctive_campaign_4540',
            'expires_at' => now()->addMinutes(30),
            'status' => 'active',
        ]);
        
        DB::statement("RESET app.business_id");

        $this->get(route('x-137.dni-pool-utilisation'))
            ->assertOk()
            ->assertSee('+15125554521')
            ->assertSee('+15125554522')
            ->assertSee('2 numbers in the pool · 1 allocated · 50% in use')
            ->assertSee('Fallback: +15125554520')
            ->assertDontSee('No pool numbers yet.');

        Livewire::test(DniPoolUtilisation::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-137.dni-pool-utilisation.admin'))->assertOk();

        Livewire::test(DniPoolUtilisation::class)->assertOk();
    }
}
