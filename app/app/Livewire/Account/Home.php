<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Models\Business;
use App\Services\Config\DefaultsRegistry;
use App\Services\Gbp\GbpConnections;
use App\Services\Proof\ProofNumbers;
use App\Services\Tenant\TenantPause;
use App\Services\Visibility\VisibilitySyncHistory;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The owner's Home, and the first one this application has had (`28` §3.3).
 *
 * ⚠️ **§3.3 SAYS THESE NUMBERS SIT "AT THE TOP OF HOME" AND THERE WAS NO HOME.**
 * Before this slice the owner's only authenticated surfaces were the wizard,
 * `/account` (820–829) and `/account/customers/import` (840–848) — the last two
 * built in the previous two days, each because the thing it exposed had no door.
 * A rollup with no screen is decision 620's write-only shape, which is why the
 * store, the reader and this ship together rather than in sequence.
 *
 * ## Two things it deliberately does not do
 *
 * **It does not live-increment.** §3.3 asks for Reverb pushing new events so a
 * fresh review appears within seconds. Reverb is not running — production has no
 * Redis, and queue, cache and session are all on `database` (415) — so the
 * honest build is a page that states when the numbers were last worked out. A
 * poller pretending to be a live feed would be a worse lie than a timestamp.
 *
 * **It does not compute on render.** `ProofNumbers::for()` reads the rollup and
 * returns three real zeros when none has been computed. Recomputing on every
 * page load would make the screen's cost grow with the tenant's history, and it
 * would hide a broken scheduler behind a page that always looks right.
 */
#[Layout('components.account.layout')]
final class Home extends Component
{
    /**
     * Which period is showing — `'all'` or `'YYYY-MM'`.
     *
     * §3.3: "Period toggle: This month / All time. No other options in Normal."
     * Two values, so it is a toggle rather than a picker, and
     * `ProofNumbers::for()` refuses anything that is neither.
     */
    public string $period = ProofNumbers::ALL;

    public function showThisMonth(): void
    {
        $this->period = ProofNumbers::monthOf();
    }

    public function showAllTime(): void
    {
        $this->period = ProofNumbers::ALL;
    }

    /**
     * ⚠️ **THE STATE READS ARE HOISTED HERE AND NOT PUSHED INTO
     * `ProofNumbers`** (9917). ⚠️ **THIS SAID "THE TWO STATE READS" AND A THIRD
     * ARRIVED ON 2026-08-26 (10120–10139), SO THE COUNT IS GONE FROM THE
     * SENTENCE RATHER THAN INCREMENTED IN IT** — the argument never depended on
     * it, and the number is one `render()` signature away.
     * {@see ProofNumbers::notes()} is a pure function of its arguments for a
     * reason: `ProofNumbers` is constructed once
     * per tenant per period by the hourly `proof:recompute` sweep, and giving it
     * a `GbpConnections` dependency would build that whole graph — the Zernio
     * client, the audit service, the spend meter, impersonation and the alert
     * bell — on a path that computes three `COUNT(*)`s and reaches no vendor at
     * all. The screen is the only caller that needs the answer, so the screen is
     * where it is asked. It is the same split
     * `Services\Reviews\ReplyPublicationStatus::for()` makes, where the list
     * screen reads the registry once and hands it down — named in backticks
     * rather than `{@see}` on purpose, because a `{@see}` here becomes an import
     * and an import reads as a dependency this component does not have.
     */
    public function render(
        ProofNumbers $proof,
        GbpConnections $connections,
        DefaultsRegistry $defaults,
        VisibilitySyncHistory $history,
        TenantPause $pause,
    ): View {
        // Refused rather than resolved when there is no tenant — `Settings`' own
        // reasoning: internal staff belong to no business by design (`28` §9.1),
        // so a signed-in support agent typing this URL is the ordinary way to
        // arrive with nothing resolved, and letting `Tenancy::idOrFail()` reach
        // the renderer is a 500 that reads as our page being broken.
        abort_if(Tenancy::id() === null, 403);

        return view('livewire.account.home', [
            'numbers' => $proof->for($this->period),
            'definitions' => ProofNumbers::definitions(),
            'notes' => ProofNumbers::notes(
                googleReadingIsOpen: $defaults->value('gbp.zernio_enabled') === true,
                googleIsConnected: $connections->hasUsableConnection(),

                // ⚠️ **THE POPULATION IS THE SWEEP'S OWN ENUMERATION, NOT A
                // SECOND QUERY** (10120–10139, on 9917's rule). `gbp:sync` fans
                // out over `usableLocationIds()`, so asking the same method here
                // is what stops the sentence and the sweep disagreeing about
                // which locations are being read — a separately-written
                // predicate would let this screen fall silent about a location
                // the sweep is failing on, or speak about one it never touches.
                //
                // ⚠️ **ASKED AFTER `hasUsableConnection()` AND SPENT ONLY IF
                // THAT ARM FALLS THROUGH IS WHAT A LADDER WOULD DO, AND PHP
                // EVALUATES BOTH ARGUMENTS HERE.** The cost is one `pluck` and
                // one indexed read per usable location on a page an owner opens
                // by hand; buying laziness would mean passing a closure into a
                // method 9917 deliberately made a pure function of its
                // arguments.
                googleReadAbsence: $history->reviewAbsenceAcross(
                    $connections->usableLocationIds()->all(),
                ),
            ),
            'isThisMonth' => $this->period !== ProofNumbers::ALL,
            'isPaused' => $pause->isCurrentTenantPaused(),
            'hasAdvanced' => Business::query()->find(Tenancy::idOrFail())?->hasAdvancedDashboard() ?? false,
        ]);
    }
}
