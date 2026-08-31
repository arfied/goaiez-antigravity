<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VoicemailAudioState;
use App\Enums\VoicemailTranscriptState;
use App\Models\Voicemail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * business_id is deliberately absent — BelongsToTenant fills it from context.
 *
 * ⚠️ **NO TRANSCRIPT BY DEFAULT, AND THAT IS THE HONEST DEFAULT RATHER THAN A
 * GAP.** A fixture that always carries words is how *"never block on
 * transcription"* gets broken with a green suite — `VoicemailRecorded`'s own
 * warning: *the tell is that every test passes because the fake transcriber
 * always answers*. A test that wants a transcript asks for one.
 *
 * @extends Factory<Voicemail>
 */
final class VoicemailFactory extends Factory
{
    protected $model = Voicemail::class;

    public function definition(): array
    {
        return [
            'provider_file_id' => 'file-'.$this->faker->unique()->numerify('########'),
            'audio_state' => VoicemailAudioState::Pending,
            'transcript_state' => VoicemailTranscriptState::Pending,
        ];
    }

    public function stored(): self
    {
        return $this->state(fn (): array => [
            'audio_state' => VoicemailAudioState::Stored,
            'recording_path' => 'voicemail/1/call-00000001.wav',
            'recording_format' => 'wav',
            'recording_bytes' => 12_345,
            'recording_seconds' => 12,
        ]);
    }

    public function transcribed(string $transcript = 'Hello, please call me back.'): self
    {
        return $this->state(fn (): array => [
            'transcript' => $transcript,
            'transcript_engine' => 'fake',
            'transcript_confidence' => 0.9,
            'transcript_state' => VoicemailTranscriptState::Transcribed,
            'transcribed_at' => now(),
        ]);
    }
}
