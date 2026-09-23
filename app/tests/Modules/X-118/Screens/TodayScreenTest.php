<?php

declare(strict_types=1);

namespace Tests\Modules\X118\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X118\Actions\OnboardingStartAction;
use App\Modules\X118\Models\OnboardingRun;
use App\Modules\X118\Ui\Today;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Tests\TestCase;

class TodayScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-118.today'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No onboarding runs yet.');

        Tenancy::setUser($owner->id);
        OnboardingRun::create([
            'business_id' => $biz->id,
            'business_name' => 'Distinctive Plumbing 4631',
            'contact_phone' => '+15550104631',
            'provisioned_number' => '+15550204631',
            'status' => 'live',
            'ttfm_ms' => 950,
            'asked_fields_count' => 2,
        ]);
        Tenancy::forget();

        $this->get(route('x-118.today'))
            ->assertOk()
            ->assertSee('Distinctive Plumbing 4631')
            ->assertSee('950')
            ->assertSee('+15550204631')
            ->assertSee('live')
            ->assertDontSee('No onboarding runs yet.');

        Livewire::test(Today::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-118.today.admin'))->assertOk();

        Livewire::test(Today::class)->assertOk();
    }

    public function test_can_confirm_an_onboarding_run(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($owner);

        $res = app(OnboardingStartAction::class)->handle($owner, 'Distinctive Biz 4471', '+15125554471');
        $runId = (int) $res['run_id'];

        Livewire::test(Today::class)->call('confirmRun', $runId)
            ->assertSet('confirmSuccess', 'Distinctive Biz 4471 is confirmed. Nothing reads a confirmed onboarding run yet.');

        $this->assertDatabaseHas((new OnboardingRun)->getTable(), [
            'id' => $runId,
            'status' => 'confirmed',
        ]);
    }

    public function test_the_list_shows_the_run_as_confirmed(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($owner);

        $res = app(OnboardingStartAction::class)->handle($owner, 'Distinctive Biz 4471', '+15125554471');
        $runId = (int) $res['run_id'];

        Livewire::test(Today::class)->call('confirmRun', $runId);

        Tenancy::forget();

        $this->get(route('x-118.today'))
            ->assertOk()
            ->assertSee('Distinctive Biz 4471')
            ->assertSee('confirmed');
    }

    public function test_the_confirm_button_disappears_once_confirmed(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($owner);

        $res = app(OnboardingStartAction::class)->handle($owner, 'Distinctive Biz 4471', '+15125554471');
        $runId = (int) $res['run_id'];

        Livewire::test(Today::class)->call('confirmRun', $runId);

        Tenancy::forget();

        $this->get(route('x-118.today'))
            ->assertOk()
            ->assertDontSee('confirmRun(');
    }

    public function test_confirming_another_tenants_run_is_refused(): void
    {
        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($ownerB);
        $resB = app(OnboardingStartAction::class)->handle($ownerB, 'Biz B 4472', '+15125554472');
        $runB = (int) $resB['run_id'];
        $bizB = (int) $resB['business_id'];

        $ownerA = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($ownerA);
        $resA = app(OnboardingStartAction::class)->handle($ownerA, 'Biz A 4471', '+15125554471');
        $bizA = (int) $resA['business_id'];

        Tenancy::setUser($ownerA->id);
        Tenancy::set($bizA);

        $this->expectException(ModelNotFoundException::class);
        Livewire::test(Today::class)->call('confirmRun', $runB);
    }
}
