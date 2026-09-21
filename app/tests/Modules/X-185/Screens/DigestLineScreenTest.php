<?php

declare(strict_types=1);

namespace Tests\Modules\X185\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X185\Actions\CampaignCreateAction;
use App\Modules\X185\Models\Sequence;
use App\Modules\X185\Models\SequenceStep;
use App\Modules\X185\Ui\DigestLine;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Tests\TestCase;

class DigestLineScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-185.digest-line'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('No sequences yet')
            ->assertDontSee('this screen is planned in');

        Tenancy::set((int) $biz->id);
        app(CampaignCreateAction::class)->createSequence(
            businessId: (int) $biz->id,
            name: 'Spring tune-up reminders',
        );
        Tenancy::forget();

        $this->get(route('x-185.digest-line'))
            ->assertOk()
            ->assertSee('Spring tune-up reminders · running')
            ->assertDontSee('No sequences yet');

        Livewire::test(DigestLine::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-185.digest-line.admin'))->assertOk();

        Livewire::test(DigestLine::class)->assertOk();
    }

    public function test_can_set_up_a_sequence(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::set((int) $biz->id);

        Livewire::test(DigestLine::class)
            ->set('sequenceName', 'Winter tune-up push')
            ->set('firstStepChannel', 'sms')
            ->set('firstStepDelayHours', '48')
            ->call('createSequence')
            ->assertSet('success', 'Sequence "Winter tune-up push" is set up and running, with a first step on sms after 48 hours. The list below shows the sequence; the step is stored but no screen shows steps yet.')
            ->assertSet('error', null)
            ->assertSet('sequenceName', '')
            ->assertSet('firstStepChannel', '')
            ->assertSet('firstStepDelayHours', '');

        $this->assertDatabaseHas((new Sequence)->getTable(), [
            'name' => 'Winter tune-up push',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas((new SequenceStep)->getTable(), [
            'step_number' => 1,
            'channel' => 'sms',
            'delay_hours' => 48,
        ]);
    }

    public function test_refuses_a_non_numeric_delay(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::set((int) $biz->id);

        Livewire::test(DigestLine::class)
            ->set('sequenceName', 'Winter tune-up push')
            ->set('firstStepChannel', 'sms')
            ->set('firstStepDelayHours', 'soon')
            ->call('createSequence')
            ->assertSet('error', 'Enter the delay in hours as a number.');

        $this->assertDatabaseMissing((new Sequence)->getTable(), [
            'name' => 'Winter tune-up push',
        ]);

        $this->assertDatabaseMissing((new SequenceStep)->getTable(), [
            'channel' => 'sms',
        ]);
    }

    public function test_a_zero_hour_delay_is_allowed(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::set((int) $biz->id);

        Livewire::test(DigestLine::class)
            ->set('sequenceName', 'Winter tune-up push')
            ->set('firstStepChannel', 'sms')
            ->set('firstStepDelayHours', '0')
            ->call('createSequence')
            ->assertSet('error', null);

        $this->assertDatabaseHas((new SequenceStep)->getTable(), [
            'delay_hours' => 0,
        ]);
    }

    public function test_digest_line_lists_the_new_sequence(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::set((int) $biz->id);

        Livewire::test(DigestLine::class)
            ->set('sequenceName', 'Winter tune-up push')
            ->set('firstStepChannel', 'sms')
            ->set('firstStepDelayHours', '48')
            ->call('createSequence');

        Tenancy::forget();

        $this->get(route('x-185.digest-line'))
            ->assertOk()
            ->assertSee('Winter tune-up push')
            ->assertSee('running')
            ->assertDontSee('No sequences yet');
    }

    public function test_can_stop_a_sequence(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::set((int) $biz->id);

        Livewire::test(DigestLine::class)
            ->set('sequenceName', 'Winter tune-up push')
            ->set('firstStepChannel', 'sms')
            ->set('firstStepDelayHours', '48')
            ->call('createSequence');

        $seq = Sequence::where('name', 'Winter tune-up push')->firstOrFail();
        $id = $seq->id;

        Livewire::test(DigestLine::class)
            ->call('stopSequence', $id)
            ->assertSet('success', 'Sequence "Winter tune-up push" is stopped. Nothing else reacts to a stop yet.');

        $this->assertDatabaseHas((new Sequence)->getTable(), ['id' => $id, 'is_active' => false]);
    }

    public function test_digest_line_shows_a_stopped_sequence(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::set((int) $biz->id);

        Livewire::test(DigestLine::class)
            ->set('sequenceName', 'Winter tune-up push')
            ->set('firstStepChannel', 'sms')
            ->set('firstStepDelayHours', '48')
            ->call('createSequence');

        $seq = Sequence::where('name', 'Winter tune-up push')->firstOrFail();
        $id = $seq->id;

        Livewire::test(DigestLine::class)
            ->call('stopSequence', $id);

        Tenancy::forget();

        $this->get(route('x-185.digest-line'))
            ->assertOk()
            ->assertSee('Winter tune-up push · stopped')
            ->assertDontSee('Winter tune-up push · running');
    }

    public function test_stopping_another_tenants_sequence_is_refused(): void
    {
        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);

        $ownerA = User::factory()->create(['role' => UserRole::Owner]);
        $bizA = $this->provisionTenant(['owner_user_id' => $ownerA->id]);

        Tenancy::set((int) $bizB->id);
        Livewire::test(DigestLine::class)
            ->set('sequenceName', 'Tenant B sequence')
            ->set('firstStepChannel', 'sms')
            ->set('firstStepDelayHours', '24')
            ->call('createSequence');

        $seq = Sequence::where('name', 'Tenant B sequence')->firstOrFail();
        $bSequenceId = $seq->id;

        Tenancy::set((int) $bizA->id);

        $this->expectException(ModelNotFoundException::class);
        Livewire::test(DigestLine::class)->call('stopSequence', $bSequenceId);
    }
}
