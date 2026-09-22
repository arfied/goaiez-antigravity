<?php

declare(strict_types=1);

namespace App\Modules\X135\Ui;

use App\Modules\CAi\Domain\AiEngine;
use App\Modules\X135\Actions\IcebreakerGenerateAction;
use App\Modules\X135\Models\ResearchRun;
use App\Support\Tenancy;
use Carbon\Carbon;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ResearchDossierPer extends Component
{
    #[Locked]
    public int $businessId = 0;

    public bool $isSample = false;

    public ?int $selectedRunId = null;

    public string $opener = '';

    public string $sourceUrl = '';

    public string $observedDate = '';

    public ?string $refusal = null;

    public bool $actionFailed = false;

    public function mount(): void
    {
        if ($this->businessId === 0) {
            $this->businessId = Tenancy::id() ?: 0;
            if ($this->businessId <= 0) {
                abort(403, 'Tenant context is required');
            }
        }
        Tenancy::set($this->businessId);
    }

    public function toggleSample(): void
    {
        $this->isSample = ! $this->isSample;
        $this->selectedRunId = null;
        $this->refusal = null;
        $this->actionFailed = false;
    }

    public function select(int $runId): void
    {
        $this->refusal = null;
        $this->actionFailed = false;

        if ($this->isSample) {
            $this->selectedRunId = $runId;

            return;
        }

        $exists = ResearchRun::where('business_id', $this->businessId)->whereKey($runId)->exists();
        if (! $exists) {
            $this->actionFailed = true;
            $this->selectedRunId = null;

            return;
        }

        $this->selectedRunId = $runId;
    }

    /**
     * Dispatch IcebreakerGenerateAction::generateIcebreaker.
     * A refusal renders as *Not grounded* and never as a failure.
     */
    public function ground(IcebreakerGenerateAction $action): void
    {
        if ($this->isSample) {
            return;
        }

        $this->refusal = null;
        $this->actionFailed = false;

        if (trim($this->opener) === '') {
            $this->addError('opener', 'Opener cannot be empty');

            return;
        }

        if ($this->observedDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->observedDate) !== 1) {
            $this->addError('observedDate', 'A date as YYYY-MM-DD');

            return;
        }

        $run = $this->selectedRunId !== null
            ? ResearchRun::where('business_id', $this->businessId)->find($this->selectedRunId)
            : null;

        if ($run === null) {
            $this->refusal = 'Pick a dossier first — an icebreaker belongs to one research run';

            return;
        }

        try {
            $action->generateIcebreaker(
                businessId: $this->businessId,
                runId: (int) $run->id,
                prospectId: (int) $run->prospect_id,
                openerText: trim($this->opener),
                sourceUrl: trim($this->sourceUrl),
                observedDate: $this->observedDate !== '' ? $this->observedDate : null,
            );
            $this->opener = '';
            $this->sourceUrl = '';
            $this->observedDate = '';
        } catch (InvalidArgumentException $e) {
            $this->refusal = $e->getMessage();
        } catch (\Exception $e) {
            $this->actionFailed = true;
        }
    }

    /**
     * Research spend this calendar month — ai_calls rows tagged research (the TEST ANCHOR's meter).
     *
     * @return array{count: int, dollars: string}
     */
    public function meter(): array
    {
        $meter = app(AiEngine::class)->getResearchMeter($this->businessId);

        return [
            'count' => $meter['count'],
            'dollars' => number_format($meter['cost_hundredths_cents'] / 10000, 2),
        ];
    }

    public function render()
    {
        if ($this->isSample) {
            $runs = collect([
                (object) ['id' => 9901, 'prospect_id' => 9401, 'is_scored' => true, 'signals_count' => 2, 'icebreakers_count' => 1, 'created_at' => now()->subDays(2)],
                (object) ['id' => 9902, 'prospect_id' => 9402, 'is_scored' => true, 'signals_count' => 1, 'icebreakers_count' => 0, 'created_at' => now()->subDay()],
            ]);
            $selected = null;
            if ($this->selectedRunId === 9901) {
                $selected = (object) [
                    'id' => 9901,
                    'prospect_id' => 9401,
                    'is_scored' => true,
                    'dossier' => ['competitor_gap' => 'No weekend coverage at the nearest rival', 'ad_intelligence' => 'Running broad-match search ads with no call routing'],
                    'signals' => collect([
                        (object) ['signal_type' => 'competitor_weakness', 'description' => 'Competitor ABC Plumbing has no weekend coverage'],
                        (object) ['signal_type' => 'ad_activity', 'description' => 'Running expensive broad-match Google ads'],
                    ]),
                    'icebreakers' => collect([
                        (object) ['opener_text' => 'Saw your North Texas expansion in the Dallas Business Journal', 'source_url' => 'https://www.bizjournals.com/dallas/news/2026/08/apex-plumbing-expansion.html', 'observed_date' => Carbon::parse('2026-08-20')],
                    ]),
                ];
            } elseif ($this->selectedRunId === 9902) {
                $selected = (object) [
                    'id' => 9902,
                    'prospect_id' => 9402,
                    'is_scored' => true,
                    'dossier' => [],
                    'signals' => collect([
                        (object) ['signal_type' => 'review_decay', 'description' => 'Three unanswered one-star reviews in thirty days'],
                    ]),
                    'icebreakers' => collect(),
                ];
            }
            $meter = ['count' => 2, 'dollars' => '0.25'];
        } else {
            $runs = ResearchRun::where('business_id', $this->businessId)
                ->withCount(['signals', 'icebreakers'])
                ->orderByDesc('id')
                ->get();
            $selected = $this->selectedRunId !== null
                ? ResearchRun::where('business_id', $this->businessId)->with(['signals', 'icebreakers'])->find($this->selectedRunId)
                : null;
            $meter = $this->meter();
        }

        return view('x-135::research-dossier-per', [
            'runs' => $runs,
            'selected' => $selected,
            'meter' => $meter,
        ]);
    }
}
