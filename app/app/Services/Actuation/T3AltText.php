<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use App\Enums\T3InjectionKind;
use App\Services\Config\DefaultsRegistry;

/**
 * Alt text for one image already on the page.
 *
 * ⛔ **THE IMAGE IS NAMED BY ITS `src`, NEVER BY A CSS SELECTOR.** A selector is
 * a small language, and a small language in a payload written into a stranger's
 * page is a way to reach elements this operation has no business reaching —
 * `input[name=password]`, a form's action, anything. Matching on the `src` the
 * page already carries can only ever address an image, and the module's own
 * suffix match is what makes a CDN's absolute URL and the page's relative one
 * the same picture.
 *
 * ⚠️ **IT SETS ALT TEXT AND NOTHING ELSE.** No `src`, no `srcset`, no
 * `loading`, no dimensions: an operation that could repoint an image is an
 * operation that could replace a customer's photograph with anything at all.
 */
final readonly class T3AltText implements T3Operation
{
    public const int MAX_TEXT = 160;

    private function __construct(
        public string $image,
        public string $text,
    ) {}

    /**
     * @param  array<string, mixed>  $fields  A change set's `after` map.
     */
    public static function fromFields(array $fields): ?self
    {
        $registry = app(DefaultsRegistry::class);
        $maxChars = $registry->int('actuation.alt_text.max_chars');

        // The `src` as the page spells it, or the tail of it. Quotes are refused
        // because the module compares strings and a quote in one would only ever
        // be somebody hoping it did not.
        $image = T3Value::text($fields['image'] ?? null, 512);
        $text = T3Value::text($fields['alt'] ?? null, $maxChars);

        if ($image === null || $text === null) {
            return null;
        }

        if (preg_match('/["\'\s]/', $image) === 1) {
            return null;
        }

        return new self($image, $text);
    }

    public function kind(): T3InjectionKind
    {
        return T3InjectionKind::AltText;
    }

    public function toPayload(): array
    {
        return ['t' => $this->kind()->value, 'i' => $this->image, 'x' => $this->text];
    }
}
