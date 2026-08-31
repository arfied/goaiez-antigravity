<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Consent\StateMessagingRules;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;

/**
 * Records one state's mini-TCPA rule (`29` §2 rule 11, `24` §3.4).
 *
 * ⚠️ THE ROWS ARE COUNSEL'S AND THE TABLE SHIPS EMPTY — see
 * `StateMessagingRules::record()` for why this file does not seed a set of
 * statutes it remembers. This command is the writer that makes the table
 * reachable, so that "counsel has not supplied the rows" stays a different
 * problem from "there is no way to put a row in" (272, 377, 399).
 *
 * ⚠️ THE WINDOW IS THE PROHIBITED ONE, WHICH IS THE OPPOSITE OF HOW MOST PEOPLE
 * SAY IT OUT LOUD. Every statute in this family reads "no telephone
 * solicitation before X or after Y", so the prohibited band is what goes in and
 * it wraps midnight. Typing the permitted window instead produces a rule that
 * forbids exactly the hours it was meant to allow, and it reads correct on the
 * way past — which is why the argument names are `quiet-start` and `quiet-end`
 * rather than `from` and `to`.
 */
#[Signature('compliance:set-state-rule
    {state : Two-letter USPS code}
    {quiet-start : Start of the PROHIBITED window, local time, e.g. 20:00}
    {quiet-end : End of the PROHIBITED window, local time, e.g. 08:00}
    {citation : The statute this row encodes}
    {effective-from : Date this text took effect. An amendment is a NEW row on its own date}
    {--written-consent : This state requires prior express WRITTEN consent for marketing}
    {--notes= : Anything the next reader needs, e.g. a pending amendment}')]
#[Description("Record one state's mini-TCPA quiet hours and consent requirement")]
final class SetStateMessagingRule extends Command
{
    public function handle(StateMessagingRules $rules): int
    {
        $state = strtoupper(trim((string) $this->argument('state')));
        $notes = $this->option('notes');

        try {
            $rule = $rules->record(
                state: $state,
                quietHoursStart: (string) $this->argument('quiet-start'),
                quietHoursEnd: (string) $this->argument('quiet-end'),
                citation: (string) $this->argument('citation'),
                effectiveFrom: (string) $this->argument('effective-from'),
                requiresWrittenConsent: (bool) $this->option('written-consent'),
                notes: is_string($notes) && $notes !== '' ? $notes : null,
            );
        } catch (QueryException $e) {
            // The CHECK constraints refuse a lowercase code and a zero-width
            // window. Surfaced as a message rather than a stack trace, which is
            // the service layer's job in the three-layer rule (314–316) — here
            // the database is the layer that caught it, so this translates.
            $this->components->error(
                'Refused: '.$e->getMessage()
            );

            return self::FAILURE;
        }

        // ⚠️ NO `AuditService` CALL, AND IT IS NOT AN OVERSIGHT. `audit_log` is
        // tenant-owned with RLS on `business_id`, so `record()` calls
        // `Tenancy::idOrFail()` — and a statute belongs to no tenant while this
        // command runs with none resolved. The table is its own history
        // instead: an amendment is a new row on its own `effective_from`, so
        // the prior text is never overwritten. See StateMessagingRules::record().
        $this->components->info(sprintf(
            'Recorded %s from %s: no messaging %s–%s local%s. %s',
            $rule->state,
            $rule->effective_from?->toDateString() ?? '?',
            $rule->quiet_hours_start,
            $rule->quiet_hours_end,
            $rule->requires_written_consent ? ', written consent required' : '',
            $rule->citation,
        ));

        // ⚠️ SAID EVERY TIME, BECAUSE THE ROW APPLIES TO FEWER PEOPLE THAN AN
        // OPERATOR EXPECTS. This warning used to say no customer carried a state
        // at all; `customers.region_code` gained a writer in row 4 slice 5
        // (1594), so the sentence changed rather than went away. What it now
        // says is the thing that is still true and still surprising: the rule
        // reaches exactly those contacts somebody has recorded a state for, and
        // everybody else is refused marketing outright under `StateUnknown`
        // whatever is in this table.
        $this->components->warn(sprintf(
            'This rule applies only to contacts whose state is recorded as %s. Contacts with '
            .'no state are refused marketing outright (SendRefusalReason::StateUnknown), and '
            .'nothing infers one — it is set on the contact or imported with the list.',
            $rule->state,
        ));

        return self::SUCCESS;
    }
}
