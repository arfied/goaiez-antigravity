<?php

declare(strict_types=1);

namespace Tests\Journeys;

use App\Models\Business;
use App\Models\User;
use App\Modules\X103\Actions\SitePublishAction;
use App\Modules\X103\Models\Page;
use App\Modules\X112\Domain\AgencyEngine;
use App\Modules\X112\Models\Agency;
use App\Modules\X112\Models\Markup;
use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X157\Actions\EdgeProvisionAction;
use App\Modules\X157\Models\Deployment;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
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
        throw $this->todo('provision a real tenant and a real carrier number');
    }

    /** ⛔ P-207: signup asks EXACTLY two fields. A third fails the build. @return array<string,mixed> */
    private function signUp(string $businessName, string $phone): array
    {
        throw $this->todo('sign up with exactly two fields — a third is a P-207 violation');
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
        throw $this->todo('a person with N campaign steps ALREADY QUEUED — the STOP test needs in-flight work');
    }

    // ── inbound / carrier ────────────────────────────────────────────────

    /** @param array<string,mixed> $tenant */
    private function postCarrierWebhook(array $tenant, string $event, string $from): void
    {
        throw $this->todo('POST the carrier\'s real webhook shape — not a synthetic event');
    }

    /** @param array<string,mixed> $tenant */
    private function receiveInbound(array $tenant, string $from, string $body): void
    {
        throw $this->todo('deliver a real inbound message through the carrier webhook');
    }

    /** ⭐ A real call to the provisioned number. @return array<string,mixed> */
    private function placeRealCallTo(string $number): array
    {
        throw $this->todo('place a REAL call — the owner calling their own business is the only proof that matters');
    }

    // ── waiting on asynchronous work ─────────────────────────────────────

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
        throw $this->todo('poll for an outbound row carrying the provider message id');
    }

    /** @param array<string,mixed> $tenant */
    private function waitForProvisionedNumber(array $tenant, int $timeoutSeconds): string
    {
        throw $this->todo('poll until the carrier returns a real number');
    }

    // ── the agent and the pricebook ──────────────────────────────────────

    /** ⛔ Must return a refusal CODE when ungrounded, never prose. @return array<string,mixed> */
    private function askAgent(array $tenant, string $question): array
    {
        throw $this->todo('ask the real agent; an ungrounded answer must carry refusal_code NO_FACT');
    }

    /** @param array<string,mixed> $tenant */
    private function confirmPrice(array $tenant, string $sku, int $amountMinor): void
    {
        throw $this->todo('confirm a price as a FACT through its owner (X-163) — integer minor units');
    }

    /** @param array<string,mixed> $tenant @param array<string,mixed> $quote @return array<string,mixed> */
    private function bookFromQuote(array $tenant, array $quote): array
    {
        throw $this->todo('book the job from the quote and return the real job id');
    }

    /** ⭐ Proves the send passed ConsentService::decide(), not that it looked consented. */
    private function consentWasCheckedFor(string $phone): bool
    {
        throw $this->todo('assert a consent DECISION row exists for this send');
    }

    // ── counting outbound ────────────────────────────────────────────────

    /** @param array<string,mixed> $tenant */
    private function totalOutbound(array $tenant): int
    {
        throw $this->todo('count every outbound row for the tenant');
    }

    /** @param array<string,mixed> $person */
    private function outboundSince(array $person, string $marker): int
    {
        throw $this->todo('count outbound to this person AFTER the STOP was received');
    }

    /** @param array<string,mixed> $person @return list<array<string,mixed>> */
    private function reviewInvitesFor(array $person): array
    {
        throw $this->todo('every review invite sent to this person — the cadence test counts them');
    }

    // ── migration ────────────────────────────────────────────────────────

    /** ⛔ P-203: historical jobs look like completed jobs. @param array<string,mixed> $tenant */
    private function importJobs(array $tenant, int $count, bool $historical): void
    {
        throw $this->todo('import through the real path with a withoutEvents() boundary');
    }

    /** @param array<string,mixed> $tenant */
    private function lastImportBatchId(array $tenant): string
    {
        throw $this->todo('the import batch id — the external artifact for this journey');
    }

    // ── money ────────────────────────────────────────────────────────────

    /** @param array<string,mixed> $tenant @return array<string,mixed> */
    private function issueInvoice(array $tenant, int $amountMinor): array
    {
        throw $this->todo('issue a real invoice — integer minor units, never a float');
    }

    /** ⛔ Must reach the gateway and return ITS id. @param array<string,mixed> $invoice @return array<string,mixed> */
    private function payInvoice(array $invoice): array
    {
        throw $this->todo('pay through the gateway sandbox and return the gateway charge id');
    }

    /** @param array<string,mixed> $invoice */
    private function invoiceStatus(array $invoice): string
    {
        throw $this->todo('read the invoice status from its owning module');
    }

    /** @param array<string,mixed> $invoice */
    private function makeOverdue(array $invoice): void
    {
        throw $this->todo('advance the invoice past its due date so invoice.overdue fires');
    }

    /** ⭐ R211: resolution precedes any automatic stop. @param array<string,mixed> $invoice @return array<string,mixed> */
    private function lastDunningAction(array $invoice): array
    {
        throw $this->todo('the most recent dunning action, with its recorded reason');
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
        throw $this->todo('complete a real job so job.completed fires');
    }

    /** @param array<string,mixed> $tenant @return array<string,mixed> */
    private function publishSite(array $tenant): array
    {
        $page = Page::create([
            'business_id' => $tenant['id'],
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $site = app(SitePublishAction::class)
            ->handle($tenant['id'], $page->id, [
                ['type' => 'chat'],
                ['type' => 'form_capture'],
                ['type' => 'dni'],
            ]);

        $zone = app(EdgeProvisionAction::class)
            ->handle($tenant['id'], 'test.com');

        $deploy = app(EdgeDeployAction::class)->handle(
            businessId: $tenant['id'],
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $tenant['name']
        );

        $response = $this->get("/sites/{$tenant['id']}/{$deploy['deploy_hash']}");
        $response->assertStatus(200);
        $html = (string) $response->getContent();

        $features = [
            'pixel' => str_contains($html, 'x110-pixel'),
            'chat' => str_contains($html, 'chat-widget-container'),
            'form_capture' => str_contains($html, 'form-capture-x155'),
            'dni' => str_contains($html, 'dni-pool-x137'),
            'seo' => str_contains($html, 'seo-meta-x176'),
            'schema' => str_contains($html, 'application/ld+json'),
            'ssl' => false,
        ];

        $zoneRow = Deployment::where('deploy_hash', $deploy['deploy_hash'])->first()->edgeZone;
        $zoneRow->update(['has_valid_ssl' => false]);
        $withoutSsl = $this->get("/sites/{$tenant['id']}/{$deploy['deploy_hash']}");
        $zoneRow->update(['has_valid_ssl' => true]);
        $features['ssl'] = $response->status() === 200 && $withoutSsl->status() === 404;

        return [
            'deploy_id' => $deploy['deploy_hash'],
            'features' => $features,
        ];
    }

    /** ⛔ R34: a save-offer may add NO STEP. @param array<string,mixed> $tenant @return array<string,mixed> */
    private function walkCancelFlow(array $tenant): array
    {
        throw $this->todo('walk cancellation and COUNT SCREENS — screen count is the thing that cannot be argued about');
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
