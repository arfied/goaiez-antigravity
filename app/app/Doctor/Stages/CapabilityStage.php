<?php

declare(strict_types=1);

namespace App\Doctor\Stages;

use App\Doctor\ManifestReader;
use Symfony\Component\Finder\Finder;

/**
 * STAGE ④ CAPABILITY ⑥ — id-matched, TWO-PASS, FAILS THE MERGE.
 *
 * ⛔⛔ LAW 128 HAS A FLOOR AS WELL AS A CEILING.
 *
 * 116 briefs were emitted and everyone — including the author — spent the whole
 * plan worrying they might be too BIG. Zero were over 20k. TWENTY-FOUR HAD NO
 * CAPABILITY SPECS AT ALL: a brief that passes every gate and contains no
 * requirements. A too-big brief is VISIBLE; a too-small one is short, tidy,
 * readable, and an agent will happily build something from it.
 *
 * ⭐ And the deeper point: P-210 says the agent does not author its tests. For a
 * module with zero specs THERE ARE NO TESTS TO NOT AUTHOR — so the one
 * protection against self-grading silently does not apply to exactly the modules
 * that hold the card vault and the consent service.
 */
final class CapabilityStage implements Stage
{
    public function __construct(private readonly ManifestReader $manifests) {}

    public function run(): array
    {
        $out = [];

        foreach ($this->manifests->all() as $m) {
            $ids = $this->specIds($m->id);

            // ⛔ THE FLOOR. doctor fails on zero.
            if ($ids === []) {
                $out[] = [
                    'where' => $m->id,
                    'what' => 'ZERO specced capabilities — its brief would carry no requirements',
                    'fix' => 'every module carries ≥1 capability with an id, an assertion and a refusal (LAW 128\'s floor)',
                ];

                continue;
            }

            // ⭐ Every id in the brief has a matching test IN THE MODULE. An agent
            //    cannot delete an inconvenient assertion: the id goes missing and
            //    the build fails.
            $tested = $this->testedIds($m->id);
            foreach ($ids as $id) {
                if (! in_array($id, $tested, true)) {
                    $out[] = [
                        'where' => "{$m->id} · {$id}",
                        'what' => 'specced but no test names this id',
                        'fix' => "add a test whose name or attribute carries '{$id}'; the brief's ⑤ is the test contract",
                    ];
                }
            }

            // ⛔ A ⑤ that restates the ① protects nothing. "It sends the email ·
            //    asserted: the email sends" satisfies a count and nothing else.
            //    The value of a ⑤ is that it names a FAILURE MODE, written by
            //    someone who was not building the thing.
            // ⛔ An id with no spec text anywhere — reported once, as itself.
            foreach ($this->orphanSpecIds($m->id) as $id) {
                $out[] = [
                    'where' => "{$m->id} · {$id}",
                    'what' => 'this capability id has NO row in the tracker or the plan',
                    'fix' => 'write a row for it, or remove it from the tracker. An id that exists '
                        .'only in a manifest is a requirement with no statement of what it must do.',
                ];
            }

            foreach ($this->specsWithoutRefusal($m->id) as $id) {
                // ⭐⭐⭐ R240 — A REFUSAL IS REQUIRED ONLY WHERE REFUSAL IS POSSIBLE.
                //
                // This check demanded a refusal on all 966 capability rows and
                // reported 1,519 violations. The owner was offered two options:
                // write 1,294 refusals, or lower LAW 128's floor. Both were wrong.
                //
                // ⛔ "List the tenant's invoices" HAS no refusal. Demanding one
                //   produces rows reading "refuses: n/a" — noise that teaches
                //   people to write n/a everywhere, INCLUDING WHERE IT MATTERS.
                //
                // ⭐ So the requirement is conditional on what the capability DOES:
                //     ① sends outward   ② moves money
                //     ③ answers with a FACT   ④ is irreversible
                //
                // Counted against the real tracker: 322 of 966 meet a test (33%).
                // The other 644 need only id + assertion.
                if (! $this->needsRefusal($id)) {
                    continue;
                }

                $out[] = [
                    'where' => "{$m->id} · {$id}",
                    'what' => 'the ⑤ names no refusal — and this capability CAN refuse',
                    'fix' => 'name the way it goes WRONG and what the system refuses. R240: this one '
                        .'sends outward, moves money, answers with a fact, or is irreversible — '
                        .'so "what does it refuse" has a real answer.',
                ];
            }
        }

        return $out;
    }

    /**
     * ⭐⭐⭐ R240's FOUR TESTS. Matched on the capability's OWN TEXT.
     *
     * ⚠️ AND THE WEAKNESS IS WORTH STATING, because N-240-02 names it:
     *
     * A capability whose wording does not reveal what it DOES will pass this
     * check wrongly. "Handle the thing" matches nothing here and escapes the
     * refusal requirement — and that is a WRITING problem this checker cannot
     * fix. 644 rows now skip the requirement on the strength of their prose.
     *
     * ⛔ So a vague capability is not merely unclear. It is UNPOLICED.
     */
    private const SENDS = '/\b(send|sms|email|mail|text|call|post|publish|invite|outreach|campaign|notif)/i';

    private const MONEY = '/\b(charg|refund|invoic|payment|payout|price|bill|credit|surcharg|spend|fee)\b/i';

    private const FACT = '/\b(quote|price|hour|answer|fact|availab|estimate|book)/i';

    private const IRREVERSIBLE = '/\b(delete|migrat|cancel|sign|restore|purge|terminat|clos)/i';

    private function needsRefusal(string $id): bool
    {
        $text = $this->specText($id);

        if ($text === '') {
            // ⛔⛔⛔ NO. THIS DEFAULT WAS WRONG AND IT COST TWO ROUNDS.
            //
            // "No text → require a refusal" conflates two different problems:
            //   ① a capability that CAN refuse and does not say so  ← R240
            //   ② a capability id with NO SPEC TEXT ANYWHERE        ← orphan id
            //
            // ② is the orphan-id defect wearing R240's clothes, and treating it
            // as ① means demanding a refusal the checker has no basis to judge.
            //
            // ⛔ It also made the count go the WRONG WAY twice. When I tightened
            //   the cache to "the id must LEAD the row" — a genuine improvement —
            //   MORE ids fell through to this branch, and the total went
            //   1,800 → 2,266. A more precise matcher made the number worse,
            //   which is the signature of a bad default.
            //
            // ⭐ An id with no spec text is reported ONCE, as itself, by
            //   orphanSpecIds(). It is not a refusal question.
            return false;
        }

        foreach ([self::SENDS, self::MONEY, self::FACT, self::IRREVERSIBLE] as $test) {
            if (preg_match($test, $text) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * ⭐⭐⭐ Ids in a manifest with NO row in the tracker OR the plan.
     *
     * This is the defect that was hiding inside R240's fallback: a capability id
     * that exists in a manifest and NOWHERE ELSE is a requirement with no
     * statement of what it must do. `capabilities:scaffold` will keep writing it
     * into a manifest forever, with nothing to assert and nothing to refuse.
     *
     * ⛔ Reported ONCE per id, as itself — not as a refusal violation, which is
     *   a question the checker cannot answer without the text.
     *
     * @return list<string>
     */
    private function orphanSpecIds(string $module): array
    {
        $out = [];
        foreach ($this->specIds($module) as $id) {
            if ($this->specText($id) === '') {
                $out[] = $id;
            }
        }

        return $out;
    }

    /** The capability's row text from the tracker, for R240's tests. */
    private function specText(string $id): string
    {
        static $cache = null;

        if ($cache === null) {
            // ⛔⛔⛔ READ BOTH SOURCES. THE SCAFFOLD DOES.
            //
            // This read only the TRACKER. `capabilities:scaffold` writes ids
            // from the tracker AND the master plan — and 299 ids live in the
            // plan alone.
            //
            // Every one of those fell through to "no text → require a refusal",
            // so R240 fired on roughly a third of what it should have. The
            // expected total was ~1,120 and the run came back 1,800: the 680
            // difference is those 299 ids and their knock-on rows.
            //
            // ⭐ The safe default was correct — an id with no text SHOULD demand
            //   a refusal. The defect was that 299 ids HAD text and this method
            //   was looking in one of the two places it lives.
            $cache = [];
            foreach (['GOAIEZ-TRACKER-CAPABILITIES.md', 'GOAIEZ-MASTER-PLAN.md'] as $file) {
                $path = base_path($file);
                if (! is_file($path)) {
                    continue;
                }
                foreach (explode("\n", (string) file_get_contents($path)) as $line) {
                    if (! str_starts_with($line, '|')) {
                        continue;
                    }
                    $cells = array_map('trim', explode('|', $line));

                    // ⛔⛔⛔ ONLY A ROW WHOSE FIRST CELL *IS* THE ID.
                    //
                    // Reading both files made this WORSE — 269 required refusals
                    // became 671 — because the plan contains SECTION SUMMARIES
                    // that merely MENTION a G-id:
                    //
                    //   | §170 | TURN 35 — TELEPHONY | T677 | LIVE | 8 modules ·
                    //     42 rows → 31 specs · the X-204 seam · the model roster…
                    //
                    // That is narrative, not a capability. Matching the whole row
                    // meant a capability about auto-categorisation matched MONEY
                    // because the paragraph said "billing" somewhere.
                    //
                    // ⭐ A capability row LEADS with its id. A summary mentions it
                    //   in passing. The difference is the first cell, and it is
                    //   the whole difference between a spec and a story.
                    // ⛔⛔⛔ THE `/u` FLAG. ITS ABSENCE DROPPED MOST OF THE TRACKER.
                    //
                    // The pattern begins `^⭐?` — a multibyte character. WITHOUT
                    // /u, PCRE treats ⭐ as three raw bytes, and `^⭐?` then
                    // fails to match a cell that does NOT begin with it.
                    //
                    // ⭐ Measured: `**G1-01**` MISSED without /u, MATCHED with it.
                    //   `⭐ **G1-02**` matched either way — so the starred rows
                    //   worked and the plain ones silently did not.
                    //
                    // ⛔⛔ Result: 758 capability ids reported as "NO row in the
                    //   tracker or the plan" when their row was sitting there.
                    //   A regex that works on the rows you eyeball and fails on
                    //   the rest is worse than one that fails on all of them.
                    if (! isset($cells[1]) || preg_match('/^⭐?\s*\*{0,2}(G\d+-\d+|N-\d+(?:-\d+)?)\b/u', $cells[1], $m) !== 1) {
                        continue;
                    }

                    // ⭐ And only the NAME and ACTION cells — not the ⑤ prose,
                    //   not the section reference. What it IS, not what it says.
                    $subject = implode(' ', array_slice($cells, 2, 3));
                    $cache[$m[1]] = ($cache[$m[1]] ?? '').' '.$subject;
                }
            }
        }

        return $cache[$id] ?? '';
    }

    /** @return list<string> */
    private function specIds(string $module): array
    {
        $f = base_path("app/Modules/{$module}/capabilities.php");
        if (! is_file($f)) {
            return [];
        }
        preg_match_all('/[\'"]((?:G\d+-\d+|N-\d+(?:-\d+)?))[\'"]/', (string) file_get_contents($f), $m);

        return array_values(array_unique($m[1] ?? []));
    }

    /** @return list<string> */
    private function testedIds(string $module): array
    {
        $dir = base_path("tests/Modules/{$module}");
        if (! is_dir($dir)) {
            return [];
        }
        $ids = [];
        foreach (Finder::create()->files()->in($dir)->name('*.php') as $f) {
            preg_match_all('/\b(G\d+-\d+|N-\d+(?:-\d+)?)\b/', $f->getContents(), $m);
            $ids = array_merge($ids, $m[1] ?? []);
        }

        return array_values(array_unique($ids));
    }

    /** @return list<string> */
    private function specsWithoutRefusal(string $module): array
    {
        $f = base_path("app/Modules/{$module}/capabilities.php");
        if (! is_file($f)) {
            return [];
        }
        $out = [];
        foreach (preg_split('/(?=[\'"](?:G\d+-\d+|N-\d+))/', (string) file_get_contents($f)) ?: [] as $chunk) {
            if (preg_match('/[\'"]((?:G\d+-\d+|N-\d+(?:-\d+)?))[\'"]/', $chunk, $m) !== 1) {
                continue;
            }
            if (preg_match('/refus|REFUSED|fails|cannot|never/i', $chunk) !== 1) {
                $out[] = $m[1];
            }
        }

        return $out;
    }
}
