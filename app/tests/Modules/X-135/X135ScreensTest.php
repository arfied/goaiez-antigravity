<?php

declare(strict_types=1);

namespace Tests\Modules\X135;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CAi\Models\AiCall;
use App\Modules\X135\Actions\IcebreakerGenerateAction;
use App\Modules\X135\Actions\ResearchRunAction;
use App\Modules\X135\Events\IcebreakerGenerated;
use App\Modules\X135\Events\ResearchCompleted;
use App\Modules\X135\Events\SignalFound;
use App\Modules\X135\Models\Icebreaker;
use App\Modules\X135\Models\ResearchRun;
use App\Modules\X135\Ui\ResearchDossierPer;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class X135ScreensTest extends TestCase
{
    public int $businessId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->businessId = TestCase::provisionTenant()->id;
        Tenancy::set($this->businessId);
    }

    private function seedRun(): ResearchRun
    {
        Event::fake([ResearchCompleted::class, IcebreakerGenerated::class, SignalFound::class]);

        return (new ResearchRunAction)->runResearch(
            businessId: $this->businessId,
            prospectId: 9401,
            isScored: true,
            discoveredSignals: [
                ['type' => 'competitor_weakness', 'description' => 'Competitor ABC Plumbing has no weekend coverage'],
                ['type' => 'ad_activity', 'description' => 'Running expensive broad-match Google ads'],
            ]
        );
    }

    private function seedIcebreaker(ResearchRun $run): void
    {
        (new IcebreakerGenerateAction)->generateIcebreaker(
            businessId: $this->businessId,
            runId: $run->id,
            prospectId: 9401,
            openerText: 'Saw your North Texas expansion in the Dallas Business Journal',
            sourceUrl: 'https://www.bizjournals.com/dallas/news/2026/08/apex-plumbing-expansion.html',
            observedDate: '2026-08-20'
        );
    }

    public function test_research_dossier_mount_and_empty(): void
    {
        Livewire::test(ResearchDossierPer::class, ['businessId' => $this->businessId])
            ->assertOk()
            ->assertSee('No dossier yet')
            ->assertSee('Research fires only on distress')
            ->assertSee('Research calls this month: 0');
    }

    public function test_research_dossier_sample_state(): void
    {
        Livewire::test(ResearchDossierPer::class, ['businessId' => $this->businessId])
            ->call('toggleSample')
            ->assertSee('Prospect 9401')
            ->assertSee('Prospect 9402')
            ->assertSee('2 signals')
            ->assertSee('Research calls this month: 2')
            ->call('select', 9901)
            ->assertSee('Competitor ABC Plumbing has no weekend coverage')
            ->assertSee('https://www.bizjournals.com/dallas/news/2026/08/apex-plumbing-expansion.html')
            ->assertSee('2026-08-20')
            ->set('opener', 'x')
            ->set('sourceUrl', 'https://example.com/a')
            ->call('ground');

        $this->assertSame(0, ResearchRun::where('business_id', $this->businessId)->count());
        $this->assertSame(0, Icebreaker::where('business_id', $this->businessId)->count());
    }

    public function test_research_dossier_lists_runs(): void
    {
        $this->seedRun();
        Livewire::test(ResearchDossierPer::class, ['businessId' => $this->businessId])
            ->assertSee('Prospect 9401')
            ->assertSee('2 signals')
            ->assertSee('0 icebreakers')
            ->assertSee('Scored')
            ->assertDontSee('No dossier yet');
    }

    public function test_research_dossier_select_shows_signals_and_icebreakers(): void
    {
        $run = $this->seedRun();
        $this->seedIcebreaker($run);
        Livewire::test(ResearchDossierPer::class, ['businessId' => $this->businessId])
            ->call('select', $run->id)
            ->assertSee('Prospect 9401 — dossier')
            ->assertSee('Competitor ABC Plumbing has no weekend coverage')
            ->assertSee('ad activity')
            ->assertSee('Saw your North Texas expansion in the Dallas Business Journal')
            ->assertSee('https://www.bizjournals.com/dallas/news/2026/08/apex-plumbing-expansion.html')
            ->assertSee('seen 2026-08-20')
            ->assertDontSee('Action failed');
    }

    public function test_research_dossier_ground_icebreaker_success(): void
    {
        $run = $this->seedRun();
        Livewire::test(ResearchDossierPer::class, ['businessId' => $this->businessId])
            ->call('select', $run->id)
            ->set('opener', 'We filled in your form on Tuesday and nobody replied')
            ->set('sourceUrl', 'https://example.com/contact')
            ->set('observedDate', '2026-09-01')
            ->call('ground')
            ->assertSee('We filled in your form on Tuesday and nobody replied')
            ->assertSee('seen 2026-09-01')
            ->assertDontSee('Not grounded')
            ->assertDontSee('Action failed');

        $this->assertSame(1, Icebreaker::where('business_id', $this->businessId)->where('run_id', $run->id)->count());
        $this->assertSame('https://example.com/contact', Icebreaker::where('run_id', $run->id)->first()->source_url);
    }

    public function test_research_dossier_ground_refuses_missing_source(): void
    {
        $run = $this->seedRun();
        Livewire::test(ResearchDossierPer::class, ['businessId' => $this->businessId])
            ->call('select', $run->id)
            ->set('opener', 'test')
            ->set('sourceUrl', '')
            ->call('ground')
            ->assertSee('Not grounded')
            ->assertSee('every icebreaker must carry a valid source_url')
            ->assertDontSee('Action failed');

        $this->assertSame(0, Icebreaker::where('business_id', $this->businessId)->count());
    }

    public function test_research_dossier_ground_refuses_bad_url(): void
    {
        $run = $this->seedRun();
        Livewire::test(ResearchDossierPer::class, ['businessId' => $this->businessId])
            ->call('select', $run->id)
            ->set('opener', 'test')
            ->set('sourceUrl', 'not a url')
            ->call('ground')
            ->assertSee('Not grounded')
            ->assertSee('http(s) URL that can be fetched')
            ->assertDontSee('Action failed')
            ->set('sourceUrl', 'ftp://example.com/x')
            ->call('ground')
            ->assertSee('Not grounded')
            ->assertSee('http(s) URL that can be fetched')
            ->assertDontSee('Action failed');

        $this->assertSame(0, Icebreaker::where('business_id', $this->businessId)->count());
    }

    public function test_research_dossier_ground_empty_opener(): void
    {
        $run = $this->seedRun();
        Livewire::test(ResearchDossierPer::class, ['businessId' => $this->businessId])
            ->call('select', $run->id)
            ->set('opener', '')
            ->set('sourceUrl', 'https://example.com/contact')
            ->call('ground')
            ->assertHasErrors('opener')
            ->assertSee('Opener cannot be empty');

        $this->assertSame(0, Icebreaker::where('business_id', $this->businessId)->count());
    }

    public function test_research_dossier_ground_bad_date(): void
    {
        $run = $this->seedRun();
        Livewire::test(ResearchDossierPer::class, ['businessId' => $this->businessId])
            ->call('select', $run->id)
            ->set('opener', 'test')
            ->set('sourceUrl', 'https://example.com/contact')
            ->set('observedDate', 'yesterday')
            ->call('ground')
            ->assertHasErrors('observedDate')
            ->assertSee('A date as YYYY-MM-DD');

        $this->assertSame(0, Icebreaker::where('business_id', $this->businessId)->count());
    }

    public function test_research_dossier_never_starts_research(): void
    {
        $component = Livewire::test(ResearchDossierPer::class, ['businessId' => $this->businessId]);
        $component->assertSee('Research fires only on distress');
        preg_match_all('/wire:click="([A-Za-z]+)/', $component->html(), $clicks);
        $this->assertSame([], array_values(array_diff(array_unique($clicks[1]), ['toggleSample'])), 'the only click on this screen toggles the sample; nothing starts research');
        $component
            ->call('toggleSample')
            ->call('select', 9901)
            ->call('toggleSample');

        $this->assertSame(0, ResearchRun::where('business_id', $this->businessId)->count());
        $this->assertSame(0, DB::table('ai_calls')->where('business_id', $this->businessId)->count());
    }

    public function test_research_dossier_meter(): void
    {
        AiCall::factory()->count(2)->create(['business_id' => $this->businessId, 'task' => 'research', 'cost_hundredths_cents' => 1250]);
        AiCall::factory()->create(['business_id' => $this->businessId, 'task' => 'reply', 'cost_hundredths_cents' => 9999]);

        Livewire::test(ResearchDossierPer::class, ['businessId' => $this->businessId])
            ->assertSee('Research calls this month: 2')
            ->assertSee('$0.25');
    }

    public function test_research_dossier_error_state(): void
    {
        Livewire::test(ResearchDossierPer::class, ['businessId' => $this->businessId])
            ->call('select', 999999)
            ->assertSee('Action failed')
            ->assertDontSee('No query results');
    }

    public function test_research_dossier_get_route(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($biz->id);

        $this->businessId = $biz->id;
        $this->seedRun();

        $this->get(route('x-135.research-dossier-per'))
            ->assertOk()
            ->assertSee('Prospect 9401');
    }
}
