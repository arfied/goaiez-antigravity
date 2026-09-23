<?php

declare(strict_types=1);

namespace App\Modules\CMail\Actions;

use App\Enums\MailEventType;
use App\Modules\CMail\Models\MailDomain;
use App\Modules\CMail\Models\MailEvent;
use Carbon\Carbon;

class ComplaintBounceSummaryAction
{
    /**
     * @return array<int, array{domain_name: string, sent: int, bounces: int, complaints: int, bounce_rate: float, complaint_rate: float, last_event_at: string|null}>
     */
    public function handle(int $businessId, int $days = 30): array
    {
        $since = Carbon::now()->subDays($days);
        $domains = MailDomain::where('business_id', $businessId)->get();

        $result = [];

        foreach ($domains as $domain) {
            $events = MailEvent::where('mail_domain_id', $domain->id)
                ->where('created_at', '>=', $since)
                ->get();

            $sent = 0;
            $bounces = 0;
            $complaints = 0;
            $lastEventAt = null;

            foreach ($events as $event) {
                if ($event->event_type === MailEventType::Sent->value) {
                    $sent++;
                } elseif ($event->event_type === MailEventType::Bounced->value) {
                    $bounces++;
                } elseif ($event->event_type === MailEventType::Complained->value) {
                    $complaints++;
                }

                if ($lastEventAt === null || $event->created_at > $lastEventAt) {
                    $lastEventAt = $event->created_at;
                }
            }

            $bounceRate = $sent > 0 ? round(($bounces / $sent) * 100, 2) : 0.0;
            $complaintRate = $sent > 0 ? round(($complaints / $sent) * 100, 2) : 0.0;

            $result[] = [
                'domain_name' => $domain->domain_name,
                'sent' => $sent,
                'bounces' => $bounces,
                'complaints' => $complaints,
                'bounce_rate' => $bounceRate,
                'complaint_rate' => $complaintRate,
                'last_event_at' => $lastEventAt ? $lastEventAt->toIso8601String() : null,
            ];
        }

        return $result;
    }
}
