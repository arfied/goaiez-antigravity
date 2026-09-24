<?php

declare(strict_types=1);

namespace App\Services\Activity;

use App\Models\Business;
use App\Modules\X108\Actions\BookingReadAction;
use App\Modules\X137\Actions\CallAttributionReadAction;
use App\Services\Billing\Subscriptions;
use App\Services\Tenant\TenantSuspension;
use App\Services\Warehouse\SiteConversions;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;

/**
 * The monthly "what your website did" email. Same eligibility discipline as
 * OwnerDigest (age, cursor, suspension, entitlement); its own cursor column;
 * the window is the previous calendar month for a first send, and "since the
 * last send" after that — so nothing is ever double-reported and no month is
 * lost. Every number is a count of rows in the window; `visits` is null when
 * the pixel has never measured this tenant, and the email says so.
 */
final class MonthlyDigest
{
    public const EARLIEST_ELIGIBLE_AGE_DAYS = 35;

    public const MIN_DAYS_BETWEEN = 28;

    public function __construct(
        private readonly TenantSuspension $suspension,
        private readonly Subscriptions $subscriptions,
        private readonly SiteDigest $site,
        private readonly SiteConversions $conversions,
        private readonly CallAttributionReadAction $calls,
        private readonly BookingReadAction $bookings,
    ) {}

    public function eligible(Business $business): bool
    {
        Tenancy::idOrFail();
        $createdAt = $business->created_at;
        if ($createdAt === null || $createdAt->gt(now()->subDays(self::EARLIEST_ELIGIBLE_AGE_DAYS))) {
            return false;
        }
        $last = $business->owner_monthly_digest_sent_at;
        if ($last !== null && $last->gt(now()->subDays(self::MIN_DAYS_BETWEEN))) {
            return false;
        }
        if ($this->suspension->isSuspended($business)) {
            return false;
        }

        return $this->subscriptions->isEntitled($business);
    }

    /** @return array{from: CarbonImmutable, to: CarbonImmutable, label: string} */
    public function window(Business $business): array
    {
        $last = $business->owner_monthly_digest_sent_at;
        if ($last === null) {
            $from = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
            $to = $from->addMonth();

            return ['from' => $from, 'to' => $to, 'label' => $from->translatedFormat('F Y')];
        }
        $from = CarbonImmutable::instance($last);
        $to = CarbonImmutable::now();

        return ['from' => $from, 'to' => $to, 'label' => 'since '.$from->translatedFormat('j F')];
    }

    /** @return ?array{label: string, pages: list<array{title: string, times: int}>, visits: ?int, lines: list<string>} */
    public function compose(Business $business): ?array
    {
        Tenancy::idOrFail();
        $w = $this->window($business);
        $id = (int) $business->id;

        $pages = $this->site->pagesPublished($id, $w['from'], $w['to']);
        $allLines = $this->site->lines($id, $w['from'], $w['to']);

        $lines = [];
        foreach ($allLines as $line) {
            if (! str_contains($line, "page' published") && ! str_contains($line, 'pages published')) {
                $lines[] = $line;
            }
        }

        $calls = $this->calls->joinedSince($id, $w['from'], $w['to']);
        $booked = $this->bookings->bookedSince($id, $w['from'], $w['to']);
        if ($calls > 0) {
            $lines[] = 'Your website: '.$calls.' '.($calls === 1 ? 'call' : 'calls').' from your tracked number';
        }
        if ($booked > 0) {
            $lines[] = 'Your website: '.$booked.' '.($booked === 1 ? 'job' : 'jobs').' booked';
        }
        $reading = $this->conversions->rate($w['from'], $w['to']);
        $visits = $reading->isMeasured() ? $reading->sessions : null;

        if ($pages === [] && $lines === [] && $visits === null) {
            return null;
        }

        return ['label' => $w['label'], 'pages' => $pages, 'visits' => $visits, 'lines' => $lines];
    }
}
