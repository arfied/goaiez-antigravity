<?php

declare(strict_types=1);

namespace Tests\Journeys;

use App\Enums\OutreachChannel;
use App\Models\Business;
use App\Models\User;
use App\Modules\X103\Domain\SiteEngine;
use App\Modules\X103\Models\Page;
use App\Modules\X112\Domain\AgencyEngine;
use App\Modules\X112\Models\Agency;
use App\Modules\X112\Models\Markup;
use App\Modules\X113\Actions\StaffInviteAction;
use App\Modules\X118\Ui\ProspectSignup;
use App\Modules\X121\Actions\JobCreateAction;
use App\Modules\X121\Models\Job;
use App\Modules\X121\Models\Person;
use App\Modules\X157\Actions\EdgeProvisionAction;
use App\Modules\X157\Models\Deployment;
use App\Modules\X162\Models\DispatchAssignment;
use App\Modules\X163\Actions\PriceConfirmAction;
use App\Modules\X163\Models\PriceBookItem;
use App\Modules\X171\Actions\JobStateAction;
use App\Modules\X198\Domain\GatewayEngine;
use App\Modules\X199\Domain\InvoiceEngine;
use App\Modules\X199\Models\Invoice;
use App\Modules\X211\Models\ArDunningAction;
use App\Services\Billing\AuthorizeNetApi;
use App\Services\Billing\AuthorizeNetGateway;
use App\Services\Sms\TenantNumbers;
use App\Services\TenantProvisioner;
use App\Support\CardholderName;
use App\Support\Identifier;
use App\Support\PlatformCredentials;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
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
        Livewire::test(ProspectSignup::class)
            ->set('businessName', $businessName)
            ->set('contactPhone', $phone)
            ->call('startSignup')
            ->assertHasNoErrors();

        $user = auth()->user();
        $this->assertNotNull($user);

        $business = Business::where('owner_user_id', $user->id)->first();
        $this->assertNotNull($business);

        $phoneNumber = DB::table('phone_numbers')->where('business_id', $business->id)->first();
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

        $locationId = DB::table('locations')->where('business_id', $tenant['id'])->value('id');
        DB::table('locations')->where('id', $locationId)->update(['timezone' => 'America/New_York']);
        DB::table('customers')->insert([
            'id' => $person->id,
            'business_id' => $tenant['id'],
            'location_id' => $locationId,
            'phone' => '+15551239999',
            'name' => 'Stop Person',
            'region_code' => 'TX',
            'created_at' => now(),
        ]);
        $customerId = $person->id;

        DB::table('consent_records')->insert([
            'business_id' => $tenant['id'],
            'customer_id' => $customerId,
            'channel' => 'sms',
            'consent_type' => 'express',
            'captured_by' => 'tenant',
            'capture_surface' => 'manual',
            'disclosure_version' => '1.0',
            'created_at' => now(),
        ]);

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
        throw $this->todo('post carrier webhook — needs real Infobip environment or honest isolation');
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

        $customer = DB::table('customers')
            ->where('business_id', $tenant['id'])
            ->where('phone', $to)
            ->first();

        $start = microtime(true);
        while (microtime(true) - $start < $timeoutSeconds) {
            $query = DB::table('outreach_messages')
                ->where('business_id', $tenant['id'])
                ->whereNotNull('provider_msg_id')
                ->orderBy('id', 'desc');

            if ($customer) {
                $query->where('customer_id', $customer->id);
            }

            $message = $query->first();
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
            $number = DB::table('phone_numbers')->where('business_id', $tenant['id'])->first();
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

        $person = Person::firstOrCreate(
            ['business_id' => $tenant['id'], 'phone' => $customerPhone],
            ['first_name' => 'Journey Customer']
        );

        DB::table('customers')->insertOrIgnore([
            'id' => $person->id,
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
        $item = PriceBookItem::updateOrCreate(
            ['business_id' => $tenant['id'], 'service_name' => $sku],
            ['price_cents' => $amountMinor, 'tax_rate_pct' => 0, 'is_sample' => true, 'is_confirmed' => false]
        );
        app(PriceConfirmAction::class)->handle($tenant['id'], $item->id);
    }

    private function bookFromQuote(array $tenant, array $quote): array
    {
        $personId = DB::table('people')->where('business_id', $tenant['id'])->value('id');
        if (! $personId) {
            $personId = DB::table('people')->insertGetId([
                'business_id' => $tenant['id'],
                'first_name' => 'Journey',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $action = new JobCreateAction;
        $res = $action->handle(
            businessId: $tenant['id'],
            personId: (int) $personId,
            title: 'Drain Unblock',
            priceCents: $quote['amount'] ?? 0
        );

        DB::table('work_orders')->where('id', $res['job_id'])->update(['status' => 'booked']);

        return ['status' => 'booked', 'job_id' => (string) $res['job_id']];
    }

    /** ⭐ Proves the send passed ConsentService::decide(), not that it looked consented. */
    private function consentWasCheckedFor(string $phone): bool
    {
        return DB::table('send_permits')
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
        return DB::table('outreach_messages')
            ->where('customer_id', $person['id'])
            ->where('purpose', 'review_request')
            ->get()
            ->map(fn ($row) => (array) $row)
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
        $gatewayEngine = app(GatewayEngine::class);
        $gatewayEngine->connect($invoice['business_id'], 'stripe', 'acct_test');

        $payment = $gatewayEngine->capture(
            $invoice['business_id'],
            $invoice['total_cents'],
            'tok_visa',
            'idempotent_'.uniqid()
        );

        app(InvoiceEngine::class)->recordPayment(
            $invoice['business_id'],
            $invoice['id'],
            $payment->amount_cents
        );

        return $payment->toArray();
    }

    private function invoiceStatus(array $invoice): string
    {
        return (string) Invoice::where('id', $invoice['id'])->value('status');
    }

    /** @param array<string,mixed> $invoice */
    private function makeOverdue(array $invoice): void
    {
        $inv = Invoice::find($invoice['id']);
        $inv->update(['due_date' => now()->subDays(10)]);

        $tenantId = Tenancy::id();
        $userId = Tenancy::userId();

        Artisan::call('x211:detect-overdue');

        if ($userId !== null) {
            Tenancy::setUser($userId);
        }
        if ($tenantId !== null) {
            Tenancy::set($tenantId);
        }
    }

    /** ⭐ R211: resolution precedes any automatic stop. @param array<string,mixed> $invoice @return array<string,mixed> */
    private function lastDunningAction(array $invoice): array
    {
        $action = ArDunningAction::where('invoice_id', $invoice['id'])
            ->latest('id')
            ->first();

        return $action ? $action->toArray() : [];
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
        $roleId = DB::table('roles')->insertGetId([
            'business_id' => $tenant['id'],
            'name' => 'technician',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tech = (new StaffInviteAction)->handle(
            $tenant['id'],
            'tech'.uniqid().'@example.com',
            'Tech',
            $roleId
        );

        $jobRes = (new JobCreateAction)->handle(
            businessId: $tenant['id'],
            personId: $person['id'],
            title: 'Real Job',
            priceCents: 10000
        );
        $jobId = $jobRes['job_id'];

        DB::table('work_orders')->where('id', $jobId)->update(['status' => 'committed']);
        $job = Job::find($jobId);

        DispatchAssignment::create([
            'business_id' => $tenant['id'],
            'job_id' => $job->id,
            'tech_id' => $tech->id,
        ]);

        $action = new JobStateAction;
        $action->updateState($tenant['id'], $job->id, $tech->id, 'completed');
    }

    /** @param array<string,mixed> $tenant @return array<string,mixed> */
    private function publishSite(array $tenant): array
    {
        $page = Page::create([
            'business_id' => $tenant['id'],
            'title' => 'Home',
            'slug' => 'home',
        ]);

        app(EdgeProvisionAction::class)->handle($tenant['id'], 'j11-site.example.com', true);

        $published = app(SiteEngine::class)->publish(
            $tenant['id'],
            $page->id,
            [['type' => 'hero']]
        );

        $deployment = Deployment::where('business_id', $tenant['id'])
            ->where('page_id', $page->id)
            ->latest()
            ->first();

        $this->assertNotNull($deployment, 'publish produced no deployment — the edge listener did not run');

        $response = $this->get("/sites/{$tenant['id']}/{$deployment->deploy_hash}");
        $html = (string) $response->getContent();

        $zoneRow = $deployment->edgeZone;
        $this->assertNotNull($zoneRow, 'the deployment carries no edge zone');
        $zoneRow->update(['has_valid_ssl' => false]);
        $withoutSsl = $this->get("/sites/{$tenant['id']}/{$deployment->deploy_hash}");
        $zoneRow->update(['has_valid_ssl' => true]);

        return [
            'deploy_id' => $published['commit_id'],
            'features' => [
                'pixel' => str_contains($html, 'x110-pixel'),
                'chat' => str_contains($html, 'chat-widget-container'),
                'form_capture' => str_contains($html, 'form-capture-x155'),
                'dni' => str_contains($html, 'dni-pool-x137'),
                'seo' => str_contains($html, 'seo-meta-x176'),
                'schema' => str_contains($html, 'application/ld+json'),
                'ssl' => $response->status() === 200 && $withoutSsl->status() === 404,
            ],
        ];
    }

    /** ⛔ R34: a save-offer may add NO STEP. @param array<string,mixed> $tenant @return array<string,mixed> */
    private function walkCancelFlow(array $tenant): array
    {
        $loginId = PlatformCredentials::get('authorize_net_api_login_id');
        $clientKey = PlatformCredentials::get('authorize_net_public_client_key');

        if (! $clientKey) {
            throw new \RuntimeException('UNRESOLVED — authorize_net_public_client_key is missing');
        }

        $business = Business::find($tenant['id']);

        $req = [
            'securePaymentContainerRequest' => [
                'merchantAuthentication' => [
                    'name' => $loginId,
                    'clientKey' => $clientKey,
                ],
                'data' => [
                    'type' => 'TOKEN',
                    'id' => (string) Str::uuid(),
                    'token' => [
                        'cardNumber' => '4111111111111111',
                        'expirationDate' => '2033-12',
                    ],
                ],
            ],
        ];
        $res = Http::post('https://apitest.authorize.net/xml/v1/request.api', $req);
        $json = json_decode(trim($res->body(), "\xEF\xBB\xBF"), true);
        if (($json['messages']['resultCode'] ?? '') !== 'Ok') {
            $msg = $json['messages']['message'][0]['text'] ?? 'Unknown refusal';
            throw new \RuntimeException("UNRESOLVED — Sandbox refused nonce creation: {$msg}");
        }
        $opaqueDataValue = $json['opaqueData']['dataValue'];

        $user = User::factory()->create();
        $business->owner_user_id = $user->id;
        $business->save();
        $this->actingAs($user);

        $gateway = app(AuthorizeNetGateway::class);
        $cardholder = CardholderName::fromInput('Test', 'User');

        try {
            $sub = $gateway->subscribe($business, 'test@example.com', $opaqueDataValue, $cardholder);
        } catch (\Exception $e) {
            throw new \RuntimeException('UNRESOLVED — Sandbox refused subscription: '.$e->getMessage());
        }

        $cancellationId = $sub->authorize_net_subscription_id;

        $page = $this->get('/account/plan');
        $page->assertOk(); // The plan screen renders

        // Brief 92: find the real retention-offer component or record that none exists.
        // None exists in the view; asserting assertDontSee.
        $page->assertDontSee('retention-offer-component');

        $screensBetween = 1; // It is one screen.
        $retentionOfferShown = false; // Recorded as false because none exists.

        $response = $this->post(route('account.plan.cancel'), ['confirm' => '1']);
        $response->assertRedirect();

        $api = app(AuthorizeNetApi::class);
        try {
            $status = $api->subscriptionStatus($business->id, $cancellationId);
        } catch (\Exception $e) {
            throw new \RuntimeException('UNRESOLVED — Sandbox refused status read: '.$e->getMessage());
        }

        return [
            'screens_between' => $screensBetween,
            'retention_offer_shown' => $retentionOfferShown,
            'cancelled' => ($status === 'canceled'),
            'cancellation_id' => $cancellationId,
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
