<?php

use App\Enums\UserRole;
use App\Enums\VoiceEventType;
use App\Enums\VoicemailAudioState;
use App\Events\Voice\VoicemailRecorded;
use App\Jobs\Voice\NotifyOwnerOfVoicemailJob;
use App\Listeners\Voice\NotifyOwnerOfVoicemail;
use App\Models\Call;
use App\Models\Location;
use App\Models\User;
use App\Models\Voicemail;
use App\Notifications\VoicemailReceived;
use App\Services\Config\DefaultsRegistry;
use App\Services\Voice\InboundCall;
use App\Support\Tenancy;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

it('tests NotifyOwnerOfVoicemailJob sends notification', function () {
    Mail::fake();
    Notification::fake();
    config(['mail.default' => 'smtp', 'mail.from.address' => 'noreply@example.com']);

    $owner = User::factory()->create(['role' => UserRole::Owner, 'email' => 'owner@example.com']);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    /** @var TestCase $this */
    $this->actingAs($owner);
    Tenancy::setUser($owner->id);
    Tenancy::set((int) $biz->id);

    $location = Location::factory()->create(['business_id' => $biz->id]);

    $call = Call::factory()->create(['provider_call_id' => 'infobip-call-123', 'location_id' => $location->id, 'business_id' => $biz->id]);
    Voicemail::factory()->create([
        'call_id' => $call->id,
        'audio_state' => VoicemailAudioState::Stored,
        'notified_at' => null,
    ]);

    app(DefaultsRegistry::class)->set('mail.daily_send_ceiling.smtp', 200, 'test');
    app(DefaultsRegistry::class)->set('mail.sending_domain', 'example.com', 'test');

    $job = new NotifyOwnerOfVoicemailJob((int) $biz->id, (int) $location->id, 'infobip-call-123');
    $job->handle();

    Notification::assertSentTo(new AnonymousNotifiable, VoicemailReceived::class);
});

it('tests NotifyOwnerOfVoicemailJob already notified', function () {
    Mail::fake();
    Notification::fake();
    config(['mail.default' => 'smtp', 'mail.from.address' => 'noreply@example.com']);

    $owner = User::factory()->create(['role' => UserRole::Owner, 'email' => 'owner@example.com']);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    /** @var TestCase $this */
    $this->actingAs($owner);
    Tenancy::setUser($owner->id);
    Tenancy::set((int) $biz->id);

    $location = Location::factory()->create(['business_id' => $biz->id]);

    $call = Call::factory()->create(['provider_call_id' => 'infobip-call-456', 'location_id' => $location->id, 'business_id' => $biz->id]);
    Voicemail::factory()->create([
        'call_id' => $call->id,
        'audio_state' => VoicemailAudioState::Stored,
        'notified_at' => now(),
    ]);

    $job = new NotifyOwnerOfVoicemailJob((int) $biz->id, (int) $location->id, 'infobip-call-456');
    $job->handle();

    Notification::assertNothingSent();
});

it('tests NotifyOwnerOfVoicemailJob is dispatched', function () {
    Queue::fake();

    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    $location = Location::factory()->create(['business_id' => $biz->id]);

    $call = Call::factory()->create(['provider_call_id' => 'infobip-call-123', 'location_id' => $location->id, 'business_id' => $biz->id]);

    $inboundCall = new InboundCall(
        type: VoiceEventType::VoicemailRecorded,
        providerCallId: 'infobip-call-123',
        numberId: 1,
        from: '+1234567890',
        to: '+0987654321',
        occurredAt: now()->toImmutable()
    );

    $event = new VoicemailRecorded($biz->id, $inboundCall, 'path/to/recording.mp3');
    app(NotifyOwnerOfVoicemail::class)->handle($event);

    Queue::assertPushed(NotifyOwnerOfVoicemailJob::class);
});
