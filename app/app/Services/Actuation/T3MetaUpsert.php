<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use App\Enums\T3InjectionKind;
use App\Services\Config\DefaultsRegistry;

/**
 * One `<meta>` tag's content, set on the tag that is there or on one we add.
 *
 * ⛔ **THE TAG NAME COMES OFF AN ALLOWLIST AND NEVER OUT OF THE CHANGE SET.**
 * `robots` is why: a meta this platform could set freely includes
 * `<meta name="robots" content="noindex">`, which would remove a customer's page
 * from Google entirely — the largest possible harm from the smallest possible
 * string, done by the system that sells them visibility. The allowlist is the
 * refusal, and `PixelCollector::FORM_FIELD_KEYS`' rule applies verbatim: a
 * denylist of dangerous names would pass the one nobody thought of.
 *
 * ⚠️ **THE ATTRIBUTE IS DECIDED HERE RATHER THAN CARRIED**, because Open Graph
 * uses `property=` and the rest use `name=`, and a module that guessed would
 * write a tag Facebook ignores and a validator flags. It travels on the payload
 * so the module never has to know the vocabulary, and the module still refuses
 * anything but those two words — the same fact stated on both sides of a public
 * network hop.
 */
final readonly class T3MetaUpsert implements T3Operation
{
    /**
     * Every meta tag this tier may write, and the attribute each is keyed by.
     *
     * @var array<string, string>
     */
    public const array NAMES = [
        'description' => 'name',
        'og:title' => 'property',
        'og:description' => 'property',
        'twitter:title' => 'name',
        'twitter:description' => 'name',
    ];

    /**
     * Long enough for a description Google will truncate, short enough that this
     * is not a content channel.
     */
    public const int MAX_CONTENT = 320;

    private function __construct(
        public string $name,
        public string $attribute,
        public string $content,
    ) {}

    /**
     * @param  array<string, mixed>  $fields  A change set's `after` map.
     */
    public static function fromFields(array $fields): ?self
    {
        $registry = app(DefaultsRegistry::class);
        $maxContent = $registry->int('actuation.meta.max_content_chars');

        $name = T3Value::text($fields['name'] ?? null, 64);
        $content = T3Value::text($fields['content'] ?? null, $maxContent);

        if ($name === null || $content === null) {
            return null;
        }

        $attribute = self::NAMES[$name] ?? null;

        if ($attribute === null) {
            return null;
        }

        return new self($name, $attribute, $content);
    }

    public function kind(): T3InjectionKind
    {
        return T3InjectionKind::Meta;
    }

    public function toPayload(): array
    {
        return ['t' => $this->kind()->value, 'a' => $this->attribute, 'n' => $this->name, 'c' => $this->content];
    }
}
