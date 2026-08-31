<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CampaignRecipient;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serve one recipient's personalised MMS picture to whoever the carrier sends.
 *
 * ⚠️ **THE SIGNATURE IS THE ENTIRE AUTHORISATION, AND IT IS APPLIED BEFORE THIS
 * RUNS.** There is no session here and there cannot be: the fetch is made by a
 * carrier's own infrastructure, from an address nobody can predict, with no
 * cookie. `signed` middleware rejects an unsigned, edited or expired URL at the
 * routing layer, which is why `{business}` can be trusted enough to establish
 * the tenant.
 *
 * ⛔ **AND THAT IS WHY THE PATH IS NOT THE SECRET.** A signed URL that has
 * expired stops working even though the file is still on disk, which is the
 * property a public-disk filename could never have — and the file has a real
 * person's name rendered into it.
 *
 * ⚠️ **NOTHING HERE RECORDS A VIEW.** A carrier fetch is not a person looking at
 * a picture, and a table counting them would be reported as engagement by the
 * first screen that found it. Decision 113's rule about destination clicks is
 * the same idea one channel over: never record as an outcome something the
 * platform cannot actually observe.
 */
final class CampaignMediaController extends Controller
{
    public function __invoke(Request $request, int $business, int $recipient): StreamedResponse
    {
        unset($request);

        return Tenancy::actingAs($business, function () use ($recipient): StreamedResponse {
            $row = CampaignRecipient::query()->findOrFail($recipient);

            // ⚠️ **RLS DECIDES THIS, NOT THE URL.** `campaign_recipients` is
            // tenant-owned and FORCEd, so a signed URL naming business A and a
            // recipient belonging to business B finds nothing and 404s — the
            // *wrong tenant* case, answered by the layer that can actually
            // answer it rather than by a comparison somebody could delete.
            $path = $row->media_path;

            abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

            return Storage::disk('local')->response($path, null, [
                // A carrier may fetch this more than once and there is nothing
                // to gain from it being re-read from disk each time; the URL's
                // own expiry is what bounds the lifetime, not the cache.
                'Cache-Control' => 'private, max-age=3600',
            ]);
        });
    }
}
