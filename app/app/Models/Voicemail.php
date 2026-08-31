<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\VoicemailAudioState;
use App\Enums\VoicemailTranscriptState;
use App\Events\Voice\VoicemailRecorded;
use Database\Factories\VoicemailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The message a caller left (T176 P2, R7).
 *
 * ⛔ **`audio_state` AND `transcript_state` ARE INDEPENDENT, AND READING ONE OFF
 * THE OTHER IS THE DEFECT THIS MODEL EXISTS TO PREVENT.** `CLAUDE.md`: *never
 * block on transcription — STT failure still delivers the audio.* The owner
 * notification fires on {@see VoicemailRecorded}, which fires
 * on the audio; a transcript is an enrichment that may never arrive.
 *
 * ⚠️ **`transcript` IS UNTRUSTED INPUT, MAY BE PHI, AND IS NEVER LOGGED.**
 * `VoicemailTranscribed` carries both arguments; the short version is that it is
 * a stranger's speech and there is no surface on which to ask a caller anything
 * before they speak.
 *
 * @property int $business_id
 * @property int $call_id
 * @property ?string $provider_file_id
 * @property ?string $recording_path
 * @property ?string $recording_format
 * @property ?int $recording_bytes
 * @property ?int $recording_seconds
 * @property VoicemailAudioState $audio_state
 * @property ?string $transcript
 * @property ?string $transcript_engine
 * @property ?float $transcript_confidence
 * @property VoicemailTranscriptState $transcript_state
 * @property ?Carbon $transcribed_at
 * @property ?Carbon $notified_at
 * @property Call $call
 */
final class Voicemail extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<VoicemailFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    /**
     * @return BelongsTo<Call, $this>
     */
    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    /**
     * Whether there is audio for a person to play (4519).
     *
     * ⛔ **BOTH HALVES, AND THE STATE IS THE ONE THAT MATTERS.** A path with no
     * `Stored` state is a row whose write failed or whose audio was refused
     * under 4500 — and in the refusal case an object could exist from an earlier
     * fetch while the state says it may not be served. **The state is the
     * decision and the path is only the locator**, so a screen or a controller
     * that checked the path alone would serve a recording this application has
     * already refused to keep.
     */
    public function isPlayable(): bool
    {
        return $this->audio_state === VoicemailAudioState::Stored
            && $this->recording_path !== null
            && $this->recording_path !== '';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audio_state' => VoicemailAudioState::class,
            'transcript_state' => VoicemailTranscriptState::class,
            'transcript_confidence' => 'float',
            'transcribed_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }
}
