<?php

declare(strict_types=1);

namespace Tests\Modules\X209\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X121\Models\Person;
use App\Modules\X209\Models\FixerCommand;
use App\Modules\X209\Ui\PrivateInbox;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class PrivateInboxScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-209.private-inbox'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No commands yet.');

        Tenancy::setUser($owner->id);
        FixerCommand::create([
            'business_id' => $biz->id,
            'staff_person_id' => 4631,
            'raw_command' => 'running 20 late to the distinctive job',
            'parsed_intent' => 'job.eta_updated',
            'eta_minutes_delayed' => 20,
            'status' => 'executed',
        ]);
        Tenancy::forget();

        $this->get(route('x-209.private-inbox'))
            ->assertOk()
            ->assertSee('running 20 late to the distinctive job')
            ->assertSee('job.eta_updated')
            ->assertDontSee('No commands yet.');

        Livewire::test(PrivateInbox::class)->assertOk();
    }

    public function test_control_writes_and_clears_empty_states(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::setUser($owner->id);

        $person = Person::create(['business_id' => $biz->id, 'first_name' => 'John', 'last_name' => 'Doe']);

        Livewire::test(PrivateInbox::class)
            ->set('staffPersonId', $person->id)
            ->set('smsBody', 'running 20 late')
            ->call('process')
            ->assertSet('error', null)
            ->assertSet('success', 'Processed SMS command from staff. Parsed delay: 20 minutes. This feeds the inbox; nothing downstream is wired to it yet.');

        $this->assertDatabaseHas('fixer_commands', [
            'business_id' => $biz->id,
            'staff_person_id' => $person->id,
            'raw_command' => 'running 20 late',
            'eta_minutes_delayed' => 20,
        ]);

        $this->get(route('x-209.private-inbox'))
            ->assertDontSee('No commands yet.')
            ->assertSee('20 min');
    }

    public function test_control_refuses_invalid_input(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::setUser($owner->id);

        Livewire::test(PrivateInbox::class)
            ->set('staffPersonId', 0)
            ->call('process')
            ->assertSet('error', 'Staff Person ID is required.');

        Livewire::test(PrivateInbox::class)
            ->set('staffPersonId', 99)
            ->set('smsBody', '')
            ->call('process')
            ->assertSet('error', 'SMS Body cannot be empty.');

        $this->assertDatabaseMissing('fixer_commands', [
            'business_id' => $biz->id,
            'staff_person_id' => 99,
        ]);
    }
}
