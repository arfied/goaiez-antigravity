<?php

declare(strict_types=1);

namespace Tests\Journeys;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * THE TWELVE JOURNEYS — end to end, on real transports.
 *
 * ⛔⛔ EVERY OTHER CHECK IN THIS PROGRAMME IS PER-MODULE. A module passing its
 * own gate says nothing about whether a plumber can be called, quoted and
 * booked. These are the only checks that cross a seam, which is why they fail
 * the WAVE and not the merge.
 *
 * ⛔⛔⛔ R227 — THERE IS NO TIME GUARANTEE, AND NO JOURNEY ASSERTS A DURATION.
 *
 * The plan's one surviving mention says the text-back logic lives "in our code
 * instead of the phone company's". That is about WHERE THE LOGIC LIVES — our text-back path
 * rather than a carrier's forwarding rule. It was never a duration owed to
 * anybody. An earlier version of this file turned it into a threshold assertion,
 * which is an architectural note promoted to a customer promise.
 *
 * ⭐ Timing is RECORDED so a human can see the trend. It is never GATED. A
 * number in a log is information; the same number in an assertion is a promise
 * nobody agreed to make.
 *
 * ⛔ Each journey writes storage/app/evidence/journeys/<slug>.json, which
 * doctor's JourneyStage reads. A journey that "passed" with no external
 * artifact id proves nothing left the building — the same forgery the anchor
 * stage exists for.
 *
 * Run: php artisan test --group=journeys
 */
#[Group('journeys')]
final class TwelveJourneysTest extends TestCase
{
    // ⛔⛔ Thirty helpers were called here and NEVER DEFINED — the suite would
    //    have died with a fatal error before the first assertion, and "the
    //    journeys are written" would have been true and worthless at once.
    //
    // ⭐ Every harness method THROWS until implemented against the real
    //   transport. Returning plausible fixtures instead would make all twelve
    //   journeys pass while touching nothing.
    use JourneyHarness;

    // ═══════════════════════════════════════════════════════════════════
    // ① THE WHOLE PRODUCT IN SIXTY SECONDS
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function a_missed_call_becomes_a_consented_text_back(): void
    {
        $this->assertQueueIsNotSync();

        $tenant = $this->tenantWithLiveNumber();
        $started = microtime(true);

        // The caller hangs up. Nothing about this is synchronous — the webhook
        // returns immediately and the work is queued, which is exactly why a
        // sync-driver run would prove nothing.
        $this->postCarrierWebhook($tenant, event: 'call.missed', from: '+15550123');

        $message = $this->waitForOutbound($tenant, to: '+15550123', timeoutSeconds: 90);
        $elapsedMs = (int) ((microtime(true) - $started) * 1000);

        $this->assertNotNull($message, 'No text-back was sent.');

        // ⛔ The vendor's id. Nothing in this system can mint one, which is the
        //    only reason this line means anything.
        $this->assertNotEmpty(
            $message['provider_message_id'] ?? '',
            'The text-back has no carrier message-id. It did not leave the building.'
        );

        // ⭐ The consent check is not a formality here: a text-back to someone who
        //    has STOPped is the one message that must never send, and the missed
        //    call is precisely when a system is most tempted to skip the check.
        $this->assertTrue(
            $this->consentWasCheckedFor('+15550123'),
            'The text-back sent without passing ConsentService::decide().'
        );

        $this->writeEvidence('missed-call-textback', [
            'passed' => true,
            'artifact_id' => $message['provider_message_id'],
            'elapsed_ms' => $elapsedMs,
        ]);

        // ⛔ R227: elapsed_ms is written to evidence above and asserted NOWHERE.
        //    No test, journey or gate compares a duration to a constant.
    }

    // ═══════════════════════════════════════════════════════════════════
    // ② DAY ONE — two fields to a live agent
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function two_fields_at_signup_put_a_live_agent_on_a_real_number(): void
    {
        $this->assertQueueIsNotSync();

        // ⛔ P-207: signup is a business name and a phone number. A third field
        //    fails the build. Passing only two here is the assertion.
        $tenant = $this->signUp(businessName: 'Journey Plumbing', phone: '+15550777');

        $number = $this->waitForProvisionedNumber($tenant, timeoutSeconds: 120);
        $this->assertNotEmpty($number, 'No number was provisioned. Day one did not happen.');

        // ⭐ The owner calling their own business is the moment the product becomes
        //   real to them, and it is the only proof that matters.
        $call = $this->placeRealCallTo($number);
        $this->assertTrue($call['answered'] ?? false, 'The agent did not answer.');

        // ⛔ And it must not quote. X-163 owns price.callout_fee and nothing has
        //   been confirmed yet — an agent that invents a price on day one is the
        //   worst possible first impression.
        $this->assertFalse(
            $call['quoted_a_price'] ?? false,
            'The agent quoted a price with an empty pricebook. P-092: looked up or REFUSED.'
        );

        $this->writeEvidence('day-one', ['passed' => true, 'artifact_id' => $call['call_sid']]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // ③ A PRICE IS LOOKED UP, NEVER INVENTED
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function a_quote_comes_from_the_pricebook_or_does_not_come_at_all(): void
    {
        $tenant = $this->tenantWithLiveNumber();

        // Empty pricebook → the agent must refuse, with a code.
        $refusal = $this->askAgent($tenant, 'how much to unblock a drain?');
        $this->assertSame('NO_FACT', $refusal['refusal_code'] ?? null,
            'With no price row the agent must refuse with NO_FACT (X-126), not improvise.');

        // One confirmed price → it quotes THAT price, to the cent.
        $this->confirmPrice($tenant, 'drain-unblock', 18_500_00);
        $quote = $this->askAgent($tenant, 'how much to unblock a drain?');

        $this->assertSame(18_500_00, $quote['amount'] ?? null,
            'The quote did not match the pricebook row exactly.');

        $booking = $this->bookFromQuote($tenant, $quote);
        $this->assertNotEmpty($booking['job_id'] ?? '');

        $this->writeEvidence('quote-to-booking', ['passed' => true, 'artifact_id' => $booking['job_id']]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // ④ STOP HALTS EVERYTHING, WITHIN ONE CYCLE
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function stop_halts_every_pending_step_for_that_person(): void
    {
        $this->assertQueueIsNotSync();

        $tenant = $this->tenantWithLiveNumber();
        $person = $this->personWithPendingSteps($tenant, count: 5);

        $this->receiveInbound($tenant, from: $person['phone'], body: 'STOP');
        $this->drainQueueOnce();

        // ⛔ Not "no new steps are scheduled" — NOTHING ALREADY QUEUED MAY FIRE.
        //    A campaign step enqueued before the STOP is the one that gets sent
        //    and the one that costs the tenant a complaint.
        $this->assertSame(0, $this->pendingStepsFor($person),
            'Steps queued BEFORE the STOP were still pending after it. Consent must halt what is already in flight.');

        $this->assertSame(0, $this->outboundSince($person, 'STOP'),
            'A message was sent to a person who had STOPped.');

        $this->writeEvidence('inbound-consent', [
            'passed' => true,
            'artifact_id' => $person['consent_decision_id'],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // ⑤ 500 IMPORTED JOBS PRODUCE ZERO OUTBOUND
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function a_migration_of_five_hundred_jobs_sends_nothing(): void
    {
        $this->assertQueueIsNotSync();

        $tenant = $this->tenantWithLiveNumber();
        $before = $this->totalOutbound($tenant);

        // ⛔⛔ P-203. Historical jobs look exactly like completed jobs, and a
        //     completed job triggers a review invite. Importing a year of history
        //     would text 500 people about work finished last March — the single
        //     most destructive thing a migration can do, and it looks like the
        //     system working correctly.
        $this->importJobs($tenant, count: 500, historical: true);
        $this->drainQueue();

        $this->assertSame(
            $before,
            $this->totalOutbound($tenant),
            'A historical import produced outbound messages. P-203: imported history NEVER triggers a send.'
        );

        $this->writeEvidence('migration-in', [
            'passed' => true,
            'artifact_id' => $this->lastImportBatchId($tenant),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // ⑥ CANCEL IS ONE TAP
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function cancel_is_one_tap_with_nothing_in_between(): void
    {
        $tenant = $this->tenantWithLiveNumber();

        $flow = $this->walkCancelFlow($tenant);

        // ⛔⛔ READ R34 BEFORE CHANGING THIS. An earlier version of this test
        //    asserted that NO save-offer may be shown. That is stricter than the
        //    law and would have failed a correct implementation.
        //
        // ⭐ R34, reconciled, says: "a save-offer may add NO STEP — one screen,
        //   both choices, cancel always one tap." The offer is ALLOWED. What is
        //   forbidden is a STEP between the tap and the cancellation.
        //
        // ⭐⭐ So the assertion is SCREEN COUNT, not offer presence — because
        //    "we only show one small offer" is how a dark pattern starts, and
        //    screen count is the thing that cannot be argued about.
        $this->assertSame(1, $flow['screens_between'] ?? -1,
            'A step was added between the cancel tap and the cancellation. R34: a save-offer may add NO STEP.');

        $this->assertTrue($flow['cancelled'] ?? false, 'The cancellation did not complete.');

        // ⭐ If an offer IS shown, both choices must live on that same screen and
        //   cancel must still be one tap from it.
        if (($flow['retention_offer_shown'] ?? false) === true) {
            $this->assertTrue($flow['both_choices_on_one_screen'] ?? false,
                'A save-offer was shown on its own screen. R34: one screen, both choices.');
            $this->assertSame(1, $flow['taps_to_cancel'] ?? -1,
                'Cancel was more than one tap from the offer screen.');
        }

        $this->writeEvidence('cancel', ['passed' => true, 'artifact_id' => $flow['cancellation_id']]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // ⑦ THE AGENCY SEES MARGIN; THE CLIENT DOES NOT
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function an_agency_client_never_sees_cost_or_margin(): void
    {
        [$agency, $client] = $this->agencyWithClient();

        $agencyView = $this->billingView(as: $agency, of: $client);
        $this->assertArrayHasKey('cost', $agencyView);
        $this->assertArrayHasKey('margin', $agencyView);

        $clientView = $this->billingView(as: $client, of: $client);

        // ⛔ Not "the UI hides it" — the PAYLOAD must not contain it. A field
        //    hidden by a template is a field one API call away from a client who
        //    now knows their agency's markup.
        foreach (['cost', 'margin', 'markup', 'platform_price'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $clientView,
                "The client's payload carried '{$forbidden}'. Hiding it in the UI is not isolation.");
        }

        $this->writeEvidence('agency-isolation', [
            'passed' => true,
            'artifact_id' => $clientView['invoice_id'] ?? '',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // ⑧ A RESTORE THAT A CORRUPT BACKUP FAILS
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function a_deliberately_corrupted_backup_fails_the_restore(): void
    {
        $good = $this->takeBackup();
        $result = $this->restoreAndVerify($good);

        $this->assertTrue($result['verified'] ?? false, 'A good backup failed verification.');
        $this->assertSame($result['expected_rows'], $result['actual_rows']);

        // ⭐⭐ THE HALF THAT MATTERS. An untested restore is a hope, and a restore
        //    test that cannot FAIL is not a test — it is a ritual. Corrupt the
        //    backup on purpose and demand that verification catches it.
        $corrupt = $this->corruptBackup($good);
        $bad = $this->restoreAndVerify($corrupt);

        $this->assertFalse($bad['verified'] ?? true,
            'A CORRUPTED backup passed verification. The restore check proves nothing.');

        $this->writeEvidence('restore', ['passed' => true, 'artifact_id' => $good['backup_id']]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // ⑨–⑫ THE REMAINING FOUR — written in FULL, expected RED until their
    //      dependencies exist.
    //
    // ⛔⛔ These were markTestIncomplete() and that was the wrong call. A skipped
    // test is invisible in a green run; a FAILING test names what is missing
    // every single time the suite runs. The runtime proofs already work this
    // way on purpose — "a green suite that proves nothing is worse than a red
    // one that proves something."
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function an_invoice_reaches_a_real_charge_id(): void
    {
        $tenant = $this->tenantWithLiveNumber();
        $invoice = $this->issueInvoice($tenant, amountMinor: 12_500);

        $charge = $this->payInvoice($invoice);

        // ⛔ The gateway's own id. Nothing here can mint one, which is the only
        //    reason this assertion means anything.
        $this->assertNotEmpty(
            $charge['gateway_charge_id'] ?? '',
            'The invoice was marked paid with no gateway charge id — no money moved.'
        );

        $this->assertSame('paid', $this->invoiceStatus($invoice));
        $this->writeEvidence('invoice-to-paid', [
            'passed' => true,
            'artifact_id' => $charge['gateway_charge_id'],
        ]);
    }

    #[Test]
    public function a_completed_job_asks_for_a_review_once_inside_the_cadence(): void
    {
        $this->assertQueueIsNotSync();

        $tenant = $this->tenantWithLiveNumber();
        $person = $this->personWithPendingSteps($tenant, count: 0);

        $this->completeJob($tenant, $person);
        $this->drainQueue();

        $invites = $this->reviewInvitesFor($person);
        $this->assertCount(1, $invites, 'A completed job must ask ONCE — not zero, not twice.');

        // ⭐ Completing a second job must NOT produce a second invite inside the
        //   cadence window. Asking twice is how a tenant's number gets reported.
        $this->completeJob($tenant, $person);
        $this->drainQueue();

        $this->assertCount(1, $this->reviewInvitesFor($person),
            'A second completed job inside the cadence window produced a SECOND invite.');

        $this->writeEvidence('review-invite', [
            'passed' => true,
            'artifact_id' => $invites[0]['provider_message_id'] ?? '',
        ]);
    }

    #[Test]
    public function a_published_site_carries_all_seven(): void
    {
        $tenant = $this->tenantWithLiveNumber();
        $site = $this->publishSite($tenant);

        // ⛔ The plan: "every site ships with pixel · identity graph · chat AI ·
        //    form capture · DNI · SEO · schema · SSL". Seven asserted here.
        foreach (['pixel', 'chat', 'form_capture', 'dni', 'seo', 'schema', 'ssl'] as $feature) {
            $this->assertTrue(
                $site['features'][$feature] ?? false,
                "A published site shipped WITHOUT {$feature}. Every site carries all seven."
            );
        }

        $this->writeEvidence('site-publish', ['passed' => true, 'artifact_id' => $site['deploy_id'] ?? '']);
    }

    #[Test]
    public function an_overdue_invoice_is_chased_by_reason_and_resolution_precedes_any_stop(): void
    {
        $this->assertQueueIsNotSync();

        $tenant = $this->tenantWithLiveNumber();
        $invoice = $this->issueInvoice($tenant, amountMinor: 40_000);
        $this->makeOverdue($invoice);
        $this->drainQueue();

        $chase = $this->lastDunningAction($invoice);

        // ⭐ R211: "ALWAYS TRY TO RESOLVE BEFORE ANY AUTOMATIC SUSPENSION." The
        //   law generalises a permission into a SEQUENCE and reaches every
        //   automatic stop — dunning included.
        $this->assertNotSame('suspend', $chase['action'] ?? null,
            'Dunning suspended before attempting resolution. R211: resolution PRECEDES any automatic stop.');

        $this->assertNotEmpty($chase['reason'] ?? '',
            'A dunning action fired with no recorded reason — the tenant cannot be told why.');

        $this->writeEvidence('dunning-by-reason', [
            'passed' => true,
            'artifact_id' => $chase['id'] ?? '',
        ]);
    }

    // ── helpers ──

    /**
     * ⛔⛔ THE GUARD THAT MAKES ALL OF THIS MEAN ANYTHING.
     *
     * Laravel runs queued jobs synchronously in tests. A journey on the sync
     * driver would pass on a box with no worker running at all — which is
     * precisely how "nothing runs the queue" stayed invisible for months while
     * every test passed.
     */
    private function assertQueueIsNotSync(): void
    {
        $this->assertNotSame('sync', config('queue.default'),
            'This journey ran on the SYNC driver. It would pass with no worker running, so it proves nothing.');
    }

    /** @param array<string, mixed> $data */
    private function writeEvidence(string $slug, array $data): void
    {
        $dir = storage_path('app/evidence/journeys');
        if (! is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        $data['captured_at'] = now()->toIso8601String();
        $data['queue_driver'] = config('queue.default');

        file_put_contents("{$dir}/{$slug}.json", (string) json_encode($data, JSON_PRETTY_PRINT));
    }

    private function drainQueueOnce(): void
    {
        $this->artisan('queue:work --once --stop-when-empty');
    }

    private function drainQueue(): void
    {
        $this->artisan('queue:work --stop-when-empty');
    }

    private function pendingStepsFor(array $person): int
    {
        return (int) DB::table('campaign_steps')
            ->where('person_id', $person['id'])
            ->whereNull('sent_at')
            ->whereNull('cancelled_at')
            ->count();
    }
}
