<?php

declare(strict_types=1);

namespace App\Doctor\Stages;

/**
 * STAGE ⑥ THE TWELVE JOURNEYS — end to end, on real transports, FAILS THE WAVE.
 *
 * ⛔ A module passing its own gate says nothing about whether a plumber can be
 * called, quoted and booked. The journeys are the only check that crosses every
 * seam at once, which is why they gate the WAVE and not the merge.
 *
 * ⭐ The first journey is the whole product in sixty seconds: a missed call
 * becomes a text back. Everything else is elaboration.
 */
final class JourneyStage implements Stage
{
    /** @var array<string, string> */
    private const JOURNEYS = [
        'missed-call-textback' => 'a call is missed, a consented text arrives with the carrier\'s own id',
        'day-one' => 'two fields at signup → the agent live on a number → the owner calls their own business',
        'quote-to-booking' => 'a price LOOKED UP from the pricebook, never invented, becomes a booking',
        'invoice-to-paid' => 'an invoice reaches a real charge-id',
        'review-invite' => 'a completed job asks for a review, once, inside the cadence ceiling',
        'inbound-consent' => 'STOP halts every pending step for that Person within one cycle',
        'site-publish' => 'a published site carries all seven — pixel, chat, form, DNI, SEO, schema, SSL',
        'migration-in' => '500 imported jobs produce ZERO outbound messages',
        'dunning-by-reason' => 'an overdue invoice is chased by REASON, and a resolution attempt precedes any stop',
        'cancel' => 'cancel is ONE TAP with no interstitial between the tap and the cancellation',
        'agency-isolation' => 'the agency sees cost and margin; their client sees the agency price only',
        'restore' => 'a rehearsed restore verified by row count and checksum — and a corrupted backup FAILS it',
    ];

    public function run(): array
    {
        $out = [];

        foreach (self::JOURNEYS as $slug => $what) {
            $file = storage_path("app/evidence/journeys/{$slug}.json");

            if (! is_file($file)) {
                $out[] = ['where' => $slug, 'what' => "not run — {$what}",
                    'fix' => "run the journey against real transports and write evidence/journeys/{$slug}.json"];

                continue;
            }

            /** @var array{passed?: bool, artifact_id?: string, elapsed_ms?: int} $r */
            $r = json_decode((string) file_get_contents($file), true) ?: [];

            if (($r['passed'] ?? false) !== true) {
                $out[] = ['where' => $slug, 'what' => "FAILED — {$what}",
                    'fix' => 'the journey crosses every seam; a failure here is a wave failure, not a module one'];

                continue;
            }

            // ⛔ A journey that "passed" with no external artifact proved nothing
            //    left the building — the same forgery the anchor stage exists for.
            if (($r['artifact_id'] ?? '') === '') {
                $out[] = ['where' => $slug, 'what' => 'passed with no external artifact id',
                    'fix' => 'a journey over real transports mints a real id; without one it is a simulation'];
            }

            // ⛔⛔ R227 — NO DURATION IS EVER ASSERTED. There is no sixty-second
            //    guarantee, no response-time SLA, and nothing time-based is
            //    promised to a customer or gated here. `elapsed_ms` is recorded
            //    in the evidence for a human to read; doctor does not judge it.
        }

        return $out;
    }
}
