<?php

declare(strict_types=1);

namespace App\Support\Messaging;

use App\Services\Messaging\Composer\ReactComposer;
use App\Support\Campaigns\PackMessage;
use InvalidArgumentException;

/**
 * The placeholder vocabulary the platform's own authored messages use — CC-5 §0.
 *
 * ## ⚠️ ONE SYNTAX, THREE VOCABULARIES, AND THAT IS NOT A FORK
 *
 * Braces, lower case, snake_case — everywhere in this application, always. What
 * differs is *which words are substituted*, and it differs because the render
 * points differ:
 *
 *   {@see PackMessage::SLOTS}    `{name}` `{link}` — a campaign body, rendered by
 *                                {@see ReactComposer}, which prefixes the
 *                                business name itself, so there is no business
 *                                slot and one would name the business twice.
 *   {@see self::PLATFORM}        `{name}` `{business}` `{link}` `{date}` — a
 *                                message GO AI EZ sends in its own voice, where
 *                                the business is named mid-sentence rather than
 *                                as a prefix ("Somos {business}").
 *   `App\Enums\SupportMacroSlot` a support reply, whose fill-ins are a status, a
 *                                step, a cancel link — a person's words, not a
 *                                merge.
 *
 * CC-5 §0's rule is *"never fork a second syntax"*, and a second **vocabulary**
 * on the same syntax is what a different render point actually needs. The
 * evidence it is not a fork: a reader who has seen one of the three can read the
 * other two without being told anything.
 */
final class MessageSlots
{
    /**
     * Every placeholder a platform-authored message may carry.
     *
     * @var list<string>
     */
    public const array PLATFORM = ['{name}', '{business}', '{link}', '{date}'];

    /**
     * Refuse any placeholder outside the permitted set.
     *
     * ⚠️ **THE SOURCES ARE WHY THIS EXISTS AND NOT A HYPOTHETICAL.** Lifecycle templates write
     * `{Business}` and `{Name}`, the guarantee rider writes `[DATA]` and `[LINK]`, and
     * LP-0 writes `{Link}`. Every one of those is delivered to a real person
     * exactly as typed, because nothing substitutes it — so the translation
     * happens in the catalogue and this is what proves it happened.
     *
     * @param  list<string>  $permitted
     *
     * @throws InvalidArgumentException
     */
    public static function assertOnly(string $body, array $permitted, string $what): void
    {
        // ⚠️ SQUARE BRACKETS ARE MATCHED TOO, BECAUSE THE SOURCES USE BOTH. A
        // check that only looked for braces would pass `[LINK]` straight through
        // — the exact spelling the guarantee rider ships, on the one rung that carries
        // a written promise.
        if (preg_match_all('/\{[^}]*\}|\[[^\]]*\]/u', $body, $matches) === 0) {
            return;
        }

        $strange = array_values(array_diff(array_unique($matches[0]), $permitted));

        if ($strange === []) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            '%s carries %s. The only placeholders substituted here are %s — spelled exactly like '
            .'that, in lower case. The sources write them differently and the translation belongs '
            .'in the catalogue: anything else is delivered to the recipient with its brackets '
            .'showing, and nothing between the template and the handset would have shown it to '
            .'anybody.',
            $what,
            implode(', ', $strange),
            implode(', ', $permitted),
        ));
    }
}
