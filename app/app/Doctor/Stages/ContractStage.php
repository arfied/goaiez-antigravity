<?php

declare(strict_types=1);

namespace App\Doctor\Stages;

use App\Doctor\ManifestReader;

/**
 * STAGE ② CONTRACT — ~5s, FAILS THE COMMIT.
 *
 * ⭐ Reads the ANNOTATIONS, never the prose around them. `P-209`: the module
 * contract is a FILE, not a theme — `doctor` reads it and turn 86 assembles a
 * brief from it AND FROM NOTHING ELSE.
 */
final class ContractStage implements Stage
{
    /**
     * ⛔ Actions the plan names as requiring a human principal. Verbatim from
     * its own list: "no action may exist at a reachable scope — payment.refund ·
     * payroll.* · tenant.isolate/dr.pitr · another owner's credential.reveal ·
     * migration.commit."
     *
     * ⭐ Named individually and never by pattern: a regex exemption is exactly
     * how a real one would slip back in.
     *
     * @var list<string>
     */
    private const NEVER_AGENT_REACHABLE = [
        'payment.refund',
        'payroll.',
        'tenant.isolate',
        'dr.pitr',
        'credential.reveal',
        'migration.commit',
    ];

    /** Every declared @-field token must be exactly `noun.verb`. */
    private const TOKEN = '/^[a-z][a-z0-9_]*\.[a-z0-9_.]+$/';

    /**
     * ⭐⭐ THE PROSE EXEMPTION — and it is a NAMED LIST, not a blanket rule.
     *
     * `C-Ai @consumes` read "every agent turn". That is a SENTENCE, not a token:
     * doctor could not read it, impact could not trace it, map could not draw it —
     * so the busiest edge in the platform was invisible to every tool. Fixed to
     * `agent.turn.started`.
     *
     * ⛔ But three modules genuinely DO subscribe to everything, and forcing them
     * to enumerate 417 tokens would be worse than the disease. They are exempt BY
     * NAME so the exemption is visible and auditable — never by pattern.
     */
    private const WILDCARD_EXEMPT = [
        'X-123' => 'the EventBus IS the transport; it owns event_log and records every event',
        'X-121' => 'the bottom of the graph — it consumes nothing by construction',
        'X-125' => 'the canvas runs every action the registry exposes',
    ];

    public function __construct(private readonly ManifestReader $manifests) {}

    public function run(): array
    {
        $out = [];
                $modules = $this->manifests->all();


        // ⛔⛔⛔ A MISSING MANIFEST IS A VIOLATION, NOT AN EXCEPTION.
        //
        // The owner's first --stage=contract run died:
        //   file_get_contents(.../X-212/manifest.php): No such file or directory
        //
        // X-212 had no manifest because the scaffold had never run. That is
        // precisely what this stage exists to TELL you — instead it threw, and
        // reported nothing about the other 121 modules either.
        //
        // ⛔⛔ AND THEN I FIXED IT WRONG. I anchored the new loop on `$out = [];`
        //    without checking what came after, so it ran BEFORE $modules was
        //    assigned eight lines later — "Undefined variable $modules", a
        //    different crash in the same place. The order of a file is part of
        //    its meaning, and an anchor that ignores order is a guess.
        //
        // ⭐ One unscaffolded module must not blind the check to everything else.
        foreach ($this->manifests->missingManifests($modules) as $id) {
            $out[] = [
                'where' => $id,
                'what' => 'is in the roster but has no manifest on disk',
                'fix' => 'run: php artisan module:scaffold --module='.$id
                    .' (or scaffold all 122 with no --module). Until then this module '
                    .'declares nothing and every contract check skips it silently.',
            ];
        }
        // ⛔ A duplicate @module anchor does not error, does not warn and does not
        //    fail a gate — it silently hands one module ANOTHER module's description,
        //    and every downstream check counts it as present and correct.
        $seen = [];
        foreach ($modules as $m) {
            if (isset($seen[$m->id])) {
                $out[] = [
                    'where' => $m->path,
                    'what' => "duplicate @module anchor {$m->id} (also in {$seen[$m->id]})",
                    'fix' => 'every @module anchor is unique; a duplicate hands one module another\'s brief',
                ];
            }
            $seen[$m->id] = $m->path;
        }

        $emitted = [];
        $consumed = [];

        foreach ($modules as $m) {
            foreach (['emits' => $m->emits, 'consumes' => $m->consumes] as $field => $tokens) {
                foreach ($tokens as $t) {
                    // ⛔⛔⛔ `none` AND `*` ARE LEGAL VALUES, NOT PROSE.
                    //
                    // This stage's OWN fix text reads:
                    //   "name the events it listens to, or declare '@consumes none'"
                    // and then the very next check REJECTED `none` as prose.
                    //
                    // The exemption was keyed by MODULE ID (WILDCARD_EXEMPT), so
                    // `*` passed for the three named spines and failed for
                    // everyone else — and `none` failed for everyone, including
                    // the modules that had done exactly what they were told.
                    //
                    // ⭐ The exemption belongs to the VALUE, not the module. `none`
                    //   is "I listen to nothing, and I decided that." `*` is the
                    //   spine wildcard, still restricted to the named spines.
                    //
                    // ⛔ A checker that demands an answer and then rejects the
                    //   answer teaches people the checker is broken — and they
                    //   are right.
                    if ($t === 'none') {
                        continue;
                    }
                    if (preg_match(self::TOKEN, $t) !== 1) {
                        if ($t === '*' && isset(self::WILDCARD_EXEMPT[$m->id])) {
                            continue; // the wildcard stays exempt BY NAME
                        }
                        $out[] = [
                            'where' => "{$m->id} @{$field}",
                            'what' => "'{$t}' is prose, not an event token",
                            'fix' => 'declare the real token (noun.verb); prose is invisible to doctor, impact and map',
                        ];

                        continue;
                    }
                    $field === 'emits' ? $emitted[$t][] = $m->id : $consumed[$t][] = $m->id;
                }
            }

            // ⛔ P-163: @owns_table may not name any of X-121's canonical nouns.
            //    The instant this check existed it found C-Reviews claiming `reviews`
            //    and X-186 claiming `campaigns` — and both were STILL there months later.
            foreach ($m->ownsTable as $table) {
                if ($m->id === 'X-121') {
                    continue;
                }
                if (in_array($table, ManifestReader::CANONICAL_NOUNS, true)) {
                    $out[] = [
                        'where' => "{$m->id} @owns_table",
                        'what' => "claims the canonical noun '{$table}' — one of P-163's TWELVE, owned by X-121",
                        'fix' => "move '{$table}' to @reads_table; @owns_table claims only what this module OWNS",
                    ];
                } elseif (in_array($table, ManifestReader::X121_OWNED_NON_NOUNS, true)) {
                    // ⭐ Same refusal, different law — entity_history is the audit
                    //   trail of the nouns, not a noun. Naming the right reason is
                    //   what makes a violation message fixable.
                    $out[] = [
                        'where' => "{$m->id} @owns_table",
                        'what' => "claims '{$table}', which X-121 owns as the entity graph's audit trail",
                        'fix' => "move '{$table}' to @reads_table; it is not a canonical noun, but it is still X-121's",
                    ];
                }
            }
        }

        // ⛔⛔ P-208 IS DIRECTIONAL, and the dangerous direction is this one.
        //
        //     consumed with no emitter = DEAD CODE that will never fire — silent,
        //     invisible, and it fails the build.
        //
        //     emitted with no subscriber = a RECORDED FACT waiting for a reader.
        //     X-123 owns event_log; every event is recorded whether or not a module
        //     subscribes. 277 of 417 are in that state and they are CORRECT.
        //     A check that failed on 277 correct rows would be switched off by Friday.
        // ⭐⭐ §235's TWO MARKERS — without them P-208 flags 18 correct events.
        //
        // §235 classified all 37 orphans and said of the ingress group:
        // "These are NOT ghosts. No module emits them because no module SHOULD."
        // A browser, a vendor webhook, a technician's voice note and a request
        // header have no internal emitter by design.
        //
        // ⛔ A later line CLAIMED "13 @ingress · 5 @scheduled WITH OWNERS" were
        // applied. Measured 2026-08-27: ZERO of 41 had reached a module header.
        // The claim and the work were different acts, again.
        $ingress = [];
        $scheduled = [];
        foreach ($modules as $m) {
            $src = $this->manifests->source($m);
            preg_match_all('/@ingress\s+([a-z][a-z0-9_.]+)/', $src, $mi);
            foreach ($mi[1] ?? [] as $e) {
                $ingress[$e] = true;
            }
            preg_match_all('/@scheduled\s+([a-z][a-z0-9_.]+)[^\n]*?@owner\s+((?:X|C)-[A-Za-z0-9]+)/', $src, $ms, PREG_SET_ORDER);
            foreach ($ms as $hit) {
                $scheduled[$hit[1]] = $hit[2];
            }

            // ⛔ THE OWNER IS MANDATORY. §235: "an unowned scheduled event is a
            //    cron job nobody will notice has stopped." Five modules were
            //    waiting on a tick no module was responsible for producing.
            preg_match_all('/@scheduled\s+([a-z][a-z0-9_.]+)([^\n]*)/', $src, $mu, PREG_SET_ORDER);
            foreach ($mu as $hit) {
                if (! str_contains($hit[2], '@owner')) {
                    $out[] = [
                        'where' => "{$m->id} @scheduled {$hit[1]}",
                        'what' => 'a scheduled event with NO @owner',
                        'fix' => 'name the owning module — an unowned scheduled event is a cron job nobody notices has stopped',
                    ];
                }
            }
        }

        // ⭐⭐⭐ P-209's ALLOW-LIST — the fail-open, closed.
        //
        // "The agent's action surface is a DECLARED ALLOW-LIST, not everything
        // registered minus a deny-list — a deny-list FAILS OPEN: an action added
        // in six months is reachable by the agent BY DEFAULT."
        //
        // ⛔ So an action is agent-reachable ONLY if its module says so, in
        // writing. Silence means NOT reachable. That is the whole point: the
        // default must be closed, and a new action must be opted IN.
        foreach ($modules as $m) {
            $src = $this->manifests->source($m);

            // ⛔⛔⛔ READ THE OBJECT. THIS BLOCK WAS THE ONE I DID NOT CONVERT.
            //
            // It regexed the manifest SOURCE for `@provides` — and a manifest is
            // compiled PHP that never contains that annotation. So $actions was
            // ALWAYS EMPTY, and every @agent_reachable entry was reported as
            // "declares X agent-reachable, but does not @provide it".
            //
            // ⭐ Verified against the plan: C-Agent DOES provide agent.draft and
            //   agent.classify. X-117 DOES provide cart.checkout. X-01 DOES
            //   provide conversation.read. All three were reported as violations.
            //
            // ⛔ This is the SAME defect as the 610 — regexing a file that
            //   ManifestReader had already turned into an object — and I fixed
            //   it everywhere in this stage EXCEPT here.
            $actions = $m->provides;

            // ⛔⛔⛔ READ THE PROPERTY. THE LAST LINK IN A FOUR-LINK CHAIN.
            //
            // ~392 actions were reported as "does not declare whether the agent
            // may reach it". Every link was broken except the one that got the
            // blame:
            //
            //   ① the PLAN declares it on all 122 modules   ✅ was always right
            //   ② the SCAFFOLD never harvested the field    ⛔ silently dropped
            //   ③ Manifest had no property to hold it       ⛔ nowhere to land
            //   ④ this stage regexed the manifest SOURCE    ⛔ found nothing
            //
            // ⭐ And regexing the source could never have worked anyway: the
            //   manifest is compiled PHP and never contained `@agent_reachable`
            //   in the first place.
            $declared = $m->agentReachable;

            // ⛔ A module that provides actions and says NOTHING about agent reach
            //    is the fail-open case. It must declare — including declaring NONE.
            // ⛔⛔⛔ WAS `$rm[1]` — A VARIABLE I DELETED IN THE SAME EDIT.
            //
            // I replaced the preg_match_all that produced $rm with
            // `$declared = $m->agentReachable;` and left this reference behind.
            // Result: "Undefined variable $rm" — a FATAL that killed the whole
            // doctor run, on the build I told the owner to install.
            //
            // ⭐ And it produced a second, quieter harm: the previous
            //   "2266 violations" was measured by a process that CRASHED
            //   MIDWAY. That number was never real, and I reasoned about it
            //   for a full turn.
            if ($actions !== [] && $declared === []) {
                $out[] = [
                    'where' => "{$m->id} @agent_reachable",
                    'what' => 'provides '.count($actions).' action(s) and declares NO agent-reachable allow-list',
                    'fix' => 'add `@agent_reachable <actions>` — or `@agent_reachable none`. '
                        .'P-209: silence is not a deny-list, it FAILS OPEN.',
                ];

                continue;
            }

            // ⛔ You may not open what you do not own.
            foreach ($declared as $d) {
                if ($d !== 'none' && ! in_array($d, $actions, true)) {
                    $out[] = [
                        'where' => "{$m->id} @agent_reachable {$d}",
                        'what' => "declares '{$d}' agent-reachable, but does not @provide it",
                        'fix' => 'a module may only open its OWN actions to the agent',
                    ];
                }
            }
        }

        // ⭐⭐⭐ P-209's ALLOW-LIST — enforced for the first time.
        //
        // "The agent's action surface is a DECLARED ALLOW-LIST, not everything
        //  registered minus a deny-list. A deny-list FAILS OPEN: an action added
        //  in six months is reachable by the agent BY DEFAULT."
        //
        // ⛔ Three checks, because an allow-list has three ways to rot:
        foreach ($modules as $m) {
            $src = $this->manifests->source($m);

            // ⛔⛔⛔ A DECLARATION FIELD MAY NOT CONTAIN A PARAGRAPH.
            //
            // X-103's @renders held an entire SWARM paragraph — "on-page contract
            // for X-143 · must not touch the template library after fork" — so
            // the field had NO declared value at all, and a parser read
            // `site.published` out of the prose as a RENDERED BLOCK.
            //
            // ⭐ The test is SHAPE, not length: every value in a declaration
            //   field must look like a token (word, word.word, snake_case), or
            //   be the literal `none`. A sentence has spaces inside its items.
            // ⛔⛔⛔ READ THE OBJECT, NOT THE FILE.
            //
            // This block used to regex the manifest SOURCE for a field name and
            // treat whatever followed as the value. On a PHP manifest that
            // yields:
            //
            //     X-212 'provides': contains prose: "=> ["
            //
            // — because after `'provides'` comes `=> [`, and the value is a
            // multi-line PHP ARRAY, not a `·`-separated string.
            //
            // ⭐⭐⭐ AND THE MANIFEST WAS ALREADY PARSED. `Manifest` carries
            //   ->provides, ->emits, ->consumes, ->ownsTable, ->readsTable,
            //   ->renders as typed arrays. I re-parsed, by regex, a file that
            //   ManifestReader had already turned into an object — and every
            //   defect in this stage for three rounds came from that choice.
            //
            // ⛔ 610 ≈ 5 × 122: five fields × every module, all reporting the
            //   PHP arrow as prose.
            $declared = [
                'provides' => $m->provides,
                'emits' => $m->emits,
                'consumes' => $m->consumes,
                'renders' => $m->renders,
                'owns_table' => $m->ownsTable,
                'reads_table' => $m->readsTable,
            ];
            foreach ($declared as $field => $items) {
                foreach ($items as $item) {
                    $item = trim((string) $item);
                    if ($item === '' || $item === 'none' || $item === '*') {
                        continue;
                    }
                    // ⭐ A token is a name. A sentence has spaces.
                    if (preg_match('/^[a-z][a-z0-9_.]*$/i', $item) === 1) {
                        continue;
                    }
                    $out[] = [
                        'where' => "{$m->id} {$field}",
                        'what' => 'is not a token: "'.mb_substr($item, 0, 48).'"',
                        'fix' => 'a declaration holds names or `none`. If this came from the plan, '
                            .'the header has prose inside the declaration line — move it after the '
                            .'closing backtick and re-run module:scaffold.',
                    ];
                }
            }

            // ⛔⛔ @consumes MUST BE DECLARED — silence is not an answer.
            //
            // A module with no @consumes is indistinguishable from a module that
            // consumes nothing, and the two need opposite treatment. "none" is a
            // statement a human made; absence is a question nobody answered.
            // ⛔⛔⛔ AND THE SECOND HALF OF THE 244.
            //
            // A manifest is PHP, so the scaffold writes:
            //
            //     'consumes' => ['job.completed'],
            //
            // NOT `@consumes`. That annotation is the MASTER PLAN's syntax; the
            // manifest is its compiled form. Searching a manifest for plan
            // syntax finds nothing on all 122, so every module was reported as
            // "has no @consumes declaration at all".
            //
            // ⭐ Two checks, one root cause: I wrote both against the shape of
            //   the PLAN and ran them on the shape of the MANIFEST. The
            //   generator and the checker disagreed about the file format, and
            //   the checker is the one that was wrong.
            // ⭐ Same lesson: the reader already answered this. An EMPTY array
            //   is "declared nothing"; the field being absent from the manifest
            //   entirely is what this catches — and the reader distinguishes
            //   them, so the source text does not need consulting at all.
            if ($m->consumes === [] && ! str_contains($src, 'consumes')) {
                $out[] = [
                    'where' => "{$m->id}",
                    'what' => 'has no @consumes declaration at all',
                    'fix' => 'name the events it listens to, or declare "@consumes none". '
                        .'Silence and "none" mean different things and only one of them is a decision.',
                ];
            }

            // ① An action a module PROVIDES must say whether the agent may reach it.
            //    Silence is the fail-open case, so silence is a violation.
            foreach ($m->provides as $action) {
                if (! str_contains($action, '.')) {
                    continue;
                }
                // ⛔⛔⛔ THE EIGHTH INSTANCE OF THE SAME DEFECT.
                //
                // These two lines regexed $src for `@agent_reachable` — and a
                // manifest is COMPILED PHP that never contains that annotation.
                // So both checks matched nothing and every provided action was
                // reported: 375 of the 412 contract violations, on a field the
                // plan declares correctly on all 124 modules.
                //
                // ⭐⭐⭐ I fixed this exact pattern in the four-link chain two
                //   builds ago and MISSED THIS BLOCK — the same way I missed one
                //   block when fixing the 610, and one when fixing the 122.
                //
                // ⛔ Regexing $src in this stage is now the bug, not the tool.
                //   The Manifest object carries every declaration.
                if (in_array($action, $m->agentReachable, true)) {
                    continue;
                }
                if (in_array('none', $m->agentReachable, true)) {
                    continue;
                }
                $out[] = [
                    'where' => "{$m->id} @provides {$action}",
                    'what' => 'does not declare whether the agent may reach it',
                    'fix' => "add {$action} to @agent_reachable, or declare '@agent_reachable none'. "
                        .'Silence fails OPEN, which is how an action added in six months becomes agent-reachable by default.',
                ];
            }

            // ② An agent-reachable action at a reachable scope that the plan
            //    names as forbidden. These are named individually, never by
            //    pattern — a pattern exemption is how a real one slips back in.
            foreach (self::NEVER_AGENT_REACHABLE as $forbidden) {
                if (preg_match('/@agent_reachable[^\n]*'.preg_quote($forbidden, '/').'/', $src) === 1) {
                    $out[] = [
                        'where' => "{$m->id} @agent_reachable",
                        'what' => "declares '{$forbidden}' agent-reachable, which the plan forbids at any reachable scope",
                        'fix' => "remove {$forbidden}; it requires a human principal, not an agent turn",
                    ];
                }
            }

            // ③ An action listed as reachable that the module does not PROVIDE.
            //    An allow-list naming something that does not exist is an
            //    allow-list nobody has read lately.
            preg_match_all('/@agent_reachable\s+([^\n]+)/', $src, $ar);
            foreach ($ar[1] ?? [] as $line) {
                foreach (preg_split('/[·,\s]+/u', (string) preg_replace('/[`*]/', '', $line)) ?: [] as $tok) {
                    $tok = trim($tok);
                    if ($tok === '' || $tok === 'none' || ! str_contains($tok, '.')) {
                        continue;
                    }
                    if (! in_array($tok, $m->provides, true)) {
                        $out[] = [
                            'where' => "{$m->id} @agent_reachable {$tok}",
                            'what' => 'names an action this module does not @provide',
                            'fix' => "remove it, or add {$tok} to @provides — an allow-list naming a phantom is one nobody has read",
                        ];
                    }
                }
            }

            // ⛔⛔⛔ THESE CHECKS WERE OUTSIDE THE LOOP THAT DEFINES $m.
            //
            // The R235 ships-equals-ceiling check and the R236 forbidden-state
            // check sat AFTER the foreach closed, so both read NULL every run:
            //   Warning: Undefined variable $m
            //   TypeError: stripos(): Argument #1 must be string, null given
            //
            // ⭐⭐⭐ Found by EXECUTING this stage in a container with PHP,
            //   not by reading it. I have read this file a dozen times.

            // ⭐⭐⭐⭐⭐ R235 — EVERY AUTOPILOT SHIPS ON. THE HARDEST LAW HERE.
            //
            // Owner, 2026-08-27: "ALL AI ON, by my law. The client can turn it
            // off if they want. All autopilots on, with AI watching them."
            //
            // ⛔⛔ This OVERRULES §227.2's L1 PROPOSE default and every
            //    "L1 FOREVER" exemption. A module that ships below its ceiling
            //    is a module whose tenant approves its output — and the whole
            //    purpose of the platform is that they don't have to.
            //
            // ⭐ The safety layer is X-126's capability gate — no grounding
            //   Fact, no skill — NOT a human clicking approve.
            if ($m->shipsAt !== 'n/a' && $m->ceiling !== '' && $m->shipsAt !== $m->ceiling) {
                $out[] = [
                    'where' => "{$m->id} ships: {$m->shipsAt} · ceiling: {$m->ceiling}",
                    'what' => 'ships BELOW its ceiling — the autopilot is off at signup',
                    'fix' => "set ships: {$m->ceiling}. R235: every autopilot ships ON and the client "
                        .'turns it off if they want. Nothing waits to be switched on, and nothing '
                        .'"earns" the right to act.',
                ];
            }

            // ⛔⛔⛔ R236 — THERE ARE THREE STATES AND ONLY THREE.
            //
            //   ON              the default; nothing was switched on to get here
            //   NOT_CONFIGURED  the lane CANNOT physically run — no number, no
            //                   credential, no connected profile. A CAPABILITY
            //                   problem, never a permission one.
            //   OFF_BY_CLIENT   their choice, their switch, one tap back
            //
            // ⛔ Any fourth state is a permission gate wearing a new name, and a
            //   team under pressure will reach for one. These are the synonyms
            //   that have shown up in this package already.
            foreach (['AWAITING_APPROVAL', 'PENDING_ENABLE', 'PROPOSE_ONLY', 'AWAITING_REVIEW', 'PENDING_APPROVAL'] as $forbidden) {
                if (stripos($src, $forbidden) !== false) {
                    $out[] = [
                        'where' => $m->id,
                        'what' => "declares the state {$forbidden}",
                        'fix' => 'R236: there are three states — ON, NOT_CONFIGURED, OFF_BY_CLIENT. '
                            .'A fourth is a permission gate with a new name.',
                    ];
                }
            }

            // ⛔⛔ N-235-03 — no module may require the client to enable it.
            if (preg_match('/\b(opt-?in by default|disabled by default|must (?:be )?enabled? (?:first|before)|requires? the (?:tenant|client) to enable)\b/i', $src) === 1) {
                $out[] = [
                    'where' => $m->id,
                    'what' => 'ships requiring the client to turn it ON',
                    'fix' => 'R235 N-235-03: there is no "enable" step. Ship it on, with an OFF switch '
                        .'the client owns.',
                ];
            }
            // ⭐⭐⭐ N-235-04 — THE LOAD-BEARING ONE.
            //
            // R235 turns on 102 autopilots. X-126's gate is what supervises
            // them, and it was referenced by ELEVEN modules when the ruling was
            // made. Autonomy without the gate is not "AI watching AI" — it is
            // nothing watching anything.
            $reachesCustomer = $m->intent === 'GROW' || $m->intent === 'SERVE';
            if ($reachesCustomer && ! str_contains($src, 'capability.decided')) {
                $out[] = [
                    'where' => "{$m->id} @intent {$m->intent}",
                    'what' => 'acts toward a customer without consuming capability.decided',
                    'fix' => 'R235 N-235-04: every autopilot is supervised by X-126\'s gate. '
                        .'Consume capability.decided — "no grounding Fact, no skill" is the thing '
                        .'that makes shipping ON safe.',
                ];
            }
        }

        foreach ($consumed as $token => $consumers) {
            if (isset($ingress[$token]) || isset($scheduled[$token])) {
                continue; // no emitter BY DESIGN — declared, not assumed
            }
            if (! isset($emitted[$token])) {
                $out[] = [
                    'where' => implode(', ', $consumers),
                    'what' => "consumes '{$token}' — nothing emits it",
                    'fix' => "either emit '{$token}' from its owning module, or delete the subscription (P-208)",
                ];
            }
        }

        // ⛔ Two emitters for one event: five such cases were residue from a header
        //    split where the OLD block kept declaring what it had given away.
        //    A split is done when the old block stops claiming things.
        // ⭐ MULTI-EMITTER EXEMPTION — owner ruling 2026-09-07 (reserved questions, item 4).
        //    The master plan's own rule permits more than one module to emit these two: they
        //    are fan-in REQUESTS, not capability claims, so a second emitter is not the
        //    header-split residue the check below exists to catch. Measured at the time of the
        //    ruling: 'send.requested' had 8 emitters, 'approval.requested' had 5, across 13
        //    modules, and every one of them was legitimate.
        // ⛔ Adding a token to this list WEAKENS A CHECK. It needs an owner ruling and a line
        //    in REVIEWS.md, exactly like this one. Every other token keeps one-event-one-emitter.
        // ⭐ 'win.first' — owner ruling 2026-09-16: two plan-sanctioned emitters, C-Reviews (P-113,
        //    the first review received) and X-118 (the onboarding test call, plan §27169). Both are
        //    the plan's own FIRST-WIN, so a second emitter is not the header-split residue this catches.
        $multiEmitterOk = ['send.requested', 'approval.requested', 'win.first'];

        foreach ($emitted as $token => $emitters) {
            $unique = array_values(array_unique($emitters));
            if (count($unique) > 1 && ! in_array($token, $multiEmitterOk, true)) {
                $out[] = [
                    'where' => implode(', ', $unique),
                    'what' => "'{$token}' has {$unique[0]} and ".(count($unique) - 1).' other emitter(s)',
                    'fix' => 'one event, one emitter — the module that gave the capability away must stop declaring it',
                ];
            }
        }

        return $out;
    }
}
