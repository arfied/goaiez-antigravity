<?php

declare(strict_types=1);

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Services\Content\Publishing;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The owner said STOP — `29` §4.5's other half.
 *
 * ⛔ **`signed` IS THE WHOLE AUTHORISATION AND IT RUNS BEFORE THIS CLASS.** The
 * owner approves by text and never logs in; the notice arrives on a phone with
 * no session. `campaign.media` answers the identical problem the identical way,
 * and its reasoning transfers verbatim: the tenant travels inside the signature
 * because there is nothing on the request to resolve one from, and a URL naming
 * the wrong tenant finds nothing because `growth_pages` is FORCEd — **the
 * database answers it, not a comparison somebody could delete**.
 *
 * ⚠️ **A GET THAT CHANGES STATE, DELIBERATELY** (5671). Mail clients and
 * corporate scanners fetch links; here the fetched outcome is *"do not publish
 * yet"*, which is the conservative direction on somebody else's website. An
 * unsubscribe link is two-step because a scanner's click silently loses somebody
 * their mail; a scanner's click here costs a page a day and a person's glance.
 *
 * ⚠️ **IT ANSWERS THE SAME WAY TWICE.** A page already waiting for a person is
 * not an error — it is an owner who pressed the button twice, or a scanner that
 * fetched before them, and telling them it failed would be a worse answer than
 * telling them it worked.
 */
final class HoldGrowthPageController extends Controller
{
    public function __invoke(Request $request, int $business, int $page): Response
    {
        unset($request);

        Tenancy::actingAs($business, function () use ($page): void {
            app(Publishing::class)->stop($page, 'owner:link');
        });

        return response()->view('content.hold-confirmed');
    }
}
