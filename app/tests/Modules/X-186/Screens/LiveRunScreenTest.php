<?php

declare(strict_types=1);

namespace Tests\Modules\X186\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X121\Models\Person;
use App\Modules\X186\Actions\CampaignCreateAction;
use App\Modules\X186\Actions\CampaignEnrolAction;
use App\Modules\X186\Actions\SequenceStopAction;
use App\Modules\X186\Events\CampaignReplied;
use App\Modules\X186\Events\SequenceStopped;
use App\Modules\X186\Models\CampaignRun;
use App\Modules\X186\Ui\LiveRun;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class LiveRunScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-186.live-run'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('No campaign is running for anyone yet')
            ->assertDontSee('this screen is planned in');

        Tenancy::set((int) $biz->id);
        $dana = Person::create(['business_id' => $biz->id, 'first_name' => 'Dana', 'last_name' => 'Whitfield']);
        $marcus = Person::create(['business_id' => $biz->id, 'first_name' => 'Marcus', 'last_name' => 'Reyes']);
        app(CampaignCreateAction::class)->createCampaign((int) $biz->id, 'spring-tune-up', [
            ['channel' => 'sms', 'template_name' => 'tune_up_sms', 'delay_days' => 1],
            ['channel' => 'email', 'template_name' => 'tune_up_email', 'delay_days' => 3],
        ]);
        app(CampaignEnrolAction::class)->enrol((int) $biz->id, 'spring-tune-up', (int) $dana->id);
        app(CampaignEnrolAction::class)->enrol((int) $biz->id, 'spring-tune-up', (int) $marcus->id);
        app(SequenceStopAction::class)->stopAllSequencesForPerson((int) $biz->id, (int) $dana->id, 'sms');
        Tenancy::forget();

        $this->get(route('x-186.live-run'))
            ->assertOk()
            ->assertSee('Marcus Reyes')
            ->assertSee('spring-tune-up')
            ->assertSee('Step 1')
            ->assertSee('Enrolled at step 1')
            ->assertDontSee('Dana Whitfield')
            ->assertDontSee('No campaign is running for anyone yet');

        Livewire::test(LiveRun::class)->assertOk();
    }

    public function test_the_owner_stops_every_campaign_for_one_person_and_it_moves_to_the_stopped_list(): void
    {
        // Fixture taken from test_screen_renders_for_tenant in this file.
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);
        $dana = Person::create(['business_id' => $biz->id, 'first_name' => 'Dana', 'last_name' => 'Whitfield']);
        $marcus = Person::create(['business_id' => $biz->id, 'first_name' => 'Marcus', 'last_name' => 'Reyes']);
        app(CampaignCreateAction::class)->createCampaign((int) $biz->id, 'spring-tune-up', [
            ['channel' => 'sms', 'template_name' => 'tune_up_sms', 'delay_days' => 1],
        ]);
        app(CampaignCreateAction::class)->createCampaign((int) $biz->id, 'fall-furnace-check', [
            ['channel' => 'sms', 'template_name' => 'furnace_sms', 'delay_days' => 1],
        ]);
        app(CampaignEnrolAction::class)->enrol((int) $biz->id, 'spring-tune-up', (int) $dana->id);
        app(CampaignEnrolAction::class)->enrol((int) $biz->id, 'fall-furnace-check', (int) $dana->id);
        app(CampaignEnrolAction::class)->enrol((int) $biz->id, 'spring-tune-up', (int) $marcus->id);
        Event::fake([CampaignReplied::class, SequenceStopped::class]);

        Livewire::test(LiveRun::class)
            ->assertSee('Stop every campaign for this person')
            ->call('stop', (int) $dana->id)
            ->assertSee('Stopped 2 campaigns — listed under Campaigns stopped or paused');

        $this->assertSame(0, CampaignRun::where('business_id', $biz->id)->where('person_id', $dana->id)->where('is_active', true)->count());
        $this->assertSame(2, CampaignRun::where('business_id', $biz->id)->where('person_id', $dana->id)->where('stopped_reason', 'You stopped this by hand')->count());
        $this->assertSame(1, CampaignRun::where('business_id', $biz->id)->where('person_id', $marcus->id)->where('is_active', true)->count());
        // A stop by hand is not a reply from the customer.
        Event::assertNotDispatched(CampaignReplied::class);
        Event::assertDispatched(SequenceStopped::class, fn (SequenceStopped $e): bool => $e->personId === (int) $dana->id && $e->reason === 'Stopped by hand');

        // Stopping again finds nothing running.
        Livewire::test(LiveRun::class)
            ->call('stop', (int) $dana->id)
            ->assertSee('Nothing was running for them any more');
        Tenancy::forget();

        $this->get(route('x-186.live-run'))->assertOk()->assertSee('Marcus Reyes')->assertDontSee('Dana Whitfield');
        $this->get(route('x-186.stop-log'))->assertOk()->assertSee('Dana Whitfield')->assertSee('You stopped this by hand');
    }

    public function test_a_stop_cannot_reach_another_business(): void
    {
        // Fixture taken from test_screen_renders_for_tenant in this file; the second business set with Tenancy::set (N261).
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $bizA = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $bizB = $this->provisionTenant();
        Tenancy::set((int) $bizB->id);
        $other = Person::create(['business_id' => $bizB->id, 'first_name' => 'Other', 'last_name' => 'Tenant']);
        app(CampaignCreateAction::class)->createCampaign((int) $bizB->id, 'b-campaign', [
            ['channel' => 'sms', 'template_name' => 'b_sms', 'delay_days' => 1],
        ]);
        app(CampaignEnrolAction::class)->enrol((int) $bizB->id, 'b-campaign', (int) $other->id);

        $this->actingAs($owner);
        Tenancy::set((int) $bizA->id);
        Livewire::test(LiveRun::class)
            ->call('stop', (int) $other->id)
            ->assertSee('Nothing was running for them any more');

        Tenancy::set((int) $bizB->id);
        $this->assertSame(1, CampaignRun::where('business_id', $bizB->id)->where('person_id', $other->id)->where('is_active', true)->count());
    }
}
