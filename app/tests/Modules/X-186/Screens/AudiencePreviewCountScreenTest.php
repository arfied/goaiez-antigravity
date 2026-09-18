<?php

declare(strict_types=1);

namespace Tests\Modules\X186\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X121\Models\Person;
use App\Modules\X186\Actions\CampaignCreateAction;
use App\Modules\X186\Actions\CampaignEnrolAction;
use App\Modules\X186\Actions\SequenceStopAction;
use App\Modules\X186\Ui\AudiencePreviewCount;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class AudiencePreviewCountScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-186.audience-preview-count'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('No one is in a campaign yet')
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

        $this->get(route('x-186.audience-preview-count'))
            ->assertOk()
            ->assertSee('1 people are in a running campaign')
            ->assertDontSee('No one is in a campaign yet');

        Livewire::test(AudiencePreviewCount::class)->assertOk();
    }
}
