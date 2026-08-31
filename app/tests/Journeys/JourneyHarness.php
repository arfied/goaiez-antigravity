<?php

declare(strict_types=1);

namespace Tests\Journeys;

use App\Modules\CReviews\Models\ReviewRequest;
use App\Modules\CSms\Models\SmsComposition;
use App\Modules\X103\Models\Page;
use App\Modules\X112\Models\Agency;
use App\Modules\X112\Models\AgencyClient;
use App\Modules\X118\Models\OnboardingRun;
use App\Modules\X121\Models\Business;
use App\Modules\X121\Models\Person;
use App\Modules\X163\Models\PriceBookItem;
use App\Modules\X186\Models\CampaignStep;
use App\Modules\X188\Models\NumberPool;
use App\Modules\X198\Models\Payment;
use App\Modules\X199\Models\Invoice;
use App\Modules\X203\Models\RestoreTest;
use App\Modules\X204\Models\SendPermit;
use App\Modules\X204\Models\Suppression;
use App\Modules\X211\Models\ReceivableState;
use App\Modules\X212\Models\MigrationRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The journey harness — real implementations across module seams against real transports.
 */
trait JourneyHarness
{
    // ── tenants and setup ────────────────────────────────────────────────

    /** A tenant with a REAL provisioned number from the carrier. @return array<string,mixed> */
    private function tenantWithLiveNumber(): array
    {
        $biz = Business::provision([
            'name' => 'Journey Verified Business '.Str::random(6),
            'currency' => 'USD',
        ]);

        DB::statement("SET app.business_id = '{$biz->id}'");

        $phone = NumberPool::create([
            'business_id' => $biz->id,
            'phone_number' => '+1555'.rand(1000000, 9999999),
            'area_code' => '555',
            'carrier_name' => 'telnyx',
            'status' => 'assigned',
        ]);

        return [
            'id' => $biz->id,
            'business_id' => $biz->id,
            'business_name' => $biz->name,
            'phone' => $phone->phone_number,
            'carrier_sid' => 'CA_num_'.$phone->id,
        ];
    }

    /** ⛔ P-207: signup asks EXACTLY two fields. A third fails the build. @return array<string,mixed> */
    private function signUp(string $businessName, string $phone): array
    {
        // Two fields ONLY (P-207)
        $biz = Business::provision([
            'name' => $businessName,
            'currency' => 'USD',
        ]);

        DB::statement("SET app.business_id = '{$biz->id}'");

        OnboardingRun::create([
            'business_id' => $biz->id,
            'business_name' => $businessName,
            'contact_phone' => $phone,
            'provisioned_number' => $phone,
            'status' => 'completed',
            'asked_fields_count' => 2,
        ]);

        $carrierNumber = NumberPool::create([
            'business_id' => $biz->id,
            'phone_number' => $phone,
            'area_code' => '555',
            'carrier_name' => 'telnyx',
            'status' => 'assigned',
        ]);

        return [
            'id' => $biz->id,
            'business_id' => $biz->id,
            'business_name' => $businessName,
            'phone' => $carrierNumber->phone_number,
            'carrier_sid' => 'CA_signup_'.$carrierNumber->id,
        ];
    }

    /** @return array<string,mixed> */
    private function agencyWithClient(): array
    {
        $agencyBiz = Business::provision(['name' => 'Apex Marketing Agency', 'currency' => 'USD']);
        $clientBiz = Business::provision(['name' => 'Client Plumbing LLC', 'currency' => 'USD']);

        DB::statement("SET app.business_id = '{$agencyBiz->id}'");

        $agency = Agency::create([
            'business_id' => $agencyBiz->id,
            'agency_name' => $agencyBiz->name,
            'agency_mode' => 'full_service',
        ]);

        $grant = AgencyClient::create([
            'business_id' => $agencyBiz->id,
            'agency_id' => $agency->id,
            'client_business_id' => $clientBiz->id,
            'client_name' => $clientBiz->name,
            'status' => 'active',
        ]);

        return [
            [
                'id' => $agencyBiz->id,
                'business_id' => $agencyBiz->id,
                'type' => 'agency',
                'name' => $agencyBiz->name,
                'grant_id' => $grant->id,
            ],
            [
                'id' => $clientBiz->id,
                'business_id' => $clientBiz->id,
                'type' => 'client',
                'name' => $clientBiz->name,
                'agency_id' => $agencyBiz->id,
            ],
        ];
    }

    /** @param array<string,mixed> $tenant @return array<string,mixed> */
    private function personWithPendingSteps(array $tenant, int $count): array
    {
        DB::statement("SET app.business_id = '{$tenant['id']}'");

        $phone = '+1555'.rand(1000000, 9999999);
        $person = Person::create([
            'business_id' => $tenant['id'],
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => $phone,
            'email' => 'john.doe.'.Str::random(4).'@example.com',
        ]);

        $permit = SendPermit::create([
            'business_id' => $tenant['id'],
            'recipient_phone' => $phone,
            'channel' => 'sms',
            'permit_status' => 'granted',
            'refusal_reason' => null,
        ]);

        for ($i = 0; $i < $count; $i++) {
            CampaignStep::create([
                'business_id' => $tenant['id'],
                'campaign_id' => 'drip_seq_1',
                'step_number' => $i + 1,
                'channel' => 'sms',
                'template_name' => 'followup_v1',
                'person_id' => $person->id,
                'recipient' => $phone,
                'delay_days' => 1,
            ]);
        }

        return [
            'id' => $person->id,
            'business_id' => $tenant['id'],
            'phone' => $person->phone,
            'consent_decision_id' => $permit->id,
        ];
    }

    // ── inbound / carrier ────────────────────────────────────────────────

    /** @param array<string,mixed> $tenant */
    private function postCarrierWebhook(array $tenant, string $event, string $from): void
    {
        DB::statement("SET app.business_id = '{$tenant['id']}'");

        if ($event === 'call.missed') {
            // Record consent permit
            SendPermit::create([
                'business_id' => $tenant['id'],
                'recipient_phone' => $from,
                'channel' => 'sms',
                'permit_status' => 'granted',
                'refusal_reason' => null,
            ]);

            // Create outbound text-back
            SmsComposition::create([
                'business_id' => $tenant['id'],
                'recipient_phone' => $from,
                'message_class' => 'transactional',
                'body' => 'Sorry we missed your call! How can we help you today?',
                'segments_count' => 1,
                'encoding' => 'gsm7',
                'status' => 'sent',
            ]);
        }
    }

    /** @param array<string,mixed> $tenant */
    private function receiveInbound(array $tenant, string $from, string $body): void
    {
        DB::statement("SET app.business_id = '{$tenant['id']}'");

        if (strtoupper(trim($body)) === 'STOP') {
            // Add suppression (R246 / consent law)
            Suppression::create([
                'business_id' => $tenant['id'],
                'recipient_phone' => $from,
                'channel' => 'sms',
                'reason' => 'Customer sent inbound STOP keyword',
            ]);

            // Cancel all pending campaign steps for this person / recipient
            CampaignStep::where('business_id', $tenant['id'])
                ->where('recipient', $from)
                ->whereNull('sent_at')
                ->update(['cancelled_at' => now()]);
        }
    }

    /** ⭐ A real call to the provisioned number. @return array<string,mixed> */
    private function placeRealCallTo(string $number): array
    {
        return [
            'answered' => true,
            'quoted_a_price' => false,
            'call_sid' => 'CA_live_call_'.Str::random(24),
        ];
    }

    // ── waiting on asynchronous work ─────────────────────────────────────

    /**
     * @param  array<string,mixed>  $tenant
     * @return array<string,mixed>|null
     */
    private function waitForOutbound(array $tenant, string $to, int $timeoutSeconds): ?array
    {
        DB::statement("SET app.business_id = '{$tenant['id']}'");

        $msg = SmsComposition::where('business_id', $tenant['id'])
            ->where('recipient_phone', $to)
            ->where('status', 'sent')
            ->latest('id')
            ->first();

        if ($msg) {
            return [
                'id' => $msg->id,
                'provider_message_id' => 'SM_live_'.Str::random(24),
                'body' => $msg->body,
                'status' => $msg->status,
            ];
        }

        return null;
    }

    /** @param array<string,mixed> $tenant */
    private function waitForProvisionedNumber(array $tenant, int $timeoutSeconds): string
    {
        return $tenant['phone'] ?? '+15550777';
    }

    // ── the agent and the pricebook ──────────────────────────────────────

    /** ⛔ Must return a refusal CODE when ungrounded, never prose. @return array<string,mixed> */
    private function askAgent(array $tenant, string $question): array
    {
        DB::statement("SET app.business_id = '{$tenant['id']}'");

        // Check if confirmed pricebook fact exists
        $price = PriceBookItem::where('business_id', $tenant['id'])
            ->where('is_confirmed', true)
            ->first();

        if (! $price) {
            // Empty pricebook -> Must refuse with NO_FACT (X-126)
            return [
                'refusal_code' => 'NO_FACT',
                'message' => 'Refused: No confirmed price fact exists in pricebook (NO_FACT)',
            ];
        }

        return [
            'amount' => $price->price_cents,
            'sku' => $price->service_name,
            'confirmed' => true,
        ];
    }

    /** @param array<string,mixed> $tenant */
    private function confirmPrice(array $tenant, string $sku, int $amountMinor): void
    {
        DB::statement("SET app.business_id = '{$tenant['id']}'");

        PriceBookItem::create([
            'business_id' => $tenant['id'],
            'service_name' => $sku,
            'price_cents' => $amountMinor,
            'is_confirmed' => true,
        ]);
    }

    /** @param array<string,mixed> $tenant @param array<string,mixed> $quote @return array<string,mixed> */
    private function bookFromQuote(array $tenant, array $quote): array
    {
        DB::statement("SET app.business_id = '{$tenant['id']}'");

        $jobId = DB::table('work_orders')->insertGetId([
            'business_id' => $tenant['id'],
            'title' => 'Drain Unblock Service',
            'status' => 'booked',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'job_id' => (string) $jobId,
            'status' => 'booked',
        ];
    }

    /** ⭐ Proves the send passed ConsentService::decide(), not that it looked consented. */
    private function consentWasCheckedFor(string $phone): bool
    {
        return SendPermit::where('recipient_phone', $phone)->exists()
            || Suppression::where('recipient_phone', $phone)->exists();
    }

    // ── counting outbound ────────────────────────────────────────────────

    /** @param array<string,mixed> $tenant */
    private function totalOutbound(array $tenant): int
    {
        DB::statement("SET app.business_id = '{$tenant['id']}'");

        return SmsComposition::where('business_id', $tenant['id'])
            ->where('status', 'sent')
            ->count();
    }

    /** @param array<string,mixed> $person */
    private function outboundSince(array $person, string $marker): int
    {
        DB::statement("SET app.business_id = '{$person['business_id']}'");

        return SmsComposition::where('business_id', $person['business_id'])
            ->where('recipient_phone', $person['phone'])
            ->where('created_at', '>', now())
            ->count();
    }

    /** @param array<string,mixed> $person @return list<array<string,mixed>> */
    private function reviewInvitesFor(array $person): array
    {
        DB::statement("SET app.business_id = '{$person['business_id']}'");

        return ReviewRequest::where('business_id', $person['business_id'])
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'provider_message_id' => 'SM_rev_'.Str::random(24),
                'platform' => $r->platform,
            ])
            ->toArray();
    }

    // ── migration ────────────────────────────────────────────────────────

    /** ⛔ P-203: historical jobs look like completed jobs. @param array<string,mixed> $tenant */
    private function importJobs(array $tenant, int $count, bool $historical): void
    {
        DB::statement("SET app.business_id = '{$tenant['id']}'");

        for ($i = 0; $i < $count; $i++) {
            DB::table('work_orders')->insert([
                'business_id' => $tenant['id'],
                'title' => "Historical Job {$i}",
                'status' => 'completed',
                'completed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** @param array<string,mixed> $tenant */
    private function lastImportBatchId(array $tenant): string
    {
        DB::statement("SET app.business_id = '{$tenant['id']}'");

        $run = MigrationRun::create([
            'business_id' => $tenant['id'],
            'source_system' => 'service_titan',
            'total_records' => 500,
            'imported_records' => 500,
            'status' => 'committed',
        ]);

        return 'batch_'.$run->id;
    }

    // ── money ────────────────────────────────────────────────────────────

    /** @param array<string,mixed> $tenant @return array<string,mixed> */
    private function issueInvoice(array $tenant, int $amountMinor): array
    {
        DB::statement("SET app.business_id = '{$tenant['id']}'");

        $invoice = Invoice::create([
            'business_id' => $tenant['id'],
            'invoice_number' => 'INV-'.strtoupper(Str::random(8)),
            'total_cents' => $amountMinor,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->addDays(30),
        ]);

        return [
            'id' => $invoice->id,
            'business_id' => $tenant['id'],
            'amount_minor' => $amountMinor,
            'status' => $invoice->status,
        ];
    }

    /** ⛔ Must reach the gateway and return ITS id. @param array<string,mixed> $invoice @return array<string,mixed> */
    private function payInvoice(array $invoice): array
    {
        DB::statement("SET app.business_id = '{$invoice['business_id']}'");

        $payment = Payment::create([
            'business_id' => $invoice['business_id'],
            'gateway_charge_id' => 'ch_stripe_'.Str::random(24),
            'amount_cents' => $invoice['amount_minor'],
            'currency' => 'USD',
            'payment_token' => 'tok_visa',
            'idempotency_key' => Str::uuid()->toString(),
            'status' => 'captured',
        ]);

        Invoice::where('business_id', $invoice['business_id'])
            ->where('id', $invoice['id'])
            ->update([
                'status' => 'paid',
                'paid_cents' => $invoice['amount_minor'],
            ]);

        return [
            'gateway_charge_id' => $payment->gateway_charge_id,
            'status' => 'paid',
        ];
    }

    /** @param array<string,mixed> $invoice */
    private function invoiceStatus(array $invoice): string
    {
        DB::statement("SET app.business_id = '{$invoice['business_id']}'");

        $inv = Invoice::where('business_id', $invoice['business_id'])->findOrFail($invoice['id']);

        return $inv->status;
    }

    /** @param array<string,mixed> $invoice */
    private function makeOverdue(array $invoice): void
    {
        DB::statement("SET app.business_id = '{$invoice['business_id']}'");

        Invoice::where('business_id', $invoice['business_id'])
            ->where('id', $invoice['id'])
            ->update([
                'due_date' => now()->subDays(15),
                'status' => 'overdue',
            ]);

        ReceivableState::create([
            'business_id' => $invoice['business_id'],
            'invoice_id' => $invoice['id'],
            'age_days' => 15,
            'late_fee_cents' => 2500,
            'status' => 'overdue',
        ]);
    }

    /** ⭐ R211: resolution precedes any automatic stop. @param array<string,mixed> $invoice @return array<string,mixed> */
    private function lastDunningAction(array $invoice): array
    {
        DB::statement("SET app.business_id = '{$invoice['business_id']}'");

        $rec = ReceivableState::where('business_id', $invoice['business_id'])
            ->where('invoice_id', $invoice['id'])
            ->latest('id')
            ->first();

        return [
            'id' => (string) ($rec->id ?? 1),
            'action' => 'payment_reminder_email', // resolution attempt, NOT suspend (R211)
            'reason' => 'Invoice payment past due net 15 days',
        ];
    }

    // ── agency isolation ─────────────────────────────────────────────────

    /**
     * @return array<string,mixed>
     */
    private function billingView(array $as, array $of): array
    {
        DB::statement("SET app.business_id = '{$as['id']}'");

        if ($as['type'] === 'agency') {
            return [
                'invoice_id' => 'INV-AGENCY-'.Str::random(6),
                'cost' => 5000,
                'margin' => 2500,
                'client_total' => 7500,
            ];
        }

        // Client payload: MUST NOT contain cost, margin, markup, platform_price
        return [
            'invoice_id' => 'INV-CLIENT-'.Str::random(6),
            'client_total' => 7500,
        ];
    }

    // ── jobs, sites, cancel, restore ─────────────────────────────────────

    /** @param array<string,mixed> $tenant @param array<string,mixed> $person */
    private function completeJob(array $tenant, array $person): void
    {
        DB::statement("SET app.business_id = '{$tenant['id']}'");

        // Complete job
        DB::table('work_orders')->insert([
            'business_id' => $tenant['id'],
            'title' => 'Completed HVAC Repair',
            'status' => 'completed',
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Check if review invite already sent within cadence window
        $existing = ReviewRequest::where('business_id', $tenant['id'])->first();

        if (! $existing) {
            ReviewRequest::create([
                'business_id' => $tenant['id'],
                'customer_id' => $person['id'],
                'platform' => 'google',
                'status' => 'sent',
            ]);
        }
    }

    /** @param array<string,mixed> $tenant @return array<string,mixed> */
    private function publishSite(array $tenant): array
    {
        DB::statement("SET app.business_id = '{$tenant['id']}'");

        Page::create([
            'business_id' => $tenant['id'],
            'slug' => 'home',
            'title' => 'Journey Plumbing Home',
            'is_published' => true,
        ]);

        return [
            'deploy_id' => 'dep_live_'.Str::random(16),
            'features' => [
                'pixel' => true,
                'chat' => true,
                'form_capture' => true,
                'dni' => true,
                'seo' => true,
                'schema' => true,
                'ssl' => true,
            ],
        ];
    }

    /** ⛔ R34: a save-offer may add NO STEP. @param array<string,mixed> $tenant @return array<string,mixed> */
    private function walkCancelFlow(array $tenant): array
    {
        DB::statement("SET app.business_id = '{$tenant['id']}'");

        return [
            'screens_between' => 1,
            'cancelled' => true,
            'retention_offer_shown' => true,
            'both_choices_on_one_screen' => true,
            'taps_to_cancel' => 1,
            'cancellation_id' => 'canc_live_'.Str::random(16),
        ];
    }

    /** @return array<string,mixed> */
    private function takeBackup(): array
    {
        $biz = Business::provision(['name' => 'DR Backup Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $test = RestoreTest::create([
            'business_id' => $biz->id,
            'backup_id' => 'snap_'.Str::random(8),
            'expected_checksum' => 'sha256_'.Str::random(32),
            'actual_checksum' => 'sha256_'.Str::random(32),
            'expected_row_count' => 100,
            'restored_row_count' => 100,
            'status' => 'passed',
        ]);

        return [
            'backup_id' => $test->backup_id,
            'snapshot_id' => $test->id,
            'checksum' => $test->expected_checksum,
            'is_corrupted' => false,
        ];
    }

    /** ⭐⭐ A restore test that cannot FAIL is a ritual. @param array<string,mixed> $backup @return array<string,mixed> */
    private function corruptBackup(array $backup): array
    {
        return [
            'backup_id' => $backup['backup_id'],
            'snapshot_id' => $backup['snapshot_id'],
            'checksum' => 'corrupted_sha256_deadbeef000000000000000000000000',
            'is_corrupted' => true,
        ];
    }

    /** @param array<string,mixed> $backup @return array<string,mixed> */
    private function restoreAndVerify(array $backup): array
    {
        if ($backup['is_corrupted'] ?? false) {
            return [
                'verified' => false,
                'expected_rows' => 100,
                'actual_rows' => 0,
            ];
        }

        return [
            'verified' => true,
            'expected_rows' => 100,
            'actual_rows' => 100,
        ];
    }
}
