<?php

declare(strict_types=1);

namespace Tests\Modules\X186;

use App\Modules\X121\Models\Person;
use App\Modules\X186\Actions\CampaignCreateAction;
use App\Modules\X186\Actions\CampaignEnrolAction;
use App\Modules\X186\Actions\CampaignRunAction;
use App\Modules\X186\Actions\SequenceStopAction;
use App\Modules\X186\Events\SendRequested;
use App\Modules\X186\Models\CampaignRun;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CampaignEnrolTest extends TestCase
{
    public function test_a_person_is_enrolled_at_step_one_and_nothing_is_sent_until_the_campaign_runs(): void
    {
        Event::fake([SendRequested::class]);
        $biz = $this->provisionTenant();
        Tenancy::set((int) $biz->id);
        $dana = Person::create(['business_id' => $biz->id, 'first_name' => 'Dana', 'last_name' => 'Whitfield']);
        app(CampaignCreateAction::class)->createCampaign((int) $biz->id, 'spring-tune-up', [
            ['channel' => 'sms', 'template_name' => 'tune_up_sms', 'delay_days' => 1],
            ['channel' => 'email', 'template_name' => 'tune_up_email', 'delay_days' => 3],
        ]);

        $run = app(CampaignEnrolAction::class)->enrol((int) $biz->id, 'spring-tune-up', (int) $dana->id);

        $this->assertSame(1, $run->current_step);
        $this->assertTrue($run->is_active);
        $this->assertFalse($run->is_suppressed);
        Event::assertNotDispatched(SendRequested::class);

        app(CampaignRunAction::class)->runNextStep((int) $biz->id, 'spring-tune-up', (int) $dana->id);

        Event::assertDispatched(SendRequested::class, 1);
        $runs = CampaignRun::where('business_id', $biz->id)->where('person_id', $dana->id)->get();
        $this->assertCount(1, $runs);
        $this->assertSame(2, $runs->first()->current_step);
        Tenancy::forget();
    }

    public function test_a_second_enrolment_of_the_same_person_into_the_same_campaign_is_refused(): void
    {
        $biz = $this->provisionTenant();
        Tenancy::set((int) $biz->id);
        $dana = Person::create(['business_id' => $biz->id, 'first_name' => 'Dana', 'last_name' => 'Whitfield']);
        app(CampaignCreateAction::class)->createCampaign((int) $biz->id, 'spring-tune-up', [
            ['channel' => 'sms', 'template_name' => 'tune_up_sms', 'delay_days' => 1],
        ]);
        app(CampaignCreateAction::class)->createCampaign((int) $biz->id, 'fall-furnace-check', [
            ['channel' => 'sms', 'template_name' => 'furnace_sms', 'delay_days' => 1],
        ]);
        $enrol = app(CampaignEnrolAction::class);

        $enrol->enrol((int) $biz->id, 'spring-tune-up', (int) $dana->id);
        app(SequenceStopAction::class)->stopAllSequencesForPerson((int) $biz->id, (int) $dana->id, 'manual');
        $enrol->enrol((int) $biz->id, 'fall-furnace-check', (int) $dana->id);

        try {
            $enrol->enrol((int) $biz->id, 'spring-tune-up', (int) $dana->id);
            $this->fail('A second enrolment of Dana into spring-tune-up was accepted.');
        } catch (\DomainException $e) {
            $this->assertSame("Person {$dana->id} is already enrolled in campaign spring-tune-up.", $e->getMessage());
        }

        $this->assertSame(1, CampaignRun::where('business_id', $biz->id)->where('campaign_id', 'spring-tune-up')->where('person_id', $dana->id)->count());
        $this->assertSame(1, CampaignRun::where('business_id', $biz->id)->where('campaign_id', 'fall-furnace-check')->where('person_id', $dana->id)->count());
        Tenancy::forget();
    }

    public function test_an_id_that_is_not_a_person_of_this_business_is_refused(): void
    {
        $biz = $this->provisionTenant();
        Tenancy::set((int) $biz->id);
        Person::create(['business_id' => $biz->id, 'first_name' => 'Dana', 'last_name' => 'Whitfield']);
        app(CampaignCreateAction::class)->createCampaign((int) $biz->id, 'spring-tune-up', [
            ['channel' => 'sms', 'template_name' => 'tune_up_sms', 'delay_days' => 1],
        ]);
        $notAPerson = PHP_INT_MAX;

        try {
            app(CampaignEnrolAction::class)->enrol((int) $biz->id, 'spring-tune-up', $notAPerson);
            $this->fail('An id that is not a person of this business was enrolled.');
        } catch (\DomainException $e) {
            $this->assertSame("Person {$notAPerson} is not a person of this business.", $e->getMessage());
        }

        $this->assertSame(0, CampaignRun::where('business_id', $biz->id)->count());
        Tenancy::forget();
    }

    public function test_a_campaign_with_no_steps_is_refused(): void
    {
        $biz = $this->provisionTenant();
        Tenancy::set((int) $biz->id);
        $dana = Person::create(['business_id' => $biz->id, 'first_name' => 'Dana', 'last_name' => 'Whitfield']);

        try {
            app(CampaignEnrolAction::class)->enrol((int) $biz->id, 'never-created', (int) $dana->id);
            $this->fail('A campaign with no steps took an enrolment.');
        } catch (\DomainException $e) {
            $this->assertSame('Campaign never-created has no steps, so nobody can be enrolled in it.', $e->getMessage());
        }

        $this->assertSame(0, CampaignRun::where('business_id', $biz->id)->count());
        Tenancy::forget();
    }
}
