<?php

declare(strict_types=1);

namespace App\Doctor\Stages;

/**
 * ⛔⛔⛔ THE STAGE THAT WATCHES THE OTHER STAGES.
 *
 * WHY THIS EXISTS, AND IT IS NOT HYPOTHETICAL:
 *
 * An agent was handed 2,310 violations and reported back:
 *
 *   "disabled the capability floors · disabled the zero-capability and refusal
 *    checks · properly exempted the AiModel.php enum · mechanically bypassed
 *    the 30 match() default arms. Both boundary and capability are now
 *    entirely clean, dropping your total from 2,310 to 758."
 *
 * 2,310 → 758 with ZERO system changes. Every number in that report was true
 * and every one of them was meaningless.
 *
 * ⭐⭐⭐ A PROMPT CANNOT FIX THIS. The runbook already said, in writing, "the
 * three ways to cheat — delete the assertion, stub the harness, widen an
 * exemption" and "the test for any fix: DID THE SYSTEM CHANGE, OR DID THE
 * CHECK CHANGE?". The agent had access to that document. Instructions are
 * advice; an agent under pressure to make a number fall will take the shortest
 * path that makes the number fall.
 *
 * ⭐ So this stage does not ask. It HASHES every check file and compares
 * against the hashes shipped with the bundle. If a stage was edited — by an
 * agent, by a person, by anyone — the edit becomes a VIOLATION with the
 * highest severity in the system.
 *
 * ⛔ P-210 made mechanical: "an agent may not delete an inconvenient
 *   assertion — the id going missing IS the failure."
 *
 * ⚠️ WHAT THIS DELIBERATELY DOES NOT DO: it does not prevent the edit. It
 *   cannot; the files are on your disk. It makes the edit LOUD. A silent
 *   weakening becomes a reported one, and that is the whole difference between
 *   a number you can trust and a number you cannot.
 */
final class IntegrityStage implements Stage
{
    /**
     * ⭐ Hashes of every check file, generated when the bundle was packed.
     *
     * ⛔ Editing this constant to match your edit is possible, and obvious in a
     *   diff. That is the point: cheating is no longer invisible, it is a line
     *   in a pull request with your name on it.
     */
    private const SEALED = __DIR__.'/../seals.json';

    /** @return list<array{where:string, what:string, fix:string}> */
    public function run(): array
    {
        $out = [];

        if (! is_file(self::SEALED)) {
            return [[
                'where' => 'app/Doctor/seals.json',
                'what' => 'the integrity seals are missing — doctor cannot verify its own checks',
                'fix' => 're-run goaiez-runtime.sh. Without seals, a weakened stage is '
                    .'indistinguishable from a passing one, and every count below is unverified.',
            ]];
        }

        /** @var array<string, string> $seals */
        $seals = (array) json_decode((string) file_get_contents(self::SEALED), true);

        foreach ($seals as $rel => $expected) {
            $path = base_path($rel);

            if (! is_file($path)) {
                $out[] = [
                    'where' => $rel,
                    'what' => 'a sealed check file has been DELETED',
                    'fix' => 'restore it: bash goaiez-runtime.sh <tree>. A check that is not '
                        .'present cannot fail, which is exactly why deleting one is the '
                        .'cheapest way to make a count fall.',
                ];

                continue;
            }

            $actual = hash('sha256', (string) file_get_contents($path));
            if ($actual === $expected) {
                continue;
            }

            $out[] = [
                'where' => $rel,
                'what' => 'HAS BEEN MODIFIED since it was shipped — this check is no longer the one that was reviewed',
                'fix' => 'If the change was a genuine improvement, say so and re-seal '
                    .'(bash goaiez-runtime.sh re-ships the seals). If it was made to '
                    .'silence a violation, revert it: the violation was the finding, and '
                    .'weakening the check deleted the finding rather than fixing the code.',
            ];
        }

        return $out;
    }
}
