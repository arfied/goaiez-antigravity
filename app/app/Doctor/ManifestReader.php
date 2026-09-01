<?php

declare(strict_types=1);

namespace App\Doctor;

use Symfony\Component\Finder\Finder;

/**
 * Reads the module contract out of the code — and out of NOTHING ELSE.
 *
 * ⭐⭐⭐ THIS CLASS IS THE ZERO-DRIFT LAW MADE MECHANICAL.
 *
 * Every stale-record defect this programme produced was a HAND-MAINTAINED file:
 *   · the status column said CLASSIFIED while 725 specs existed
 *   · 57 rows pointed at a side file instead of naming a parent
 *   · a P-163 violation was found, written down, and never applied
 *   · the law grid claimed it had added seven modules it had not
 *   · `@laws` was proposed at §173 and written into 0 of 119 headers
 *
 * ⛔ So nothing here is read from a tracker, a grid or a markdown table.
 * `doctor`, `map`, `context`, `impact` and `brief` all read THIS, and this
 * reads the annotations in the source. A document that can drift from the code
 * is deleted and generated instead.
 *
 * ⚠️⚠️ INCOMPLETE, AND SAYING SO IS THE POINT.
 *
 * P-209 says "SIXTEEN annotations; doctor reads them". This class parses
 * THIRTEEN. The four not yet read are named in MISSING_ANNOTATIONS below.
 *
 * ⛔ P-209 also carries THE BACKFILL GATE: "every module must declare every
 * applicable marker BEFORE the Blueprint may run" — and warns that today
 * "@intent appears only where it was invented, @sink 8 times, @reads_table
 * twice", so a generator would produce briefs "in which arbitration, the
 * day-one default and the ladder floor are simply absent."
 *
 * ⭐ A reader that silently parses 13 of 16 produces exactly that brief and
 * reports success. Naming the gap is what stops it.
 */
final class ManifestReader
{
    /**
     * ⛔ P-163: no other module may claim one of these in `@owns_table`. The
     * instant that check existed it found C-Reviews claiming `reviews` and X-186
     * claiming `campaigns` — and both were still there months later, because
     * finding a defect and fixing it are different acts.
     *
     * ⭐⭐ TWELVE, NOT THIRTEEN — AND THE DISTINCTION IS REAL.
     *
     * P-163 says "the TWELVE canonical nouns" in four places. X-121's
     * `@owns_table` lists THIRTEEN. Both are correct: `entity_history` is the
     * AUDIT TRAIL OF the nouns, not a noun itself. X-121 owns it for the same
     * reason it owns the graph, but it is not a business entity anyone models.
     *
     * ⛔ Keeping them in one flat list would have quietly redefined P-163 to
     * mean thirteen, and the next person to count would find a law and a
     * constant that disagree — which is how "the 905" and the CLASSIFIED status
     * column both started.
     */
    public const CANONICAL_NOUNS = [
        'businesses', 'people', 'conversations', 'messages', 'jobs', 'reviews',
        'campaigns', 'assets', 'ledger_entries', 'facts', 'sites', 'numbers',
    ];

    /**
     * Also owned exclusively by X-121, and equally forbidden to other modules —
     * but NOT a canonical noun. Checked together, reported separately, so the
     * violation message names the right law.
     */
    public const X121_OWNED_NON_NOUNS = ['entity_history'];

    /**
     * ⛔ P-209's sixteen, minus the thirteen parsed below. Each is a real gate
     * that currently cannot fire:
     *
     *   @sink            — where data leaves the system; P-209 counts 8 uses today
     *
     *   @agent_reachable — THE ALLOW-LIST. P-209: "the agent's action surface is a
     *                      DECLARED ALLOW-LIST, not everything registered minus a
     *                      deny-list — a deny-list FAILS OPEN: an action added in
     *                      six months is reachable by the agent by default."
     *
     *   @excludes        — a module naming a fenced term to say it does NOT do it
     *
     *   @laws            — struck by D4; the binding is generated from the law grid
     *
     * ⭐⭐ @agent_reachable is the one that matters. Until it is parsed, nothing
     * enforces the allow-list, and the fail-open case P-209 warns about is the
     * live behaviour.
     *
     * @var list<string>
     */
    public const MISSING_ANNOTATIONS = ['@sink', '@excludes'];

    /**
     * ⭐⭐⭐ @agent_reachable IS NOW PARSED — it was the fail-open in P-209.
     *
     * P-209, verbatim: "the agent's action surface is a DECLARED ALLOW-LIST, not
     * everything registered minus a deny-list — a deny-list FAILS OPEN: an action
     * added in six months is reachable by the agent BY DEFAULT."
     *
     * ⛔ Until this was read, nothing enforced the allow-list, so the fail-open
     * case the law warns about WAS the live behaviour. An action nobody thought
     * about was agent-reachable because nobody had blocked it.
     */
    public const AGENT_REACHABLE_MARKER = '@agent_reachable';

    /** @var list<Manifest>|null */
    private ?array $cache = null;

    /** @return list<Manifest> */
    public function all(): array
    {
        return $this->cache ??= $this->scan();
    }

    public function find(string $id): ?Manifest
    {
        foreach ($this->all() as $m) {
            if ($m->id === $id) {
                return $m;
            }
        }

        return null;
    }

    /** @return list<Manifest> */
    private function scan(): array
    {
        $out = [];

        foreach (Finder::create()->files()->in(base_path('app/Modules'))->name('manifest.php') as $file) {
            $path = $file->getRelativePathname();

            // ⛔⛔⛔ A MANIFEST IS PHP. REQUIRE IT. DO NOT REGEX IT.
            //
            // This method used to read the SOURCE TEXT with
            //   preg_match('/@owns_table\s+(.+)$/m', $src)
            // and every manifest the scaffold writes contains:
            //
            //   // ⛔ P-163 — @owns_table may not name one of X-121's canonical nouns.
            //   'owns_table' => ['agent_turns', ...],
            //
            // The regex hit the COMMENT and returned the sentence as the field's
            // value — on all 122 modules, which is exactly the 122 violations
            // the owner saw, all with identical text.
            //
            // ⭐⭐⭐ The file is PHP that RETURNS AN ARRAY. `require` gives the
            //   real value with no parser, no comment confusion, and no format
            //   to disagree about. Regexing generated PHP was never the right
            //   move — it just took four rounds of failing counts to see it.
            $data = @require $file->getPathname();
            if (! is_array($data) || ! isset($data['module'])) {
                continue;
            }

            $list = static function (mixed $v): array {
                if (! is_array($v)) {
                    return [];
                }

                return array_values(array_filter(array_map(
                    static fn (mixed $x): string => trim((string) $x),
                    $v
                ), static fn (string $s): bool => $s !== ''));
            };

            $out[] = new Manifest(
                id: (string) $data['module'],
                path: $path,
                intent: (string) ($data['intent'] ?? 'NONE'),
                provides: $list($data['provides'] ?? []),
                emits: $list($data['emits'] ?? []),
                consumes: $list($data['consumes'] ?? []),
                ownsTable: $list($data['owns_table'] ?? []),
                readsTable: $list($data['reads_table'] ?? []),
                renders: $list($data['renders'] ?? []),
                shipsAt: (string) ($data['ships'] ?? 'n/a'),
                ceiling: (string) ($data['ceiling'] ?? 'n/a'),
                ownsFacts: $list($data['owns_facts'] ?? []),
                readsFacts: $list($data['reads_facts'] ?? []),
                agentReachable: $list($data['agent_reachable'] ?? []),
            );
        }

        return $out;
    }

    private function one(string $src, string $field): ?string
    {
        return preg_match('/@'.preg_quote($field, '/').'\s+([^\s*]+)/', $src, $m) === 1 ? $m[1] : null;
    }

    /**
     * ⛔ Tokens only — prose is NOT a declaration.
     *
     * `C-Ai @consumes` read "every agent turn". A sentence. doctor could not read
     * it, impact could not trace it, map could not draw it — so the busiest edge
     * in the platform was invisible to every tool in the programme. Returning the
     * raw string here lets ContractStage report it rather than silently dropping it.
     *
     * @return list<string>
     */
    /**
     * ⛔⛔⛔ DEAD. KEPT ONLY AS A WARNING.
     *
     * This regexed a manifest's SOURCE for `@field` and returned whatever
     * followed. Every manifest the scaffold writes carries an explanatory
     * comment containing `@owns_table`, so it returned that sentence as the
     * value — on all 122 modules at once.
     *
     * ⭐ Manifests are PHP and are now `require`d. If you find yourself
     *   reaching for this, you are re-parsing something already parsed.
     */
    private function many(string $src, string $field): array
    {
        throw new \LogicException(
            'ManifestReader::many() is dead. A manifest is PHP — require it. '
            .'Regexing generated PHP is what produced 122 identical false violations.'
        );
    }

    /**
     * ⭐⭐⭐ THE ONE WAY TO READ A MANIFEST. TWO BUGS LIVE HERE, BOTH REAL.
     *
     * ⛔ BUG 1 — THE PATH.
     *   `Manifest::$path` is set from `getRelativePathname()` on a Finder rooted
     *   at `app/Modules`, so it holds `X-212/manifest.php` — NOT
     *   `app/Modules/X-212/manifest.php`. Three call sites did:
     *
     *       file_get_contents(base_path($m->path))
     *
     *   which resolves to `<tree-root>/X-212/manifest.php` and crashed the whole
     *   stage on the owner's first `--stage=contract` run.
     *
     * ⛔⛔ BUG 2 — AND THIS ONE IS WORSE.
     *   The "guarded" call sites used `@file_get_contents(...)`. That does not
     *   guard anything: it SUPPRESSES the warning, returns false, casts to '',
     *   and every regex below then finds nothing. The stage reports CLEAN on a
     *   module whose manifest it could not open.
     *
     *   ⭐ A crash tells you something is wrong. A silenced read tells you
     *     everything is fine. The second is the one that ships.
     *
     * @return string the manifest source, or '' if it genuinely does not exist
     */
    public function source(Manifest $m): string
    {
        $full = base_path('app/Modules/'.ltrim($m->path, '/'));

        return is_file($full) ? (string) file_get_contents($full) : '';
    }

    /**
     * Modules in the roster that have NO manifest on disk.
     *
     * ⭐ This is a VIOLATION to report, not an exception to throw. `X-212` had
     *   no manifest because the scaffold had never run — which is exactly the
     *   thing a contract check should TELL you, in the words "run
     *   module:scaffold", rather than dying on.
     *
     * @param  list<Manifest>  $modules
     * @return list<string>
     */
    public function missingManifests(array $modules): array
    {
        $missing = [];
        foreach ($modules as $m) {
            if ($this->source($m) === '') {
                $missing[] = $m->id;
            }
        }

        return $missing;
    }
}
