<?php

declare(strict_types=1);

namespace App\Support\Campaigns;

use App\Services\Campaigns\CampaignPacks;
use App\Services\Messaging\Composer\ReactComposer;
use InvalidArgumentException;

/**
 * One message in an authored campaign pack, and its place in the sequence.
 *
 * T296 §B3: *"each pack = its messages in order with day offsets"*. Read by
 * {@see CampaignPacks}, which is the only thing that turns one into a campaign.
 *
 * ## ⚠️ THE SLOT SYNTAX IS THE HOUSE'S AND THIS IS WHERE THAT IS ENFORCED
 *
 * The T296/T308/LP-0 sources write `{Business} {Name} {Link}`. This repository
 * substitutes exactly two placeholders, spelled `{name}` and `{link}` in lower
 * case — {@see ReactComposer::render()} — and **there is no business slot at
 * all**, because `ReactComposer::compose()` prefixes the business name itself.
 * So a body carrying `{Business}` would be delivered with the literal characters
 * in it *and* would name the business twice; a body carrying `{Name}` would be
 * delivered with the braces showing.
 *
 * ⛔ **TRANSLATED IN THE CATALOGUE, NEVER FORKED INTO A SECOND SYNTAX** (CC-5
 * §0). {@see self::assertSlotsAreTheHouse()} refuses anything else the moment a
 * pack is read, which is before a preview renders and long before a send —
 * rather than leaving it to `ReactComposer` to catch one recipient at a time.
 */
final readonly class PackMessage
{
    /**
     * Every placeholder this application substitutes. Two, lower case.
     *
     * ⚠️ **NOT DUPLICATED FROM A CONSTANT, BECAUSE THERE IS NONE TO POINT AT.**
     * `ReactComposer::render()` spells them inline in a `str_replace`. A lint in
     * `tests/Feature/Architecture/CampaignPackTest.php` reads that method's
     * source and asserts this list against it, so the two cannot drift silently.
     *
     * @var list<string>
     */
    public const array SLOTS = ['{name}', '{link}'];

    public function __construct(
        public int $dayOffset,
        public string $body,
    ) {}

    /**
     * @param  array<array-key, mixed>  $message
     *
     * @throws InvalidArgumentException when the stored row is not a pack message
     */
    public static function fromArray(array $message): self
    {
        $offset = $message['day_offset'] ?? null;
        $body = $message['body'] ?? null;

        if (! is_int($offset) || $offset < 0) {
            throw new InvalidArgumentException(
                'A pack message needs a whole-number day offset of 0 or more. The offset is what '
                .'orders the sequence, and a missing one would silently send the last message first.'
            );
        }

        if (! is_string($body) || trim($body) === '') {
            throw new InvalidArgumentException(
                'A pack message with an empty body has nothing to say. An empty text still costs a '
                .'segment, still arrives, and still counts against the brand throughput.'
            );
        }

        self::assertSlotsAreTheHouse($body);

        return new self($offset, $body);
    }

    /**
     * @return array{day_offset: int, body: string}
     */
    public function toArray(): array
    {
        return ['day_offset' => $this->dayOffset, 'body' => $this->body];
    }

    /**
     * Refuse any placeholder this application does not substitute.
     *
     * ⚠️ **THIS IS THE SAME CLAIM `ReactComposer::assertNothingUnsubstituted()`
     * MAKES, ONE STEP EARLIER, AND NEITHER REPLACES THE OTHER.** That one guards
     * the *rendered* message and is the thing standing between a typo and a real
     * handset; this one guards the *authored* template and can say so while
     * somebody is still writing it. 398's shape is an outer guard making an
     * inner one unfalsifiable, so both are driven independently by test —
     * `ReactComposer`'s with a template this class never saw.
     *
     * @throws InvalidArgumentException
     */
    private static function assertSlotsAreTheHouse(string $body): void
    {
        if (preg_match_all('/\{[^}]*\}/u', $body, $matches) === 0) {
            return;
        }

        $strange = array_values(array_diff(array_unique($matches[0]), self::SLOTS));

        if ($strange === []) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'This pack message carries %s, and the only placeholders this application substitutes '
            .'are %s — spelled exactly like that, in lower case. There is no business placeholder: '
            .'the composer prefixes the business name itself, so one here would name the business '
            .'twice. Anything else is delivered to the recipient with the braces showing.',
            implode(', ', $strange),
            implode(' and ', self::SLOTS),
        ));
    }
}
