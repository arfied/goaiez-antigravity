<?php

declare(strict_types=1);

namespace App\Services\Voice\Live;

use App\Enums\LiveAnswerMode;
use App\Enums\LiveCallDecline;
use App\Models\Business;
use App\Models\Call;
use App\Services\Agent\AgentRules;
use App\Services\Ai\AiSpend;
use App\Services\Sms\TenantNumbers;
use App\Services\Voice\CallForwarding;
use App\Services\Voice\RecordingAnnouncement;
use App\Services\Voice\VoiceCalls;
use App\Services\Voice\VoiceGreeting;
use App\Services\Voice\VoiceSpend;
use App\Support\Tenancy;

/**
 * May the AI receptionist answer this ring, and with what first words (AI receptionist plan, wave 2, 2026-10-05).
 *
 * The tenant is the owner of the DIALLED number (`TenantNumbers::tenantFor()`), never a field the worker sends. Then, in
 * order, each of these declines the call and the worker takes a message instead:
 *  - no operator has attested the recording announcement ({@see RecordingAnnouncement::isAttested()});
 *  - the number belongs to nobody;
 *  - the business chose to be rung first (owner ruling D-6) — not built until wave 5;
 *  - its AI spend ceiling refuses ({@see AiSpend::allows()});
 *  - its inbound minutes today crossed the daily ceiling ({@see VoiceSpend::allowsLiveAnswer()}).
 *
 * Otherwise the call is written ({@see VoiceCalls::answerLive()}) and the worker is handed a call token and the first
 * words: the recording announcement, uninterruptible and always first, then the AI disclosure. A retry for a call already
 * answered gets the same call back without asking the gates again — the decision was made when the caller was picked up.
 *
 * With the first words come the instructions the receptionist speaks under ({@see AgentRules::forVoiceCall()}, the SMS
 * assistant's never-list with a voice price rule) and the facts it may use, kept apart so the worker fences the facts as data.
 * ⛔ No price list is ever in what this returns: prices on a call are only ever a tool call into X-163 (wave 3).
 */
final class CallStart
{
    public function __construct(
        private readonly TenantNumbers $numbers,
        private readonly RecordingAnnouncement $announcement,
        private readonly CallForwarding $forwarding,
        private readonly VoiceSpend $spend,
        private readonly VoiceCalls $calls,
        private readonly LiveCallTokens $tokens,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(string $dialled, string $from, string $transportCallId): array
    {
        if (! $this->announcement->isAttested()) {
            return $this->decline(LiveCallDecline::AnnouncementNotAttested);
        }

        $businessId = $this->numbers->tenantFor($dialled);

        if ($businessId === null) {
            return $this->decline(LiveCallDecline::UnknownNumber);
        }

        return Tenancy::actingAs($businessId, function () use ($businessId, $dialled, $from, $transportCallId): array {
            $call = Call::query()
                ->where('provider_call_id', VoiceCalls::LIVE_PREFIX.$transportCallId)
                ->first();

            if (! $call instanceof Call) {
                if ($this->forwarding->liveAnswerFor() === LiveAnswerMode::OwnerFirst) {
                    return $this->decline(LiveCallDecline::OwnerFirstNotBuilt);
                }

                if (! app(AiSpend::class)->allows()) {
                    return $this->decline(LiveCallDecline::AiSpendRefused);
                }

                if (! $this->spend->allowsLiveAnswer($businessId)) {
                    return $this->decline(LiveCallDecline::DailyMinutesCeiling);
                }

                $call = $this->calls->answerLive($businessId, $transportCallId, $from, $dialled);
            }

            $name = trim((string) Business::query()->whereKey($businessId)->value('name'));

            return [
                'status' => 'answer',
                'call_token' => $this->tokens->mint($businessId, (int) $call->getKey()),
                // First, whole, and never interrupted — the same sentence every recorded call plays (2104).
                'announcement' => VoiceGreeting::ANNOUNCEMENT,
                // The caller is told they are speaking to an AI before anything else is said to them.
                'disclosure' => "You've reached {$name}. I'm their AI assistant — how can I help?",
                'instructions' => AgentRules::forVoiceCall(),
                // Tenant-typed, so it travels apart from the instructions and the worker fences it as data.
                'facts' => ['business_name' => $name],
            ];
        });
    }

    /**
     * @return array<string, string>
     */
    private function decline(LiveCallDecline $reason): array
    {
        return ['status' => 'declined', 'reason' => $reason->value];
    }
}
