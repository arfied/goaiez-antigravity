<?php

declare(strict_types=1);

namespace Tests\Modules\X186\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X186\Actions\CampaignCreateAction;
use App\Modules\X186\Ui\SequenceBuilder;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class SequenceBuilderScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-186.sequence-builder'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('No sequence yet')
            ->assertDontSee('this screen is planned in');

        Tenancy::set((int) $biz->id);
        app(CampaignCreateAction::class)->createCampaign((int) $biz->id, 'spring-tune-up', [
            ['channel' => 'sms', 'template_name' => 'tune_up_sms', 'delay_days' => 1],
            ['channel' => 'email', 'template_name' => 'tune_up_email', 'delay_days' => 3],
        ]);
        Tenancy::forget();

        $this->get(route('x-186.sequence-builder'))
            ->assertOk()
            ->assertSee('spring-tune-up')
            ->assertSee('2 steps')
            ->assertDontSee('No sequence yet');

        Livewire::actingAs($owner)
            ->test(SequenceBuilder::class)
            ->set('newCampaignId', 'fall-check')
            ->set('newTemplateName', 'fall_sms')
            ->call('compose');

        $this->assertDatabaseHas('campaign_steps', [
            'business_id' => $biz->id,
            'campaign_id' => 'fall-check',
        ]);
    }
}
