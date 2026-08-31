<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where one step of a multi-step flow stands for the person looking at it.
 *
 * ⚠️ **THE STATE IS A SENTENCE FIRST.** `label()` is what the screen prints; the
 * marker and the tint are what make it findable. Colour is never the sole
 * indicator (`22`), and a stepper is the shape that most often breaks that rule
 * — three coloured circles, one of them filled, and nothing that reads aloud.
 *
 * ⚠️ **OUTCOME LANGUAGE, SO THE WORDS NAME WHAT THE PERSON DOES** (`22`, `29`
 * §5.7). "Do this now" rather than "active"; "Still to do" rather than
 * "pending". Neither of those describes the system's state machine, which is the
 * whole point of the rule.
 */
enum StepState: string
{
    /** Finished. Nothing here needs the person again. */
    case Done = 'done';

    /** The one thing to do next. */
    case Now = 'now';

    /** Real, and not reachable until an earlier step is finished. */
    case Next = 'next';

    public function label(): string
    {
        return match ($this) {
            self::Done => 'Done',
            self::Now => 'Do this now',
            self::Next => 'Still to do',
        };
    }

    /**
     * The non-colour half of the signal, as text rather than an icon font — it
     * survives a stylesheet that did not load, which is exactly the moment
     * colour conveys nothing.
     */
    public function marker(): string
    {
        return match ($this) {
            self::Done => '✓',
            self::Now => '→',
            self::Next => '·',
        };
    }

    public function textClass(): string
    {
        return match ($this) {
            self::Done => 'text-ok',
            self::Now => 'text-attention',
            self::Next => 'text-ink-2',
        };
    }

    public function backgroundClass(): string
    {
        return match ($this) {
            self::Done => 'bg-ok-bg',
            self::Now => 'bg-attention-bg',
            self::Next => 'bg-paper',
        };
    }
}
