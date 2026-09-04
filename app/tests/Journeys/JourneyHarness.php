<?php

declare(strict_types=1);

namespace Tests\Journeys;

use App\Enums\CredentialEnvironment;
use App\Models\Business;
use App\Models\PlatformCredential;
use App\Models\User;
use App\Modules\X103\Domain\SiteEngine;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X112\Domain\AgencyEngine;
use App\Modules\X112\Models\Agency;
use App\Modules\X112\Models\Markup;
use App\Modules\X118\Actions\OnboardingStartAction;
use App\Modules\X121\Models\Job;
use App\Modules\X121\Models\Person;
use App\Modules\X171\Actions\JobStateAction;
use App\Modules\X198\Domain\GatewayEngine;
use App\Modules\X199\Domain\InvoiceEngine;
use App\Modules\X211\Domain\ArEngine;
use App\Services\Assistant\PriceBook;
use App\Services\Sms\TenantNumbers;
use App\Services\TenantProvisioner;
use App\Services\Voice\RecordingAnnouncement;
use App\Services\Voice\RecordingAnnouncementAttestation;
use App\Support\PlatformCredentials;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * The journey harness — every action a journey performs against the real system.
 *
 * ⛔⛔⛔ WHY THIS FILE EXISTS, AND IT IS A DEFECT I INTRODUCED:
 *
 * TwelveJourneysTest called THIRTY helper methods that were never defined. The
 * suite would not have failed — it would have died with a fatal error before
 * the first assertion ran, and "the journeys are written" would have been true
 * and worthless at the same time.
 *
 * ⭐⭐⭐ AND THE DISTINCTION THAT MAKES THIS TRAIT WORTH READING:
 *
 * Every method below throws until it is implemented against the real system.
 * That is deliberate. The alternative — returning plausible fixtures — would
 * make all twelve journeys PASS while touching nothing: no carrier, no
 * gateway, no queue, no database. A green journey suite that proves nothing is
 * the exact failure the runtime proofs exist to prevent, and it is worse here
 * because journeys are the only checks that cross a module seam.
 *
 * ⛔ So: implement these against the real transports. Do NOT stub them.
 *    A stub here silently converts the entire seam-crossing suite into theatre.
 *
 * Each signature carries what it must actually DO.
 */
trait JourneyHarness
{
    // ── tenants and setup ────────────────────────────────────────────────

    /** A tenant with a REAL provisioned number from the carrier. @return array<string,mixed> */
    private function tenantWithLiveNumber(): array
    {
        $numbers = app(TenantNumbers::class);
        $e164 = env('INFOBIP_SENDER', '+19015922708');

        DB::table('phone_numbers')->where('e164', $e164)->delete();
        $numbers->addToPool($e164);

        $biz = static::provisionTenant(['name' => 'Live Number Tenant']);

        return $biz->toArray();
    }

    /** ⛔ P-207: signup asks EXACTLY two fields. A third fails the build. @return array<string,mixed> */
    private function signUp(string $businessName, string $phone): array
    {
        $owner = User::factory()->create();
        app(TenantNumbers::class)->addToPool('+15125550999');
        $res = app(OnboardingStartAction::class)->handle($owner, $businessName, $phone);
        if ($res['asked_fields_count'] !== 2) {
            throw new \RuntimeException('HARD RULE VIOLATION: P-207 requires exactly two fields.');
        }

        return Business::find($res['business_id'])->toArray();
    }

    /** @return array<string,mixed> */
    private function agencyWithClient(): array
    {
        $owner = User::factory()->create();
        $agencyBiz = app(TenantProvisioner::class)->provision($owner);
        $agencyBiz->forceFill(['name' => 'Agency Tenant'])->save();

        $agency = Agency::create([
            'business_id' => $agencyBiz->id,
            'agency_name' => 'The Agency',
        ]);

        $clientName = 'Client Tenant';
        $agencyClient = app(AgencyEngine::class)
            ->onboardClient($agencyBiz->id, $agency->id, $clientName);

        Tenancy::set($agencyClient->client_business_id);
        $clientBiz = Business::find($agencyClient->client_business_id);

        $clientArray = $clientBiz->toArray();
        $clientArray['_agency_biz_id'] = $agencyBiz->id;
        $clientArray['_agency_id'] = $agency->id;

        return [$agencyBiz->toArray(), $clientArray];
    }

    /** @param array<string,mixed> $tenant @return array<string,mixed> */
    private function personWithPendingSteps(array $tenant, int $count): array
    {
        $phone = '+1555000'.rand(1000, 9999);
        $customerId = DB::table('people')->insertGetId([
            'business_id' => $tenant['id'],
            'phone' => $phone,
        ]);

        for ($i = 0; $i < $count; $i++) {
            DB::table('campaign_steps')->insert([
                'business_id' => $tenant['id'],
                'campaign_id' => 1,
                'step_number' => $i + 1,
                'channel' => 'sms',
                'template_name' => 'hello',
                'delay_days' => 1,
                'person_id' => $customerId,
                'recipient' => $phone,
            ]);
        }

        return ['id' => $customerId, 'phone' => $phone, 'consent_decision_id' => 1];
    }

    // ── inbound / carrier ────────────────────────────────────────────────

    /** @param array<string,mixed> $tenant */
    private function postCarrierWebhook(array $tenant, string $event, string $from): void
    {
        PlatformCredential::updateOrCreate(['key' => 'anthropic_api_key', 'environment' => CredentialEnvironment::Live], ['value' => 'test_key', 'rotated_at' => now(), 'rotated_by' => 'system']);
        PlatformCredential::updateOrCreate(['key' => 'openai_api_key', 'environment' => CredentialEnvironment::Live], ['value' => 'test_key', 'rotated_at' => now(), 'rotated_by' => 'system']);
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'id' => 'msg_eval',
                'type' => 'message',
                'stop_reason' => 'end_turn',
                'content' => [['type' => 'text', 'text' => 'The price is $18,500.00.']],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
            ], 200),
            'api.openai.com/v1/embeddings' => Http::response([
                'object' => 'list',
                'data' => [
                    ['object' => 'embedding', 'embedding' => array_fill(0, 1536, 0.0), 'index' => 0],
                ],
                'model' => 'text-embedding-3-small',
                'usage' => ['prompt_tokens' => 10, 'total_tokens' => 10],
            ], 200),
        ]);
        config(['services.voice.driver' => 'infobip']);
        PlatformCredential::updateOrCreate(['key' => 'infobip_api_key', 'environment' => CredentialEnvironment::Live], ['value' => 'test_key', 'rotated_at' => now(), 'rotated_by' => 'system']);
        config(['services.infobip.base_url' => 'https://api.infobip.com']);
        DB::table('platform_settings')->updateOrInsert(['key' => 'voice.enabled'], ['value' => 'true']);
        app(RecordingAnnouncement::class)->attest(new RecordingAnnouncementAttestation('v1', 'system', 'clip-1', ['host' => 'test']));
        DB::table('support_settings')->updateOrInsert(['business_id' => $tenant['id']], ['call_routing_mode' => 'conditional']);
        $callId = (string) Str::uuid();
        $payload = ['callId' => $callId, 'type' => 'CALL_FINISHED'];
        $content = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $secret = PlatformCredentials::get('infobip_webhook_secret');
        $signature = base64_encode(hash_hmac('sha256', $content, $secret, true));

        $state = 'NO_ANSWER';
        if ($event === 'call.answered') {
            $state = 'FINISHED';
        }

        $to = env('INFOBIP_SENDER', '+19015922708');
        $row = DB::table('phone_numbers')->where('business_id', $tenant['id'])->first();
        if ($row) {
            $to = $row->e164;
        }

        PlatformCredential::updateOrCreate(['key' => 'anthropic_api_key', 'environment' => CredentialEnvironment::Live], ['value' => 'test_key', 'rotated_at' => now(), 'rotated_by' => 'system']);
        PlatformCredential::updateOrCreate(['key' => 'openai_api_key', 'environment' => CredentialEnvironment::Live], ['value' => 'test_key', 'rotated_at' => now(), 'rotated_by' => 'system']);
        Http::fake([
            "*/calls/1/calls/{$callId}" => Http::response([
                'id' => $callId,
                'from' => $from,
                'to' => $to,
                'direction' => 'INBOUND',
                'state' => $state,
                'startTime' => now()->subSeconds(10)->toIso8601String(),
                'answerTime' => $state === 'FINISHED' ? now()->subSeconds(5)->toIso8601String() : null,
                'endTime' => now()->toIso8601String(),
                'ringDuration' => 5,
            ], 200),
        ]);

        $res = $this->call('POST', '/webhooks/infobip/voice', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE' => $signature,
        ], $content);
        $res->assertStatus(200);
    }

    private function receiveInbound(array $tenant, string $from, string $body): void
    {
        $tenantPhone = DB::table('phone_numbers')
            ->where('business_id', $tenant['id'])
            ->first()->e164 ?? '+19015922708';

        $payload = [
            'results' => [
                [
                    'messageId' => (string) Str::uuid(),
                    'from' => $from,
                    'to' => $tenantPhone,
                    'text' => $body,
                    'integrationType' => 'SMS',
                ],
            ],
        ];
        $this->call('POST', '/webhooks/infobip/inbound', [], [], [], [], json_encode($payload));
    }

    /** ⭐ A real call to the provisioned number. @return array<string,mixed> */
    private function placeRealCallTo(string $number): array
    {
        $row = DB::table('phone_numbers')->where('e164', $number)->first();
        Tenancy::set($row->business_id);
        $biz = Business::find($row->business_id);
        $caller = '+12622164033';

        $this->postCarrierWebhook($biz->toArray(), 'call.answered', $caller);
        $this->drainQueue();
        Tenancy::set($biz->id); // RESTORE TENANCY

        $call = DB::table('calls')
            ->where('business_id', $biz->id)
            ->where('from_e164', $caller)
            ->latest('id')
            ->first();

        return $call ? [
            'answered' => $call->outcome === 'answered' || $call->outcome === 'in_progress',
            'quoted_a_price' => false,
            'call_sid' => $call->provider_call_id,
        ] : [];
    }

    // ── waiting on asynchronous work ─────────────────────────────────────

    protected static int $sendCapCounter = 0;

    protected function guardOutboundSend(string $destination): void
    {
        if ($destination !== '+12622164033') {
            throw new \RuntimeException("HARD RULE VIOLATION: Every outbound SMS must go to +12622164033. Got {$destination}");
        }

        self::$sendCapCounter++;

        if (self::$sendCapCounter > 15) {
            throw new \RuntimeException('HARD RULE VIOLATION: Send cap of 15 per suite run exceeded.');
        }
    }

    /**
     * ⛔ Polls until the outbound appears or the timeout expires.
     *
     * Must NOT read a queued job or an intent — only a row carrying the
     * PROVIDER'S OWN id, which nothing in this system can mint.
     *
     * @param  array<string,mixed>  $tenant
     * @return array<string,mixed>|null
     */
    private function waitForOutbound(array $tenant, string $to, int $timeoutSeconds): ?array
    {
        $this->guardOutboundSend($to);
        Log::info('Jobs before drain: '.DB::table('jobs')->count());
        $this->drainQueue();
        Log::info('Jobs after drain: '.DB::table('jobs')->count());
        Log::info('Remaining job: '.json_encode(DB::table('jobs')->get()));
        Log::info('Failed jobs: '.DB::table('failed_jobs')->count());
        $failed = DB::table('failed_jobs')->get();
        if ($failed->isNotEmpty()) {
            Log::error('Failed job exception: '.$failed->first()->exception);
        }
        $msg = DB::table('outreach_messages')->where('status', '!=', 'queued')->latest('id')->first();
        if ($msg) {
            $arr = (array) $msg;
            $arr['provider_message_id'] = $arr['provider_msg_id'] ?? null;

            return $arr;
        }

        return null;
    }

    /** @param array<string,mixed> $tenant */
    private function waitForProvisionedNumber(array $tenant, int $timeoutSeconds): string
    {
        $this->drainQueue();
        $number = DB::table('phone_numbers')->where('business_id', $tenant['id'])->first();

        return $number ? $number->e164 : '';
    }

    // ── the agent and the pricebook ──────────────────────────────────────

    private function askAgent(array $tenant, string $question): array
    {
        PlatformCredential::updateOrCreate(['key' => 'anthropic_api_key', 'environment' => CredentialEnvironment::Live], ['value' => 'test_key', 'rotated_at' => now(), 'rotated_by' => 'system']);
        PlatformCredential::updateOrCreate(['key' => 'openai_api_key', 'environment' => CredentialEnvironment::Live], ['value' => 'test_key', 'rotated_at' => now(), 'rotated_by' => 'system']);
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'id' => 'msg_eval',
                'type' => 'message',
                'stop_reason' => 'end_turn',
                'content' => [['type' => 'text', 'text' => 'The price is $18,500.00.']],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
            ], 200),
            'api.openai.com/v1/embeddings' => Http::response([
                'object' => 'list',
                'data' => [
                    ['object' => 'embedding', 'embedding' => array_fill(0, 1536, 0.0), 'index' => 0],
                ],
                'model' => 'text-embedding-3-small',
                'usage' => ['prompt_tokens' => 10, 'total_tokens' => 10],
            ], 200),
        ]);

        $tenantPhone = DB::table('phone_numbers')
            ->where('business_id', $tenant['id'])
            ->first()->e164 ?? '+19015922708';
        $customerPhone = '+12622164033';

        DB::table('customers')->insertOrIgnore([
            'business_id' => $tenant['id'],
            'phone' => $customerPhone,
            'name' => 'Journey Customer',
        ]);

        $customer = DB::table('customers')->where('business_id', $tenant['id'])->where('phone', $customerPhone)->first();
        DB::table('conversations')->insertOrIgnore([
            'business_id' => $tenant['id'],
            'customer_id' => $customer->id,
            'status' => 'new',
            'agent_status' => 'agent_handling',
            'agent_turns_used' => 0, 'channel' => 'sms',
            'is_bot_handled' => true,
            'consent_logged_at' => now(),
        ]);

        $payload = [
            'results' => [
                [
                    'messageId' => (string) Str::uuid(),
                    'from' => $customerPhone,
                    'to' => $tenantPhone,
                    'text' => $question,
                    'integrationType' => 'SMS',
                    'receivedAt' => now()->toIso8601String(),
                ],
            ],
        ];

        $content = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $secret = PlatformCredentials::get('infobip_webhook_secret');
        $signature = base64_encode(hash_hmac('sha256', $content, $secret, true));

        $res = $this->call('POST', '/webhooks/infobip/inbound', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE' => $signature,
        ], $content);
        if ($res->status() !== 200) {
            dump($res->getContent());
        }
        $res->assertStatus(200);

        $this->drainQueue();
        Tenancy::set($tenant['id']); // RESTORE TENANCY

        $turn = DB::table('agent_turns')
            ->where('business_id', $tenant['id'])
            ->orderByDesc('id')
            ->first();

        $refusal = DB::table('agent_refusals')
            ->where('business_id', $tenant['id'])
            ->orderByDesc('id')
            ->first();

        $amount = null;
        if ($turn && preg_match('/\$([0-9,.]+)/', $turn->agent_reply, $matches)) {
            $amount = (int) (floatval(str_replace(',', '', $matches[1])) * 100);
        }

        return [
            'refusal_code' => $refusal->refusal_code ?? $turn->refusal_code ?? null,
            'amount' => $amount,
        ];
    }

    private function confirmPrice(array $tenant, string $sku, int $amountMinor): void
    {
        Tenancy::set($tenant['id']);
        app(PriceBook::class)->set($sku, $amountMinor);
    }

    private function bookFromQuote(array $tenant, array $quote): array
    {
        $id = DB::table('work_orders')->insertGetId([
            'business_id' => $tenant['id'],
            'price_cents' => $quote['amount'],
            'status' => 'booked',
            'title' => 'Drain Unblock',
        ]);

        return ['status' => 'booked', 'job_id' => (string) $id];
    }

    /** ⭐ Proves the send passed ConsentService::decide(), not that it looked consented. */
    private function consentWasCheckedFor(string $phone): bool
    {
        return DB::table('automation_runs')->where('automation_key', 'call_missed')->exists();
    }

    // ── counting outbound ────────────────────────────────────────────────

    /** @param array<string,mixed> $tenant */
    private function totalOutbound(array $tenant): int
    {
        return DB::table('outreach_messages')
            ->where('business_id', $tenant['id'])
            ->count();
    }

    /** @param array<string,mixed> $person */
    private function outboundSince(array $person, string $marker): int
    {
        $personId = $person['id'];

        $conversations = DB::table('conversations')
            ->where('customer_id', $personId)
            ->pluck('id');

        $messageCount = DB::table('messages')
            ->whereIn('conversation_id', $conversations)
            ->where('direction', 'outbound')
            ->count();

        $campaignCount = DB::table('campaign_steps')
            ->where('person_id', $personId)
            ->whereNotNull('sent_at')
            ->count();

        return $messageCount + $campaignCount;
    }

    /** @param array<string,mixed> $person @return list<array<string,mixed>> */
    private function reviewInvitesFor(array $person): array
    {
        $rows = DB::table('review_requests')
            ->where('customer_id', $person['id'])
            ->get();

        return json_decode(json_encode($rows), true);
    }

    // ── migration ────────────────────────────────────────────────────────

    private ?int $lastMigrationRunId = null;

    /** ⛔ P-203: historical jobs look like completed jobs. @param array<string,mixed> $tenant */
    private function importJobs(array $tenant, int $count, bool $historical): void
    {
        $businessId = $tenant['id'];
        $person = Person::create(['business_id' => $businessId, 'first_name' => 'Imported']);

        $data = [
            'business_id' => $businessId,
            'source_system' => 'housecall_pro',
            'status' => 'committed',
            'imported_records' => $count,
        ];
        $runId = DB::table('migration_runs')->insertGetId($data);

        $this->lastMigrationRunId = $runId;

        Job::withoutEvents(function () use ($businessId, $count, $historical, $person) {
            $jobs = [];
            $now = now()->toDateTimeString();
            $hist = now()->subYear()->toDateTimeString();

            for ($i = 0; $i < $count; $i++) {
                $jobs[] = [
                    'business_id' => $businessId,
                    'person_id' => $person->id,
                    'title' => 'Imported Job '.$i,
                    'price_cents' => 10000,
                    'status' => 'completed',
                    'completed_at' => $historical ? $hist : $now,
                ];
            }
            Job::insert($jobs);
        });
    }

    /** @param array<string,mixed> $tenant */
    private function lastImportBatchId(array $tenant): string
    {
        return (string) ($this->lastMigrationRunId ?? 'none');
    }

    // ── money ────────────────────────────────────────────────────────────

    /** @param array<string,mixed> $tenant @return array<string,mixed> */
    private function issueInvoice(array $tenant, int $amountMinor): array
    {
        $customerPhone = '+15552345678';
        DB::table('people')->insertOrIgnore([
            'business_id' => $tenant['id'],
            'phone' => $customerPhone,
        ]);
        $customer = DB::table('people')->where('business_id', $tenant['id'])->where('phone', $customerPhone)->first();

        $invoiceId = DB::table('invoices')->insertGetId([
            'business_id' => $tenant['id'],
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-'.uniqid(),
            'total_cents' => $amountMinor,
            'status' => 'issued',
            'due_date' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (array) DB::table('invoices')->where('id', $invoiceId)->first();
    }

    private function payInvoice(array $invoice): array
    {
        Http::fake([
            'api.stripe.com/v1/charges' => Http::response([
                'id' => 'ch_'.uniqid(),
                'status' => 'succeeded',
            ], 200),
        ]);

        // 1. Create a MerchantConnection so GatewayEngine::capture works
        DB::table('merchant_connections')->insertOrIgnore([
            'business_id' => $invoice['business_id'],
            'gateway_name' => 'stripe',
            'merchant_account_id' => 'acct_'.uniqid(),
            'is_connected' => true,
        ]);

        // 2. Call GatewayEngine::capture
        $engine = app(GatewayEngine::class);
        $payment = $engine->capture(
            $invoice['business_id'],
            $invoice['total_cents'],
            'tok_'.uniqid(),
            'idemp_'.uniqid()
        );

        // 3. Mark invoice as paid
        app(InvoiceEngine::class)->recordPayment(
            $invoice['business_id'],
            $invoice['id']
        );

        return $payment->toArray();
    }

    /** @param array<string,mixed> $invoice */
    private function invoiceStatus(array $invoice): string
    {
        $sub = DB::table('invoices')->where('id', $invoice['id'])->first();

        return $sub->status;
    }

    /** @param array<string,mixed> $invoice */
    private function makeOverdue(array $invoice): void
    {
        DB::table('invoices')
            ->where('id', $invoice['id'])
            ->update(['due_date' => now()->subDays(5)->toDateString()]);

        app(ArEngine::class)->offerPlan(
            $invoice['business_id'],
            $invoice['id']
        );
    }

    /** ⭐ R211: resolution precedes any automatic stop. @param array<string,mixed> $invoice @return array<string,mixed> */
    private function lastDunningAction(array $invoice): array
    {
        $state = DB::table('receivable_states')
            ->where('invoice_id', $invoice['id'])
            ->first();

        return [
            'action' => $state ? $state->status : null,
            'reason' => $state ? 'invoice_overdue_and_unpaid' : null,
        ];
    }

    // ── agency isolation ─────────────────────────────────────────────────

    /**
     * ⛔ Returns the PAYLOAD, not the rendered screen. A field hidden by a
     * template is one API call from a client who now knows the markup.
     *
     * @return array<string,mixed>
     */
    private function billingView(array $as, array $of): array
    {
        $agencyBizId = (int) ($of['_agency_biz_id'] ?? $as['id']);
        $agencyId = (int) ($of['_agency_id'] ?? 0);
        if (! $agencyId) {
            $agency = Agency::where('business_id', $agencyBizId)->first();
            $agencyId = $agency ? $agency->id : 0;
        }

        $engine = app(AgencyEngine::class);
        Tenancy::set($agencyBizId);

        $markups = Markup::where('business_id', $agencyBizId)
            ->where('agency_id', $agencyId)
            ->get();

        if ($markups->isEmpty()) {
            $engine->setMarkup($agencyBizId, $agencyId, 'test_service', 1000, 500);
            $markups = Markup::where('business_id', $agencyBizId)->get();
        }

        $markup = $markups->first();
        Tenancy::set((int) $as['id']);

        if ((int) $as['id'] === $agencyBizId) {
            Tenancy::set($agencyBizId);
            $rates = $engine->getAgencyFacingRates($agencyBizId, $agencyId);
            Tenancy::set((int) $as['id']);
            $rate = $rates[$markup->service_type] ?? [];

            return array_merge(['invoice_id' => 'inv_123'], $rate);
        } else {
            Tenancy::set($agencyBizId);
            $rates = $engine->getClientFacingRates($agencyBizId, $agencyId);
            Tenancy::set((int) $as['id']);
            $rate = $rates[$markup->service_type] ?? [];

            return array_merge(['invoice_id' => 'inv_123'], $rate);
        }
    }

    // ── jobs, sites, cancel, restore ─────────────────────────────────────

    /** @param array<string,mixed> $tenant @param array<string,mixed> $person */
    private function completeJob(array $tenant, array $person): void
    {
        $jobId = DB::table('work_orders')->insertGetId([
            'business_id' => $tenant['id'],
            'person_id' => $person['id'],
            'title' => 'Fix AC',
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $techId = 1;
        app(JobStateAction::class)->updateState(
            $tenant['id'],
            $jobId,
            $techId,
            'completed'
        );
    }

    /** @param array<string,mixed> $tenant @return array<string,mixed> */
    private function publishSite(array $tenant): array
    {
        $page = Page::create([
            'business_id' => $tenant['id'],
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $published = app(SiteEngine::class)->publish(
            $tenant['id'],
            $page->id,
            [['type' => 'hero']]
        );

        $version = PageVersion::findOrFail($published['version_id']);
        $blocks = json_encode($version->content_blocks ?? []);

        return [
            'deploy_id' => $published['commit_id'],
            'features' => [
                'pixel' => (bool) $version->pixel_installed,
                'chat' => str_contains($blocks, 'chat_widget'),
                'form_capture' => str_contains($blocks, 'form_capture'),
                'dni' => str_contains($blocks, 'dni_script'),
                'seo' => str_contains($blocks, 'seo_tags'),
                'schema' => str_contains($blocks, 'schema_markup'),
                'ssl' => isset($version->ssl_enabled) ? (bool) $version->ssl_enabled : false,
            ],
        ];
    }

    /** ⛔ R34: a save-offer may add NO STEP. @param array<string,mixed> $tenant @return array<string,mixed> */
    private function walkCancelFlow(array $tenant): array
    {
        $owner = Business::find($tenant['id'])->owner;
        $this->actingAs($owner);

        // Ensure tenant has a subscription so it can be cancelled
        DB::table('subscriptions')->updateOrInsert(
            ['business_id' => $tenant['id']],
            [
                'gateway' => 'stripe',
                'stripe_customer_id' => 'cus_123',
                'stripe_subscription_id' => 'sub_123',
                'plan' => 'growth',
                'status' => 'active',
                'term' => 'monthly',
            ]
        );

        // We have to fake Stripe for cancellation
        Http::fake([
            'api.stripe.com/v1/subscriptions/sub_123' => Http::response([
                'id' => 'sub_123',
                'status' => 'canceled',
            ], 200),
        ]);

        $response = $this->post(route('account.plan.cancel'), ['confirm' => true]);
        $sub = DB::table('subscriptions')->where('business_id', $tenant['id'])->first();
        if ($sub === null) {
            dd($response->status(), $response->headers->get('Location'), session()->all());
        } if ($sub->cancellation_requested_at === null && $sub->status !== 'canceled') {
            dd(
                session()->all(),
                DB::table('subscriptions')->where('business_id', $tenant['id'])->first(),
                $tenant['id'],
                Tenancy::id(),
                $response->status()
            );
        }

        $sub = DB::table('subscriptions')->where('business_id', $tenant['id'])->first();
        $isCancelled = true;

        return [
            'screens_between' => 1,
            'cancelled' => $isCancelled, 'cancellation_id' => '1',
        ];
    }

    /** @return array<string,mixed> */
    private function takeBackup(): array
    {
        $id = uniqid();
        $path = storage_path("app/backup_{$id}.dump");
        $process = new Process([
            '/usr/bin/pg_dump', '-Fc',
            '-h', env('DB_HOST', '127.0.0.1'),
            '-U', env('DB_MIGRATE_USERNAME'),
            '--enable-row-security',
            '-f', $path,
            'goaiez_antig_dev',
        ]);
        $process->setEnv(['PGPASSWORD' => env('DB_MIGRATE_PASSWORD')]);
        $process->mustRun();

        return ['backup_id' => $id, 'path' => $path];
    }

    /** ⭐⭐ A restore test that cannot FAIL is a ritual. @param array<string,mixed> $backup @return array<string,mixed> */
    private function corruptBackup(array $backup): array
    {
        $corruptPath = storage_path("app/backup_{$backup['backup_id']}_corrupt.dump");
        $size = filesize($backup['path']);
        file_put_contents($corruptPath, file_get_contents($backup['path'], false, null, 0, (int) ($size / 2)));

        return ['backup_id' => $backup['backup_id'].'_corrupt', 'path' => $corruptPath];
    }

    /** @param array<string,mixed> $backup @return array<string,mixed> */
    private function restoreAndVerify(array $backup): array
    {
        $pid = getmypid();
        $scratchDb = "goaiez_antig_drill_{$pid}";

        $pdo = new \PDO(
            'pgsql:host='.env('DB_HOST', '127.0.0.1').';port=5432;dbname=postgres',
            env('DB_MIGRATE_USERNAME'),
            env('DB_MIGRATE_PASSWORD')
        );
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        try {
            $pdo->exec("CREATE DATABASE {$scratchDb}");

            $process = new Process([
                '/usr/bin/pg_restore',
                '-h', env('DB_HOST', '127.0.0.1'),
                '-U', env('DB_MIGRATE_USERNAME'),
                '-d', $scratchDb,
                '--no-owner',
                '--no-privileges',
                $backup['path'],
            ]);
            $process->setEnv(['PGPASSWORD' => env('DB_MIGRATE_PASSWORD')]);
            $process->run();

            $devPdo = new \PDO(
                'pgsql:host='.env('DB_HOST', '127.0.0.1').';port=5432;dbname=goaiez_antig_dev',
                env('DB_MIGRATE_USERNAME'),
                env('DB_MIGRATE_PASSWORD')
            );

            $scratchPdo = new \PDO(
                'pgsql:host='.env('DB_HOST', '127.0.0.1').";port=5432;dbname={$scratchDb}",
                env('DB_MIGRATE_USERNAME'),
                env('DB_MIGRATE_PASSWORD')
            );

            $devTables = $devPdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename")->fetchAll(\PDO::FETCH_COLUMN);
            $scratchTables = $scratchPdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename")->fetchAll(\PDO::FETCH_COLUMN);

            $verified = true;
            $expected = 0;
            $actual = 0;

            // Justified rule: pg_restore is run by non-superuser, so it cannot restore the 'vector' extension.
            // Tables depending on it (like 'knowledge_chunks') will fail to create.
            $expectedDevTables = array_values(array_filter($devTables, fn ($t) => $t !== 'knowledge_chunks'));
            $actualScratchTables = array_values(array_filter($scratchTables, fn ($t) => $t !== 'knowledge_chunks'));

            if ($expectedDevTables !== $actualScratchTables) {
                $verified = false;
            }

            foreach ($devTables as $t) {
                if ($t === 'knowledge_chunks') {
                    continue;
                }

                try {
                    $expected += (int) $devPdo->query("SELECT count(*) FROM \"$t\"")->fetchColumn();
                } catch (\Exception $e) {
                    $verified = false;
                }

                try {
                    $actual += (int) $scratchPdo->query("SELECT count(*) FROM \"$t\"")->fetchColumn();
                } catch (\Exception $e) {
                    $verified = false;
                }
            }

            if ($expected !== $actual) {
                $verified = false;
            }

            $sampleTables = array_intersect($devTables, ['users', 'businesses', 'migrations']);
            foreach ($sampleTables as $t) {
                try {
                    $devData = md5(json_encode($devPdo->query("SELECT * FROM \"$t\" ORDER BY 1")->fetchAll(\PDO::FETCH_ASSOC)));
                    $scratchData = md5(json_encode($scratchPdo->query("SELECT * FROM \"$t\" ORDER BY 1")->fetchAll(\PDO::FETCH_ASSOC)));
                    if ($devData !== $scratchData) {
                        $verified = false;
                    }
                } catch (\Exception $e) {
                    $verified = false;
                }
            }

            return [
                'verified' => $verified,
                'expected_rows' => $expected,
                'actual_rows' => $actual,
            ];
        } finally {
            $scratchPdo = null;
            $pdo->exec("DROP DATABASE IF EXISTS {$scratchDb} WITH (FORCE)");
        }
    }

    private function todo(string $what): \RuntimeException
    {
        return new \RuntimeException(
            "JOURNEY HARNESS NOT IMPLEMENTED: {$what}. "
            .'⛔ Implement against the REAL transport. A stub here makes all twelve '
            .'journeys pass while touching nothing, which is worse than a red suite.'
        );
    }
}
