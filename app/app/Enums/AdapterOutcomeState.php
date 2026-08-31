<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\CmsAdapter;
use App\Services\Actuation\AdapterOutcome;
use App\Services\Actuation\SiteChanges;
use App\Services\Actuation\SiteSnapshot;

/**
 * What an attempt to change somebody else's website amounted to.
 *
 * ⛔ **THIS EXISTS BECAUSE {@see AdapterOutcome} WAS A BOOLEAN WHERE
 * {@see SiteSnapshot} WAS DELIBERATELY GIVEN FOUR STATES** (6041, closed at
 * 6140). 5770 split a snapshot into `Read`, `Absent`, `Unreadable` and `Unread`
 * precisely so that *"we looked and there is no page"* could never be confused
 * with *"we could not see"* — and the **write** side never got the same
 * treatment. `WordPressAdapter::unpublishPage()` therefore had nowhere to put
 * *"nothing answers at that address and I cannot tell whether the page moved"*,
 * and chose `ok`: the owner was told *"Undone — the page is off your website"*
 * about a page that was still live and still indexed under their name.
 *
 * ## The three, and the line between them
 *
 *   - {@see self::Ok} — **we changed the site and we checked.** Every `ok` on
 *     the WordPress path is read back off the page before it is returned; a
 *     `200` is not evidence, which is `WordPressRestClient`'s whole subject.
 *   - {@see self::Failed} — **we tried, or we could not try, and something is
 *     wrong.** The site refused, the credential is gone, the host did not
 *     answer, or the page was left in a state that needs a person.
 *   - {@see self::Unverified} — **we did not write, because we could not
 *     establish that the page in front of us is the page this change set is
 *     about.** Nothing on the customer's website changed, and — this is the
 *     half that matters — **our change is still on it.**
 *
 * ⚠️ **`Unverified` IS NOT A SOFTER `Failed`, AND READING IT AS ONE IS THE
 * DEFECT COMING BACK.** A `Failed` revert is a website that said no, and *"we
 * tried and your site did not accept it"* is a true sentence about it. An
 * `Unverified` revert is a page we could not identify, and that same sentence is
 * false twice over: nothing was refused, and nothing was even asked. The two
 * carry different owner-facing copy for that reason, and a caller that branches
 * only on {@see AdapterOutcome::$ok} will say the wrong one.
 *
 * ⚠️ **AND NEITHER OF THE TWO NON-`Ok` STATES MAY STAMP A ROLLBACK.**
 * {@see SiteChanges::revert()} stamps `rolled_back_at` on `Ok` alone, which is
 * what keeps `SiteMeasurements::dueForRevert()`'s nightly retry able to see the
 * row — 6040's second half, and the one a fix that only changed the sentence
 * would have left switched off.
 *
 * @see CmsAdapter the contract every one of these comes out of
 */
enum AdapterOutcomeState: string
{
    /** We changed the site, and we read the page back to prove it. */
    case Ok = 'ok';

    /** We tried or could not try, and the attempt did not succeed. */
    case Failed = 'failed';

    /**
     * We did not write, because we could not establish that the page in front
     * of us is the page this change set is about.
     *
     * ⛔ **THE THREE THINGS THAT REACH HERE, ALL OF THEM ON THE REVERT PATH:**
     * the address the change set names is no longer on the website its location
     * points to (5965/5976); nothing is published at that address and we cannot
     * tell whether the page moved or was taken down (6040); and the page is
     * still there and **is not as we left it**, so putting our snapshot back
     * would write over whatever the owner has written since (6042).
     *
     * ⚠️ **IN ALL THREE THE CUSTOMER'S WEBSITE IS UNTOUCHED**, which is what
     * makes one state right for all three: what an owner needs told is that we
     * changed nothing and that our change is still there.
     */
    case Unverified = 'unverified';
}
