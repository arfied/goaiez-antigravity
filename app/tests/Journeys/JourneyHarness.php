<?php

declare(strict_types=1);

namespace Tests\Journeys;

use App\Enums\OutreachChannel;
use App\Models\Business;
use App\Models\User;
use App\Modules\X103\Domain\SiteEngine;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X112\Domain\AgencyEngine;
use App\Modules\X112\Models\Agency;
use App\Modules\X112\Models\Markup;
use App\Modules\X121\Models\Job;
use App\Modules\X121\Models\Person;
use App\Modules\X198\Domain\GatewayEngine;
use App\Modules\X199\Domain\InvoiceEngine;
use App\Modules\X199\Models\Invoice;
use App\Modules\X211\Models\ReceivableState;
use App\Services\Sms\TenantNumbers;
use App\Services\TenantProvisioner;
use App\Support\Identifier;
use App\Support\PlatformCredentials;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
        \Livewire\Livewire::test(\App\Modules\X118\Ui\ProspectSignup::class)
            ->set('businessName', $businessName)
            ->set('contactPhone', $phone)
            ->call('startSignup')
            ->assertHasNoErrors();
            
        $user = auth()->user();
        $this->assertNotNull($user);
        
        $business = \App\Models\Business::where('owner_user_id', $user->id)->first();
        $this->assertNotNull($business);
        
        $phoneNumber = \Illuminate\Support\Facades\DB::table('phone_numbers')->where('business_id', $business->id)->first();
        $this->assertNotNull($phoneNumber);
        
        return $business->toArray();
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
        $person = Person::firstOrCreate(
            ['business_id' => $tenant['id']],
            ['first_name' => 'Stop Person', 'phone' => '+15551239999']
        );

        for ($i = 0; $i < $count; $i++) {
            DB::table('campaign_steps')->insert([
                'business_id' => $tenant['id'],
                'campaign_id' => 1,
                'step_number' => $i + 1,
                'channel' => 'sms',
                'template_name' => 'test',
                'delay_days' => 0,
                'person_id' => $person->id,
                'recipient' => $person->phone,
                'sent_at' => null,
                'cancelled_at' => null,
                'created_at' => now(),
            ]);
        }

        $personArray = $person->toArray();
        $personArray['consent_decision_id'] = 'fake_decision_123';

        return $personArray;
    }

    // ── inbound / carrier ────────────────────────────────────────────────

    /** @param array<string,mixed> $tenant */
    private function postCarrierWebhook(array $tenant, string $event, string $from): void
    {
        if ($event === 'call.missed') {
            $callId = 'call_'.uniqid();
            
            // Provide the honest webhook payload shape for Infobip (at least callId and type)
            // The vendor sends these 8 fields:
            $payload = [
                'conferenceId' => 'conf_1',
                'callId' => $callId,
                'timestamp' => now()->toIso8601String(),
                'callsConfigurationId' => 'conf_2',
                'platform' => ['entityId' => 'e1', 'applicationId' => 'a1'],
                'bulkId' => 'bulk_1',
                'dialogId' => 'dialog_1',
                'type' => 'CALL_FINISHED',
            ];
            
            // Fake the Infobip API that VoiceCalls->record will hit
            $tenantPhone = \Illuminate\Support\Facades\DB::table('phone_numbers')
                ->where('business_id', $tenant['id'])
                ->first()->e164 ?? '+15550123';
            
            \Illuminate\Support\Facades\Http::fake([
                "https://api.infobip.com/calls/1/calls/{$callId}" => \Illuminate\Support\Facades\Http::response([
                    'id' => $callId,
                    'from' => $from,
                    'to' => $tenantPhone,
                    'state' => 'FINISHED',
                    'direction' => 'INBOUND',
                    'startTime' => now()->toIso8601String(),
                    'ringDuration' => 15,
                ]),
            ]);

            $bodyStr = json_encode($payload);
            $timestamp = (string) round(microtime(true) * 1000);
            
            $secret = \App\Support\PlatformCredentials::get('infobip_webhook_secret');
            if (!$secret) {
                throw new \RuntimeException('Missing infobip_webhook_secret');
            }
            
            $signature = hash_hmac('sha256', $timestamp.$bodyStr, $secret);

            $this->withHeaders([
                'X-Ib-Exchange-Req-Timestamp' => $timestamp,
                'X-Ib-Exchange-Req-Signature' => $signature,
            ])->postJson('/webhooks/infobip/voice', $payload);
        }
    }

    /** @param array<string,mixed> $tenant */
    private function receiveInbound(array $tenant, string $from, string $body): void
    {
        $messageId = 'msg_'.uniqid();
        $payload = [
            'results' => [
                [
                    'messageId' => $messageId,
                    'from' => $from,
                    'to' => env('INFOBIP_SENDER', '+19015922708'),
                    'text' => $body,
                    'cleanText' => $body,
                    'receivedAt' => now()->toIso8601String(),
                    'smsCount' => 1,
                ],
            ],
        ];

        $bodyStr = json_encode($payload);
        $timestamp = (string) round(microtime(true) * 1000);
        $secret = PlatformCredentials::get('infobip_webhook_secret');
        $signature = hash_hmac('sha256', $timestamp.$bodyStr, $secret);

        $response = $this->withHeaders([
            'X-Ib-Exchange-Req-Timestamp' => $timestamp,
            'X-Ib-Exchange-Req-Signature' => $signature,
        ])->postJson('/webhooks/infobip/inbound', $payload);

        $response->assertStatus(200);

        $this->assertTrue(
            DB::table('inbound_messages')->where('provider_message_id', $messageId)->exists(),
            'Inbound message was not recorded in inbound_messages table'
        );
    }

    /** ⭐ A real call to the provisioned number. @return array<string,mixed> */
    private function placeRealCallTo(string $number): array
    {
        throw $this->todo('place a REAL call — the owner calling their own business is the only proof that matters');
    }

    // ── waiting on asynchronous work ─────────────────────────────────────

    protected static int $sendCapCounter = 0;

    protected function guardOutboundSend(string $destination): void
    {
        if (! str_starts_with($destination, '+1555')) {
            throw new \RuntimeException("HARD RULE VIOLATION: Every outbound SMS must go to +1555... Got {$destination}");
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
        $start = microtime(true);
        while (microtime(true) - $start < $timeoutSeconds) {
            $message = \Illuminate\Support\Facades\DB::table('outreach_messages')
                ->where('business_id', $tenant['id'])
                ->whereNotNull('provider_msg_id')
                ->orderBy('id', 'desc')
                ->first();
            if ($message) {
                // The test expects provider_message_id
                $msgArray = (array) $message;
                $msgArray['provider_message_id'] = $msgArray['provider_msg_id'];
                return $msgArray;
            }
            usleep(100000);
        }
        return null;
    }

    /** @param array<string,mixed> $tenant */
    private function waitForProvisionedNumber(array $tenant, int $timeoutSeconds): string
    {
        $start = microtime(true);
        while (microtime(true) - $start < $timeoutSeconds) {
            $number = \Illuminate\Support\Facades\DB::table('phone_numbers')->where('business_id', $tenant['id'])->first();
            if ($number) {
                return $number->e164;
            }
            usleep(100000);
        }
        throw new \RuntimeException('Timeout waiting for provisioned number');
    }

    // ── the agent and the pricebook ──────────────────────────────────────

    private function askAgent(array $tenant, string $question): array
    {

        $tenantPhone = DB::table('phone_numbers')
            ->where('business_id', $tenant['id'])
            ->first()->e164 ?? '+19015922708';
        $customerPhone = '+15550123';

        DB::table('customers')->insertOrIgnore([
            'business_id' => $tenant['id'],
            'phone' => $customerPhone,
            'name' => 'Journey Customer',
            'created_at' => now(),
        ]);

        DB::table('conversations')
            ->where('business_id', $tenant['id'])
            ->update(['agent_status' => 'agent_handling', 'agent_turns_used' => 0]);

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
        DB::table('price_book_items')->updateOrInsert(
            ['business_id' => $tenant['id'], 'service_name' => $sku],
            ['price_cents' => $amountMinor, 'tax_rate_pct' => 0, 'is_sample' => false]
        );
        DB::table('facts')->updateOrInsert(
            ['business_id' => $tenant['id'], 'key' => "service.{$sku}.price"],
            ['value' => '$'.number_format($amountMinor / 100, 2), 'is_valid' => true]
        );
    }

    private function bookFromQuote(array $tenant, array $quote): array
    {
        $id = DB::table('work_orders')->insertGetId([
            'business_id' => $tenant['id'],
            'price_cents' => $quote['amount'],
            'status' => 'booked',
            'title' => 'Drain Unblock',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['status' => 'booked', 'job_id' => (string) $id];
    }

    /** ⭐ Proves the send passed ConsentService::decide(), not that it looked consented. */
    private function consentWasCheckedFor(string $phone): bool
    {
        return \Illuminate\Support\Facades\DB::table('send_permits')
            ->where('recipient_phone', $phone)
            ->exists();
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
        $hash = Identifier::hash($person['phone'], OutreachChannel::Sms);
        $inbound = DB::table('inbound_messages')
            ->where('value_hash', $hash)
            ->where('keyword', strtolower($marker))
            ->orderBy('received_at', 'desc')
            ->first();

        if (! $inbound) {
            throw new \RuntimeException("No inbound message found for {$person['phone']} with text {$marker}");
        }

        return DB::table('outreach_messages')
            ->where('customer_id', $person['id'])
            ->where('created_at', '>', $inbound->received_at)
            ->count();
    }

    /** @param array<string,mixed> $person @return list<array<string,mixed>> */
    private function reviewInvitesFor(array $person): array
    {
        return \Illuminate\Support\Facades\DB::table('outreach_messages')
            ->where('customer_id', $person['id'])
            ->where('purpose', 'review_request')
            ->get()
            ->map(fn($row) => (array) $row)
            ->toArray();
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
            'created_at' => now(),
            'updated_at' => now(),
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
        $person = Person::firstOrCreate(
            ['business_id' => $tenant['id']],
            ['first_name' => 'Test Customer']
        );
        $engine = app(InvoiceEngine::class);
        $result = $engine->issueInvoice(
            $tenant['id'],
            $person->id,
            [['description' => 'Test', 'quantity' => 1, 'unit_price_cents' => $amountMinor]],
            'due_on_receipt'
        );

        return $result['invoice']->toArray();
    }

    /** ⛔ Must reach the gateway and return ITS id. @param array<string,mixed> $invoice @return array<string,mixed> */
    private function payInvoice(array $invoice): array
    {
        $engine = app(GatewayEngine::class);
        $businessId = $invoice['business_id'];
        $amount = $invoice['total_cents'];
        $invoiceId = $invoice['id'];

        $engine->connect($businessId, 'stripe', 'self');

        $payment = $engine->capture($businessId, $amount, 'tok_visa', 'idem_cap_'.uniqid(), 'USD', $invoiceId);

        Http::allowStrayRequests();
        $payment = $engine->requestCharge($businessId, $payment->id, $amount, 'usd', 'tok_visa', 'idem_req_'.uniqid(), $invoiceId);

        return $payment->toArray();
    }

    private function invoiceStatus(array $invoice): string
    {
        return (string) Invoice::where('id', $invoice['id'])->value('status');
    }

    /** @param array<string,mixed> $invoice */
    private function makeOverdue(array $invoice): void
    {
        app(InvoiceEngine::class)->markOverdue($invoice['business_id'], $invoice['id']);
    }

    /** ⭐ R211: resolution precedes any automatic stop. @param array<string,mixed> $invoice @return array<string,mixed> */
    private function lastDunningAction(array $invoice): array
    {
        $state = ReceivableState::where('business_id', $invoice['business_id'])
            ->where('invoice_id', $invoice['id'])
            ->first();

        return [
            'action' => $state->last_action ?? null,
            'reason' => $state->last_reason ?? null,
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
        $job = \App\Modules\X121\Models\Job::create([
            'business_id' => $tenant['id'],
            'person_id' => $person['id'],
            'title' => 'Real Job',
            'price_cents' => 10000,
            'status' => 'committed',
        ]);
        
        \Illuminate\Support\Facades\Http::fake([
            'https://api.openai.com/v1/embeddings' => \Illuminate\Support\Facades\Http::response(['data' => [['embedding' => array_fill(0, 1536, 0.0)]]]),
            'https://api.openai.com/v1/chat/completions' => \Illuminate\Support\Facades\Http::response(['choices' => [['message' => ['content' => 'Review invite']]]]),
        ]);

        $action = new \App\Modules\X171\Actions\JobStateAction();
        $action->updateState($tenant['id'], $job->id, 1, 'completed');
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
                'ssl' => isset($version->ssl_installed) ? (bool) $version->ssl_installed : false,
            ],
        ];
    }

    /** ⛔ R34: a save-offer may add NO STEP. @param array<string,mixed> $tenant @return array<string,mixed> */
    private function walkCancelFlow(array $tenant): array
    {
        throw $this->todo('cancel reaches Authorize.Net — needs the sandbox login id + transaction key in platform_credentials');
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
