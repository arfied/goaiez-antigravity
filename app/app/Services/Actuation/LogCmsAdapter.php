<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use App\Contracts\CmsAdapter;
use App\Enums\SiteSnapshotState;
use App\Models\Location;
use Illuminate\Support\Facades\Log;

/**
 * The adapter that changes nobody's website, which is what makes the rest of the
 * actuation chain buildable.
 *
 * `BUILD-PLAN` §2.11.3 slice A: *"nothing touches a real site in this slice and
 * that is the deliverable."* `App\Services\Sms\LogTexter` is the shape being
 * copied — the driver that reaches nobody, shipped first, and the default
 * everywhere until an operator names another one.
 *
 * ⚠️ **NAMED IN BACKTICKS RATHER THAN `{@see}` DELIBERATELY.** `MessagingTest`'s
 * *"only the platform texter reaches a transport"* lint refuses the **import**
 * of that class outside five permitted files, and Pint's
 * `fully_qualified_strict_types` shortens a fully-qualified `{@see}` and re-adds
 * the import on the next `composer lint` — so the reference would have reddened
 * the build one format run after the change that satisfied it. `GbpTest` records
 * the same trap.
 *
 * ⛔ **IT LOGS THE SHAPE OF A CHANGE, NEVER THE CONTENT OF ONE.** `LogTexter`'s
 * rule, for a second reason on top of the first: a change set's values are a
 * tenant's own page copy, but a snapshot read back off a live site can contain
 * anything that was on that page — including, on a healthcare tenant, text rule
 * 24 fences. `storage/logs` has no retention policy, no encryption and no audit,
 * so what is recorded is the URL host and path, the change type, the tier and
 * the field *names* on each side. Never a value.
 *
 * ⛔ **`snapshot()` RETURNS AN EMPTY SNAPSHOT, AND THAT IS THE HONEST ANSWER
 * RATHER THAN A STUB TO FILL IN LATER** (decision 5528). A driver that transmits
 * nothing has read nothing. Returning a plausible-looking placeholder would
 * satisfy {@see SiteChanges::open()}'s rule-32 guard with a value describing no
 * website at all — turning a promise about reversibility into a shape check, on
 * the one path where being wrong means a stranger's page cannot be put back.
 * **So this driver genuinely cannot open a change set**, and a test asserts that
 * pairing rather than leaving it to be discovered.
 *
 * ⛔ **AND IT IS {@see SiteSnapshotState::Unread}, NEVER `Absent` — THE ONE LINE
 * IN THIS CLASS WHERE THE OBVIOUS EDIT IS A LIVE DEFECT** (5770). Decision 5753's
 * fix gives a snapshot four states, and *"we looked and there is no page at this
 * URL"* is now the state a **creation** proceeds from. This driver has not
 * looked. Answering `Absent` here would make the log driver — the adapter on
 * every deployment that exists — able to open change sets and create pages,
 * which is 5528's argument arriving with a new verb attached.
 *
 * ⚠️ **IT NEVER FAILS**, on `LogTexter`'s reason: a driver that does nothing has
 * nothing to fail at, and an artificial failure mode would make every later
 * slice's tests depend on this class's mood. The failure paths that matter are
 * the live WordPress adapter's and are driven there, in slice G.
 *
 * ⛔ **AND THERE IS NO `readBack()` VERB ON `CmsAdapter` FOR THIS CLASS TO HAVE
 * TO ANSWER — 2026-08-20 (5988), WHICH IS A DECISION AND NOT AN OMISSION.**
 * Decision 5819(b) is that a write was recorded as applied while the site may
 * have rewritten it, and the fix is a **second request inside the WordPress
 * client** (5980): `writeChangeSet()` already answers *did this reach the page*,
 * and the read-back only makes that answer true. **Putting it on the contract
 * would have been the worse shape twice over.** It would need a caller in
 * `app/` on the day it landed and has none (5770's rule); and this driver would
 * have had to answer it, where the only honest answer is *"nothing was written,
 * so nothing was read back"* — a verb whose log-driver arm is a shrug is one
 * every test in this repository satisfies by construction, which is 398's
 * unfalsifiable guard with a new name.
 *
 * ⚠️ **WHAT THIS DRIVER SAYS ABOUT A WRITE IS UNCHANGED AND IS ALREADY THE
 * HONEST ANSWER**: *"log driver: change set not written"*. It makes no claim
 * about a page, because it touched none.
 */
final class LogCmsAdapter implements CmsAdapter
{
    public function connect(Location $location): AdapterOutcome
    {
        $this->note('connect', $location);

        return AdapterOutcome::ok('log driver: nothing was connected');
    }

    public function health(Location $location): AdapterHealth
    {
        $this->note('health', $location);

        // ⚠️ **NOT WRITABLE, WHICH IS THE TRUTH AND NOT A PESSIMISM.** A caller
        // asking "may I write to this site" must not be told yes by a driver
        // that will then write nowhere — that is the silent-inertness shape
        // `CLAUDE.md` opens with, and slice D gates publishing on this answer.
        return new AdapterHealth(false, 'log driver: this deployment writes to no website');
    }

    /**
     * ⛔ **NOTHING IS WRITABLE, BECAUSE NOTHING IS WRITTEN.** `health()` already
     * says so; this says it per field, so that a caller which asks *"what can
     * you write"* rather than *"are you writable"* gets the same answer. The
     * reason string is recorded on the change set and read back by a test.
     *
     * @param  list<string>  $fields
     */
    public function fieldSupport(array $fields): FieldSupport
    {
        return FieldSupport::none($fields, 'log driver: this deployment writes to no website');
    }

    public function writeChangeSet(Location $location, ChangeSet $set): AdapterOutcome
    {
        $this->note('write', $location, [
            'change_type' => $set->changeType,
            'tier' => $set->tier->value,
            'url_path' => $this->pathOf($set->url),
            // ⚠️ THE FIELD NAMES, NEVER THE VALUES. See the class docblock.
            'before_fields' => array_keys($set->before),
            'after_fields' => array_keys($set->after),
        ]);

        return AdapterOutcome::ok('log driver: change set not written');
    }

    /**
     * @param  list<string>  $fields
     */
    public function snapshot(Location $location, string $url, array $fields): SiteSnapshot
    {
        $this->note('snapshot', $location, [
            'url_path' => $this->pathOf($url),
            'fields' => $fields,
        ]);

        // ⛔ **UNREAD, DELIBERATELY — AND NOT `absent()`.** See the class
        // docblock: this is what stops a driver that reads nothing from
        // satisfying rule 32's guard, and what stops it creating pages.
        return SiteSnapshot::unread();
    }

    /**
     * ⚠️ **UNREACHABLE ON THIS DRIVER AND IMPLEMENTED HONESTLY ANYWAY.**
     * `snapshot()` never answers `Absent`, so nothing can build a creation change
     * set against this adapter — a test asserts that pairing. The method exists
     * because the interface has it, and it logs the shape like every other verb.
     */
    public function createPage(Location $location, ChangeSet $set): AdapterOutcome
    {
        $this->note('create', $location, [
            'change_type' => $set->changeType,
            'tier' => $set->tier->value,
            'url_path' => $this->pathOf($set->url),
            'after_fields' => array_keys($set->after),
        ]);

        return AdapterOutcome::ok('log driver: no page was created');
    }

    /**
     * ⚠️ **UNPUBLISH, NEVER DELETE** — the contract's promise, kept even by the
     * driver that reaches nobody, because this is the method a reader copies.
     */
    public function unpublishPage(Location $location, ChangeSet $set): AdapterOutcome
    {
        $this->note('unpublish', $location, [
            'change_type' => $set->changeType,
            'tier' => $set->tier->value,
            'url_path' => $this->pathOf($set->url),
        ]);

        return AdapterOutcome::ok('log driver: no page was unpublished');
    }

    public function rollback(Location $location, ChangeSet $set): AdapterOutcome
    {
        $this->note('rollback', $location, [
            'change_type' => $set->changeType,
            'tier' => $set->tier->value,
            'url_path' => $this->pathOf($set->url),
        ]);

        return AdapterOutcome::ok('log driver: nothing was rolled back');
    }

    public function uninstall(Location $location): AdapterOutcome
    {
        $this->note('uninstall', $location);

        return AdapterOutcome::ok('log driver: nothing was uninstalled');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function note(string $verb, Location $location, array $context = []): void
    {
        Log::info('cms log driver: '.$verb.' reached no website', [
            // `29` §2.4 permits the tenant in log context and nothing about a
            // person. A location id is neither.
            'business_id' => $location->business_id,
            'location_id' => $location->id,
            ...$context,
        ]);
    }

    /**
     * The path, never the whole URL.
     *
     * ⚠️ A tenant's own hostname is not a secret, but a full URL with its query
     * string is where a booking reference or an email address ends up, and this
     * line is written for every change on every deployment.
     */
    private function pathOf(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }
}
