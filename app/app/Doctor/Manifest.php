<?php

declare(strict_types=1);

namespace App\Doctor;

/**
 * One module's contract, as READ FROM THE CODE.
 *
 * ⭐ `ships` and `ceiling` are two different numbers and conflating them was a
 * real defect: I derived a single ladder from each module's AUTOPILOT prose
 * ("runs continuously" → L2→L3) and 52 of 119 then disagreed with §227.2.
 *
 * ⛔⛔⛔ ALL OF THAT IS OVERRULED BY R235 (owner, 2026-08-27):
 *   "ALL AI ON, by my law. The client can turn it off if they want."
 *
 *   ships   = the CEILING. The autopilot is ON from minute one.
 *   ceiling = the same value. There is nothing left to earn.
 *
 * The two fields survive because a module still HAS a ladder position — L2 acts
 * and reports, L3 runs unattended — but that position now DESCRIBES the action
 * rather than gating when it may run. §227.2's intent default and the "earned
 * on measured clean runs" promotion are both struck.
 *
 * ⭐ The only OFF states are NOT_CONFIGURED (it physically cannot run yet) and
 * OFF_BY_CLIENT (they switched it off). R236 forbids every other. Both are true; one field could only hold one of them.
 */
final readonly class Manifest
{
    /**
     * @param list<string> $provides
     * @param list<string> $emits
     * @param list<string> $consumes
     * @param list<string> $ownsTable
     * @param list<string> $readsTable
     * @param list<string> $renders
     * @param list<string> $ownsFacts
     * @param list<string> $readsFacts
     */
    public function __construct(
        public string $id,
        public string $path,
        public string $intent,
        public array $provides,
        public array $emits,
        public array $consumes,
        public array $ownsTable,
        public array $readsTable,
        public array $renders,
        public string $shipsAt,
        public string $ceiling,
        public array $ownsFacts,
        public array $readsFacts,

        /**
         * ⛔⛔⛔ P-209's ALLOW-LIST — AND IT DID NOT EXIST UNTIL NOW.
         *
         * `ContractStage` failed ~392 actions with "does not declare whether
         * the agent may reach it". The plan declares it on all 122 modules.
         * The scaffold never harvested it. And this class had no property to
         * hold it even if it had.
         *
         * ⭐ Three links in one chain, and the checker — the only honest part —
         *   got the blame for four turns.
         *
         * @var list<string>
         */
        public array $agentReachable = [],
    ) {}

    /**
     * ⛔⛔⛔ OVERRULED BY R235 — AND THIS FOUR-LINE HELPER WOULD HAVE BROKEN
     * THE ENTIRE PLATFORM.
     *
     * It returned §227.2's intent→ladder mapping (SERVE/INFORM → L2,
     * RECOVER/GROW → L1), and `brief` REFUSED any module shipping differently.
     *
     * R235: "ALL AI ON, by my law. The client can turn it off if they want."
     * Every module now ships at its CEILING — so the old answer was wrong for
     * all 122 AT ONCE, and `brief` would have refused EVERY SINGLE MODULE.
     * Unbriefable, entirely, because a helper still believed a struck rule.
     *
     * ⭐ @intent still DESCRIBES what an action is. It no longer decides when
     * that action may run.
     */
    public function shipsAtByLaw(): string
    {
        // R235: the shipping level IS the ceiling. There is no separate
        // "the law says" answer left for it to disagree with.
        return $this->ceiling !== '' ? $this->ceiling : 'n/a';
    }
}
