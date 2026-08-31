<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CampaignPack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignPack>
 */
final class CampaignPackFactory extends Factory
{
    protected $model = CampaignPack::class;

    /**
     * ⛔ **THE DEFAULT IS NOT ONE OF THE TWELVE, AND MUST NOT BECOME ONE.**
     *
     * `PlanOfferFactory`'s rule, for the same reason: a factory producing a real
     * pack's key and words would let a test assert the gallery's contents
     * without `packs:sync` ever having run — the shape `CLAUDE.md` calls *"an
     * isolation test passing perfectly against a table nothing writes"*.
     * `CampaignPackTest` drives the command for that assertion.
     *
     * ⚠️ **AND IT SHIPS `messages` FILLED WHERE THE CATALOGUE SHIPS THEM
     * EMPTY.** That is the whole use of this factory: the twelve real packs have
     * no authored copy (decision 5253), so the activation and send paths would
     * be untestable — and therefore unproven — without a pack that does. The
     * body here is deliberately plain and deliberately not marketing.
     *
     * No `@return array<string, mixed>` docblock: Laravel's stub generates one
     * and Larastan rejects it, because the parent declares the narrower
     * `array<model property of CampaignPack, mixed>`.
     */
    public function definition(): array
    {
        return [
            'key' => 'test-pack',
            'name' => 'A test pack',
            'one_liner' => 'What this pack does, in one line.',
            'messages' => [
                ['day_offset' => 0, 'body' => 'Hi {name}, a quick note from us: {link}'],
            ],
            'position' => 99,
        ];
    }

    /**
     * A pack shipped as the twelve are: named, promised, and with no copy.
     */
    public function unauthored(): self
    {
        return $this->state(fn (): array => ['messages' => []]);
    }

    /**
     * A pack whose messages run over several days.
     *
     * ⚠️ **NOT `sequence()`** — `Factory` already declares a variadic method of
     * that name, so an override would be an incompatible declaration: the
     * runtime fatal `CLAUDE.md` records as the fourth cause of a run that prints
     * zero bytes. Larastan named it here before it could ever fire.
     *
     * @param  list<int>  $dayOffsets
     */
    public function overDays(array $dayOffsets): self
    {
        return $this->state(fn (): array => [
            'messages' => array_map(
                static fn (int $day): array => [
                    'day_offset' => $day,
                    'body' => "Day {$day}: hello {name}, here is the thing: {link}",
                ],
                $dayOffsets,
            ),
        ]);
    }
}
