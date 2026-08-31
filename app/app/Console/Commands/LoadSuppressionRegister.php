<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ComplianceList;
use App\Enums\OutreachChannel;
use App\Models\ComplianceSuppression;
use App\Services\Consent\SuppressionRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Loads a scrubbing register from a file of identifiers, one per line.
 *
 * ⚠️ THIS EXISTS SO THAT `compliance_suppressions` HAS A WRITER ON THE DAY IT IS
 * CREATED. Five tables in this schema have shipped with a policy, a factory, an
 * isolation test and nothing able to create a row — `Business::provision()`,
 * `autopilot_settings` (377), `feedback_pages`, `review_destinations`, `plugins`
 * (399) — and at five CLAUDE.md turned it into a rule: check for a writer before
 * depending on any table in this schema. An isolation test passes perfectly
 * against a table nothing writes.
 *
 * ⚠️ A COMMAND RATHER THAN AN ADMIN SCREEN, AND NOT BECAUSE IT WAS CHEAPER. The
 * input is a multi-million-line extract downloaded under a subscription
 * agreement, and the operator running it is platform staff with shell access,
 * not a tenant — `29` §2 forbids a tenant-facing toggle here and a tenant has no
 * business editing a federal register. When a register is actually subscribed
 * to, a scheduled monthly refresh calls `replace()` and this stays the manual
 * path.
 *
 * ⚠️ THE FILE IS READ LAZILY, LINE BY LINE. A federal DNC extract does not fit
 * in memory, and `file()` on one would kill the process before a single row was
 * written — which would look exactly like the register being unavailable.
 */
#[Signature('compliance:load-suppressions
    {list : federal_dnc|state_dnc|litigator|reassigned_number}
    {file : Path to a file of identifiers, one per line}
    {--channel=sms : The channel these identifiers belong to}
    {--state= : Two-letter USPS code, required for state_dnc and refused otherwise}
    {--effective-from= : Date the reassignment took effect; required for reassigned_number}
    {--replace : Delete this register\'s existing entries first, in the same transaction}')]
#[Description('Load a DNC, litigator or reassigned-number register from a file')]
final class LoadSuppressionRegister extends Command
{
    /**
     * Who ran this, for the audit row.
     *
     * A console command has no authenticated user, so the actor names the
     * command — which is the honest answer to `29` §2 rule 42's "who caused
     * this". An operator on the box is what it was.
     */
    private const string ACTOR = 'console:compliance:load-suppressions';

    public function handle(SuppressionRegistry $registry): int
    {
        $list = ComplianceList::tryFrom((string) $this->argument('list'));

        if ($list === null) {
            $this->components->error(
                'Unknown register. One of: '.implode(', ', array_column(ComplianceList::cases(), 'value'))
            );

            return self::FAILURE;
        }

        $channel = OutreachChannel::tryFrom((string) $this->option('channel'));

        if ($channel === null) {
            $this->components->error(
                'Unknown channel. One of: '.implode(', ', array_column(OutreachChannel::cases(), 'value'))
            );

            return self::FAILURE;
        }

        $path = (string) $this->argument('file');

        if (! is_readable($path)) {
            $this->components->error("Cannot read [{$path}].");

            return self::FAILURE;
        }

        $effectiveFrom = $this->option('effective-from');
        $state = $this->option('state');

        try {
            // The service validates the list/state/date combination and throws a
            // message an operator can act on. That check is not repeated here —
            // two copies of one rule is how the second stops matching the first.
            $result = $this->option('replace')
                ? $registry->replace(
                    $list,
                    $channel,
                    $this->identifiers($path),
                    // `replace()` needs an actor and `load()` does not: only
                    // replace removes rows, and a removal is what has to be
                    // attributed. See SuppressionRegistry's docblock for why
                    // that attribution lives on the row rather than in
                    // audit_log.
                    self::ACTOR,
                    basename($path),
                    is_string($state) && $state !== '' ? $state : null,
                    is_string($effectiveFrom) && $effectiveFrom !== ''
                        ? CarbonImmutable::parse($effectiveFrom)
                        : null,
                )
                : $registry->load(
                    $list,
                    $channel,
                    $this->identifiers($path),
                    basename($path),
                    is_string($state) && $state !== '' ? $state : null,
                    is_string($effectiveFrom) && $effectiveFrom !== ''
                        ? CarbonImmutable::parse($effectiveFrom)
                        : null,
                );
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Loaded %d into %s on %s. Skipped %d unparseable.%s',
            $result['loaded'],
            $list->value,
            $channel->value,
            $result['skipped'],
            isset($result['removed']) ? sprintf(' Removed %d prior entries.', $result['removed']) : '',
        ));

        // ⚠️ SAID OUT LOUD RATHER THAN LEFT TO BE INFERRED FROM A COUNT. An
        // identifier that did not normalise is not in the register, so it will
        // never be refused — and the operator's mental model after a success
        // message is that the file was loaded.
        if ($result['skipped'] > 0) {
            $this->components->warn(
                'Skipped lines did not normalise to an identifier and were NOT loaded. '
                .'An unparseable number in a register is a number that will never be refused.'
            );
        }

        // `29` §2 rule 11 names four registers, and loading one satisfies none
        // of the other three. Stated here because the success message above is
        // the only thing an operator reads.
        $this->components->info(sprintf(
            'Registers still empty: %s',
            $this->emptyRegisters($list) ?: 'none',
        ));

        return self::SUCCESS;
    }

    /**
     * Which of rule 11's registers still hold nothing.
     */
    private function emptyRegisters(ComplianceList $justLoaded): string
    {
        $empty = [];

        foreach (ComplianceList::cases() as $case) {
            if ($case === $justLoaded) {
                continue;
            }

            if (! ComplianceSuppression::query()->where('list', $case)->exists()) {
                $empty[] = $case->value;
            }
        }

        return implode(', ', $empty);
    }

    /**
     * The file as a generator of trimmed, non-empty lines.
     *
     * @return iterable<string>
     */
    private function identifiers(string $path): iterable
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return;
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);

                if ($line !== '') {
                    yield $line;
                }
            }
        } finally {
            fclose($handle);
        }
    }
}
