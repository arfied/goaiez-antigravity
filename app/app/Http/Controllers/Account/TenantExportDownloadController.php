<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Enums\ImpersonationCapability;
use App\Exceptions\ImpersonationRefused;
use App\Http\Controllers\Controller;
use App\Models\TenantExport;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Export\ExportBuilder;
use App\Services\Impersonation\Impersonation;
use App\Services\Support\DataRequests;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The one place a "Download my data" ZIP is served (`28` §3.7).
 *
 * ⚠️ **FIVE INDEPENDENT LAYERS, AND ALL FIVE ARE LOAD-BEARING.**
 *
 *   1. `signed` middleware (routes/web.php) — Laravel's own signature check,
 *      refusing a tampered or expired URL before this class runs at all.
 *   2. `auth` middleware — a link is useless to somebody not signed in, which
 *      is most of what "the link is useless to a different tenant" means in
 *      practice: no session, no attempt.
 *   3. This controller's own explicit lookup and {@see TenantExport::isDownloadable()}
 *      check — the one the other two cannot do. `ResolveTenant` (the whole web
 *      group) has already scoped this request to whichever business the
 *      *signed-in* visitor belongs to, so `TenantExport::query()->findOrFail()`
 *      returns 404 for another tenant's export id even carrying a technically
 *      valid signature — a signature proves the link was minted by us, not who
 *      is holding it. `isDownloadable()` is asked again here rather than
 *      trusted from the signature's own expiry, because that is the row's own
 *      business clock and the one {@see ExportBuilder::downloadUrl()}
 *      minted the signature from — the brief this closes is explicit that
 *      expiry must be enforced server-side "at fetch time, not merely encoded
 *      in a link nobody re-checks".
 *   4. ⚠️ **THE IMPERSONATION REFUSAL, WHICH IS THE ONE THE OTHER FOUR ALL
 *      PASS.** See below.
 *   5. ⚠️ **THE OBJECT ITSELF**, checked rather than assumed. See below.
 *   6. ⛔ **THE SCOPE, RE-ASSERTED AGAINST THE ROW AT THE MOMENT THE LINK IS
 *      REDEEMED.** This is the layer this route did not have. See below.
 *
 * ## ⚠️ 4 — A SUPPORT AGENT MAY NOT FETCH THIS, IN EITHER MODE (1905)
 *
 * A **view-only** session is GET-only and its database connection is read-only,
 * and a download is a GET read — so both of `Impersonating`'s layers wave it
 * through. One agent, alone, reaching `/account` and clicking the link already
 * on the page pulls every contact, review and message in the account, with no
 * second person anywhere in the transaction. That routes entirely around
 * `28` §9.7's approval step, which `DataRequests::approveTenantExport()`
 * implements and which is the whole reason the ops path exists.
 *
 * `28` §9.4 has named {@see ImpersonationCapability::ExportTenantData} since it
 * was written; {@see ExportBuilder::request()} guards the *starting* half and
 * this guards the *fetching* half, which is the half where bytes actually leave.
 *
 * ⚠️ **NO CARVE-OUT FOR AN APPROVED `data_request_id`, AND IT WAS CONSIDERED**
 * (1905). The tempting version lets an agent fetch a row two people approved.
 * It is refused because the ops export exists so that *the tenant* receives
 * their data — `announce()` emails the owner and the link is on the owner's own
 * screen — not so that an agent can hold a copy, and a carve-out would make the
 * blocklist entry conditional on a field the agent can see before deciding
 * whether to bother. The cost is real and stated: an agent cannot click the link
 * on the owner's behalf while on a call with them. They can see that it exists.
 *
## ⛔ 6 — A LINK THAT NAMES A CONTACT MAY ONLY EVER FETCH THAT CONTACT (6565)

 * `44` §10 adds an export scoped to **one customer**, and the dangerous shape of
 * that feature is not the one it looks like. The scope lives on
 * `tenant_exports.customer_id` and is fixed before a byte is assembled, so the
 * object this route streams is already the right size — a handler that ignored
 * the scope entirely would still serve the contact ZIP for a contact row.
 *
 * ⛔ **WHAT IT WOULD NOT SURVIVE IS A MINTING MISTAKE, AND THAT IS A BREACH.**
 * {@see ExportBuilder::downloadUrl()} puts the row's own `customer_id` in the
 * signed query string. Suppose some future caller mints one by hand, or passes
 * the wrong row, and produces `?export=<the whole-account archive>&subject=<a
 * customer>` — correctly signed by us, correctly unexpired, belonging to the
 * right tenant. Without the check below, that link streams **every contact,
 * review and message in the account** to whoever the tenant forwarded it to as
 * *"here is everything we hold about you"*. Nothing goes red: the row is real,
 * the signature is valid, the file exists, and the audit entry records a
 * successful export.
 *
 * ⚠️ **SO THE ASSERTION IS AT THE VERIFICATION POINT AND NOT ONLY AT THE MINT.**
 * The link states a subject; the row states what was actually built; they must
 * agree exactly, in **both** directions — a subject-less link cannot redeem a
 * contact-scoped row, and a subject-bearing link cannot redeem the account
 * archive. A disagreement is a 404 rather than a 403, because a link this
 * application never minted should not be told which half of it was wrong.
 *
 * ⚠️ **THE SIGNATURE IS NOT WHAT ENFORCES THIS, AND CONFUSING THE TWO IS 398'S
 * TRAP.** `signed` refuses a *tampered* parameter, so no holder of a URL can
 * edit `subject` themselves — which means the only way to reach this check is a
 * link we minted wrongly, and a test that tampers proves the middleware rather
 * than this line. The test that matters signs a mismatched pair with our own
 * key and expects a 404.
 *
 * ## ⚠️ 5 — A MISSING OBJECT IS A 410, NOT A 500 (1909)
 *
 * `storage_path` being non-null says a build believed it uploaded, which is not
 * the same as an object being there: `config/filesystems.php` sets
 * `'throw' => false` on this disk, so a swallowed R2 failure used to leave a
 * `ready` row pointing at nothing and this route answered 500. The row is not
 * mutated here — a reader does not get to decide an export failed — it is
 * reported as gone, with the sentence that says to build another.
 *
 * ## ⚠️ EVERY SUCCESSFUL FETCH IS AUDITED (1904)
 *
 * `29` §2 rule 42 and `28` §3.7's *"export logged to `audit_log`"*, which the
 * requesting and the building halves already satisfied and the *retrieval* half
 * did not — the moment the account leaves the building was the one moment with
 * no entry at all. Recorded after every refusal above, so the trail says what
 * was served rather than what was attempted.
 *
 * ## ⚠️ AND A SUCCESSFUL FETCH IS WHAT CLOSES A STATUTORY REQUEST (1999)
 *
 * `28` §9.5's queue used to close a tenant-export ask as `Fulfilled` when the
 * build finished. Nothing in this application can observe that the tenant
 * *received* anything — the mail relay vendor is not picked (1191–1195), bounce
 * handling is unbuilt (open question H), and layer 4 above deliberately stops
 * an agent collecting it on the owner's behalf — so that word was a claim. A
 * fetch here is the one delivery event this system does observe, so
 * {@see DataRequests::noteExportFetched()} is called with it.
 *
 * ⚠️ **BOTH WRITES SIT BELOW LAYER 4 ON PURPOSE.** An impersonated request
 * never reaches either line, which matters for a reason beyond the policy: a
 * **view-only** session's connection is `default_transaction_read_only`, so an
 * INSERT here would be SQLSTATE 25006 rather than the 403 the reader deserves.
 * The refusal above is what keeps that unreachable, and moving either write
 * higher would find it.
 */
final class TenantExportDownloadController extends Controller
{
    public function __invoke(
        Request $request,
        int $export,
        Impersonation $impersonation,
        AuditService $audit,
        DataRequests $dataRequests,
    ): StreamedResponse {
        abort_if(Tenancy::id() === null, 403);

        $model = TenantExport::query()->findOrFail($export);

        // ⛔ LAYER 6 (6565). The subject the link states, against the subject the
        // row was actually built for. Above `isDownloadable()` on purpose: a
        // link whose two halves disagree is one this application never minted,
        // and answering it with "expired" would describe a row it has no
        // business being told about at all.
        //
        // ⚠️ `?subject=` ABSENT AND `?subject=0` BOTH READ AS "THE WHOLE
        // ACCOUNT", WHICH IS WHY THE COMPARISON IS ON AN `?int` RATHER THAN ON
        // THE RAW STRING. A contact id is never 0 — `customers.id` is a bigserial
        // — so no scoped row can be reached by either spelling, and the account
        // archive is reachable only by a link that names no subject at all.
        $claimed = $request->query('subject');
        $claimedId = is_scalar($claimed) && (int) $claimed > 0 ? (int) $claimed : null;

        abort_unless($claimedId === $model->contactScope(), 404);

        abort_unless($model->isDownloadable(), 410, 'This download has expired.');

        try {
            $impersonation->refuse(ImpersonationCapability::ExportTenantData);
        } catch (ImpersonationRefused $refusal) {
            abort(403, $refusal->getMessage());
        }

        $path = $model->storage_path;

        abort_if($path === null, 404);

        // ⚠️ NO SCREEN IS NAMED, AND THAT IS THE FIX RATHER THAN VAGUENESS
        // (1998). This said "from your account screen", and the reader most
        // likely to see it is a **suspended** owner — `SuspendedTenantStatus`
        // redirects them off `/account`, so the sentence sent them to the one
        // page they cannot reach, to press a control that is on the on-hold
        // page instead. Naming the button rather than the page is true on both
        // screens and stays true when a third one carries it.
        abort_unless(
            Storage::disk('s3')->exists($path),
            410,
            'This download is no longer available. Build a new one with the '
            .'"Download my data" button — it is on your account page, and on the '
            .'on-hold page if your account is stopped.',
        );

        $user = auth()->user();

        $audit->record(
            'export.downloaded',
            $user instanceof User ? 'user:'.$user->id : 'unknown',
            $model,
            ['byte_size' => $model->byte_size],
        );

        // ⚠️ THE ONLY DELIVERY SIGNAL THIS APPLICATION HAS (1999). A statutory
        // request approved through `28` §9.5's queue closed as `Fulfilled` on
        // *build*, which asserts a delivery nothing here can observe — the mail
        // relay vendor is not picked (1191–1195), bounce handling is unbuilt,
        // and 1905 refuses to let an agent collect it on the owner's behalf. A
        // fetch is the one event that is observed, so it is what closes the row.
        // A no-op for the owner's own button, which links no request.
        $dataRequests->noteExportFetched($model);

        return Storage::disk('s3')->response(
            $path,
            // ⚠️ THE ID AND NEVER THE NAME. A file name travels through a
            // browser's download folder, a mail client and whatever the tenant
            // forwards it with; the id is meaningless outside this account and
            // the manifest inside says who the file is about.
            $model->contactScope() === null ? 'my-data.zip' : 'contact-'.$model->contactScope().'-data.zip',
            ['Content-Type' => 'application/zip'],
        );
    }
}
