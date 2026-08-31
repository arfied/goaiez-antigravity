<?php

declare(strict_types=1);

namespace App\Services\Content;

/**
 * Whether this location may publish another page this period (`16` §15.3).
 *
 * ⚠️ **A COUNT AND NOT A STORED FLAG.** Both caps this answers are derived from
 * `growth_pages` on every attempt, so the pause releases itself at the period
 * boundary and there is nothing to clear, nothing to drift and nothing for an
 * operator to reset. The self-audit's stop is the other shape and lives on the
 * `locations` row, because its inputs are ninety days of somebody else's API.
 */
final readonly class VolumeVerdict
{
    /**
     * @param  string  $capKey  The registry row the owner-facing item names.
     */
    private function __construct(
        public bool $allowed,
        public ?string $capKey = null,
        public int $published = 0,
        public int $cap = 0,
    ) {}

    public static function allowed(): self
    {
        return new self(true);
    }

    public static function reached(string $capKey, int $published, int $cap): self
    {
        return new self(false, $capKey, $published, $cap);
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'cap' => $this->capKey,
            'published' => $this->published,
            'allowed' => $this->cap,
        ];
    }
}
