<?php

declare(strict_types=1);

namespace Tests\Modules\X124;

use App\Modules\X124\Models\AssistantRecommendation;
use App\Modules\X124\Models\AssistantSession;
use App\Modules\X124\Ui\TodaysRecommendationStrip;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class TodaysRecommendationStripTest extends TestCase
{
    use DatabaseTransactions;

    public function test_todays_recommendation_strip(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Strip Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        $sess = AssistantSession::create(['business_id' => $biz->id, 'session_token' => 'tok1']);
        $sess2 = AssistantSession::create(['business_id' => $biz->id, 'session_token' => 'tok2']);

        $rec = AssistantRecommendation::create([
            'business_id' => $biz->id,
            'session_id' => $sess->id,
            'title' => '14 missed calls, no text-back template — turn it on?',
            'action_key' => 'enable_text_back',
            'status' => 'active',
        ]);

        Livewire::test(TodaysRecommendationStrip::class, ['businessId' => $biz->id])
            ->assertSee('14 missed calls')
            ->call('preview', $rec->id)
            ->assertSet('previewingId', $rec->id)
            ->call('execute', $rec->id);

        $this->assertEquals('executed', $rec->fresh()->status);

        $rec2 = AssistantRecommendation::create([
            'business_id' => $biz->id,
            'session_id' => $sess2->id,
            'title' => 'Another rec',
            'action_key' => 'another_action',
            'status' => 'active',
        ]);

        Livewire::test(TodaysRecommendationStrip::class, ['businessId' => $biz->id])
            ->assertSee('Another rec')
            ->call('dismiss', $rec2->id);

        $this->assertEquals('dismissed', $rec2->fresh()->status);

        // empty state
        Livewire::test(TodaysRecommendationStrip::class, ['businessId' => $biz->id])
            ->assertSee('Nothing on your desk right now');
    }
}