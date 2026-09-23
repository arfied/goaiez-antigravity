<?php

$content = file_get_contents('app/Support/DefaultsManifest.php');

$start = strpos($content, "'reviews.request.cadence_window_days' =>");
$end = strpos($content, "'crm.lead_score.tier_hot' =>");

$newBlock = <<<'TEXT'
'reviews.request.cadence_window_days' => [
                'seed' => \App\Modules\CReviews\Actions\ReviewRequestAction::CADENCE_WINDOW_DAYS,
                'group' => 'Reviews',
            ],
            'reviews.request.low_csat_below' => [
                'seed' => \App\Modules\CReviews\Actions\ReviewRequestAction::LOW_CSAT_BELOW,
                'group' => 'Reviews',
            ],
            'reviews.request.low_csat_job_age_days' => [
                'seed' => \App\Modules\CReviews\Actions\ReviewRequestAction::LOW_CSAT_JOB_AGE_DAYS,
                'group' => 'Reviews',
            ],
            'billing.card.expiring_warning_days' => [
                'seed' => \App\Modules\X120\Actions\CardExpiringScanAction::EXPIRING_WARNING_DAYS,
                'group' => 'Billing',
            ],
            'alerts.claim.expiry_minutes' => [
                'seed' => \App\Modules\X153\Actions\AlertSendAction::CLAIM_EXPIRY_MINUTES,
                'group' => 'Operations',
            ],
            'approvals.expiry_hours' => [
                'seed' => \App\Modules\X202\Domain\ApprovalDeskEngine::EXPIRY_HOURS,
                'group' => 'Operations',
            ],
            'portal.link.ttl_hours' => [
                'seed' => \App\Modules\X172\Actions\PortalLinkAction::TTL_HOURS,
                'group' => 'Messaging',
            ],
            'qa.ticket.sla_hours' => [
                'seed' => \App\Modules\X181\Actions\QaTicketCreateAction::SLA_HOURS,
                'group' => 'Operations',
            ],
            'attribution.call.ttl_minutes' => [
                'seed' => \App\Modules\X137\Actions\CallAttributeAction::TTL_MINUTES,
                'group' => 'Marketing',
            ],
            
TEXT;

$content = substr($content, 0, $start).$newBlock.'            '.substr($content, $end);
file_put_contents('app/Support/DefaultsManifest.php', $content);
