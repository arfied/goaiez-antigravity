<?php

declare(strict_types=1);

namespace Tests\Modules\X193\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X193\Models\NotificationClass;
use App\Modules\X193\Ui\Sendsbyclass;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class SendsbyclassScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-193.sendsbyclass'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No notification classes yet.');

        Tenancy::setUser($owner->id);
        NotificationClass::create([
            'business_id' => $biz->id,
            'caller_type' => 'Distinctive missed_call 4609',
            'classification' => 'transactional',
            'respects_quiet_hours' => false,
        ]);
        Tenancy::forget();

        $this->get(route('x-193.sendsbyclass'))
            ->assertOk()
            ->assertSee('Distinctive missed_call 4609')
            ->assertSee('transactional')
            ->assertSee('sends any time')
            ->assertDontSee('No notification classes yet.');

        Livewire::test(Sendsbyclass::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-193.sendsbyclass.admin'))->assertOk();

        Livewire::test(Sendsbyclass::class)->assertOk();
    }

    public function test_classify_control(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        // Control: Classify failure (Empty)
        Livewire::test(Sendsbyclass::class)
            ->set('callerType', '')
            ->call('classify')
            ->assertSet('error', 'Caller type is required.');

        // Control: Classify success (Operational)
        Livewire::test(Sendsbyclass::class)
            ->set('callerType', 'missed_call_reminder')
            ->call('classify')
            ->assertSet('callerType', '')
            ->assertSet('error', null)
            ->assertSee('Derived classification: operational');

        $this->assertDatabaseHas((new NotificationClass)->getTable(), [
            'business_id' => $biz->id,
            'caller_type' => 'missed_call_reminder',
            'classification' => 'operational',
        ]);

        // Control: Classify success (Account)
        Livewire::test(Sendsbyclass::class)
            ->set('callerType', 'dunning_notice')
            ->call('classify')
            ->assertSet('callerType', '')
            ->assertSet('error', null)
            ->assertSee('Derived classification: account');

        $this->assertDatabaseHas((new NotificationClass)->getTable(), [
            'business_id' => $biz->id,
            'caller_type' => 'dunning_notice',
            'classification' => 'account',
        ]);

        // Fan-out assertions
        $this->get(route('x-193.sendsbyclass'))
            ->assertOk()
            ->assertSee('missed_call_reminder')
            ->assertSee('operational')
            ->assertSee('dunning_notice')
            ->assertSeeHtml('<span class="text-ink-2">account</span>')
            ->assertDontSee('No notification classes yet.');
    }
}
