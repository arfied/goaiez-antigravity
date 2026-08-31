<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\CampaignMediaController;
use App\Http\Controllers\Controller;
use App\Models\InboundMedia;
use App\Policies\InboundMediaPolicy;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serve one picture a customer texted this business — T176 P10.
 *
 * ⛔ **THE OPPOSITE OF {@see CampaignMediaController} IN
 * EVERY RESPECT, AND THE CONTRAST IS THE DESIGN.** That one is fetched by a
 * carrier from an unpredictable address with no session, so a signed URL is the
 * entire authorisation and the path has to be unguessable. This is fetched by a
 * person who is signed in to the business the picture belongs to — so it
 * authenticates, the tenant is resolved from the session, RLS answers *"is this
 * row yours"*, and a policy answers *"may this role look"*. **A signed URL here
 * would be a worse control**: it would survive the person leaving the business.
 *
 * ## Three layers, and the middle one is the boundary
 *
 *   1. `auth` — a link is useless to somebody not signed in.
 *   2. ⛔ **`InboundMedia::query()->findOrFail()` inside the tenant.**
 *      `ResolveTenant` has already scoped this request to the visitor's own
 *      business, the model is `BelongsToTenant`, and `inbound_media` is
 *      `FORCE ROW LEVEL SECURITY` — so another tenant's id is a 404 decided by
 *      the layer that can actually decide it, not by a comparison somebody
 *      could delete.
 *   3. The policy — role, never tenancy. See {@see InboundMediaPolicy}.
 *
 * ⚠️ **A ROW IS NOT AN OBJECT, AND BOTH ARE CHECKED.** A refusal row carries no
 * path at all, and `config/filesystems.php` sets `'throw' => false` on this disk
 * — so a swallowed upload failure would otherwise answer 500 here. Both are 404:
 * the row is a record of what happened, and what happened may be *"there is no
 * picture"*.
 *
 * ⚠️ **THE CONTENT TYPE COMES OFF THE ROW, WHICH IS A TYPE WE OBSERVED.**
 * `InboundMediaFetcher` refuses a response whose magic bytes disagree with its
 * header, so what is echoed back to a browser is a type both the server and the
 * file agreed on — never a carrier's unchecked claim. `Content-Disposition:
 * inline` with no filename, because a carrier-supplied filename is sender-chosen
 * free text and this schema deliberately does not keep one.
 *
 * ⚠️ **`nosniff` IS SET EXPLICITLY.** These bytes are the only thing in this
 * application uploaded by a member of the public and served back, and a browser
 * that sniffs its way from `image/jpeg` to `text/html` is how one becomes stored
 * XSS.
 */
final class InboundMediaController extends Controller
{
    public function __invoke(int $media): StreamedResponse
    {
        abort_if(Tenancy::id() === null, 403);

        $row = InboundMedia::query()->findOrFail($media);

        Gate::authorize('view', $row);

        $path = $row->storage_path;
        $disk = $row->storage_disk;

        abort_if($path === null || $disk === null, 404);
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response($path, null, [
            'Content-Type' => (string) $row->content_type,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            // Private: this is one business's customer's photograph, and a
            // shared cache holding it is a cross-tenant read waiting to happen.
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
