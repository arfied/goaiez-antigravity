<?php

declare(strict_types=1);

namespace Tests\Support\AgentEvals;

use App\Models\Business;
use App\Services\Knowledge\KnowledgeSnippet;
use Closure;

/**
 * One eval — a tenant, a message, and **two** model outputs.
 *
 * ## ⛔ THE SECOND OUTPUT IS THE WHOLE DESIGN
 *
 * An eval that only shows a bad output being refused is an eval that passes when
 * the tenant fixture refuses *everything*. That is not hypothetical here: L7's
 * mutation M19 came back GREEN because every price test used a business with no
 * price list, so the allowed set was empty either way and deleting the guard
 * changed nothing. The fixture had made the thing under test unreachable, and the
 * suite was green and blind.
 *
 * **So every case carries a `compliantOutput` that must go out unrefused**, and
 * the runner asserts both arms. The pair is what proves the fixture can say *no*
 * and *yes*, which is the only evidence that the refusal came from the guard
 * rather than from the setup. ⚠️ **It is not a nicety and it is not optional** —
 * `AgentEvalTest`'s harness lint fails the build on a case whose two outputs are
 * the same string, because that is how somebody satisfies the constructor without
 * satisfying the argument.
 *
 * ## ⛔ AND THE REFUSAL REASON IS ASSERTED EXACTLY, NEVER "IT WAS REFUSED"
 *
 * `AgentComposer::lint()` runs four checks in order and they all end in the same
 * replacement line. A case asserting only *refused* would pass while a completely
 * different guard did the refusing — CLAUDE.md 398, with the guards side by side
 * rather than stacked: delete the arm the case is named for, and its neighbour
 * covers for it silently. Asserting `fallbackReason` exactly is what makes each
 * case falsifiable on its own line, and it is why every `violatingOutput` below
 * breaks exactly one rule.
 *
 * ## ⚠️ WHAT A CASE CANNOT CLAIM
 *
 * The model is scripted. **No case here is evidence about how a real model
 * behaves** — not that it resists an injection, not that it declines to invent a
 * price. Every case is evidence about **what this application does with an output
 * once it has one**, which is the half that is ours and the half a test can
 * actually reach. {@see self::$notCovered} carries that sentence per case rather
 * than leaving it to this docblock, because the sentence differs per case and a
 * general disclaimer is one nobody reads twice.
 */
final readonly class AgentEvalCase
{
    /**
     * @param  string  $id  Stable, unique, and used as the dataset key so a
     *                      failure names the eval rather than a row number.
     * @param  string  $property  The claim, written as the thing that is actually
     *                            proven — never as the thing one wishes were.
     * @param  string  $notCovered  What a pass here does **not** say.
     * @param  Closure(Business): void  $seed  Runs inside the tenant, before the
     *                                         skill set is resolved.
     * @param  string  $violatingOutput  What a bad model says. Exactly one rule
     *                                   broken — see the class docblock.
     * @param  string  $expectedRefusal  The exact `AgentReplyDraft::$fallbackReason`
     *                                   the violation must produce.
     * @param  string  $compliantOutput  What a good model says. Must be sent.
     * @param  ?string  $bodyAlwaysContains  A substring both arms must carry —
     *                                       the disclosure is the one that needs
     *                                       it, because it rides the replacement
     *                                       line as well as the model's own words.
     * @param  ?Closure(): list<KnowledgeSnippet>  $snippets
     *                                                        Rail 2's facts, built inside the
     *                                                        tenant. ⚠️ **A CLOSURE RATHER THAN AN
     *                                                        ARRAY**, because a snippet is an
     *                                                        Eloquent-backed object and the case
     *                                                        list is built at collection time —
     *                                                        before a database exists (2423's
     *                                                        `DatasetMissing`, one layer over).
     */
    public function __construct(
        public AgentEvalSubject $subject,
        public string $id,
        public string $property,
        public string $notCovered,
        public Closure $seed,
        public string $customerMessage,
        public string $violatingOutput,
        public string $expectedRefusal,
        public string $compliantOutput,
        public bool $isFirstAgentTurn = false,
        public ?string $bodyAlwaysContains = null,
        public ?Closure $snippets = null,
    ) {}
}
