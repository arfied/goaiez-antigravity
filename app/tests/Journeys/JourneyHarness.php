<?php

declare(strict_types=1);

namespace Tests\Journeys;

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
        throw $this->todo('an agency and a client with a real grant row (R233 N-233-01)');
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
     * @param array<string,mixed> $tenant
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
        throw $this->todo('return the API payload as this principal sees it — never the rendered view');
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
        throw $this->todo('publish a real site and report which of the seven features shipped');
    }

    /** ⛔ R34: a save-offer may add NO STEP. @param array<string,mixed> $tenant @return array<string,mixed> */
    private function walkCancelFlow(array $tenant): array
    {
        throw $this->todo('walk cancellation and COUNT SCREENS — screen count is the thing that cannot be argued about');
    }

    /** @return array<string,mixed> */
    private function takeBackup(): array
    {
        throw $this->todo('take a real backup');
    }

    /** ⭐⭐ A restore test that cannot FAIL is a ritual. @param array<string,mixed> $backup @return array<string,mixed> */
    private function corruptBackup(array $backup): array
    {
        throw $this->todo('corrupt the backup ON PURPOSE so verification has something to catch');
    }

    /** @param array<string,mixed> $backup @return array<string,mixed> */
    private function restoreAndVerify(array $backup): array
    {
        throw $this->todo('restore and verify row counts against expected');
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
