<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use App\Enums\T3InjectionKind;
use App\Services\Config\DefaultsRegistry;

/**
 * One link from this page to another page on the same site.
 *
 * ⛔ **THE DESTINATION IS A PATH AND CANNOT BE A URL** —
 * {@see T3Value::path()} carries the argument. An operation that could carry a
 * host would be a way to put a link to anywhere on every page of every tenant
 * that has ever installed the pixel, which is a link farm with our name on it
 * and a Google penalty landing on the customer.
 *
 * ⚠️ **`rel` AND `target` ARE NOT FIELDS.** They are decided by the module: an
 * internal link needs neither, and both are how a link stops being the link the
 * change set described.
 */
final readonly class T3InternalLink implements T3Operation
{
    public const int MAX_TEXT = 120;

    private function __construct(
        public string $path,
        public string $text,
    ) {}

    /**
     * @param  array<string, mixed>  $fields  A change set's `after` map.
     */
    public static function fromFields(array $fields): ?self
    {
        $registry = app(DefaultsRegistry::class);
        $maxText = $registry->int('actuation.internal_link.max_text_chars');

        $path = T3Value::path($fields['path'] ?? null);
        $text = T3Value::text($fields['text'] ?? null, $maxText);

        if ($path === null || $text === null) {
            return null;
        }

        return new self($path, $text);
    }

    public function kind(): T3InjectionKind
    {
        return T3InjectionKind::InternalLink;
    }

    public function toPayload(): array
    {
        return ['t' => $this->kind()->value, 'p' => $this->path, 'x' => $this->text];
    }
}
