<?php

declare(strict_types=1);

namespace App\Support\Admin;

use Illuminate\Database\Eloquent\Model;

/**
 * A titled group of read-only fields on a detail screen.
 *
 * Reuses Column for the entries, so a value formatted one way in a table is
 * formatted the same way on the record it belongs to — the alternative is two
 * renderings of the same field that disagree, and the one nobody checks is the
 * one that leaks.
 *
 * **Declarative by allowlist, on purpose.** The obvious detail layout iterates
 * `$model->getAttributes()` and renders everything, which on `oauth_connections`
 * prints `access_token_enc`, and on `customers` prints every consent field and
 * phone number. `29` §2 rule 42's audit trail does not help after the fact if
 * the screen showed it. Nothing renders here that a section did not name.
 */
final class DetailSection
{
    /**
     * @param  array<int, Column>  $entries
     */
    private function __construct(
        public readonly string $title,
        private readonly array $entries,
        public readonly ?string $description = null,
    ) {}

    /**
     * @param  array<int, Column>  $entries
     */
    public static function make(string $title, array $entries, ?string $description = null): self
    {
        return new self($title, $entries, $description);
    }

    /**
     * @return array<int, array{label: string, value: mixed, numeric: bool}>
     */
    public function render(Model $record): array
    {
        return array_map(
            static fn (Column $entry): array => [
                'label' => $entry->label,
                'value' => $entry->render($record),
                'numeric' => $entry->isNumeric(),
            ],
            $this->entries,
        );
    }
}
