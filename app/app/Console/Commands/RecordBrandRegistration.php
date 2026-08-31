<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\BrandRegistrationRefused;
use App\Models\BrandRegistration;
use App\Services\Sms\BrandRegistrations;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Records where a tenant's own 10DLC filing has got to — decision 3310.
 *
 * ⛔ **THIS EXISTS SO `brand_registrations` IS NOT A WRITERLESS TABLE ON THE DAY
 * IT SHIPS**, which is `CLAUDE.md`'s first recurring failure shape and whose tell
 * is *"an isolation test passes perfectly against a table nothing writes"*.
 * `BroadcastPreconditions` refuses a broadcast until a row here says `approved`;
 * without a writer that refusal would be permanent, uniform and invisible, and
 * the feature would look built.
 *
 * ⚠️ **OPS-ONLY, ON `RegisterSendingNumber`'S PRECEDENT.** There is no
 * tenant-facing screen for a number either, `26`'s automated TCR lifecycle and
 * its registration-queue screen are unbuilt, and `CLAUDE.md`'s standing rule is
 * that every tenant-facing toggle is a future support ticket. **The vendor call
 * that actually files the brand is not here** — that is `26`'s automation and it
 * does not exist; what this records is the outcome somebody read off the vendor
 * console, which is the honest shape while the filing is manual.
 *
 * ⚠️ **STILL THE ONLY WRITER, AND SINCE 2026-08-18 NO LONGER THE ONLY READER**
 * (5329, 5420–5439). `App\Livewire\Account\Texting` renders what this
 * command records, so **every run of this command changes what a tenant sees**
 * — a `submit` puts the honest one-to-two-week wait in front of them, an
 * `approve` tells them they are accepted, and a `reject` shows them the
 * `--reason` **verbatim**. That last one is worth pausing over before typing:
 * the reason is written for the person who has to fix it, not for a colleague.
 *
 * ⚠️ **NO EIN, NO TAX ID, NO CONTACT DETAILS ARE ACCEPTED OR PRINTED.** The
 * creating migration argues it: the vendor holds the filing and we hold the
 * answer, and `CLAUDE.md`'s rule is that sensitive data stays off every path it
 * does not have to be on — an argument list reaches shell history and a process
 * table.
 */
#[Signature('sms:brand-registration
    {business : The business id whose own 10DLC filing this is}
    {action : submit | approve | reject}
    {--provider=infobip : Which vendor the filing went through (submit)}
    {--brand= : The vendor\'s brand reference (approve)}
    {--campaign= : The vendor\'s campaign reference (approve)}
    {--reason= : Why the carriers refused it (reject)}')]
#[Description("Record the state of a tenant's own 10DLC brand and campaign registration")]
final class RecordBrandRegistration extends Command
{
    /** Who the record names for an Ops action taken on a vendor console. */
    private const string ACTOR = 'system:brand-registration';

    public function handle(BrandRegistrations $registrations): int
    {
        $businessId = (int) $this->argument('business');
        $action = (string) $this->argument('action');

        if ($businessId <= 0) {
            $this->components->error('Pass the business id: sms:brand-registration 42 submit');

            return self::FAILURE;
        }

        // ⚠️ **`actingAs`, BECAUSE EVERYTHING BELOW IS TENANT-SCOPED.**
        // `brand_registrations` carries the trait, so a call with no tenant
        // established throws rather than reading the whole table — which is the
        // direction this has to fail when the subject is who may send marketing.
        return Tenancy::actingAs($businessId, function () use ($registrations, $action, $businessId): int {
            try {
                return match ($action) {
                    'submit' => $this->submit($registrations, $businessId),
                    'approve' => $this->approve($registrations, $businessId),
                    'reject' => $this->reject($registrations, $businessId),
                    default => $this->unknownAction($action),
                };
            } catch (BrandRegistrationRefused $e) {
                // The service's messages are written for an operator and name
                // the thing they can change, so they are printed as given
                // rather than summarised into "failed".
                $this->components->error($e->getMessage());

                return self::FAILURE;
            }
        });
    }

    private function submit(BrandRegistrations $registrations, int $businessId): int
    {
        $provider = trim((string) $this->option('provider'));

        if ($provider === '') {
            $this->components->error('A filing goes through a vendor. Pass --provider=infobip.');

            return self::FAILURE;
        }

        $registrations->submit($provider, self::ACTOR);

        $this->components->info("Recorded a {$provider} 10DLC filing for business {$businessId}, submitted.");

        // ⚠️ SAID EVERY TIME, BECAUSE THE ROW DOES LESS THAN IT LOOKS LIKE IT
        // DOES. A submission unlocks nothing: `BroadcastPreconditions` reads
        // `approved` and nothing else, and the carriers take roughly one to two
        // weeks (1562).
        $this->components->warn(
            'A submission is not an approval and unlocks no broadcasting. Run this again with '
            .'`approve` when the carriers have cleared both the brand and the campaign.'
        );

        return self::SUCCESS;
    }

    private function approve(BrandRegistrations $registrations, int $businessId): int
    {
        $registration = $this->live($registrations, $businessId);

        if (! $registration instanceof BrandRegistration) {
            return self::FAILURE;
        }

        $registrations->approve(
            $registration,
            (string) $this->option('brand'),
            (string) $this->option('campaign'),
            self::ACTOR,
        );

        $this->components->info(
            "Business {$businessId} is approved on its own 10DLC brand and campaign."
        );

        // ⛔ THE SECOND AND THIRD PRECONDITIONS ARE NOT THIS COMMAND'S AND ARE
        // NAMED SO NOBODY READS THIS AS "BROADCASTING IS ON" (3310).
        $this->components->warn(
            'This is one of three. A broadcast also needs the tenant\'s own number — '
            .'`sms:adopt-own-number` — and a purchased credit balance, and all three are checked '
            .'again at send time.'
        );

        return self::SUCCESS;
    }

    private function reject(BrandRegistrations $registrations, int $businessId): int
    {
        $registration = $this->live($registrations, $businessId);

        if (! $registration instanceof BrandRegistration) {
            return self::FAILURE;
        }

        $registrations->reject($registration, (string) $this->option('reason'), self::ACTOR);

        $this->components->info(
            "Business {$businessId}'s 10DLC filing is recorded as refused. Any broadcast of theirs "
            .'stops on its next recipient.'
        );

        return self::SUCCESS;
    }

    private function live(BrandRegistrations $registrations, int $businessId): ?BrandRegistration
    {
        $registration = $registrations->live();

        if ($registration === null) {
            $this->components->error(
                "Business {$businessId} has no live 10DLC filing to act on. Record the submission "
                .'first: sms:brand-registration '.$businessId.' submit'
            );
        }

        return $registration;
    }

    private function unknownAction(string $action): int
    {
        $this->components->error("Unknown action '{$action}'. Use submit, approve or reject.");

        return self::FAILURE;
    }
}
