<?php

declare(strict_types=1);

namespace Tests\Modules\X217\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X217\Ui\RecruitPipeline;
use Livewire\Livewire;
use Tests\TestCase;

class RecruitPipelineScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-217.recruit-pipeline'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console');

        Livewire::test(RecruitPipeline::class)->assertOk();
    }

    public function test_can_recruit_prospect(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(RecruitPipeline::class)
            ->set('partnerName', 'Acme Corp')
            ->set('email', 'partner@acme.com')
            ->call('recruitProspect')
            ->assertSet('error', null)
            ->assertSet('success', 'Recorded prospect Acme Corp and pitched. This feeds the recruitment pipeline lists; nothing downstream is wired to it yet.');

        $this->assertDatabaseHas('affiliate_prospects', [
            'business_id' => $biz->id,
            'partner_name' => 'Acme Corp',
            'email' => 'partner@acme.com',
            'stage' => 'pitched',
        ]);

        $this->get(route('x-217.recruit-pipeline'))
            ->assertOk()
            ->assertSee('Acme Corp')
            ->assertSee('partner@acme.com')
            ->assertSee('pitched')
            ->assertDontSee('No prospects found in the recruitment pipeline.');
    }

    public function test_refuses_empty_input(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(RecruitPipeline::class)
            ->set('partnerName', '')
            ->set('email', '')
            ->call('recruitProspect')
            ->assertSet('error', 'Partner name and email are required.');

        $this->assertDatabaseMissing('affiliate_prospects', [
            'business_id' => $biz->id,
        ]);
    }
}
