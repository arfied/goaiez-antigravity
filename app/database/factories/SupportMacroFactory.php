<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SupportMacroSlot;
use App\Models\SupportMacro;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportMacro>
 */
final class SupportMacroFactory extends Factory
{
    protected $model = SupportMacro::class;

    /**
     * ⛔ **NOT ONE OF THE ELEVEN, AND MUST NOT BECOME ONE.**
     * `CampaignPackFactory`'s rule and `PlanOfferFactory`'s before it: a factory
     * producing a real macro's key and words would let a test assert the
     * library's contents with `macros:sync` having never run.
     *
     * No `@return array<string, mixed>` docblock: Laravel's stub generates one
     * and Larastan rejects it, because the parent declares the narrower
     * `array<model property of SupportMacro, mixed>`.
     */
    public function definition(): array
    {
        return [
            'key' => 'test-macro',
            'title' => 'A test macro',
            'body' => 'Here is where things stand: '.SupportMacroSlot::Status->placeholder().'.',
            'position' => 99,
        ];
    }

    /**
     * A macro bound to a sentence counsel owns, as S-3 is.
     */
    public function canonBound(): self
    {
        return $this->state(fn (): array => [
            'key' => 'test-canon-macro',
            'body' => SupportMacroSlot::GuaranteeSentence->placeholder().' That is the promise.',
        ]);
    }
}
