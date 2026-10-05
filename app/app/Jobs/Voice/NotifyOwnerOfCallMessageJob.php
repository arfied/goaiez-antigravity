<?php

declare(strict_types=1);

namespace App\Jobs\Voice;

use App\Enums\AutopilotActionType;
use App\Jobs\AutopilotJob;
use App\Models\Business;
use App\Models\Call;
use App\Models\User;
use App\Notifications\CallMessageLeft;
use App\Services\Mail\PlatformMailer;
use App\Support\Tenancy;

/**
 * Tell the owner a caller left a message with their AI receptionist (AI receptionist plan, wave 3c) — the voicemail job's
 * shape ({@see NotifyOwnerOfVoicemailJob}), once per call.
 *
 * ⚠️ The email says a message is waiting and where; it carries neither the message nor the caller's number, for the reason
 * the voicemail email gives — those stay on the calls page, behind the owner's login.
 */
final class NotifyOwnerOfCallMessageJob extends AutopilotJob
{
    private bool $notified = false;

    public function __construct(
        int $businessId,
        ?int $locationId,
        public readonly string $providerCallId,
    ) {
        parent::__construct($businessId, $locationId);
    }

    public function automationKey(): string
    {
        return 'voice.call_message.notify_owner';
    }

    protected function idempotencyKey(): string
    {
        return 'call-message-notify:'.$this->providerCallId;
    }

    protected function claimIsSpent(): bool
    {
        return $this->notified;
    }

    protected function execute(): array
    {
        return $this->mailOwner();
    }

    protected function handoff(): array
    {
        return $this->mailOwner();
    }

    /**
     * @return array<string, mixed>
     */
    private function mailOwner(): array
    {
        $call = Call::query()->where('provider_call_id', $this->providerCallId)->first();

        if (! $call instanceof Call || $call->message_text === null) {
            $this->notified = true;

            return ['notified' => false, 'reason' => 'no_message'];
        }

        if ($call->message_notified_at !== null) {
            $this->notified = true;

            return ['notified' => false, 'reason' => 'already_notified'];
        }

        $owner = Business::query()->find(Tenancy::idOrFail())?->owner;

        if (! $owner instanceof User) {
            return ['notified' => false, 'reason' => 'no_owner'];
        }

        $address = trim((string) $owner->email);

        if ($address === '') {
            return ['notified' => false, 'reason' => 'no_address'];
        }

        app(PlatformMailer::class)->deliverNow($address, new CallMessageLeft);

        $call->forceFill(['message_notified_at' => now()])->save();

        $this->notified = true;

        return ['notified' => true];
    }

    protected function activityAction(): ?AutopilotActionType
    {
        return null;
    }

    protected function input(): array
    {
        return array_merge(parent::input(), ['provider_call_id' => $this->providerCallId]);
    }
}
