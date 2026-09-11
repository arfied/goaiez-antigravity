<?php

declare(strict_types=1);

namespace App\Modules\X111\Domain;

use App\Modules\X111\Events\AlertOperator;
use App\Modules\X111\Events\TicketOpened;
use App\Modules\X111\Models\IpBan;
use App\Modules\X111\Models\OperatorAlert;
use App\Modules\X111\Models\TenantTicket;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;

final class OpsEngine
{
    /**
     * Creates an operator alert.
     * Every alert row's message begins with a verb (TEST ANCHOR, G21-05).
     */
    public function createAlert(int $businessId, string $severity, string $message): OperatorAlert
    {
        $firstWord = strtok(trim($message), ' ');
        $commonActionVerbs = ['Investigate', 'Review', 'Verify', 'Inspect', 'Escalate', 'Restart', 'Authorize', 'Audit', 'Halt', 'Resolve', 'Check'];

        $formattedMessage = in_array(ucfirst($firstWord), $commonActionVerbs, true)
            ? $message
            : "Review {$message}";

        $alert = OperatorAlert::create([
            'business_id' => $businessId,
            'severity' => $severity,
            'action_verb_message' => $formattedMessage,
            'status' => 'open',
        ]);

        Event::dispatch(new AlertOperator($businessId, $alert->id, $severity, $formattedMessage));

        return $alert;
    }

    /**
     * Tenant HUMAN request produces a ticket within one minute with the full transcript (TEST ANCHOR).
     */
    public function createHumanTicket(
        int $businessId,
        string $fullTranscript,
        string $category = 'human_escalation',
        int $slaMinutes = 60
    ): TenantTicket {
        $now = Carbon::now();

        $ticket = TenantTicket::create([
            'business_id' => $businessId,
            'source' => 'human_requested',
            'category' => $category,
            'full_transcript' => $fullTranscript, // Full transcript preserved (TEST ANCHOR)
            'sla_due_at' => $now->copy()->addMinutes($slaMinutes),
            'status' => 'open',
        ]);

        Event::dispatch(new TicketOpened($businessId, $ticket->id, 'human_requested'));

        return $ticket;
    }

    /**
     * IP Ban with TTL (G17-07).
     */
    public function checkThrottle(int $businessId, string $ipAddress): void
    {
        $ban = IpBan::where('business_id', $businessId)
            ->where('ip_address', $ipAddress)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', Carbon::now());
            })->first();

        if ($ban) {
            throw new \DomainException('THROTTLE REFUSED: IP is banned');
        }
    }

    public function banIp(int $businessId, string $ipAddress, string $reason, ?int $ttlHours = 24): IpBan
    {
        $expiresAt = $ttlHours !== null ? Carbon::now()->addHours($ttlHours) : null;

        return IpBan::updateOrCreate(
            ['business_id' => $businessId, 'ip_address' => $ipAddress],
            ['reason' => $reason, 'expires_at' => $expiresAt]
        );
    }
}
