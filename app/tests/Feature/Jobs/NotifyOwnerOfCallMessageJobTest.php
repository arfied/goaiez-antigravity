<?php

use App\Enums\UserRole;
use App\Jobs\Voice\NotifyOwnerOfCallMessageJob;
use App\Models\Call;
use App\Models\Location;
use App\Models\User;
use App\Notifications\CallMessageLeft;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

it('tells the owner a caller left a message, once, without the message or the number in the email', function () {
    // Fixture taken from NotifyOwnerOfVoicemailJobTest 'tests NotifyOwnerOfVoicemailJob sends notification'.
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

    Call::factory()->create([
        'provider_call_id' => 'livekit:SCL_9441',
        'location_id' => $location->id,
        'business_id' => $biz->id,
        'from_e164' => '+14155559442',
        'message_text' => 'Leak under the sink 9443.',
        'message_callback' => '+14155559444',
        'message_left_at' => now(),
    ]);

    app(DefaultsRegistry::class)->set('mail.daily_send_ceiling.smtp', 200, 'test');
    app(DefaultsRegistry::class)->set('mail.sending_domain', 'example.com', 'test');

    (new NotifyOwnerOfCallMessageJob((int) $biz->id, (int) $location->id, 'livekit:SCL_9441'))->handle();
    (new NotifyOwnerOfCallMessageJob((int) $biz->id, (int) $location->id, 'livekit:SCL_9441'))->handle();

    Notification::assertSentTimes(CallMessageLeft::class, 1);
    Notification::assertSentTo(new AnonymousNotifiable, CallMessageLeft::class, function (CallMessageLeft $notification): bool {
        $mail = $notification->toMail(new AnonymousNotifiable);
        $text = implode("\n", array_merge([(string) $mail->subject, (string) $mail->greeting], $mail->introLines, $mail->outroLines));

        return str_contains($text, 'AI receptionist')
            && ! str_contains($text, '9443')
            && ! str_contains($text, '9444')
            && ! str_contains($text, '9442');
    });

    expect(Call::query()->where('provider_call_id', 'livekit:SCL_9441')->value('message_notified_at'))->not->toBeNull();
});
