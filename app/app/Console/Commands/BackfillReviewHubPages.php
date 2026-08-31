<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Location;
use App\Models\User;
use App\Services\Feedback\FeedbackPages;
use App\Services\Reviews\ReviewHubPages;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * The hosted review hub's backfill — decision 6552's owed loop (6620–6639).
 *
 * `LocationProvisioner` mints a hub page with every **new** location, so every
 * location provisioned before 2026-08-21 answers 404 at its own `/r/{slug}`.
 * This is the sweep that gives those locations the row they never got.
 *
 * ## ⛔ IT PREVIEWS BY DEFAULT AND WRITES ONLY ONE TENANT AT A TIME, WHICH IS
 * THE DECISION IN THIS FILE RATHER THAN THE LOOP
 *
 * A bare run reports and writes nothing. `--commit` writes, and **`--commit`
 * without `--tenant` is refused outright** — there is no invocation of this
 * command that publishes every tenant's review hub at once, and that is
 * deliberate (6621, 6622). Three facts make the platform-wide write the wrong
 * default:
 *
 *   1. **The page publishes other people's words**, and the address is not
 *      secret: `/r/{slug}` takes the *feedback page's* slug (6545), which is
 *      printed on QR signs, carried in every review-invite text and submitted
 *      to a carrier as a 10DLC opt-in URL. `noindex` (6549) keeps it out of a
 *      search index; it does not keep it from anyone holding the address.
 *   2. **`display_on_website` is not always a human's decision.**
 *      `ReviewRouter` sets it mechanically on every auto-approved five-star
 *      review, so the list on a backfilled page can consist entirely of
 *      reviews nobody chose to publish anywhere.
 *   3. ⚠️ **ONE OF THE TWO SWITCHES NOW HAS A WRITER — CORRECTED 2026-08-21
 *      (6860-6879).** This read *"Neither switch that could take a page down
 *      has a writer (6623) … not `Admin\LocationSettings`, not
 *      `Account\ReviewRules`, nothing … So a tenant published by this command
 *      cannot be unpublished by this application"* — and it named by name the
 *      file that writes it today. `Admin\LocationSettings` renders
 *      `update_review_hub` as a field; a `super_admin` untick takes the page
 *      down on the next request, with no cache on that path.
 *      ⚠️ **`review_hub_pages.is_published` still has no writer**, so the
 *      narrower switch remains unreachable.
 *      ⛔ **AND THE PERSON WHO CAN REACH IT IS NOT THE TENANT** (6863):
 *      `AdminAccess::GATE` is `canAdministerPlatform()`, which is `super_admin`
 *      alone — not the owner, not a support agent, not a support lead. A tenant
 *      asking for their page down requires an escalation, so "unpublishable by
 *      this application" has become "unpublishable by anyone the tenant can
 *      reach", which is a weaker claim but not a different decision.
 *
 * ⚠️ **NONE OF THAT IS AN ARGUMENT AGAINST THE BACKFILL** — it is already true
 * of every location provisioned since the hub shipped, and refusing to level
 * the old population up while the new one publishes would leave one product
 * behaving two ways. It is an argument that the *sequencing* is a person's:
 * tell a tenant, then run this for them.
 *
 * ## ⚠️ THE ENUMERATION IS `gbp:sync`'s, AND THE SCOPE DROP IS THE SAME ONE
 *
 * The sweep runs outside any tenant, `businesses` is FORCE ROW LEVEL SECURITY
 * on a policy keyed to the session tenant, so `Business::all()` returns nothing
 * and dropping the global scope alone does not help — the policy is in the
 * database, and the read answers **zero rows rather than an error** (569,
 * 6181), which is the more dangerous of the two. Reaching each business through
 * its owner via `owner_lookup` is the way out that grants the sweep no
 * privilege a logged-in owner lacks. Read {@see RefreshOauthTokens}' docblock
 * before changing it; the argument is set out there in full.
 *
 * ⚠️ **`--tenant` NARROWS AND NEVER WIDENS**, exactly as
 * {@see CheckGrandfatheredPricing}'s does: with it, one business is established
 * directly and no owner walk happens at all.
 *
 * ## ⚠️ WHAT IT DELIBERATELY DOES NOT PRINT
 *
 * **No location name and no slug.** A location's name is a personal name
 * whenever the owner signed up before naming a business — `nameIsPersonal` is
 * a parameter of {@see FeedbackPages::provisionFor()}
 * for that reason — and the slug is minted from it. Business and location ids
 * are what an operator needs to act, and they are not somebody's name.
 */
#[Signature('reviews:backfill-hub-pages
    {--commit : Mint the pages. Without this the command reports and writes nothing}
    {--tenant= : The one business id to work on. Required with --commit}')]
#[Description('Give locations provisioned before the review hub the page they never got')]
final class BackfillReviewHubPages extends Command
{
    public function handle(ReviewHubPages $hubPages): int
    {
        // ⚠️ `=== true` RATHER THAN A CAST, WHICH IS THE OPPOSITE CHOICE FROM
        // `--tenant` BELOW AND IS DELIBERATE. `--commit` is a value-less flag,
        // so the CLI gives a real bool — and a caller passing something else
        // through `Artisan::call()` gets a **preview** rather than a
        // publication. Both options fail toward not writing; the cast below is
        // needed because there the safe answer is a refusal, not a wider run.
        $commit = $this->option('commit') === true;

        // ⛔ REFUSED RATHER THAN COERCED, AND THE REASON IS THIS COMMAND'S OWN
        // FAILURE DIRECTION. An unreadable `--tenant` cast quietly to null would
        // widen the run from one account to the owner walk over every account,
        // so a typo would change the *scope* of a publication sweep.
        //
        // ⚠️ CAST RATHER THAN TYPE-TESTED, AND THAT IS NOT TIDINESS. `option()`
        // is documented as returning `string|null`, and `Artisan::call()` with
        // an array hands `ArrayInput` the value untouched — so a caller passing
        // `['--tenant' => $business->id]` produces an **int**, which no
        // `is_string()` check sees. The first draft of this file did exactly
        // that and read every scoped invocation as "no tenant given", falling
        // through to the refusal below. A cast is right for both.
        $tenant = $this->option('tenant');
        $given = trim((string) ($tenant ?? ''));

        if ($given !== '' && ! ctype_digit($given)) {
            $this->error('--tenant takes one business id.');

            return self::FAILURE;
        }

        $onlyBusinessId = $given === '' ? null : (int) $given;

        if ($commit && $onlyBusinessId === null) {
            // ⛔ THE REFUSAL IS THE DECISION (6621). Publishing every existing
            // tenant's review hub in one command is a thing somebody should
            // have to do on purpose, one account at a time, having told them —
            // and nothing in this application can take a page back down.
            $this->error(
                'Minting across every account at once is refused. This publishes other people\'s '
                .'reviews at an address their customers already hold, and nothing in this '
                .'application can take a page down again. Run without --commit to see who is '
                .'waiting, then --commit --tenant=<id> for one account at a time.',
            );

            return self::FAILURE;
        }

        /** @var list<array{business: int, location: int, outcome: string}> $rows */
        $rows = [];

        if ($onlyBusinessId !== null) {
            // ⚠️ SAID PLAINLY RATHER THAN REPORTED AS "NOTHING TO DO". A
            // business id that matches nothing reads every table through RLS
            // and finds no rows, so the sweep would print *"every location
            // already has a review hub page"* — a true sentence about an empty
            // set that an operator would read as "this account is done". A
            // mistyped id is the likeliest way to get here.
            if (! $this->businessExists($onlyBusinessId)) {
                Tenancy::forgetAll();

                $this->error('No account matched business '.$onlyBusinessId.'. Nothing was read or written.');

                return self::FAILURE;
            }

            $rows = $this->sweepBusiness($onlyBusinessId, $commit, $hubPages);
        } else {
            User::query()
                ->select('id')
                ->orderBy('id')
                ->chunkById(200, function (Collection $users) use (&$rows, $hubPages): void {
                    foreach ($users as $user) {
                        $rows = [...$rows, ...$this->sweepOwner((int) $user->getKey(), $hubPages)];
                    }
                });
        }

        // Never leave a security context established after a console command —
        // the PostgreSQL session variable outlives this process's connection
        // under any pooler, and a worker inheriting it would start as whichever
        // tenant the loop happened to touch last.
        Tenancy::forgetAll();

        return $this->report($rows, $commit);
    }

    /**
     * Whether `--tenant` named a business that exists.
     *
     * Asked from inside the tenant it names, which is the only place it can be
     * asked: `businesses` is FORCE row-level secured on its own id, so a read
     * with no tenant established answers zero rows rather than an error, and
     * `IsTenantRoot`'s scope calls `Tenancy::idOrFail()` besides. Establishing
     * the tenant grants nothing — the policy admits exactly the row asked for.
     */
    private function businessExists(int $businessId): bool
    {
        return (bool) Tenancy::actingAs(
            $businessId,
            fn (): bool => Business::query()->whereKey($businessId)->exists(),
        );
    }

    /**
     * @return list<array{business: int, location: int, outcome: string}>
     */
    private function sweepOwner(int $userId, ReviewHubPages $hubPages): array
    {
        Tenancy::setUser($userId);

        // withoutGlobalScopes because the scope calls Tenancy::idOrFail() and no
        // tenant is established yet — the circularity ResolveTenant documents.
        // The database still restricts this to businesses owned by the user just
        // set, so it cannot widen beyond one person's own.
        $businessIds = Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->orderBy('id')
            ->pluck('id');

        $rows = [];

        foreach ($businessIds as $businessId) {
            // ⛔ THE LITERAL `false` IS THE SECOND CONTAINMENT AND IS NOT
            // DEAD CODE. `handle()` refuses `--commit` without `--tenant`, so
            // this arm is already unreachable from a committing run — and the
            // owner walk **is not given `$commit` to pass on**, so removing
            // that refusal still could not make this loop write. A mutation
            // deleting the guard was driven and the report went wrong while
            // **nothing was minted**, which is the shape worth keeping: the
            // containment is in what this method cannot express, not only in a
            // condition somebody can delete.
            $rows = [...$rows, ...$this->sweepBusiness((int) $businessId, false, $hubPages)];
        }

        return $rows;
    }

    /**
     * @return list<array{business: int, location: int, outcome: string}>
     */
    private function sweepBusiness(int $businessId, bool $commit, ReviewHubPages $hubPages): array
    {
        return Tenancy::actingAs($businessId, function () use ($businessId, $commit, $hubPages): array {
            $awaiting = $hubPages->locationsAwaitingAPage();

            $rows = [];

            foreach ($awaiting['unaddressable'] as $location) {
                $rows[] = [
                    'business' => $businessId,
                    'location' => (int) $location->id,
                    // Reported rather than minted past: a location with no
                    // feedback page has no public address to publish at, and
                    // provisionFor() would refuse it. Every location either
                    // provisioner has ever made has one, so this is a finding.
                    'outcome' => 'no feedback page — cannot publish',
                ];
            }

            foreach ($awaiting['mintable'] as $location) {
                $rows[] = [
                    'business' => $businessId,
                    'location' => (int) $location->id,
                    'outcome' => $commit ? $this->mint($location, $hubPages) : 'waiting',
                ];
            }

            return $rows;
        });
    }

    /**
     * Mint one location's page through the one method that mints them.
     *
     * ⚠️ IDEMPOTENCE IS provisionFor()'s AND IS NOT RE-IMPLEMENTED HERE. It
     * reads first, inserts inside its own transaction, and re-selects on a
     * unique violation — so a second run of this command creates nothing, and
     * so does a run that races the ordinary provisioning path.
     */
    private function mint(Location $location, ReviewHubPages $hubPages): string
    {
        $hubPages->provisionFor($location);

        return 'published';
    }

    /**
     * @param  list<array{business: int, location: int, outcome: string}>  $rows
     */
    private function report(array $rows, bool $commit): int
    {
        if ($rows === []) {
            $this->info('Every location already has a review hub page.');

            return self::SUCCESS;
        }

        $this->table(['Business', 'Location', 'Outcome'], $rows);

        $waiting = count(array_filter($rows, static fn (array $row): bool => $row['outcome'] === 'waiting'));
        $published = count(array_filter($rows, static fn (array $row): bool => $row['outcome'] === 'published'));

        if ($commit) {
            $this->info($published.' review hub pages are now published and readable by anybody holding the address.');

            return self::SUCCESS;
        }

        // Outcome language, and the consequence stated rather than the count
        // alone: the operator is about to make other people's reviews readable.
        $this->info(
            $waiting.' locations are waiting for a review hub page. Nothing was written. '
            .'Publishing one account\'s reviews is --commit --tenant=<id>, and this application '
            .'cannot take a page down again once it is up.',
        );

        return self::SUCCESS;
    }
}
