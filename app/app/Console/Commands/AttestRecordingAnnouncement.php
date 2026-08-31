<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\RecordingAnnouncementNotAttested;
use App\Services\Consent\ConsentProof;
use App\Services\Voice\RecordingAnnouncement;
use App\Services\Voice\RecordingAnnouncementAttestation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * The writer the recording-announcement attestation did not have (4514).
 *
 * ## ⛔ THE GATE WAS BUILT AND NOTHING COULD SATISFY IT
 *
 * {@see RecordingAnnouncement} landed with two readers — the registry
 * precondition and the ingest job — and **no caller of `attest()` anywhere in
 * `app/`**. That is `CLAUDE.md`'s first recurring failure shape with the sign
 * flipped: not a control nothing writes, a *precondition nothing can meet*. The
 * consequence is exact and was not hypothetical: step 7 of the activation
 * runbook (4423) is *"turn `voice.enabled` on in Ops"*, and with no writer that
 * step **could not be performed by any path in this application** — the switch
 * refuses, for the right reason, for ever.
 *
 * ⚠️ **AND THE TELL WAS A GREEN SUITE**, because every test attested by calling
 * the service directly. A feature test that constructs the writer itself proves
 * the mechanism and says nothing about whether a person can reach it.
 *
 * ## Why a console command rather than an Ops screen
 *
 * ⚠️ **BECAUSE OF WHERE THE OPERATOR ALREADY IS.** Steps 2–6 of the runbook are
 * a vendor console and an `.env` edit on the server, and the clip id being
 * attested is the one they just typed into Infobip's number configuration. A
 * screen would move the statement away from the act it describes, and add the
 * support surface `CLAUDE.md` refuses by default. `staff:grant` and
 * `sms:brand-registration` are the same shape: an Ops act with an actor and a
 * record, taken on the box.
 *
 * ⛔ **IT IS NOT `--no-interaction`-SAFE, AND THAT IS DELIBERATE.** The whole
 * value of an attestation is that a named person was shown these words and said
 * yes to them, so the confirmation defaults to **no** — a deploy script that
 * runs this non-interactively records nothing and exits 1, rather than signing a
 * statement on somebody's behalf.
 */
#[Signature('voice:announcement-attestation
    {action : record | withdraw | show}
    {--clip= : The clip id placed first on the number\'s Calls configuration (record)}
    {--operator= : Who is attesting or withdrawing — an audit actor such as support:9}')]
#[Description('Record, withdraw or show the operator attestation that every caller hears the recording announcement')]
final class AttestRecordingAnnouncement extends Command
{
    public function handle(RecordingAnnouncement $announcement): int
    {
        return match ((string) $this->argument('action')) {
            'record' => $this->record($announcement),
            'withdraw' => $this->withdraw($announcement),
            'show' => $this->show($announcement),
            default => $this->unknownAction(),
        };
    }

    private function record(RecordingAnnouncement $announcement): int
    {
        $operator = $this->operator();

        if ($operator === null) {
            return self::FAILURE;
        }

        $clip = trim((string) $this->option('clip'));

        if ($clip === '') {
            $this->components->error(
                'Pass the clip that is first on the number: voice:announcement-attestation record '
                .'--clip=announce-8k-ulaw.wav --operator=support:9'
            );

            return self::FAILURE;
        }

        // ⛔ **THE WORDS ARE SHOWN BEFORE THE ANSWER IS TAKEN, AND THEY COME FROM
        // THE SERVICE.** What is stored has to be what was displayed, so there is
        // no second copy of this sentence here to drift from the one the
        // attestation records a version of.
        $this->components->info($announcement->statement());
        $this->line('  Clip: '.$clip);

        if (! $this->confirm('Is that true of this platform right now?', false)) {
            $this->components->warn('Nothing recorded. Call recording stays refused.');

            return self::FAILURE;
        }

        try {
            $announcement->attest(new RecordingAnnouncementAttestation(
                statementVersion: RecordingAnnouncement::STATEMENT_VERSION,
                attestedBy: $operator,
                announcementMediaId: $clip,
                // ⚠️ **PROVENANCE, NOT A PERSON.** The host says where the
                // statement was made; who made it is `attestedBy`. An OS
                // username would be a second, weaker name for the same person
                // and `CLAUDE.md`'s tie-break is less stored personal data.
                proof: ['channel' => 'console', 'host' => self::provenanceHost((string) gethostname())],
            ));
        } catch (RecordingAnnouncementNotAttested|InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Recorded. `voice.enabled` can now be turned on in Ops.');

        return self::SUCCESS;
    }

    /**
     * The machine name, or a marker when the machine is named by an address.
     *
     * ⛔ **`29` §2 RULE 21 IS ABSOLUTE AND THE WALK THAT ENFORCES IT CANNOT TELL
     * A SERVER'S OWN ADDRESS FROM A PERSON'S** (2933, 7954(c)). Since 2026-08-22
     * {@see RecordingAnnouncementAttestation} delegates to
     * {@see ConsentProof::refuseRawAddress()}, which
     * checks every string at every depth rather than two key names — so a
     * container or VPS whose `gethostname()` is a bare address would make this
     * command throw, and an operator on that host could never attest at all.
     *
     * ⚠️ **THAT IS A HARD FAIL WITH NO WAY FORWARD**, which rule 43's surviving
     * half forbids (3294): the attestation is the precondition for `voice.enabled`,
     * so the whole feature would be unreachable on such a host. The marker keeps
     * the record honest — it says the provenance was withheld and why, rather
     * than inventing a hostname — and `channel` still carries the rest.
     *
     * ⚠️ **THE HOST IS A PARAMETER SO THAT BOTH ARMS CAN BE DRIVEN.** Reading
     * `gethostname()` inside would leave the branch that matters reachable only
     * on a machine named by an address, which is no machine anybody runs the
     * suite on — 256's shape in a method rather than in a lint.
     */
    public static function provenanceHost(string $host): string
    {
        return filter_var($host, FILTER_VALIDATE_IP) === false
            ? $host
            : 'withheld: this host is named by an address';
    }

    /**
     * ⚠️ **IT DOES NOT TURN RECORDING OFF, AND SAYS SO.**
     * {@see RecordingAnnouncement::revoke()} deliberately leaves the switch
     * alone — two acts, both logged, rather than one that quietly does the
     * other — so this prints the second step instead of taking it.
     */
    private function withdraw(RecordingAnnouncement $announcement): int
    {
        $operator = $this->operator();

        if ($operator === null) {
            return self::FAILURE;
        }

        $announcement->revoke($operator);

        $this->components->info('Withdrawn. No new recording will be stored.');
        $this->components->warn(
            'Calls may still be being recorded by the vendor. Turn `voice.enabled` off in Ops as well '
            .'if that is what you meant.'
        );

        return self::SUCCESS;
    }

    private function show(RecordingAnnouncement $announcement): int
    {
        $current = $announcement->current();

        if ($current === null) {
            $this->components->warn('Nobody has attested the recording announcement. Recording is refused.');

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail('Attested by', $current->attestedBy);
        $this->components->twoColumnDetail('Clip', $current->announcementMediaId);
        $this->components->twoColumnDetail('Wording version', $current->statementVersion);

        return self::SUCCESS;
    }

    /**
     * The person making the statement, or null when they have not been named.
     *
     * ⛔ **A `system:` ACTOR IS REFUSED.** The one question an attestation exists
     * to answer is who said this, and every other Ops command here names the
     * platform as the actor precisely because nobody typed the value. This is
     * the opposite case: the whole artefact is a person's claim.
     */
    private function operator(): ?string
    {
        $operator = trim((string) $this->option('operator'));

        if ($operator === '' || str_starts_with($operator, 'system:')) {
            $this->components->error(
                'Pass the person taking this action: --operator=support:9. An attestation nobody '
                .'signed is not evidence, and `system:` is not a person.'
            );

            return null;
        }

        return $operator;
    }

    private function unknownAction(): int
    {
        $this->components->error('Unknown action. Use: record | withdraw | show');

        return self::FAILURE;
    }
}
