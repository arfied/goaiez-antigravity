<?php

declare(strict_types=1);

namespace App\Modules\X103\Ui;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Location;
use App\Modules\X103\Actions\HeadlineProposeAction;
use App\Modules\X103\Actions\PageReadAction;
use App\Modules\X103\Actions\PageVariantReadAction;
use App\Modules\X103\Actions\PageVariantResultAction;
use App\Modules\X103\Actions\PageVariantStartAction;
use App\Modules\X103\Actions\PageVariantStopAction;
use App\Modules\X103\Actions\SiteBuildRunAction;
use App\Modules\X103\Actions\SiteEditProposeAction;
use App\Modules\X103\Actions\SitePreviewAction;
use App\Modules\X103\Actions\SitePublishAction;
use App\Modules\X103\Domain\BlockPatchApplier;
use App\Modules\X103\Domain\SectionOrder;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVariant;
use App\Modules\X103\Models\SiteRecommendation;
use App\Modules\X157\Actions\CustomDomainRequestAction;
use App\Modules\X157\Actions\CustomDomainStatusAction;
use App\Modules\X157\Actions\CustomDomainVerifyAction;
use App\Modules\X157\Actions\LatestDeploymentForPageAction;
use App\Modules\X157\Actions\PlatformSiteAddressAction;
use App\Services\Ai\AiSpend;
use App\Services\Config\DefaultsRegistry;
use App\Services\Facts\BusinessFacts;
use App\Services\Industry\IndustryQuestions;
use App\Services\Industry\IndustryResolver;
use App\Services\Industry\IndustryStartingPoints;
use App\Services\Visibility\CompetitorSignals;
use App\Services\Visibility\CompetitorSiteNotes;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

#[Layout('components.account.layout', ['heading' => 'Build my site'])]
class SiteBuild extends Component
{
    #[Locked]
    public int $businessId;

    #[Locked]
    public int $locationId;

    public array $ledger = [];

    public ?string $buildStatus = null;

    public ?string $buildReason = null;

    public string $domainName = '';

    public ?string $dnsStatus = null;

    public ?string $error = null;

    public ?string $success = null;

    public ?string $proposed = null;

    public array $headlineOptions = [];

    public string $headlineChoice = '';

    public string $ownHeadline = '';

    public ?string $trial = null;

    public function mount()
    {
        // A platform admin arrives here with no tenant (staff own no business); this
        // screen builds ONE business's site, so refuse plainly rather than throw (wave 834).
        abort_unless(Tenancy::check(), 403, 'Build my site works on one business — open it from Tenant locations first.');
        $this->businessId = Tenancy::idOrFail();
        $location = Location::where('business_id', $this->businessId)->first();
        $this->locationId = $location ? $location->id : 0;
    }

    public function chooseLook(string $variant, IndustryStartingPoints $sp, IndustryResolver $ir, PageReadAction $pages): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        if (! in_array($variant, IndustryStartingPoints::VARIANTS, true)) {
            $this->error = 'Invalid look.';

            return;
        }

        Business::query()->whereKey($this->businessId)->update(['site_variant' => $variant]);

        $home = $pages->homeFor($this->businessId);
        if ($home && is_array($home->draft_blocks)) {
            $family = $ir->for($this->businessId)['family'];
            $variantTokens = $sp->variant($sp->for($family), $variant);
            $home->draft_blocks = SectionOrder::apply($home->draft_blocks, $variantTokens['section_order']);
            $home->save();
        }

        $this->success = 'Look '.strtoupper($variant).' picked — your pages follow it from the next draft and the next publish.';
    }

    public function runBuild(SiteBuildRunAction $action)
    {
        $this->error = null;
        try {
            $result = $action->handle($this->businessId, $this->locationId);
            $this->buildStatus = $result['status'];
            if ($this->buildStatus === 'refused') {
                $this->buildReason = $result['reason'] ?? '';
            }
            $this->ledger = $result;
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function publishAll(SitePublishAction $action, PlatformSiteAddressAction $addressAction, DefaultsRegistry $registry)
    {
        $this->error = null;
        try {
            $addressAction->handle($this->businessId);

            $maxPages = $registry->int('sites.build.max_pages_publish');
            $pages = Page::where('business_id', $this->businessId)->where('is_published', false)->take($maxPages)->get();
            foreach ($pages as $page) {
                $action->handle($this->businessId, $page->id, $page->draft_blocks ?? []);
            }
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function verifyDomain(CustomDomainVerifyAction $action)
    {
        $this->error = null;
        try {
            $result = $action->handle($this->businessId);
            $this->dnsStatus = $result['status'] === 'no_request' ? null : 'verification_ran';
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function useDomain(CustomDomainRequestAction $action)
    {
        $this->error = null;
        if (empty($this->domainName)) {
            return;
        }

        try {
            $action->handle($this->businessId, $this->domainName);
            $this->dnsStatus = 'requested';
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function dismissRecommendation(int $id): void
    {
        SiteRecommendation::where('business_id', $this->businessId)
            ->whereKey($id)
            ->update(['status' => 'dismissed']);
    }

    public function askRecommendation(int $id, SiteEditProposeAction $action, PageReadAction $pages, BlockPatchApplier $applier): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->proposed = null;

        $recommendation = SiteRecommendation::where('business_id', $this->businessId)->whereKey($id)->first();
        if ($recommendation === null) {
            return;
        }

        $home = $pages->homeFor($this->businessId) ?? Page::where('business_id', $this->businessId)->orderBy('id')->first();
        if ($home === null) {
            $this->error = 'No page drafted yet. Run the build first, then ask again.';

            return;
        }

        try {
            $res = $action->handle(
                businessId: $this->businessId,
                pageId: (int) $home->id,
                request: (string) $recommendation->text,
                continue: isset($home->draft_meta['pending_edit'])
            );
            if ($res['status'] === 'refused') {
                $this->error = 'The AI did not propose anything for this ('.$res['reason'].').';

                return;
            }

            $applyResult = $applier->apply($home->draft_blocks ?? [], $res['patches']);
            if ($applyResult['status'] === 'refused') {
                $this->error = $applyResult['reason'];

                return;
            }

            $meta = $home->draft_meta ?? [];
            $thread = [];

            if (isset($meta['pending_edit'])) {
                $thread = $meta['pending_edit']['thread'] ?? [];
                if (empty($thread)) {
                    $thread[] = [
                        'request' => $meta['pending_edit']['request'] ?? '',
                        'explanation' => $meta['pending_edit']['explanation'] ?? '',
                        'at' => $meta['pending_edit']['drafted_at'] ?? '',
                    ];
                }
            }
            $now = now()->toIso8601String();
            $thread[] = [
                'request' => (string) $recommendation->text,
                'explanation' => $res['explanation'],
                'at' => $now,
            ];

            $pendingEdit = [
                'request' => (string) $recommendation->text,
                'blocks' => $applyResult['blocks'],
                'explanation' => $res['explanation'],
                'model' => $res['model'],
                'drafted_at' => $now,
                'thread' => $thread,
            ];
            if (! empty($res['image_notes'])) {
                $pendingEdit['image_notes'] = $res['image_notes'];
            }
            if ($res['style'] !== null) {
                $pendingEdit['style'] = $res['style'];
            }
            if ($res['style_refused'] !== null) {
                $pendingEdit['style_refused'] = $res['style_refused'];
            }
            $meta['pending_edit'] = $pendingEdit;
            $home->draft_meta = $meta;
            $home->save();

            $numEdits = count($res['patches']);
            $this->proposed = "Proposed on your {$home->title} page — {$numEdits} edits with {$res['model']}. Review it on Pages, then Apply or Discard.";
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function proposeHeadlines(HeadlineProposeAction $action): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->headlineOptions = [];

        try {
            $result = $action->handle($this->businessId);
            if ($result['status'] === 'refused') {
                if ($result['reason'] === 'no_hero') {
                    $this->error = 'Draft the site first — there is no home headline to test yet.';
                } elseif ($result['reason'] === 'no_facts_available') {
                    $this->error = 'Confirm a price or show a review first, so the AI has something true to write from.';
                } else {
                    $this->error = 'The AI could not propose right now: '.match (true) {
                        $result['reason'] === AiSpend::REFUSAL_PLAN_INACTIVE => 'this account\'s plan is not active — add a card on Your plan, or ask the platform to extend the trial',
                        $result['reason'] === AiSpend::REFUSAL_CREDIT_EXHAUSTED => 'this month\'s AI credit is used up',
                        default => $result['reason']
                    }.'.';
                }
            } else {
                $this->headlineOptions = $result['headlines'];
            }
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function startHeadlineTest(PageVariantStartAction $action, PageReadAction $pages): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;

        $headline = trim($this->ownHeadline) !== '' ? trim($this->ownHeadline) : $this->headlineChoice;
        if (trim($headline) === '') {
            $this->error = 'Pick one of the two, or type your own.';

            return;
        }

        $home = $pages->homeFor($this->businessId);
        if (! $home) {
            $this->error = 'Draft the site first — there is no home headline to test yet.';

            return;
        }

        try {
            $result = $action->handle($this->businessId, $home->id, $headline);
            if ($result['status'] === 'refused') {
                $reasonMap = [
                    'variant_running' => 'A test is already running on this page — stop it first.',
                    'not_deployed' => 'Publish the site first; a test needs a live page.',
                    'tenant_wording' => 'This page carries your own edits, so it is never tested (your words are yours).',
                    'same_as_control' => 'That is the headline you already have.',
                    'brand_name' => 'Your business name is never tested.',
                    'bad_headline' => 'Keep it under 120 characters.',
                ];
                $this->error = $reasonMap[$result['reason']] ?? 'Could not start: '.$result['reason'];
            } else {
                $this->trial = 'Running: half your visitors now see "'.$headline.'". Results appear here once each version has been seen 100 times.';
            }
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function stopHeadlineTest(int $variantId, PageVariantStopAction $action): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        try {
            $action->handle($this->businessId, $variantId);
            $this->trial = 'Stopped — everyone sees your original headline again.';
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function keepMineAndFreeze(int $variantId, PageVariantStopAction $action): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        try {
            $action->handle($this->businessId, $variantId, true);
            $this->trial = 'Kept your headline and marked it left-alone — nothing will propose a change to it.';
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        $pages = Page::where('business_id', $this->businessId)->get();

        $domainStatus = app(CustomDomainStatusAction::class)->handle($this->businessId);

        $deployments = [];
        foreach ($pages as $page) {
            $deployments[$page->id] = app(LatestDeploymentForPageAction::class)->handle($this->businessId, $page->id);
        }
        $deployments = collect($deployments)->filter();

        $recommendations = SiteRecommendation::where('business_id', $this->businessId)
            ->where('status', 'pending')
            ->orderBy('code')
            ->get();

        $location = Location::where('business_id', $this->businessId)->first();
        $peers = $location ? app(CompetitorSignals::class)->compare($location) : null;
        $peerTopics = app(CompetitorSiteNotes::class)->topicsFor($this->businessId);

        $home = app(PageReadAction::class)->homeFor($this->businessId);
        $variant = null;
        $variantResult = null;
        $frozen = false;
        if ($home) {
            $variant = app(PageVariantReadAction::class)->runningFor($this->businessId, $home->id);
            if ($variant) {
                $variantResult = app(PageVariantResultAction::class)->handle($this->businessId, $variant['id']);
            }
            $lastRow = PageVariant::where('business_id', $this->businessId)
                ->where('page_id', $home->id)
                ->latest('id')
                ->first();
            $frozen = $lastRow && $lastRow->status === 'frozen';
        }

        $industry = app(IndustryResolver::class)->for($this->businessId);

        $unansweredQuestions = 0;
        if ($industry['family'] !== null) {
            $questions = app(IndustryQuestions::class)->forBusiness($this->businessId);
            $facts = app(BusinessFacts::class)->all($this->businessId);
            foreach (array_keys($questions) as $key) {
                if (! array_key_exists($key, $facts) || trim((string) ($facts[$key] ?? '')) === '') {
                    $unansweredQuestions++;
                }
            }
        }

        return view('x-103::site-build', [
            'pages' => $pages,
            'domainStatus' => $domainStatus,
            'deployments' => $deployments,
            'recommendations' => $recommendations,
            'peers' => $peers,
            'peerTopics' => $peerTopics,
            'variant' => $variant,
            'variantResult' => $variantResult,
            'frozen' => $frozen,
            'industry' => $industry,
            'previews' => app(SitePreviewAction::class)->handle($this->businessId),
            'chosenVariant' => Business::query()->whereKey($this->businessId)->value('site_variant') ?? 'a',
            'unansweredQuestions' => $unansweredQuestions,
        ]);
    }
}
