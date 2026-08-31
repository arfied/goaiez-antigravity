<?php

declare(strict_types=1);

namespace App\Services\Agent;

/**
 * One turn's worth of words, and how they were arrived at — T176 §2.3, patch P4.
 *
 * ⛔ **THERE IS NO "NO REPLY" OUTCOME, AND THAT IS RAIL 6.** *"Model-down → static
 * template … The thread never dead-ends."* Every construction below produces
 * sendable text, so a caller cannot reach a state where it holds nothing to say
 * — the branch that would have to be handled correctly does not exist to be
 * handled wrongly.
 *
 * ⚠️ **`$fromModel` IS WHAT THE OWNER NOTIFY AND THE RUN ROW READ.** A static
 * template and a model answer are the same shape to the sender and completely
 * different facts to an operator: one means a customer got a real answer, the
 * other means somebody has to. Collapsing them would make a vendor outage
 * invisible on every screen while the queue drained normally.
 */
final readonly class AgentReplyDraft
{
    /**
     * @param  string  $body  The text to send. Never empty.
     * @param  bool  $fromModel  Whether a model wrote this, or a template did.
     * @param  ?string  $fallbackReason  Why the template was used — `model_down`,
     *                                   `refused`, or a rail-5 limb. `null` only
     *                                   when `$fromModel` is true.
     * @param  bool  $needsOwner  Whether the owner has to pick this thread up.
     *                            ⚠️ **NOT THE SAME AS `! $fromModel`**: a model
     *                            answer that took the message rather than
     *                            answering it also needs an owner, and a static
     *                            template on a thread the owner has already been
     *                            told about does not need a second ping.
     */
    private function __construct(
        public string $body,
        public bool $fromModel,
        public ?string $fallbackReason = null,
        public bool $needsOwner = false,
    ) {}

    /**
     * The model answered and every rail let it through.
     */
    public static function written(string $body): self
    {
        return new self(trim($body), fromModel: true);
    }

    /**
     * Rail 6: no model, so the standing template, and the owner is told.
     *
     * ⚠️ **THE REASON IS CARRIED AND THE CUSTOMER NEVER SEES IT.** *"Thanks —
     * {Owner} will get back to you shortly"* is the whole of what a member of the
     * public is told, whatever went wrong. `22`'s outcome-language rule: a string
     * names what the person controls, never how the system is built.
     */
    public static function template(string $body, string $reason): self
    {
        return new self(trim($body), fromModel: false, fallbackReason: $reason, needsOwner: true);
    }

    /**
     * The model wrote something rail 5 refused, so it says the safe line instead.
     *
     * ⚠️ **THE REFUSED TEXT IS NOT CARRIED ON THIS OBJECT.** It is what the
     * assistant nearly said to a customer, and putting it on a value object that
     * travels into a run row and an owner notify is how it ends up in an
     * operator-visible table. The *limb* is the fact anybody needs.
     */
    public static function refused(string $body, string $limb): self
    {
        return new self(trim($body), fromModel: false, fallbackReason: $limb, needsOwner: true);
    }
}
