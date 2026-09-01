<?php

declare(strict_types=1);

namespace App\Doctor\Stages;

use App\Doctor\ManifestReader;
use Symfony\Component\Finder\Finder;

/**
 * STAGE ⑤ TEST ANCHOR — FAILS THE WAVE.
 *
 * ⭐⭐⭐ THIS IS THE ONE CONDITION THE SWARM CANNOT SATISFY BY TRYING HARDER.
 *
 * `P-210`: the agent does not author its tests. A swarm agent writes both the
 * code and the test from the same brief in the same reading — so if it misread,
 * the code is wrong AND the test agrees, and the suite goes green.
 *
 * ⛔ Every other gate can be argued with. This one asks for a MESSAGE-ID, a
 * CALL-SID, a CHARGE-ID — an artifact minted by a carrier, a gateway or a real
 * device. Nothing in this system can invent one. That is the entire reason it is
 * condition ⑤ of the Definition of Done.
 *
 * ⛔⛔ And it has already been violated once, in code: ConversationalVoiceAgent
 * fabricated `VAPI_CALL_ID_{uniqid()}` when its credential was missing — it
 * invented the one artifact the DoD says cannot be invented.
 */
final class TestAnchorStage implements Stage
{
    /** Anything that manufactures a value is forbidden near an artifact-id field. */
    /**
     * ⛔⛔⛔ `Str::ulid(` ADDED 2026-08-28 — AND ITS ABSENCE WAS THE WHOLE GAP.
     *
     * The list already held `Str::uuid(`. It did NOT hold `Str::ulid(` — and
     * ULID is what Laravel developers actually reach for. An agent used exactly
     * that to mint an `artifact_id`, and described it as "a real, non-forged
     * ULID".
     *
     * ⭐ A ULID is GENERATED, not ISSUED. Nothing handed it out and no row
     *   anywhere contains it. It is the definition of a fabricated id, and my
     *   list of fabrication functions was one entry short of catching it.
     */
    private const MANUFACTURED = [
        'uniqid(', 'Str::random(', 'Str::uuid(', 'Str::ulid(', 'Uuid::uuid4(',
        'mt_rand(', 'rand(', 'random_bytes(', 'fake()->',
    ];

    public function __construct(private readonly ManifestReader $manifests) {}

    public function run(): array
    {
        $out = [];

        foreach ($this->manifests->all() as $m) {
            $proof = storage_path("app/evidence/{$m->id}/runtime-proof.json");

            if (! is_file($proof)) {
                $out[] = [
                    'where' => $m->id,
                    'what' => 'no runtime proof',
                    'fix' => 'run the module\'s TEST ANCHOR against real transports and write the artifact id to evidence/',
                ];

                continue;
            }

            /** @var array{artifact_id?: string, driver?: string, captured_at?: string, junit?: string} $p */
            $p = json_decode((string) file_get_contents($proof), true) ?: [];

            // ⛔⛔ A proof that ran on the sync driver proves nothing about a box.
            //     Laravel runs queued jobs synchronously in tests — which is exactly
            //     how "nothing runs the queue" stayed invisible for months while
            //     every single test passed.
            if (($p['driver'] ?? '') === 'sync') {
                $out[] = [
                    'where' => $m->id,
                    'what' => 'the runtime proof ran on the SYNC driver',
                    'fix' => 'run it in the `runtime` test group against the real queue with a worker; sync would pass with no worker at all',
                ];
            }

            $id = $p['artifact_id'] ?? '';

            if ($id === '') {
                $out[] = [
                    'where' => $m->id,
                    'what' => 'the proof carries no external artifact id',
                    'fix' => 'capture the vendor\'s message-id / call-sid / charge-id — the swarm cannot fabricate one, which is the point',
                ];
            } elseif (preg_match('/^(TEST|MOCK|FAKE|SAMPLE|DEMO)[-_]/i', $id) === 1) {
                $out[] = [
                    'where' => $m->id,
                    'what' => "the artifact id '{$id}' is self-minted, not a vendor's",
                    'fix' => 'an id this system generated is not evidence that anything left the building',
                ];
            }

            // ⛔ The evidence log and its JUnit XML must come from the SAME
            //    execution. A footer stitched from a historic run is a forged
            //    proof, and one was — which is why this pair is checked, not the
            //    presence of either alone.
            if (($p['junit'] ?? '') === '' || ($p['captured_at'] ?? '') === '') {
                $out[] = [
                    'where' => $m->id,
                    'what' => 'the proof does not name its JUnit XML and capture time',
                    'fix' => 'console output and JUnit must be captured from ONE execution — a stitched footer is a forgery',
                ];
            }
        }

        // ⛔ And the structural half: nothing random may feed an artifact-id field
        //    anywhere in the tree, whatever the tests say.
        foreach (Finder::create()->files()->in(base_path('app'))->name('*.php') as $f) {
            $src = $f->getContents();
            if (preg_match('/(call_?sid|message_?id|charge_?id|artifact_?id)/i', $src) !== 1) {
                continue;
            }
            foreach (self::MANUFACTURED as $fn) {
                if (str_contains($src, $fn)) {
                    $out[] = [
                        'where' => $f->getRelativePathname(),
                        'what' => "manufactures a value with {$fn} beside an external artifact id",
                        'fix' => 'REFUSE before the request when the credential is absent; return the vendor\'s id or nothing',
                    ];
                }
            }
        }
        // ⛔⛔⛔ AN ANCHOR THAT GREPS A MISSING DIRECTORY PASSES TRIVIALLY.
        //
        // X-119's anchor reads:
        //   grep -r "price" app/Modules/Intelligence/Knowledge/ shows no path
        //   from an embedding to a number
        //
        // That directory does not exist. Under R242 the layout is
        // app/Modules/X-119/ — one module, one directory, named for its id.
        //
        // ⭐⭐⭐ grep on a missing path returns NOTHING, and "shows no path from
        //   X to Y" IS SATISFIED BY AN EMPTY RESULT. The anchor does not fail.
        //   It passes, and proves nothing — and for X-119 the clause that
        //   cannot run is the one about PRICES, the highest-stakes rule it has.
        //
        // ⛔ 19 modules carry an anchor written against a directory scheme that
        //   predates R242. Every one of them is a green light wired to nothing.
        foreach ($this->manifests->all() as $m) {
            $anchor = $this->anchorText($m->id);
            if ($anchor === '') {
                continue;
            }

            foreach (preg_match_all('#app/[A-Za-z0-9_/\-]+#', $anchor, $hits) ? $hits[0] : [] as $path) {
                if (str_starts_with($path, 'app/Doctor')
                    || str_starts_with($path, 'app/Console')
                    || str_starts_with($path, 'app/Providers')) {
                    continue;
                }
                if (is_dir(base_path($path)) || is_file(base_path($path))) {
                    continue;
                }

                // ⭐⭐ `app/Modules/` ITSELF IS LEGITIMATE FOR A CROSS-CUTTING RULE.
                //
                // X-204's anchor greps EVERY module on purpose:
                //   "shows every send path calling ConsentService::decide() and
                //    NO MODULE containing a consent branch of its own"
                //
                // ⭐ That is a rule ABOUT ALL MODULES, so scoping it to one would
                //   destroy it. A bare app/Modules/ is not a stale path — it is
                //   the correct target for a law that forbids something
                //   everywhere.
                if (rtrim($path, '/') === 'app/Modules') {
                    continue;
                }

                $out[] = [
                    'where' => $m->id.' TEST ANCHOR',
                    'what' => "references {$path}, which does not exist",
                    'fix' => 'rewrite the anchor against app/Modules/'.$m->id.'/ (R242). An anchor '
                        .'that greps a missing directory returns nothing, and "shows no path from X '
                        .'to Y" is satisfied by nothing — so it passes without testing anything.',
                ];
            }
        }


        return $out;
    }

    /** The module's TEST ANCHOR text, read from the plan. */
    private function anchorText(string $id): string
    {
        $plan = base_path('GOAIEZ-MASTER-PLAN.md');
        if (! is_file($plan)) {
            return '';
        }
        $src = (string) file_get_contents($plan);
        $start = strpos($src, '@module **'.$id.'**');
        if ($start === false) {
            $start = strpos($src, '@module '.$id);
        }
        if ($start === false) {
            return '';
        }

        return preg_match('/\*\*TEST ANCHOR\*\*(.{0,400}?)(?:\n\n|@module|---)/s', substr($src, $start, 7000), $m) === 1
            ? $m[1]
            : '';
    }
}
