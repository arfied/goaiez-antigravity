<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Services\Sms\InboundMediaFetcher;

/**
 * One recording file the vendor holds for a call.
 *
 * Every field maps to a documented member of Infobip's recording `files[]`
 * object, verified against the live reference on 2026-08-16:
 *
 *   https://www.infobip.com/docs/api/channels/voice/calls/files-and-recordings/get-call-recordings
 *
 *   `id` · `name` · `fileFormat` · `size` · `creationTime` · `duration` ·
 *   `startTime` · `endTime` · `location` · `sftpUploadStatus` · `customData`
 *
 * ⛔ **`location` IS DOCUMENTED AND IS DELIBERATELY NOT CARRIED.** It is a URL,
 * and a URL out of a vendor payload is the whole of an SSRF —
 * {@see InboundMediaFetcher} exists because of that exact
 * hazard on the MMS path, and it needs a hostname allowlist an operator can only
 * fill from a real delivery. This path needs none, because the bytes are fetched
 * from a **documented endpoint built from the file id** —
 * `GET /calls/1/recordings/files/{fileId}` on the account's own API host, which
 * is already the only host this application talks to Infobip on.
 *
 * ⚠️ **`duration` IS SECONDS AND IS NOT ASSUMED TO BE PRESENT.** The reference
 * marks every member optional; a missing duration is a voicemail with no length
 * shown, never a voicemail withheld.
 */
final readonly class VoiceRecording
{
    public function __construct(
        public string $fileId,
        public ?string $format = null,
        public ?int $seconds = null,
        public ?int $bytes = null,
    ) {}
}
