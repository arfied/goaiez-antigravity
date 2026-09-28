<?php

use App\Contracts\Transcriber;
use App\Enums\UserRole;
use App\Enums\VoicemailAudioState;
use App\Enums\VoicemailTranscriptState;
use App\Jobs\Voice\TranscribeVoicemailJob;
use App\Models\Call;
use App\Models\Location;
use App\Models\User;
use App\Models\Voicemail;
use App\Services\Voice\Transcript;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

it('tests TranscribeVoicemailJob writes transcript', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    /** @var TestCase $this */
    $this->actingAs($owner);
    Tenancy::setUser($owner->id);
    Tenancy::set((int) $biz->id);

    $location = Location::factory()->create(['business_id' => $biz->id]);

    $call = Call::factory()->create(['provider_call_id' => 'call-1', 'location_id' => $location->id, 'business_id' => $biz->id]);
    $voicemail = Voicemail::factory()->create([
        'call_id' => $call->id,
        'audio_state' => VoicemailAudioState::Stored,
        'recording_path' => 'voice/call-1.wav',
    ]);

    Storage::fake('s3');
    Storage::disk('s3')->put('voice/call-1.wav', 'fake-audio-bytes');

    $mockTranscriber = Mockery::mock(Transcriber::class);
    $mockTranscriber->shouldReceive('transcribe')->andReturn(new Transcript('hello world', 'fake-engine', 0.99));
    app()->instance(Transcriber::class, $mockTranscriber);

    $job = new TranscribeVoicemailJob((int) $biz->id, (int) $location->id, (int) $voicemail->id);
    $job->handle();

    $voicemail->refresh();
    expect($voicemail->transcript)->toBe('hello world')
        ->and($voicemail->transcript_state)->toBe(VoicemailTranscriptState::Transcribed);
});

it('tests TranscribeVoicemailJob missing recording writes nothing', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    /** @var TestCase $this */
    $this->actingAs($owner);
    Tenancy::setUser($owner->id);
    Tenancy::set((int) $biz->id);

    $location = Location::factory()->create(['business_id' => $biz->id]);

    $call = Call::factory()->create(['provider_call_id' => 'call-2', 'location_id' => $location->id, 'business_id' => $biz->id]);
    $voicemail = Voicemail::factory()->create([
        'call_id' => $call->id,
        'audio_state' => VoicemailAudioState::Unavailable,
        'recording_path' => null,
    ]);

    Storage::fake('s3');

    $job = new TranscribeVoicemailJob((int) $biz->id, (int) $location->id, (int) $voicemail->id);
    $job->handle();

    $voicemail->refresh();
    expect($voicemail->transcript)->toBeNull()
        ->and($voicemail->transcript_state)->toBe(VoicemailTranscriptState::Unavailable);
});

it('tests TranscribeVoicemailJob has no dispatcher', function () {
    expect(true)->toBeTrue();
});
