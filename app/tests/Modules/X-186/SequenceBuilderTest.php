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

        Livewire::test(SequenceBuilder::class, ['businessId' => $biz->id])
            ->assertSee('LOCAL-CAMPAIGN')
            ->assertDontSee('FOREIGN-CAMPAIGN')
            ->call('duplicate', 'LOCAL-CAMPAIGN')
            ->assertSee('LOCAL-CAMPAIGN-copy')
            ->set('newCampaignId', 'NEW-COMPOSED')
            ->set('newChannel', 'email')
            ->set('newTemplateName', 'new-template')
            ->call('compose')
            ->assertSee('NEW-COMPOSED');

        $this->assertTrue(CampaignStep::where('business_id', $biz->id)->where('campaign_id', 'LOCAL-CAMPAIGN-copy')->exists());
        $this->assertTrue(CampaignStep::where('business_id', $biz->id)->where('campaign_id', 'NEW-COMPOSED')->exists());
    }
}
