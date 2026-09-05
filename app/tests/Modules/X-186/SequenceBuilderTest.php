<?php

declare(strict_types=1);

namespace Tests\Modules\X186;

use App\Modules\X186\Models\CampaignStep;
use App\Modules\X186\Ui\SequenceBuilder;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class SequenceBuilderTest extends TestCase
{
    use DatabaseTransactions;

    public function test_renders_sequence_builder(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Sequence Tenant']);
        $foreignBiz = TestCase::provisionTenant(['name' => 'Foreign Sequence Tenant']);

        Tenancy::set((int) $foreignBiz->id);
        CampaignStep::create([
            'business_id' => $foreignBiz->id,
            'campaign_id' => 'FOREIGN-CAMPAIGN',
            'step_number' => 1,
            'channel' => 'email',
            'template_name' => 'foreign-hello',
            'delay_days' => 1,
        ]);

        Tenancy::set((int) $biz->id);
        CampaignStep::create([
            'business_id' => $biz->id,
            'campaign_id' => 'LOCAL-CAMPAIGN',
            'step_number' => 1,
            'channel' => 'sms',
            'template_name' => 'local-hello',
            'delay_days' => 2,
        ]);

        CampaignStep::create([
            'business_id' => $biz->id,
            'campaign_id' => 'LOCAL-CAMPAIGN',
            'step_number' => 2,
            'channel' => 'email',
            'template_name' => 'local-followup',
            'delay_days' => 3,
        ]);

        Livewire::test(SequenceBuilder::class, ['businessId' => $biz->id])
            ->assertSee('LOCAL-CAMPAIGN')
            ->assertSee('local-hello')
            ->assertSee('local-followup')
            ->assertSee('Step 1')
            ->assertSee('Step 2')
            ->assertDontSee('FOREIGN-CAMPAIGN')
            ->set('newCampaignId', '')
            ->set('newTemplateName', '')
            ->call('compose')
            ->assertSee('A sequence needs a campaign id and a template name.')
            ->call('duplicate', 'LOCAL-CAMPAIGN')
            ->assertSee('LOCAL-CAMPAIGN-copy')
            ->set('newCampaignId', 'NEW-COMPOSED')
            ->set('newChannel', 'email')
            ->set('newTemplateName', 'new-template')
            ->call('compose')
            ->assertSee('NEW-COMPOSED');

        $copiedSteps = CampaignStep::where('business_id', $biz->id)
            ->where('campaign_id', 'LOCAL-CAMPAIGN-copy')
            ->orderBy('step_number')
            ->get();
        
        $this->assertCount(2, $copiedSteps);
        $this->assertEquals(1, $copiedSteps[0]->step_number);
        $this->assertEquals(2, $copiedSteps[1]->step_number);

        $this->assertTrue(CampaignStep::where('business_id', $biz->id)->where('campaign_id', 'NEW-COMPOSED')->exists());
    }
}
