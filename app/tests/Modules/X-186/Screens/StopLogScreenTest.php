<?php

declare(strict_types=1);

namespace Tests\Modules\X186\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X121\Models\Person;
use App\Modules\X186\Actions\CampaignCreateAction;
use App\Modules\X186\Actions\CampaignRunAction;
use App\Modules\X186\Actions\SequenceStopAction;
use App\Modules\X186\Ui\StopLog;
use App\Modules\X204\Domain\ConsentService;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class StopLogScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-186.stop-log'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('No campaign has stopped or paused yet')
            ->assertDontSee('this screen is planned in');

        Tenancy::set((int) $biz->id);
        $dana = Person::create(['business_id' => $biz->id, 'first_name' => 'Dana', 'last_name' => 'Whitfield']);
        $marcus = Person::create(['business_id' => $biz->id, 'first_name' => 'Marcus', 'last_name' => 'Reyes']);
        app(CampaignCreateAction::class)->createCampaign((int) $biz->id, 'spring-tune-up', [
            ['channel' => 'sms', 'template_name' => 'tune_up_sms', 'delay_days' => 1],
            ['channel' => 'email', 'template_name' => 'tune_up_email', 'delay_days' => 3],
        ]);
        app(CampaignCreateAction::class)->createCampaign((int) $biz->id, 'fall-furnace-check', [
            ['channel' => 'sms', 'template_name' => 'furnace_sms', 'delay_days' => 1],
        ]);
        app(CampaignRunAction::class)->runNextStep((int) $biz->id, 'spring-tune-up', (int) $dana->id);
        app(SequenceStopAction::class)->stopAllSequencesForPerson((int) $biz->id, (int) $dana->id, 'sms');
        app(CampaignRunAction::class)->runNextStep((int) $biz->id, 'fall-furnace-check', (int) $dana->id);
        app(CampaignRunAction::class)->runNextStep((int) $biz->id, 'spring-tune-up', (int) $marcus->id, hasOpenRecover: true);
        Tenancy::forget();

        $this->get(route('x-186.stop-log'))
            ->assertOk()
            ->assertSee('Dana Whitfield')
            ->assertSee('spring-tune-up')
            ->assertSee('Stopped by inbound customer reply on channel: sms')
            ->assertDontSee('fall-furnace-check')
            ->assertSee('Marcus Reyes')
            ->assertSee('Suppressed by active RECOVER intent in progress')
            ->assertDontSee('(P-205)')
            ->assertDontSee('No campaign has stopped or paused yet');

        Tenancy::set((int) $biz->id);
        Livewire::test(StopLog::class)
            ->call('stopRemaining', (int) $dana->id)
            ->assertSee('Stopped 1 remaining sequence');
        Tenancy::forget();

        $this->get(route('x-186.stop-log'))
            ->assertOk()
            ->assertSee('fall-furnace-check')
            ->assertSee('You stopped this by hand')
            ->assertDontSee('channel: manual');

        Livewire::test(StopLog::class)->assertOk();
    }

    public function test_suppress_phone_stops_campaign_run_via_event_chain(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::set((int) $biz->id);
        $phone = '+15551239999';
        $person = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'Chain',
            'last_name' => 'Tester',
            'phone' => $phone,
        ]);

        app(CampaignCreateAction::class)->createCampaign((int) $biz->id, 'chain-campaign', [
            ['channel' => 'sms', 'template_name' => 'chain_sms', 'delay_days' => 1],
        ]);
        app(CampaignRunAction::class)->runNextStep((int) $biz->id, 'chain-campaign', (int) $person->id);
        Tenancy::forget();

        $this->get(route('x-186.stop-log'))
            ->assertOk()
            ->assertSee('No campaign has stopped or paused yet');

        Tenancy::set((int) $biz->id);
        app(ConsentService::class)->suppress((int) $biz->id, $phone, 'sms', 'opt_out');
        Tenancy::forget();

        $this->get(route('x-186.stop-log'))
            ->assertOk()
            ->assertDontSee('No campaign has stopped or paused yet')
            ->assertSee('Chain Tester');
    }
}
