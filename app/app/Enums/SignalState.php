<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a signal colour is allowed to mean, and what has to accompany it.
 *
 * `29` §2 and `22` state the rule this enum exists to make unbreakable: **colour
 * is information, never decoration, and never the sole indicator** — every use
 * is paired with an icon or a label, because roughly one man in twelve cannot
 * separate the green from the red and the whole product is a status display.
 *
 * The pairing lives here rather than in each component. A component that renders
 * `->colourClass()` without `->icon()` and `->label()` is possible but obviously
 * wrong at the call site, which is the most a type can do; a component that
 * picked its own hex would not even be visibly wrong. `29` §12.1 makes the rule
 * build-failing where testable, and ComponentLibraryTest tests it here once
 * rather than in every component that draws a dot.
 *
 * `Unknown` IS NOT A FOURTH SEVERITY. It is the absence of a measurement, and it
 * is the reason this is an enum rather than a string: row 2 slice E can produce
 * a null score (decision 227 — "we could not look" is not zero), and a component
 * given null must render a dash and a plain sentence rather than an empty dial
 * that reads as nought out of a hundred. Every other state is a fact about the
 * business; this one is a fact about us.
 */
enum SignalState: string
{
    /** Running. Nothing needs a person. */
    case Ok = 'ok';

    /** Working, but worth a look. Never blocks anything. */
    case Attention = 'attention';

    /** Costing the business something now. */
    case Alert = 'alert';

    /** Not measured. Never rendered as a zero. */
    case Unknown = 'unknown';

    /**
     * The band a 0-100 score falls in.
     *
     * Null in, Unknown out — the whole reason the fourth case exists.
     *
     * The thresholds are ours and they are deliberately generous at the top:
     * a business scoring 80 has its listing genuinely in order, and telling it
     * otherwise to manufacture urgency is the kind of thing that makes an
     * instrument stop being believed.
     */
    public static function fromScore(?int $score): self
    {
        return match (true) {
            $score === null => self::Unknown,
            $score >= 80 => self::Ok,
            $score >= 50 => self::Attention,
            default => self::Alert,
        };
    }

    /**
     * The Tailwind token class for the fill. Never used without label() or icon().
     *
     * Token classes rather than hex, so the `@theme` block in
     * `resources/css/app.css` stays the only place a signal hue is defined and
     * dark mode keeps working — the tokens flip there, and a component holding a
     * literal colour would not flip with them.
     */
    public function textClass(): string
    {
        return match ($this) {
            self::Ok => 'text-ok',
            self::Attention => 'text-attention',
            self::Alert => 'text-alert',
            self::Unknown => 'text-ink-3',
        };
    }

    public function backgroundClass(): string
    {
        return match ($this) {
            self::Ok => 'bg-ok-bg',
            self::Attention => 'bg-attention-bg',
            self::Alert => 'bg-alert-bg',
            self::Unknown => 'bg-paper',
        };
    }

    /**
     * The stroke colour for the gauge arc, as a CSS custom property reference.
     */
    public function strokeVar(): string
    {
        return match ($this) {
            self::Ok => 'var(--color-ok)',
            self::Attention => 'var(--color-attention)',
            self::Alert => 'var(--color-alert)',
            self::Unknown => 'var(--color-gauge-track)',
        };
    }

    /**
     * The non-colour half of the signal. Text, not an icon font or an SVG
     * sprite, so it survives a stylesheet failing to load — which is exactly the
     * moment colour is least likely to be conveying anything.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Ok => '●',
            self::Attention => '▲',
            self::Alert => '■',
            self::Unknown => '–',
        };
    }

    /**
     * The word a screen reader announces and a colour-blind reader reads.
     *
     * Outcome language (`22`): what the state means for the person, never what
     * the system did to work it out.
     */
    public function label(): string
    {
        return match ($this) {
            self::Ok => 'Running',
            self::Attention => 'Needs a look',
            self::Alert => 'Needs action',
            self::Unknown => 'Not checked',
        };
    }

    /**
     * The one plain sentence `29` §5.3 puts beneath the gauge — "never a metric".
     *
     * A default rather than a fixed string: the caller usually knows something
     * more specific, and the instrument is more useful when it does. What this
     * guarantees is that the fallback is still a sentence rather than a number
     * with a percent sign, which is what §5.3 is actually guarding against.
     */
    public function sentence(): string
    {
        return match ($this) {
            self::Ok => 'Everything is running.',
            self::Attention => 'A few things are worth a look.',
            self::Alert => 'Some things need attention now.',
            self::Unknown => 'We could not check everything this time.',
        };
    }
}
