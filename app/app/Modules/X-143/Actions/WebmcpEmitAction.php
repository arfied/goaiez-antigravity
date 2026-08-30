<?php

declare(strict_types=1);

namespace App\Modules\X143\Actions;

use App\Modules\X121\Models\Job;
use App\Modules\X143\Events\WebmcpInvoked;
use Illuminate\Support\Facades\Event;

final class WebmcpEmitAction
{
    /**
     * Emits WebMCP HTML markup for published pages (TEST ANCHOR).
     */
    public function emit(int $businessId, bool $isWebmcpLive = false): string
    {
        // 1. With flag dark: published page contains ZERO modelContext markup (TEST ANCHOR)
        if (! $isWebmcpLive) {
            return '';
        }

        // 2. With flag live: outputs modelContext markup
        $contracts = [
            'booking' => [
                'name' => 'book_appointment',
                'description' => 'Book service appointment',
                'parameters' => ['service', 'datetime', 'customer_name'],
            ],
        ];

        return '<meta name="modelContext" content="'.htmlspecialchars(json_encode($contracts), ENT_QUOTES, 'UTF-8').'">';
    }

    /**
     * Invokes booking contract by browser agent, creating same Job row with actor_type: webmcp (TEST ANCHOR).
     */
    public function invokeBooking(int $businessId, array $bookingParams): Job
    {
        $job = Job::create([
            'business_id' => $businessId,
            'title' => $bookingParams['title'] ?? 'WebMCP Direct Service Booking',
            'description' => $bookingParams['description'] ?? 'Automated booking contract execution via Browser Agent WebMCP',
            'status' => 'pending',
            'price_cents' => $bookingParams['price_cents'] ?? 15000,
            'actor_type' => 'webmcp', // TEST ANCHOR
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'available_at' => time(),
            'created_at' => time(),
        ]);

        Event::dispatch(new WebmcpInvoked($businessId, 'book_appointment', $job->id, 'webmcp'));

        return $job;
    }
}
