<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Enums\ModerationFlag;

/**
 * What moderation decided, including the case where it decided nothing.
 *
 * THREE STATES, NOT TWO, and the third is why `moderation_flags` is nullable:
 *
 *   clean        a model read the text and found nothing        flags = []
 *   flagged      a model read the text and found something      flags = [...]
 *   unavailable  no model read the text at all                  flags = null
 *
 * Collapsing the third into "clean" publishes unmoderated customer writing under
 * a business's own name during a vendor outage. Collapsing it into "flagged"
 * withholds every review written that hour for a reason no human can appeal.
 * It is its own state, and Review::displayable() reads it as neither.
 *
 * A REFUSAL IS `flagged`, NOT `unavailable`. The model did read the text — it
 * declined to classify it, which is information about the text. See
 * ModerationFlag::Refused.
 */
final readonly class ModerationVerdict
{
    /**
     * @param  list<ModerationFlag>|null  $flags  Null means no model looked.
     */
    private function __construct(
        public ?array $flags,
        public ?string $reason = null,
    ) {}

    public static function clean(): self
    {
        return new self([]);
    }

    /**
     * @param  list<ModerationFlag>  $flags
     */
    public static function flagged(array $flags): self
    {
        return $flags === [] ? self::clean() : new self($flags);
    }

    /**
     * No verdict exists. `$reason` is for the run row, never for the customer.
     */
    public static function unavailable(string $reason): self
    {
        return new self(null, $reason);
    }

    public function wasModerated(): bool
    {
        return $this->flags !== null;
    }

    public function isFlagged(): bool
    {
        return $this->flags !== null && $this->flags !== [];
    }

    /**
     * The value written to `reviews.moderation_flags`, or null when no model
     * looked. Null and `[]` are different rows and different meanings.
     *
     * @return list<string>|null
     */
    public function forStorage(): ?array
    {
        return $this->flags === null
            ? null
            : array_map(static fn (ModerationFlag $flag): string => $flag->value, $this->flags);
    }

    /**
     * @return list<string>
     */
    public function flagValues(): array
    {
        return $this->forStorage() ?? [];
    }
}
