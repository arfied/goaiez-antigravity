<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use App\Enums\T3InjectionKind;
use App\Services\Config\DefaultsRegistry;

/**
 * A question-and-answer block, rendered into the module's own container.
 *
 * ⛔ **THIS IS THE OPERATION RULE 24 IS ABOUT, AND IT IS WHY THE ENDPOINT
 * REFUSES A `Phi` BUSINESS OUTRIGHT** (`T3PayloadRefusal::HealthTenant`). FAQ
 * content is written from the questions a business's customers actually asked —
 * on a dental or medical tenant those questions are health information about
 * identifiable people, and this operation's whole purpose is to publish text on
 * the open internet. The refusal is at the endpoint rather than here because a
 * refusal that depended on inspecting the text would be a refusal that reads it.
 *
 * ⚠️ **PAIRS, NOT A DOCUMENT.** Each item is one short question and one short
 * answer, and the module builds a heading and a paragraph per pair with
 * `textContent`. There is no way to express a list, a table, a link inside an
 * answer or a heading level — every one of those is markup wearing a field name.
 */
final readonly class T3FaqBlock implements T3Operation
{
    public const int MAX_ITEMS = 12;

    public const int MAX_QUESTION = 200;

    public const int MAX_ANSWER = 600;

    /**
     * @param  list<array{q: string, a: string}>  $items
     */
    private function __construct(public array $items) {}

    /**
     * @param  array<string, mixed>  $fields  A change set's `after` map.
     */
    public static function fromFields(array $fields): ?self
    {
        $registry = app(DefaultsRegistry::class);
        $maxItems = $registry->int('actuation.faq.max_items');
        $maxAnswer = $registry->int('actuation.faq.max_answer_chars');

        $raw = $fields['faq'] ?? null;

        if (! is_array($raw) || $raw === [] || count($raw) > $maxItems) {
            return null;
        }

        $items = [];

        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                return null;
            }

            $question = T3Value::text($entry['q'] ?? null, self::MAX_QUESTION);
            $answer = T3Value::text($entry['a'] ?? null, $maxAnswer);

            // ⚠️ ONE BAD PAIR REFUSES THE WHOLE BLOCK rather than being skipped.
            // A half-rendered FAQ is a page carrying a question with no answer,
            // in the business's own voice, and nobody asked for that.
            if ($question === null || $answer === null) {
                return null;
            }

            $items[] = ['q' => $question, 'a' => $answer];
        }

        return new self($items);
    }

    public function kind(): T3InjectionKind
    {
        return T3InjectionKind::Faq;
    }

    public function toPayload(): array
    {
        return ['t' => $this->kind()->value, 'f' => $this->items];
    }
}
