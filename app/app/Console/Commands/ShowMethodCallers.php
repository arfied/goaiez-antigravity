<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ops\MethodCallers;
use App\Services\Ops\MethodDeclaration;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Which of our own public methods have no caller in `app/` - decisions
 * 9160-9163.
 *
 * ## ⛔ THE INSTRUMENT `CLAUDE.md` ASKED FOR BY NAME
 *
 * *"A writerless column is a decoration; **a readerless one is a claim nobody
 * checks**, and it is invisible to a column census, which measures whether a
 * *name* is spelled and never whether a *method* is called … the same
 * instrument for methods - **which of our own public methods have no caller in
 * `app/`** - is recommended and not built."* This is that command, and
 * `db:column-readers` is its sibling: same corpus split, same printed caveats,
 * same effect lint, a different subject and **the opposite error direction**.
 *
 * ## ⚠️ IT ERRS TOWARD A FALSE *DEAD*, WHICH IS THE LOUD DIRECTION AND NOT THE
 * SAFE ONE
 *
 * `ColumnReaders` argues that every blind spot of its scores a dead column
 * alive, because *"a false dead gets investigated and corrected, a false alive
 * is never looked at again"*. **A method census cannot have that property** -
 * its hardest shapes are methods reached without their name being spelled - so
 * its failure is 511's instead: **a report that cries wolf is one nobody opens
 * twice.** Every design choice below is spent on that, and the ones that cost
 * something are named in `MethodCallers` rather than implied here.
 *
 * ## ⛔ IT REFUSES NOTHING AND IT IS NOT A LINT
 *
 * It always exits zero. A build-failing *"every method has a caller"* over a
 * tree with 197 enums and sixty-odd contract declarations ships with an
 * exemption list of hundreds on the day it is written, which is 511 within a
 * week; `db:column-readers` refused the same lint for the same reason at 8551.
 * What is machine-checked is in `tests/Feature/Ops/MethodCensusTest.php` and is
 * narrow: the derived role counts, that nothing in the excusal map is unearned,
 * that no `@uncalled` note has gone stale, and that this census cannot change
 * its own answer by printing it.
 *
 * ## ⚠️ THE TRIAGE IS NOT THIS COMMAND'S AND IS NOT ONE PERSON'S
 *
 * *"Is this uncalled deliberately?"* can only be answered by somebody who knows
 * the area - `MessageCostLedger::totalCost()` has been argued five times and is
 * correct, and `ColumnReaders::excluding()` is a test seam built on purpose.
 * **Both are indistinguishable from a defect to any instrument**, which is what
 * `--under` and the `@uncalled` tag are for: a lane triages the namespace it
 * owns and writes the answer at the declaration, where the next census reads it
 * and the lint checks it has not gone stale.
 */
#[Signature('ops:method-callers {--under= : Only declarations whose path starts with this, e.g. app/Services/Billing} {--role= : Only this role - plain, enum, relation, contract, livewire, job, …} {--silent : Only declarations nothing in the tree mentions at all} {--suspect : Instead, list the declarations scored ALIVE by fewest call sites - where the collisions hide} {--collisions : Instead, list the method names the tree has fewer call sites for than declarations} {--acknowledged : Instead, list every @uncalled note and whether it still holds}')]
#[Description('Print every public method declaration nothing in app/ calls')]
final class ShowMethodCallers extends Command
{
    public function handle(MethodCallers $callers): int
    {
        $all = $callers->all();

        $this->newLine();
        $this->line('Subject   '.count($all).' public method DECLARATION sites in app/, constructors excluded.');
        $this->line('          A trait or abstract method is ONE declaration serving many classes;');
        $this->line('          the same tree answers a larger number for class-and-method pairs.');
        $this->line('Searched  '.count($callers->scannedPaths()).' roots, comments stripped. database/ excluded; tests/ counted separately.');
        $this->newLine();

        if ($this->option('collisions')) {
            $this->collisions($callers);
            $this->caveats();

            return self::SUCCESS;
        }

        if ($this->option('acknowledged')) {
            $this->acknowledged($callers);
            $this->caveats();

            return self::SUCCESS;
        }

        $shown = $this->option('suspect')
            ? $this->suspects($callers, $all)
            : $callers->dead();

        $shown = $this->filtered($shown);

        $this->row('DECLARATION', 'METHOD', 'STATE');

        foreach ($shown as $line) {
            $this->row(
                $line->file.':'.$line->line,
                $line->method.'()',
                $this->option('suspect')
                    ? $callers->callMatches($line->method).' call site(s), role '.$line->role
                    : $line->state().($line->testCalls > 0 ? ', '.$line->testCalls.' in tests/' : ''),
            );
        }

        $this->summarise($callers, $all);
        $this->caveats();

        return self::SUCCESS;
    }

    /**
     * The `@uncalled` notes, and whether each still holds.
     *
     * ⛔ **THIS IS THE ANSWER TO *"HOW DOES A DELIBERATE ABSENCE STOP LOOKING
     * LIKE A DEFECT"*, AND IT IS THE ONE DESIGN QUESTION THIS SLICE HAD TO
     * ANSWER.** A test seam and a forgotten method are identical to every
     * instrument; the difference is a ruling, and a ruling lives in a person's
     * head until somebody writes it down. **So it is written at the
     * declaration, keyed to the declaration, and checked.**
     *
     * ⚠️ **IT IS NOT AN EXEMPTION LIST AND THE CHECK IS WHAT MAKES THAT TRUE.**
     * `CLAUDE.md`: an exemption *"a lint grants itself cannot go stale, because
     * there is nothing to notice"*, and deferred-with-a-reason is only
     * distinguishable from a defect when *"the absence is pinned by a test that
     * reddens when it stops being true"*. The day something calls an
     * acknowledged method the note is wrong, this report says so, and
     * `MethodCensusTest` fails the build.
     */
    private function acknowledged(MethodCallers $callers): void
    {
        $this->row('DECLARATION', 'METHOD', 'NOTE');

        foreach ($this->filtered($callers->acknowledged()) as $line) {
            $this->row(
                $line->file.':'.$line->line,
                $line->method.'()',
                ($line->acknowledgementIsStale() ? 'STALE - SOMETHING CALLS IT NOW: ' : '').$line->acknowledgement,
            );
        }

        $this->newLine();
        $this->line('  '.count($callers->acknowledged()).' declaration(s) carry an @uncalled note, of which '
            .count($callers->stale()).' have stopped being true.');
        $this->line('  A note is a record of a ruling and never evidence of a call. The build fails on a stale one.');
    }

    /**
     * The method names the tree cannot account for.
     *
     * ⛔ **THE ONE QUIET BLIND SPOT WITH ARITHMETIC RATHER THAN A CAVEAT**, and
     * it is `db:column-readers --collisions` one level up: there, a bare token
     * carries no table; here, a call site carries no receiver this class can
     * type. `$this->reader->all()` is **one** site reaching **one** declaration
     * and it scores every declaration of that name alive, so a name carried by
     * more declarations than the tree has sites for leaves the difference with
     * no caller at all - and every one of them is missing from the list above.
     */
    private function collisions(MethodCallers $callers): void
    {
        $collisions = $callers->collisions();

        $this->row('METHOD', 'DECLARED BY', 'UNACCOUNTED FOR');

        $omitted = 0;

        foreach ($collisions as $collision) {
            if ($collision['scoredAlive']) {
                $omitted += $collision['unaccounted'];
            }

            $this->row(
                $collision['method'].'()',
                count($collision['declarations']).' declarations',
                $collision['unaccounted'].' of '.count($collision['declarations'])
                    .' from '.$collision['sites'].' untyped call site(s)'
                    .($collision['scoredAlive'] ? ' - scored ALIVE, so missing above' : ' - already listed above'),
            );
        }

        $this->newLine();
        $this->line('  '.count($collisions).' method name(s) the tree cannot account for.');
        $this->line('  At least '.$omitted.' declaration(s) have no call site of their own AND are scored alive,');
        $this->line('  so the list above omits at least that many. This is a floor under a floor.');
    }

    /**
     * The declarations scored *alive*, fewest call sites first.
     *
     * ⚠️ **NO THRESHOLD AND NO EXEMPTIONS**, on `db:column-readers --suspect`'s
     * argument: there is no number of call sites above which a declaration is
     * certainly alive, and picking one would be 511's lint tuned until it
     * catches nothing. **The order is the product** - start at the top and stop
     * when the call sites stop looking like coincidences.
     *
     * @param  list<MethodDeclaration>  $all
     * @return list<MethodDeclaration>
     */
    private function suspects(MethodCallers $callers, array $all): array
    {
        $alive = array_values(array_filter(
            $all,
            static fn (MethodDeclaration $line): bool => ! $line->isDead()
                && $line->framework === null
                && $line->acknowledgement === null,
        ));

        usort(
            $alive,
            static fn (MethodDeclaration $a, MethodDeclaration $b): int => $callers->callMatches($a->method)
                <=> $callers->callMatches($b->method),
        );

        return $alive;
    }

    /**
     * @param  list<MethodDeclaration>  $lines
     * @return list<MethodDeclaration>
     */
    private function filtered(array $lines): array
    {
        $under = $this->option('under');
        $role = $this->option('role');

        if (is_string($under) && $under !== '') {
            $lines = array_values(array_filter(
                $lines,
                static fn (MethodDeclaration $line): bool => str_starts_with($line->file, $under),
            ));
        }

        if (is_string($role) && $role !== '') {
            $lines = array_values(array_filter(
                $lines,
                static fn (MethodDeclaration $line): bool => $line->role === $role,
            ));
        }

        if ($this->option('silent')) {
            $lines = array_values(array_filter(
                $lines,
                static fn (MethodDeclaration $line): bool => $line->state() === 'silent',
            ));
        }

        return $lines;
    }

    /**
     * The counts, and what each of them excuses.
     *
     * ⛔ **WHOM THE GATE EXCUSES IS ENUMERATED HERE AND NOT LEFT TO A DOCBLOCK**
     * - `CLAUDE.md`'s 9020-9039 rule, *"enumerate whom a gate excuses and assert
     * the set in both directions"*. Nearly a sixth of this subject is excused by
     * a rule rather than by a call site, and an operator reading a dead list
     * that short is entitled to know how many rows were taken out of it and by
     * what.
     *
     * ⚠️ **`documented` IS THE FINDING AND NOT A FOOTNOTE.** A method whose
     * only trace is a paragraph recording that nothing calls it **reads as
     * alive to every grep anybody will ever run**, so the ones somebody already
     * investigated are exactly the ones a hand census drops.
     *
     * @param  list<MethodDeclaration>  $all
     */
    private function summarise(MethodCallers $callers, array $all): void
    {
        $dead = $callers->dead();
        $reasons = [];
        $entryPoints = 0;

        foreach ($all as $line) {
            if ($line->framework !== null) {
                $reasons[$line->framework] = ($reasons[$line->framework] ?? 0) + 1;
            }

            if ($line->entryPoint !== null) {
                $entryPoints++;
            }
        }

        arsort($reasons);

        $documented = array_filter($dead, static fn (MethodDeclaration $l): bool => $l->state() === 'documented');
        $tested = array_filter($dead, static fn (MethodDeclaration $l): bool => $l->testCalls > 0);

        $this->newLine();
        $this->line('  '.count($dead).' of '.count($all).' declarations have no call site in app/, none in a template,');
        $this->line('  route file or config map, and no note saying why.');
        $this->line('  '.count($tested).' of those are called from tests/ and nowhere else.');
        $this->line('  '.count($documented).' of those are named in an app/ comment - a grep scores every one of them alive.');
        $this->line('  '.$entryPoints.' declarations are named as an entry point by the router or the event map,');
        $this->line('  out of '.count($callers->derivedCallers()).' the two of them name - the rest resolve to a class'.
            ' the');
        $this->line('  router names whole, or to a verb declared inside the framework, and credit nothing here.');
        $this->line('  '.count($callers->acknowledged()).' carry an @uncalled note ('.count($callers->stale()).' stale). '
            .array_sum($reasons).' are excused as framework hooks:');

        foreach ($reasons as $reason => $count) {
            $this->line('    '.str_pad((string) $count, 5, ' ', STR_PAD_LEFT).'  '.$reason);
        }

        if ($callers->unresolvable() !== []) {
            $this->newLine();
            $this->line('  '.count($callers->unresolvable()).' file(s) under app/ declare no class this could resolve,');
            $this->line('  so their declarations are absent from every number above:');

            foreach ($callers->unresolvable() as $file) {
                $this->line('    '.$file);
            }
        }
    }

    /**
     * What a `no caller` line does not mean - and what an `alive` one does not
     * mean either.
     *
     * ⚠️ **PRINTED EVERY RUN RATHER THAN LEFT IN A DOCBLOCK**, because the
     * failure being closed is a number travelling into a brief without its
     * method. `CLAUDE.md` removed the pixel's measured byte figure from prose
     * for exactly this reason.
     *
     * ⛔ **THESE SENTENCES ARE INSIDE THE CORPUS THE VERDICTS COME FROM.** They
     * are `$this->line()` string literals - a `T_CONSTANT_ENCAPSED_STRING` is
     * not a comment - and `app/Console` is scanned. **A method name typed here
     * is a quoted identifier**, which this census counts as weak evidence and
     * turns into a call **for a policy ability, a relation and an accessor**,
     * so a relation name written bare in this block silently deletes it from
     * the list above. ⚠️ **`db:column-readers` has the identical hazard with a
     * `table.` qualifier to hide behind and a method name has nothing**;
     * `MethodCensusTest` asks this census the same questions over a corpus
     * without this file and fails the build if the answers differ.
     */
    private function caveats(): void
    {
        $this->newLine();
        $this->line('  This is a count under a stated method and not the population. It errs toward a FALSE DEAD,');
        $this->line('  which is the opposite of db:column-readers. It cannot see:');
        $this->line('   · a receiver it cannot type. A call through a property, a container resolution or a');
        $this->line('     variable is counted for EVERY declaration of that name, so it hides a dead one -');
        $this->line('     --collisions is that shortfall, counted rather than described, and --suspect orders');
        $this->line('     the alive set by fewest call sites, which is where the rest of them are.');
        $this->line('   · an implementation reached only through the interface or parent it satisfies. It is');
        $this->line('     scored alive by the calls made to that declaration, so an unbound implementation');
        $this->line('     reads as live. Without that rule a quarter of this list was one abstract job.');
        $this->line('   · a method reached by a computed name - $object->{$verb}() - which reads as dead. The');
        $this->line('     five dispatch primitives that name it in a string are read and score it alive.');
        $this->line('   · a caller in a template written some way this cannot match. A wire: directive and a');
        $this->line('     parenthesised call are read; a bare quoted word is weak evidence and is not a call.');
        $this->line('  A row here means NOTHING IN app/ SPELLS A CALL TO THIS, never THIS METHOD IS DEAD.');
        $this->line('  Whether an absence is deliberate is a ruling: write it at the declaration as @uncalled.');
        $this->newLine();
    }

    private function row(string $declaration, string $method, string $state): void
    {
        // ⚠️ PADDED *AND* MARK-SEPARATED - `laravel/pao`'s OutputCleaner
        // collapses every run of spaces in a dev install and not in production,
        // so padding alone aligns only on the machine nobody is looking at
        // (8003). No test may assert this alignment.
        $this->line('  '.str_pad($declaration, 58).' · '.str_pad($method, 34).' · '.$state);
    }
}
