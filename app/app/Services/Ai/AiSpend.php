<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\AiModel;
use App\Enums\AiTask;
use App\Enums\CreditVerdict;
use App\Models\AiCall;
use App\Modules\X219\Actions\ModelResolveAction;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;

/**
 * The meter on AI spend, per tenant, per calendar month — and the outer half of
 * the brake, whose inner half lives one class over.
 *
 * ⛔ **`ai.monthly_cap_per_tenant` CAME OUT AT 3608 AND WENT STRAIGHT BACK IN AT
 * 3820, AND THE SECOND MOVE IS THE ONE TO READ.** 3608 made the credit balance
 * the only ceiling on the strength of 3612's claim that the unbounded remainder
 * was `Plan::Free` and `Plan::Limited`. **That enumeration was wrong in both
 * directions and the escape was the entire install base**: `Subscriptions`
 * provisions every new business as `Plan::Base` / `pending_checkout` and nothing
 * in `app/` writes either of the plans 3612 named, while `ResetMonthlyCredits`
 * refuses the whole account — all three products — for any tenant
 * `TrialEligibility` turns down, which is every account without a confirmed
 * Google listing. So *"never funded"* was not a corner of the ladder; it was
 * **every account from registration until it confirms a listing and survives a
 * daily reset**, and 3609's escape handed each of them unlimited AI for as long
 * as they stayed unverified — a state under the tenant's own control and free to
 * remain in.
 *
 * ✅ **SO THE TWO CEILINGS ARE BOTH LIVE AND THEY BOUND DIFFERENT POPULATIONS**
 * (3820). 3297's rule is that a cap comes out only once its replacement bounds
 * the path — the balance bounds the funded path and bounds nothing else, so the
 * cap stays until the reset has demonstrably granted in production and the owner
 * has ruled on the unfunded account. **Neither ceiling is a budget to be managed
 * and both are tripwires.**
 *
 * ⛔ **AND THEY DID OVERLAP UNTIL 3960, WHICH IS THE THING TO READ TWICE.** This
 * paragraph and two others said the two *"bound different populations … neither
 * covers the other's"* while {@see self::allows()} read the cap for every tenant
 * alike. The arithmetic: the cap is 500,000 hundredths of **our** cost, which at
 * {@see AiCredits::RETAIL_MULTIPLE} is **$400 of the tenant's charge**, and the
 * manual AI SKU sells $300 of credit at a time. **A tenant who bought two of them
 * and spent $400 of what they had paid for hit a ceiling nobody had told them
 * about** — mid-month, nothing refunded, no screen to see it on (3626), and no
 * clearing until the calendar month rolled. That is *"a cap that is sometimes
 * lower [than the balance and] refuses a tenant mid-campaign by a limit rule 43
 * forbids showing them"* — the exact sentence 3293 deleted the dollar cap over,
 * and it was reachable by paying us. {@see self::refusal()} now asks the cap for
 * {@see CreditVerdict::NeverFunded} and for nothing else, which is what all three
 * docblocks had been describing.
 *
 * ⚠️ **WHAT SURVIVES OF RULE 43 IS THE DISCIPLINE, NOT THE FIGURE** (3294), and
 * this class is still where the discipline lives: it refuses the call, and the
 * caller writes down that it was refused and carries on, because the alternative
 * to an AI-written reply is a human writing one, not an outage. Nothing here
 * throws for a budget reason.
 *
 * ⚠️ **THE METER DID NOT GO WITH THE CAP.** {@see self::spentThisMonthHundredths()}
 * still sums what the *provider* billed us this month, and it is not a ceiling and
 * never was — it is the cost book, and the only number that answers *"where did
 * the money go"* after a surprising bill. The tenant's side of the same call is
 * {@see AiCredits::retailSpentThisMonthHundredths()}, eight times larger and
 * denominated the same way.
 *
 * SHAPED AFTER PlacesSpend, WITH ONE DIFFERENCE THAT CHANGES THE MATH. The Places
 * budget is a platform ceiling on an unauthenticated endpoint — one pot, spent by
 * strangers, and the risk is a stranger draining it. This one is per tenant and
 * the spend is caused by the tenant's own customers writing reviews. So the
 * bounded quantity is different and so is the failure: exhausting the Places
 * budget stops the marketing home working for everyone, exhausting this one stops
 * one business's replies being drafted, and their reviews still arrive, still get
 * triaged, and still wait for a person.
 *
 * ---------------------------------------------------------------------------
 * WHAT THIS ACTUALLY COSTS, AND WHY THE BALANCE SHOULD NEVER RUN OUT
 * ---------------------------------------------------------------------------
 *
 * Prices read from live vendor documentation on AiModel::VERIFIED_ON. At the
 * tier defaults in AiTask — Haiku 4.5 for analysis and moderation, Opus 5 for the
 * reply published under the business's name — one review costs:
 *
 *   analysis    Haiku 4.5   ~800 in, ~200 out                       0.18c
 *   moderation  Haiku 4.5   ~600 in, ~100 out                       0.11c
 *   reply       Opus 5      ~1,200 in, ~250 out                     1.23c
 *                                                                   -----
 *                                                    per review     1.52c
 *
 * A busy local business gets perhaps 40 reviews a month, so **about 61 cents of
 * our cost — $4.88 charged at the 8:1 sell rate (3304, 3412) — against a $50
 * monthly grant (9180), and against this class's own $50-of-our-cost cap.** Ten
 * times the headroom on the tenant's money, eighty times it on the platform
 * ceiling. ⚠️ **The tenant-side multiple moved with 9180 and the platform-side
 * one did not**, because they are denominated differently: the grant is retail
 * and the cap is our cost.
 *
 * That is the point of recording the figure. Neither ceiling is a budget to be
 * managed; running one down is a tripwire on a runaway loop, a prompt that grew a
 * hundredfold, or an integration that retries forever. If a tenant exhausts a
 * whole month of AI credit, the right response is to read `ai_calls`, not to hand
 * out more — and if one reaches $50 of *our* cost, something is looping.
 *
 * ⚠️ **THOSE CENTS ARE OUR COST, AND THE TENANT'S CHARGE IS EIGHT TIMES THEM**
 * (3304, 3358). The two figures sit side by side on every `ai_calls` row and
 * neither is derivable from the other after the multiple moves — {@see AiCredits}.
 * **The ceiling is on the tenant's charge**, because that is what the credit
 * balance is denominated in.
 */
final class AiSpend
{
    /**
     * The cap's `platform_settings` key. Namespaced by area, per that table's
     * convention.
     *
     * ⛔ **THIS CONSTANT WAS DELETED AT 3608 AND RESTORED AT 3820, AND THE
     * RESTORATION IS NOT A REVERT OF THE SLICE — IT IS THE OTHER HALF OF IT.** The
     * balance gate stays exactly as 3608 built it; what changed is that the cap no
     * longer *competes* with it. The ceiling that bounds a funded tenant is their
     * balance; the ceiling that bounds an unfunded one is this, and 3612's error
     * was believing the unfunded set was small enough not to need one.
     *
     * ⚠️ **IT IS DENOMINATED IN OUR COST AND THE BALANCE IS DENOMINATED IN THE
     * TENANT'S CHARGE, EIGHT TIMES LARGER** (3304, 3331). That is why the two gates
     * are not merged into one figure: converting between them in a guard is the
     * mistake 3331 records as invisible afterwards. Each ceiling is read in its own
     * currency by the class that owns that currency, and {@see self::allows()}
     * combines the two *verdicts* rather than the two numbers.
     *
     * The per-task model keys are not here: AiTask::settingKey() owns them, one
     * row per task, so an operator can move a single task onto a different model
     * without touching the others. Building the same key in two places is how the
     * two drift.
     */
    public const string CAP_KEY = 'ai.monthly_cap_per_tenant';

    /**
     * The three reasons a call is refused before it is made — {@see self::refusal()},
     * and what `AiRouter` writes into the log and onto the failed response (3962).
     *
     * ⚠️ **THREE STRINGS RATHER THAN ONE, BECAUSE THEY HAVE THREE REMEDIES.** They
     * all read *"ai_credit_exhausted"* until 3962, so an operator could not tell a
     * genuinely empty account from a lapsed one holding purchased credit from a
     * runaway loop stopped by the platform cap — and the balance figure logged
     * beside them was the right number for only one of the three.
     *
     * ⚠️ **`REFUSAL_CREDIT_EXHAUSTED` KEEPS ITS OLD SPELLING ON PURPOSE.** It is
     * what `AiResponse::failed()` has carried since the gate shipped and what the
     * tests that predate this pin, and renaming a wire-visible string to match a
     * new sibling would be churn on the one of the three that was never wrong.
     */
    public const string REFUSAL_CREDIT_EXHAUSTED = 'ai_credit_exhausted';

    public const string REFUSAL_PLAN_INACTIVE = 'ai_plan_inactive';

    public const string REFUSAL_COST_CAP = 'ai_cost_cap_reached';

    public function __construct(
        private readonly DefaultsRegistry $registry = new DefaultsRegistry,
        private readonly AiCredits $credits = new AiCredits,
    ) {}

    /**
     * The monthly per-tenant ceiling in hundredths of a cent, failing closed.
     *
     * ⚠️ **EVERY READ CARRIES ITS OWN CONSERVATIVE DEFAULT**, so a missing settings
     * row, an unparseable value or a database that cannot answer all mean *less*
     * spend, never unlimited spend. `DefaultsRegistryTest`'s *"fails the spend
     * budgets closed to the manifest seeds"* is what proves it, and that
     * assertion was deleted rather than inverted at 3608 — 3821 puts it back.
     */
    public function monthlyCapHundredths(): int
    {
        // Zero means zero; a negative override is a mistake and clamps to zero
        // rather than wrapping into an enormous budget. Same treatment as
        // PlacesSpend::dailyAuditBudget().
        return max(0, $this->registry->int(self::CAP_KEY));
    }

    /**
     * The model configured to serve a task, falling back to the task's default.
     *
     * `CLAUDE.md` is explicit that **AI models are configuration, not code** and
     * that the router is "admin-editable" — so the mapping lives in
     * `platform_settings` and moves without a deploy, which is what makes a
     * retired model or a price change a settings edit rather than a release.
     *
     * An unrecognised value falls back rather than throwing. A typo in a settings
     * row should degrade to the sensible default, not take every queued job with
     * it — and AiModel's cases are the allowlist that makes "unrecognised" mean
     * something: no settings row can point this application at a model it was
     * never priced for.
     */
    /**
     * The model that will answer this task.
     *
     * Resolution order:
     * 1. Tenant assignment (ModelResolveAction) if a tenant is set
     * 2. Platform registry key (ai.model.<task>)
     * 3. Task default (AiTask::defaultModel())
     */
    public function modelFor(AiTask $task): AiModel
    {
        if ($this->hasTenant()) {
            $action = app(ModelResolveAction::class);
            $assignment = $action->handle(Tenancy::idOrFail(), $task->value);

            if ($assignment !== null) {
                $model = AiModel::tryFrom($assignment);
                if ($model !== null && $model->isEmbedding() === $task->producesEmbedding()) {
                    return $model;
                }
            }
        }

        $configured = $this->registry->stringOrNull($task->settingKey());

        if ($configured === null) {
            return $task->defaultModel();
        }

        $model = AiModel::tryFrom($configured);

        if (! $model instanceof AiModel) {
            return $task->defaultModel();
        }

        // ⚠️ THE KIND CHECK, AND IT IS THE SAME ARGUMENT AS THE FALLBACK ABOVE
        // TAKEN ONE STEP FURTHER. `AiModel::tryFrom()` makes "unrecognised" mean
        // something; this makes "recognised but wrong shape" mean something too.
        // An operator moving `ai.model.knowledge_embedding` onto Opus 5 has made
        // a settings typo, not a routing decision — and the failure it produces
        // is a chat model posted to an embeddings endpoint, which reads as a
        // vendor outage on the one path that runs in the largest batches.
        // Degrading to the tier's default is the same answer this method already
        // gives a typo, for the same reason: a queued job should not die of a
        // settings row.
        if ($model->isEmbedding() !== $task->producesEmbedding()) {
            return $task->defaultModel();
        }

        return $model;
    }

    /**
     * What the current tenant has spent this calendar month, in hundredths of a
     * cent.
     *
     * Scoped by the global scope on AiCall, so it is this tenant's spend and
     * cannot silently become the platform's.
     */
    public function spentThisMonthHundredths(): int
    {
        return (int) AiCall::query()
            ->inMonthOf(Carbon::now())
            ->sum('cost_hundredths_cents');
    }

    /**
     * Whether this tenant can pay for another call.
     *
     * ⛔ **BOTH CEILINGS, AND EACH BINDS THE POPULATION THE OTHER CANNOT**
     * (decisions 3820, 3960). 3608 replaced the cap with the balance; 3820
     * restored the cap *behind* the balance; 3960 made *behind* mean what these
     * paragraphs had been claiming all along:
     *
     *   the balance gate   bounds a tenant who **has** been funded. It is the
     *                      ceiling 3293 left standing and the one a tenant can
     *                      see, query and be told about
     *   the cap           bounds an account that has **never** been funded, and
     *                      nobody else. It is the only thing standing between
     *                      such an account and unlimited AI
     *
     * ⛔ **THE CAP READ FOR A FUNDED TENANT WAS A LIVE DEFECT, NOT A BELT-AND-
     * BRACES** (3960): $50 of our cost is $400 of theirs, the manual AI SKU sells
     * $300 at a time, and a tenant who bought two and spent them was refused
     * mid-month while holding a balance the gate had just permitted. See the class
     * docblock. **Adding the cap back to the funded arm re-creates it**, and the
     * test named for a funded tenant over the cap reddens.
     *
     * ⛔ **THE UNFUNDED POPULATION IS NOT A CORNER OF THE PLAN LADDER, WHICH IS
     * WHAT 3612 GOT WRONG AND WHAT MADE THIS URGENT.** Verified against `app/`
     * rather than against the tier table: `Subscriptions` writes `Plan::Base` /
     * `pending_checkout` for every new business and nothing anywhere writes
     * `Plan::Free` or `Plan::Limited` to a subscription at all, while
     * `ResetMonthlyCredits::sweepBusiness()` returns `'refused'` — granting **no
     * product** — for any account `TrialEligibility` turns down, and it turns down
     * every account with no confirmed Google listing. **So every account is
     * unfunded from registration onward, and staying unfunded is free and under
     * the tenant's own control.** 2066 asked for abuse controls on a no-card
     * trial; without this line every one of them hands the refused account
     * unlimited AI instead.
     *
     * ⚠️ **THE TWO ARE NOW DISJOINT BY CONSTRUCTION RATHER THAN ORDERED, AND THE
     * FALSIFIABILITY STILL HAS TO BE DRIVEN** (398, 3960). {@see self::refusal()}
     * is a `match` on the verdict, so neither ceiling can hide the other by
     * refusing first — but a test that only ever drove one shape of tenant would
     * still prove nothing, so `AiCreditGateTest` drives three: a funded tenant at
     * zero balance well under the cap, an unfunded tenant over the cap, and a
     * funded tenant **over** the cap who must be permitted. Deleting either arm
     * reddens its own case, and merging them reddens the third.
     *
     * ⚠️ **THE TWO FIGURES ARE NEVER COMPARED, ONLY THE TWO VERDICTS.** This
     * class's numbers are what the *provider* bills us; the balance is what the
     * *tenant* is charged, eight times larger. A single merged ceiling would have
     * had to convert, and 3331's hazard is that a conversion in the wrong place is
     * invisible afterwards. {@see AiCredits} owns the charge and the balance
     * refusal in one unit; this method owns the cost refusal in the other.
     *
     * ⚠️ **3377 SKETCHED THIS AS `AiSpend` LOSING `allows()` ALTOGETHER AND
     * `AiRouter` ASKING `AiCredits` DIRECTLY.** Kept here instead, because five
     * call sites ask this question — the router twice, plus three jobs and a
     * console command — and repointing all of them at a second collaborator would
     * be a wider change than the swap needs. It is now also the only place both
     * ceilings meet, which is a reason of its own to leave it where it is.
     *
     * Checked before the call, not after, and without estimating what the call
     * will cost. An estimate would need a token count the request does not have
     * yet; see {@see AiCredits::allowsAnotherCall()} for what happens to the
     * overshoot.
     *
     * ⚠️ **THE BODY IS {@see self::refusal()} SINCE 3962** — the four call sites
     * that want a yes or no keep this one, and the router takes the reason so its
     * log can say which ceiling refused. Everything argued above is about that
     * method; this is the projection of it that loses the reason.
     */
    public function allows(): bool
    {
        return $this->refusal() === null;
    }

    /**
     * Which ceiling refuses this tenant's next call, or null if none does.
     *
     * ⛔ **THE CAP IS ASKED FOR ONE VERDICT AND FOR NO OTHER, WHICH IS WHAT MAKES
     * {@see self::allows()}'s CLAIM TRUE** (decision 3960). The `match` is the fix:
     * every state a balance can be in gets an explicit answer, and
     * {@see CreditVerdict::NeverFunded} is the only one that reaches the cost cap.
     * A fifth state cannot inherit an answer nobody chose.
     *
     * ⚠️ **THE REASON IS RETURNED RATHER THAN A BOOLEAN, SO THE REFUSAL CAN NAME
     * ITSELF** (3962). `AiRouter` logged *"ai credit exhausted"* with a balance
     * figure beside it for all three refusals — so an operator looking at a lapsed
     * tenant holding $250 of purchased credit, or at a runaway loop stopped by the
     * cap, read a sentence about credit that was not the reason and a figure that
     * was not the ceiling. The three have three different remedies.
     */
    public function refusal(): ?string
    {
        return match ($this->credits->verdict()) {
            // The balance is the ceiling 3293 left standing, and this account has
            // one. Nothing else has standing to refuse it — see the class docblock
            // for the arithmetic that makes asking the cap here a live defect.
            CreditVerdict::Spendable => null,
            CreditVerdict::Exhausted => self::REFUSAL_CREDIT_EXHAUSTED,
            // Not "exhausted": 3441's credits are waiting rather than gone, and
            // the remedy is resubscribing rather than buying more.
            CreditVerdict::PlanInactive => self::REFUSAL_PLAN_INACTIVE,
            // The account a balance cannot bound (3609), and today that is every
            // account from registration until it verifies. This is the only arm
            // the cap is read in.
            CreditVerdict::NeverFunded => $this->capRefusal(),
        };
    }

    /**
     * The cost cap's own verdict, in hundredths of a cent of our cost.
     *
     * ⚠️ **NEVER COMPARED WITH THE BALANCE, ONLY WITH THE COST METER** (3331). The
     * two ceilings are eight times apart and in different currencies; converting
     * between them inside a guard is the mistake 3331 records as invisible
     * afterwards. Each is read in its own currency and only the verdicts meet.
     */
    private function capRefusal(): ?string
    {
        $cap = $this->monthlyCapHundredths();

        // A cap of zero refuses, and that is the fail-closed direction rather than
        // a special case: an operator who types `0` into a spend ceiling has said
        // "no spend", and reading it as "no limit" is how a settings field becomes
        // an outage with a bill attached.
        if ($cap === 0) {
            return self::REFUSAL_COST_CAP;
        }

        return $this->spentThisMonthHundredths() < $cap
            ? null
            : self::REFUSAL_COST_CAP;
    }

    /**
     * Record a completed call against the meter.
     *
     * Records refusals and failures too, and the cost column is why. A refusal
     * bills for whatever was generated before the classifier fired, and a request
     * that 500s after the model has read the prompt may still bill for input — a
     * ledger that only records successes under-reports the bill in exactly the
     * situation where somebody is trying to work out where the money went.
     *
     * ⚠️ **THE TENANT'S CHARGE IS WRITTEN ON THE SAME ROW AS OUR COST, AND FOR
     * THE SAME REASON A REFUSAL IS RECORDED AT ALL** (decision 3359). A refused
     * or failed call still bills us, so it still charges the tenant; a book that
     * only charged for successes would under-report the balance in exactly the
     * situation where somebody is asking where their credit went. The two figures
     * are different currencies — see {@see AiCredits} — and neither is derivable
     * from the other after {@see AiCredits::RETAIL_MULTIPLE} moves.
     *
     * The tenant comes from BelongsToTenant, so this cannot be written against a
     * business other than the ambient one.
     */
    public function record(AiTask $task, AiResponse $response): AiCall
    {
        $cost = $response->costInHundredthsOfCents();
        $retail = $this->credits->retailFor($cost);

        $call = AiCall::query()->create([
            'task' => $task,
            'provider' => $response->model->provider(),
            'model' => $response->model,
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'cost_hundredths_cents' => $cost,
            'retail_hundredths_cents' => $retail,
            'refused' => $response->refused,
            'failure_reason' => $response->failureReason,
        ]);

        // ⛔ THE DEBIT, AND IT COMES AFTER THE ROW ON PURPOSE (3424). The provider
        // has already answered and already billed us by the time either line runs,
        // so the charge is a fact and the row recording it must survive whatever
        // the balance says. `debitForCall()` therefore absorbs a refusal rather
        // than throwing — 2904, and rule 43's surviving half (3294).
        //
        // ✅ AND THE OTHER HALF OF THE CEILING NOW EXISTS (3608): `allows()` above
        // asks this same balance before the call, so a debit that cannot be
        // covered is the *last* one a tenant gets rather than one of an unbounded
        // series. 3297's ordering is complete — the debit landed first (3424), the
        // grant was fixed (3421), and the refusal came third.
        $this->credits->debitForCall($retail, $call);

        return $call;
    }

    /**
     * Record a completed embedding call against the same meter.
     *
     * ⚠️ **A SECOND METHOD RATHER THAN A WIDENED `record()`, AND THE REASON IS
     * THE COLUMN IT WOULD HAVE TO GUESS.** `AiResponse` and `EmbeddingResponse`
     * are different shapes on purpose: one can be refused and one cannot, one has
     * output tokens and one has none. A single method taking a union would have
     * to ask `instanceof` twice inside itself to fill `refused` and
     * `output_tokens`, and the arm that got it wrong would write a plausible row
     * — a `refused = false` that means "could not be refused" reading identically
     * to one that means "was not refused".
     *
     * `refused` is therefore always false here and that is a fact rather than a
     * default: the embeddings endpoint has no classifier and no `stop_reason`,
     * so there is no refusal to record. `output_tokens` is zero for the same
     * reason — nothing was generated.
     *
     * The tenant comes from BelongsToTenant, so this cannot be written against a
     * business other than the ambient one.
     */
    public function recordEmbedding(AiTask $task, EmbeddingResponse $response): AiCall
    {
        $cost = $response->costInHundredthsOfCents();
        $retail = $this->credits->retailFor($cost);

        $call = AiCall::query()->create([
            'task' => $task,
            'provider' => $response->model->provider(),
            'model' => $response->model,
            'input_tokens' => $response->inputTokens,
            'output_tokens' => 0,
            'cost_hundredths_cents' => $cost,
            'retail_hundredths_cents' => $retail,
            'refused' => false,
            'failure_reason' => $response->failureReason,
        ]);

        // ⛔ EMBEDDINGS DEBIT TOO, AND LEAVING THEM OUT WOULD HAVE BEEN THE
        // EXEMPTION 3361 REFUSES. They run in the largest batches of anything in
        // this application — knowledge ingest — so an unmetered embedding path is
        // the one most likely to be the whole bill. `record()`'s reasoning above
        // applies unchanged.
        $this->credits->debitForCall($retail, $call);

        return $call;
    }

    /**
     * This month's spend broken down by task, for answering "what did we spend it
     * on?" after a surprising bill.
     *
     * Grouped by task rather than by model because the task is the thing an
     * operator can act on — moving analysis to a cheaper tier is a settings edit;
     * "Opus 5 cost a lot" is not actionable without knowing what it was doing.
     *
     * @return array<string, array{calls: int, hundredths: int}>
     */
    public function thisMonthByTask(): array
    {
        return AiCall::query()
            ->inMonthOf(Carbon::now())
            ->get(['task', 'cost_hundredths_cents'])
            ->groupBy(fn (AiCall $call): string => $call->task->value)
            ->map(fn ($calls): array => [
                'calls' => $calls->count(),
                'hundredths' => (int) $calls->sum('cost_hundredths_cents'),
            ])
            ->all();
    }

    /**
     * Whether a tenant is resolved at all.
     *
     * Every AI call is billed to a business, and AiCall's tenant key is
     * `NOT NULL`, so a call outside a tenant context cannot be metered. The
     * router refuses rather than letting BelongsToTenant throw from inside a
     * queued job, where the failure would read as a database error rather than as
     * the missing-context bug it is.
     */
    public function hasTenant(): bool
    {
        return Tenancy::check();
    }
}
