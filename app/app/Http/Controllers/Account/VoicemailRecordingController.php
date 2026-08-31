<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Jobs\Voice\FetchVoicemailRecordingJob;
use App\Models\Voicemail;
use App\Notifications\VoicemailReceived;
use App\Policies\VoicemailPolicy;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Play the message a caller left — T176 P2, and the reader the audio never had
 * (4519).
 *
 * ⛔ **THE DEFECT THIS CLOSES IS THAT THE BYTES HAD NO READER AT ALL.**
 * {@see FetchVoicemailRecordingJob} pulled a member of the public's voice out of
 * the vendor and wrote it to durable object storage, {@see VoicemailReceived}
 * told the owner *"the recording is on your calls page"*, and **there was no
 * route, no controller and no player** — so every recording was write-only
 * personal data at a deterministic key, and the mail named a place that did not
 * exist. `CLAUDE.md`'s 272 with the failure inverted: not a table nothing
 * writes, a store nothing reads.
 *
 * ## Three layers, and the middle one is the boundary
 *
 * {@see InboundMediaController}'s design, verbatim, because this is the same
 * question about worse data:
 *
 *   1. `auth` — a link is useless to somebody not signed in.
 *   2. ⛔ **`Voicemail::query()->findOrFail()` inside the tenant.** `ResolveTenant`
 *      has scoped the request, the model is `BelongsToTenant`, and `voicemails`
 *      is `FORCE ROW LEVEL SECURITY` — so another tenant's id is a 404 decided
 *      by the layer that can decide it, not by a comparison somebody could
 *      delete.
 *   3. The policy — role, never tenancy. See {@see VoicemailPolicy}.
 *
 * ⛔ **AND NOT A SIGNED URL, WHICH WOULD BE THE WEAKER CONTROL.** A signature
 * keeps working after the person leaves the business and survives being
 * forwarded; a session does not. It is the same reason
 * {@see VoicemailReceived} carries no link at all: an address in an email is an
 * address anybody who sees the email can open.
 *
 * ⚠️ **THE STATE DECIDES, NOT THE PATH** — {@see Voicemail::isPlayable()}. A row
 * whose audio was refused under 4500 must not become playable because an object
 * happens to exist at the key, and this disk is `'throw' => false`, so a missing
 * object is a 404 rather than a 500.
 *
 * ⚠️ **`nosniff`, `no-store`, AND NO FILENAME.** These bytes are a stranger's
 * voice; a shared cache holding them is a cross-tenant read waiting to happen,
 * and the object key deliberately carries no number for a `Content-Disposition`
 * to leak.
 */
final class VoicemailRecordingController extends Controller
{
    public function __invoke(int $voicemail): StreamedResponse
    {
        abort_if(Tenancy::id() === null, 403);

        $row = Voicemail::query()->findOrFail($voicemail);

        Gate::authorize('view', $row);

        abort_unless($row->isPlayable(), 404);

        $path = (string) $row->recording_path;
        $disk = Storage::disk(FetchVoicemailRecordingJob::DISK);

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            // ⚠️ **THE FORMAT WE ASKED THE VENDOR FOR, DEFAULTED TO WAV.** The
            // recordings this platform configures are 8kHz μ-law WAV; an
            // unexpected value cannot become an executable type here because the
            // header is built from a short map rather than echoed.
            'Content-Type' => $this->contentType($row->recording_format),
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function contentType(?string $format): string
    {
        return match (mb_strtolower(trim((string) $format))) {
            'mp3' => 'audio/mpeg',
            'ogg' => 'audio/ogg',
            default => 'audio/wav',
        };
    }
}
