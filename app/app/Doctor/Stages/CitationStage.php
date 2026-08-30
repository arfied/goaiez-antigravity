<?php

declare(strict_types=1);

namespace App\Doctor\Stages;

use Symfony\Component\Finder\Finder;

/**
 * STAGE ⑦ CITATION — ~3s, FAILS THE COMMIT.
 *
 * ⛔⛔ THIS STAGE EXISTS BECAUSE A MANUAL AUDIT FOUND SIX BAD CITATIONS IN THIRTY.
 *
 * Every law cited across this codebase was checked by hand on 2026-08-27. All
 * thirty existed. SIX said something materially different from what had been
 * built on them, and one — `M-95` — did not exist at all: it was cited four
 * times, three of those citations quoting the author's own earlier text back at
 * itself. Before that it was `R39`/`R81`/`R168`, three ruling numbers raised
 * eleven times that appear in ZERO package files.
 *
 * ⭐⭐⭐ AN ID NOBODY CAN LOOK UP IS WORSE THAN NO CITATION AT ALL. It looks
 * authoritative, it ends the argument, and it cannot be checked. This stage
 * makes it checkable.
 *
 * ⚠️ WHAT THIS STAGE CANNOT DO, STATED PLAINLY:
 *
 * It proves a law EXISTS. It cannot prove the code MATCHES it. Five of the six
 * findings were of the second kind — `R34` forbids a STEP, not the offer;
 * `R193` names THREE things, not two; `P-163` says TWELVE nouns; `P-198` is the
 * secure-field law, not credential timing; `P-209` names SIXTEEN annotations
 * where thirteen were parsed. Every one of those cited a real law and described
 * it wrongly, and no grep can catch that. ONLY READING CAN.
 *
 * ⭐ So this stage closes the cheap half and says so, rather than implying the
 * expensive half is covered.
 *
 * ⛔⛔⛔ AND ONE MORE THING, LEARNED BY THE CHECK FAILING ITS OWN CONTROL:
 *
 * The first version resolved a citation if the id appeared anywhere in a source
 * document. Run against `M-95` — the id that started this — IT PASSED. Because
 * writing the audit finding into the master plan HAD PUT `M-95` IN THE SOURCE.
 *
 * ⭐⭐⭐ DOCUMENTING A BAD CITATION MADE THE CHECK ACCEPT IT. A programme that
 * writes about its own defects will always contaminate the corpus it checks
 * against, so "appears somewhere" can never be the test.
 *
 * The test is DEFINITION POSITION: the id at the head of a table row, or
 * followed by an em-dash or a capitalised statement. A mention is not a
 * definition. Measured over 24 known cases: 4 of 4 phantoms refused
 * (`M-95` · `R39` · `R81` · `R168`), 19 of 20 real laws passed.
 */
final class CitationStage implements Stage
{
    /** Anything shaped like a law reference in a comment or a message. */
    private const CITATION = '/\b(P-\d{3}|Q-\d{3}|R\d{1,3}|M-\d{1,3}|ML-\d|N-\d{3}(?:-\d{2})?|LAW \d{1,3})\b/';

    /**
     * ⛔ Ids that look like citations and are not. Named individually, because a
     * pattern-based exemption is how a real bad citation slips back in.
     */
    private const NOT_A_LAW = [
        'R2',   // ubiquitous in prose as a shorthand
        'M-1',  // a finding-number prefix used loosely in early sections
    ];

    public function run(): array
    {
        $index = $this->packageIndex();

        if ($index === []) {
            return [[
                'where' => 'package',
                'what' => 'no GOAIEZ-*.md files found, so citations cannot be resolved',
                'fix' => 'run doctor from the repository root, where the package lives',
            ]];
        }

        $out = [];
        $seen = [];

        foreach ($this->sourceFiles() as $file) {
            $path = $file->getRelativePathname();

            foreach ($this->comments($file->getContents()) as $line => $text) {
                preg_match_all(self::CITATION, $text, $m);

                foreach (array_unique($m[1] ?? []) as $id) {
                    if (in_array($id, self::NOT_A_LAW, true)) {
                        continue;
                    }

                    // One report per id per file — a law cited eight times in one
                    // class is one problem, not eight.
                    $key = "{$path}:{$id}";
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;

                    $hits = $index[$id] ?? 0;

                    if ($hits === 0 || ($index["{$id}::defined"] ?? false) === false) {
                        $out[] = [
                            'where' => "{$path}:{$line}",
                            'what' => $hits === 0
                                ? "cites {$id}, which appears NOWHERE in the package"
                                : "cites {$id}, which is MENTIONED {$hits}× but has neither a definition "
                                    .'position NOR a carrier law',
                            'fix' => "state the FACT instead of the id, or give {$id} a definition line. "
                                .'An id nobody can look up looks authoritative and cannot be checked.',
                        ];

                        continue;
                    }

                }
            }
        }

        return $out;
    }

    /**
     * Files this programme GENERATES about itself. ⛔⛔ EXCLUDED FROM THE CORPUS.
     *
     * ⭐⭐⭐ THIS EXCLUSION EXISTS BECAUSE THE BUG HAS BITTEN TWICE.
     *
     *  ① `M-95` was cited 4× and defined 0×. Writing that finding into the plan
     *     put `M-95` IN THE PLAN — and the first version of this stage PASSED it.
     *  ② Twenty legacy ruling ids were reported "never defined". The ledger
     *     entry recording that finding LISTED ALL TWENTY IN ONE LINE, so the
     *     re-check matched its own output and reported all twenty resolved.
     *
     * A programme that writes about its own defects contaminates the corpus it
     * checks against. Definition-POSITION alone is not enough, because a ledger
     * table row is a definition position.
     *
     * @var list<string>
     */
    private const AUTHORED = [
        'GOAIEZ-AUDIT-LEDGER.md',
        'GOAIEZ-LEGACY-RULING-INDEX.md',
        'GOAIEZ-COMPLETION-ESTIMATE.md',
    ];

    /**
     * For every id: whether it is DEFINED, and by what.
     *
     * ⭐⭐ TWO WAYS AN ID CAN BE DEFINED, and the second was missed entirely:
     *
     *  ① DEFINITION POSITION — the id heads a table row, or is followed by an
     *     em-dash and a statement.
     *  ② ⭐⭐⭐ A CARRIER — a P-law row whose BODY cites the id:
     *
     *        | P-011 | Trials need no card (R31); card-less tenants ride…
     *
     *     The P-law holds the content; the R-id is its PROVENANCE. Fifteen of
     *     twenty legacy rulings resolve this way, and a stage that only knew
     *     about ① reported every one of them missing.
     *
     * @return array<string, int|bool>
     */
    private function packageIndex(): array
    {
        $blob = '';
        foreach (glob(base_path('GOAIEZ-*.md')) ?: [] as $path) {
            if (in_array(basename($path), self::AUTHORED, true)) {
                continue;   // ⛔ never resolve a citation against our own findings
            }
            $blob .= (string) file_get_contents($path)."\n";
        }

        if ($blob === '') {
            return [];
        }

        $index = [];
        preg_match_all(self::CITATION, $blob, $m);

        // ⭐ Every law-row body, harvested once — the carrier lookup.
        preg_match_all('/\|\s*\*{0,2}(P-\d{3}|Q-\d{3})\*{0,2}\s*\|([^|]{20,500})/u', $blob, $rows, PREG_SET_ORDER);

        foreach (array_unique($m[1] ?? []) as $id) {
            $index[$id] = substr_count($blob, $id);
            $e = preg_quote($id, '/');

            $byPosition =
                   preg_match('/\|\s*[⭐⛔\s]*\*{0,2}'.$e.'\b[^|]{0,70}\|/u', $blob) === 1
                || preg_match('/\*{0,2}'.$e.'\*{0,2}\s*[—:-]\s*\*{0,2}\w/u', $blob) === 1
                || preg_match('/\*{0,2}'.$e.'\*{0,2}\s+\*{0,2}[A-Z]{3,}/u', $blob) === 1;

            $carrier = null;
            if (! $byPosition) {
                foreach ($rows as $row) {
                    if (preg_match('/\b'.$e.'\b/', $row[2]) === 1) {
                        $carrier = $row[1];
                        break;
                    }
                }
            }

            $index["{$id}::defined"] = $byPosition || $carrier !== null;
            $index["{$id}::carrier"] = $carrier;
        }

        return $index;
    }

    /**
     * Comment and string content only. ⭐ A citation in executable code is a
     * variable name; a citation in a comment is a claim about a law.
     *
     * @return array<int, string>
     */
    private function comments(string $src): array
    {
        $out = [];

        foreach (explode("\n", $src) as $i => $line) {
            $trimmed = ltrim($line);
            $isComment = str_starts_with($trimmed, '//')
                || str_starts_with($trimmed, '*')
                || str_starts_with($trimmed, '/*');

            // Messages shown to a human are claims too — a `fix` string that
            // cites a law is exactly as checkable as a docblock.
            $isMessage = preg_match("/'[^']{20,}'/", $line) === 1;

            if ($isComment || $isMessage) {
                $out[$i + 1] = $line;
            }
        }

        return $out;
    }

    /**
     * ⛔⛔ THE LEGACY TEST SUITE IS NOT THIS PACKAGE'S CODE.
     *
     * The first real run returned 276 violations and EVERY ONE was in tests/ —
     * `CampaignPreflightTest` citing M-363, `UnpluggedInstrumentTest` citing
     * M-241 and M-193, and so on. Those ids are real: they came from earlier
     * build sessions whose records are not in these 76 files.
     *
     * ⭐ They are genuine findings — an id nobody can look up is exactly the
     *   M-95 defect. But 276 of them, all pre-existing, all at COMMIT severity,
     *   is a checker nobody will ever get to zero, and a check that can never
     *   pass is a check people learn to skip.
     *
     * ⛔ So: `app/` is in scope, `tests/` is not. This package writes app/ and
     *   four test files; it does not own a suite it did not write.
     *
     * ⚠️ THE COST OF THIS DECISION, STATED PLAINLY: ~276 unresolvable citations
     *   remain in the test suite and this stage will no longer mention them.
     *   They are listed in the owner's run of build 20260828-1517 if anyone
     *   wants to work them, and `--stage=citation --include-tests` would be the
     *   honest way to bring them back.
     */
    private function sourceFiles(): Finder
    {
        return Finder::create()->files()
            ->in(base_path('app'))
            ->name('*.php')
            // the checker still cannot check itself
            ->filter(static fn (\SplFileInfo $f): bool => ! str_contains(
                str_replace('\\', '/', $f->getPathname()),
                '/app/Doctor/'
            ));
    }
}
