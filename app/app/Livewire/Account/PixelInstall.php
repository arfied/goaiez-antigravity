<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Models\Business;
use App\Services\Pixel\PixelCollections;
use App\Services\Pixel\PixelKeys;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The install screen for the pixel — the thing that hands a tenant their
 * public key and their snippet. Decision 4979 item (2): the collector lane
 * built the writer and the reader (`PixelKeys`) and left "nothing hands a
 * tenant their public key or their snippet" as the next thing owed.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS RENDERS ON A `GET` AND STILL MINTS A KEY, WHICH `PixelKeys::
 * forBusiness()`'S OWN DOCBLOCK WARNS AGAINST FOR THE SIBLING SCREEN
 * ---------------------------------------------------------------------------
 * `WidgetInstall` never calls `WidgetPlugins::provisionFor()` from its
 * renderer, because every plugin is already minted at registration and a
 * missing one means the location has none — showing "not ready yet" is
 * honest. **That precondition does not hold here for every tenant.**
 * `TenantProvisioner` now calls `PixelKeys::ensureFor()` for every new
 * signup, but decision 4961/4979 refused a migration writing public
 * identifiers for tenants that already existed: "a migration writing public
 * identifiers was refused". So a tenant who registered before the collector
 * shipped has no row, forever, unless something mints one — and the only
 * candidate is the one screen that will ever need the answer. `ensureFor()`
 * is idempotent by lookup-then-insert under a unique index (decision 350's
 * shape), so calling it here on every render of a tenant with no key costs
 * one extra query once and nothing after — not a side effect that repeats.
 *
 * ⚠️ **STILL NOT A DESTRUCTIVE ACTION, WHICH IS THE DISTINCTION THAT MATTERS.**
 * `WidgetPlugins::provisionFor()`'s hazard was writing a **fresh** row with an
 * audit entry as a side effect of a page view. A pixel key has nothing to
 * configure — no domains, no rating filter, nothing an audit log would ever
 * need to explain — so minting it early changes nothing about who could see
 * what; it only changes whether the row exists yet.
 *
 * ⛔ **THIS SAID "NO 'CONNECTED' INDICATOR, AND THAT IS DELIBERATE, NOT AN
 * OMISSION" AND IT WAS FALSE ON THE DAY IT WAS WRITTEN — BOTH READINGS KEPT AND
 * DATED, 2026-08-22 (7980-7983).** The paragraph read: *"`pixel_keys` carries no
 * `last_seen_at` and nothing in this application writes one — the collector
 * archives to object storage on a queued job, **never to a row this screen could
 * query**, and the only thing that turns L0 into anything queryable
 * (`warehouse:replay`) is a manual command with no schedule. A 'connected ✓'
 * wired to nothing is `CLAUDE.md`'s own naming of this codebase's most common
 * defect, so there is nothing this screen could truthfully claim to see. It says
 * so instead of guessing."*
 *
 * ⛔ **THE CLAUSE IN BOLD IS THE FALSE ONE AND EVERYTHING ELSE IN IT IS TRUE**,
 * which is what made it survive three waves and two rewrites of this docblock.
 * [[\App\Jobs\ArchivePixelBatchJob]] stores the L0 object **and then loads the
 * derived event layer inline, in the same job**, per tenant, `ENABLE`+`FORCE`
 * row-level secured, on an index led by the tenant and the receipt time — the
 * index for exactly this question. `git log -S"L1Loader"` puts that write in
 * `f3b42066` and this file in `b9e65304`, **twelve hours later**. So the
 * mechanism was standing here first and the paragraph is what stopped the next
 * reader looking, which is `CLAUDE.md` 314-316 in the place it is most
 * expensive: the artefact whose whole subject is what may honestly be said.
 * ⚠️ **`warehouse:replay` is a red herring in that sentence** — a replay
 * *rebuilds* the derived layer from L0; it was never what puts a row there for
 * the first time.
 *
 * ✅ **SO THERE IS AN INDICATOR NOW, AND IT IS A TIMESTAMP RATHER THAN A TICK.**
 * `App\Services\Warehouse\PixelArrivals` answers *when did something this
 * tenant sent last get through*, {@see PixelCollections} carries it, and
 * `App\Enums\PixelCollectionState::Collecting` is the first case in that
 * enum permitted to render `SignalState::Ok`. ⚠️ **The refusal that survives is
 * the one about silence**: a null answer is still not a diagnosis, because the
 * write is queued, an undecodable batch derives nothing, the layer expires at
 * four hundred days and a replay can empty a range. The reader's own docblock
 * argues all four and the screen's copy says the two a tenant can act on.
 *
 * ⛔ **AND THE COPY THAT REFUSAL PRODUCED WAS A CLAIM ABOUT THE TENANT'S OWN
 * WEBSITE, WHICH IS THE ONE THING A NULL FROM THAT READER CANNOT SUPPORT —
 * CORRECTED 2026-08-26 (9900).** *"A website has to be visited before there is
 * anything to send"* is a sentence about their visitors, and **the archive that
 * produces the silence behind it is ours**: `Storage::disk('s3')` could not be
 * built on any deployment between 2026-08-18 and 2026-08-25 (9408, 9421), and
 * [[\App\Jobs\ArchivePixelBatchJob]] stores before it derives, so for that week
 * every owner with a correct install and a busy website was told nobody had
 * come. ⚠️ **The list of four above is now five and the fifth is that one** —
 * see the reader — and `App\Services\Pixel\PixelAcceptances` is what tells a
 * website nobody visited apart from a website whose visits we took and lost.
 * ⛔ **The bell for it rang to an operator and the sentence went to the person
 * it was untrue about** (9840): the platform knew, and the owner was told the
 * opposite on the one screen they open to find out.
 *
 * ⛔ **AND THE PARAGRAPH ABOVE IS WHY THIS SCREEN WENT ON SAYING THE ONE THING
 * IT COULD PROVE FALSE — CORRECTED 2026-08-22 (7800–7803).** Having refused to
 * claim a positive it could not see, it told every tenant *"Once it is on your
 * site, there is nothing else to do"* — and then, for good measure, sent them
 * to their browser's network tab, which shows `204 No Content` on every
 * refusal by §11's transport rule. **Both sentences are gone.** There *is* a
 * negative this application can see, it was already written down, and it was
 * being told to an operator instead: `WidgetPlugins::provisionFor()` seeds an
 * empty allowlist, `PixelCollector` answers §11 row 2 from it (4968), so a
 * freshly provisioned tenant collects nothing — and `IngestRejects` records
 * every refused batch under `PixelRefusal::OriginNotAllowed`.
 * {@see PixelCollections} is that read, and this screen renders it.
 * ⛔ **AND THE SENTENCE THAT CLOSED THIS PARAGRAPH IS THE ONE THIS SLICE
 * DISPROVED — CORRECTED 2026-08-22 (7980).** It read: *"THE REFUSAL TO CLAIM
 * ACCEPTANCE IS UNCHANGED AND IS NOW STATED BY `PixelCollectionState::Listening`
 * ITSELF — silence is still not health, and **no case in that enum can render a
 * green tick**."* ⚠️ **The first half stands and the second does not**: silence
 * is still not health, and `Collecting` renders a green tick because it is not
 * silence — it is a row that only an accepted, archived and derived batch of
 * this tenant's own traffic can have written.
 *
 * ⛔ **THE PARENTHESIS HERE READ "decision 4966 — bundle delivery, §10, is
 * unbuilt" AND THAT STOPPED BEING TRUE IN THE WAVE THIS SCREEN WAS COMPOSED
 * INTO — CORRECTED 2026-08-18 (5147).** `PixelBundleController` serves `/p.js`
 * and `/v/<sha>/p.js`, and `pixel:publish` puts bytes behind them
 * (4980–4998). ⛔ **AND THE CONCLUSION THAT FOLLOWED IS SUPERSEDED TOO —
 * 2026-08-22 (7980).** It read: *"The conclusion above is unchanged and the
 * reason is narrower: no tenant has pasted a snippet, no browser has fetched
 * `/p.js` from a real site, and no production L0 object has ever come from one
 * (5141) — **a route existing is not traffic arriving**. The indicator stays
 * absent because `pixel_keys` still carries no `last_seen_at` and nothing writes
 * one, which is a fact about this schema rather than about the delivery half."*
 * ⚠️ **THE PRODUCTION HALF IS STILL TRUE AND IS THE HALF WORTH KEEPING**: no
 * production L0 object has yet come from a real page, so on the live install
 * every tenant reads `Listening` today and none reads `Collecting`. ⛔ **What
 * does not follow is the "indicator stays absent" clause**, and the final
 * sentence is exactly why it survived — a true statement about `pixel_keys`
 * doing duty as a claim about the whole schema. **A screen that cannot see
 * anything today and a screen that could never see anything are different
 * screens**, and only the second justifies having no indicator at all.
 *
 * AUTHORIZATION: `auth` alone, `WidgetInstall`'s reasoning exactly — anyone
 * signed in may read the key and the line built from it; there is nothing
 * here to change, so there is no second gate to add.
 */
#[Layout('components.account.layout')]
final class PixelInstall extends Component
{
    public function render(PixelKeys $keys, PixelCollections $collections): View
    {
        // ⚠️ **ONE REFUSAL, NOT TWO, AND THE SECOND ONE WAS REMOVED BY MUTATION
        // RATHER THAN BY TASTE** (decision 5051). This method carried its own
        // `abort_if(Tenancy::id() === null, 403)` as well as `business()`'s, and
        // **each mutant survived**: delete either and the other refuses one line
        // later with the same status, so the test naming the claim passed with a
        // guard missing. That is decision 398's shape — an outer guard refusing
        // first leaves the inner one unfalsifiable — and the fix is to have one
        // guard a test can actually drive red, not two that alibi each other.
        $business = $this->business();

        $key = $keys->forBusiness($business);

        if ($key === null) {
            // The back-fill this screen exists to do — see the class docblock.
            $key = $keys->ensureFor($business);
        }

        return view('livewire.account.pixel-install', [
            'snippet' => $this->snippet($key),
            // ⚠️ **READ ON EVERY RENDER AND HELD IN NO PROPERTY**, `business()`'s
            // reasoning: a state cached across requests is a screen reporting a
            // gate the tenant has since changed — and the one action this page
            // asks for happens on another screen, so the very next render is
            // where the change has to show.
            'collection' => $collections->status(),
        ]);
    }

    /**
     * The tenant in context, re-read on every render — `Account\Settings`'s
     * reasoning: never a held property, so this never renders a stale tenant
     * across requests.
     *
     * ⛔ **THE `abort_if` IS THE WHOLE REFUSAL AND IT FAILS CLOSED WITHOUT IT
     * TOO — WHICH IS WHY IT IS ABOUT THE STATUS CODE, NOT ABOUT THE LEAK.**
     * Removing it does not disclose another tenant's key: `TenantScope` calls
     * `Tenancy::idOrFail()`, so the query throws `TenantNotResolved` and the
     * response is a 500. Refused rather than resolved when there is no tenant —
     * `Account\Settings`'s and `Account\WidgetInstall`'s reasoning: internal
     * staff belong to no business by design, so a signed-in support agent
     * typing this URL is the ordinary way to arrive with nothing resolved, and
     * a 500 there reads as our page being broken rather than as a door that is
     * not theirs.
     */
    private function business(): Business
    {
        $id = Tenancy::id();

        abort_if($id === null, 403);

        return Business::query()->findOrFail($id);
    }

    /**
     * The line an owner pastes into their website.
     *
     * §10's Install block, verified against the raw artefact rather than
     * remembered: `<script async src="…" data-k="pk_live_XXXXXXXX"></script>`.
     * `data-k`, not `data-key` — the bundle reads its key with
     * `self.getAttribute('data-k')` and posts it as the `k` that
     * `StorePixelBatchRequest` requires.
     *
     * ⛔ **AND SOMETHING CHECKS THAT THOSE THREE AGREE NOW** (decision 5050).
     * They did not always: the first test here asserted `assertSee('data-k')`,
     * which **`data-key` satisfies as a prefix** — so renaming this to match
     * the sibling install screen one file over passed the whole suite while
     * every tenant's line read no key at all. `PixelInstallTest`'s agreement
     * lint reads the attribute out of the bundle rather than restating it.
     *
     * ⚠️ **AND THIS DOCBLOCK MAY NOT NAME THE BUNDLE'S PATH** (decision 5055).
     * `Architecture/PixelTest`'s delivery tripwire matches raw file contents,
     * comments included, across `app/`, `routes/` and `resources/views/` — an
     * earlier draft of this paragraph spelled it out and reddened the build
     * naming this file. Reworded rather than excepted, which is 4977's answer
     * to the identical event one wave ago: an exception for a mere mention is
     * decision 511's failure, and rewording costs nothing.
     *
     * The address is `config('pixel.install_script_url')` rather than written
     * here — that file carries the seam's own argument, and W21 owns the other
     * end of it.
     */
    private function snippet(string $key): string
    {
        return sprintf(
            '<script async src="%s" data-k="%s"></script>',
            config('pixel.install_script_url'),
            $key,
        );
    }
}
