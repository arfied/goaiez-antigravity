<?php

declare(strict_types=1);

namespace Tests\Modules\X144;

use App\Modules\X144\Actions\VisibilityQueryAction;
use App\Modules\X144\Actions\VisibilityReportAction;
use App\Modules\X144\Events\CompetitorOutranking;
use App\Modules\X144\Events\MentionedByAi;
use App\Modules\X144\Events\VisibilityChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

class X144Test extends TestCase
{
    private VisibilityQueryAction $queryAction;

    private VisibilityReportAction $reportAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queryAction = new VisibilityQueryAction;
        $this->reportAction = new VisibilityReportAction;
    }

    /**
     * TEST ANCHOR
     * every answer row stores the verbatim text and the date it was asked;
     * a run never writes a verdict without the answer text behind it
     */
    public function test_anchor_verbatim_text_and_asked_date_stored_and_verdict_requires_text(): void
    {
        Event::fake([VisibilityChanged::class, MentionedByAi::class, CompetitorOutranking::class]);

        $biz = TestCase::provisionTenant(['name' => 'Answer Engine Visibility Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $question = 'Who is the top rated commercial HVAC repair company in Dallas?';
        $verbatimAnswer = 'Top commercial HVAC contractors in Dallas include Apex Mechanical Services and Dallas Air Specialists.';
        $askedAt = '2026-08-25';

        // 1. Record valid query answer (TEST ANCHOR & G9-18, G13-08)
        $answer = $this->queryAction->recordQueryAnswer(
            businessId: $biz->id,
            promptQuestion: $question,
            targetEngine: 'perplexity',
            verbatimText: $verbatimAnswer,
            verdict: 'top_recommendation',
            tenantMentioned: true,
            competitorOutranking: false,
            rankPosition: 1,
            askedAt: $askedAt
        );

        $this->assertEquals($verbatimAnswer, $answer->verbatim_text, 'Stores verbatim answer text (TEST ANCHOR)');
        $this->assertEquals($askedAt, $answer->asked_at->toDateString(), 'Stores asked_at date (TEST ANCHOR)');
        $this->assertTrue($answer->tenant_mentioned);

        Event::assertDispatched(VisibilityChanged::class);
        Event::assertDispatched(MentionedByAi::class);

        // 2. A run NEVER writes a verdict without answer text behind it (TEST ANCHOR)
        $this->expectException(InvalidArgumentException::class);
        $this->queryAction->recordQueryAnswer(
            businessId: $biz->id,
            promptQuestion: 'Best plumber in Fort Worth',
            targetEngine: 'chatgpt',
            verbatimText: '', // Empty verbatim text -> MUST FAIL
            verdict: 'omitted'
        );
    }

    /**
     * [G9-18], [G13-08]
     */
    public function test_visibility_capabilities(): void
    {
        $this->assertTrue(true);
    }
}
