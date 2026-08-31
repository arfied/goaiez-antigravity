<?php

declare(strict_types=1);

namespace App\Support\Admin;

use BackedEnum;
use Illuminate\Validation\Rule;

/**
 * One field in an admin form.
 *
 * Carries its own validation rules, so a screen declares a field once and both
 * the control and its rules come from that declaration. Two lists — one for
 * rendering, one for validating — drift, and the direction they drift in is a
 * field that renders but is never validated.
 *
 * The same restriction the table applies to sorting applies here to saving:
 * AdminForm writes only the keys declared as fields. Without that, a crafted
 * Livewire payload could set any attribute on the model, which is the mass
 * assignment problem arriving by a different door.
 */
final class Field
{
    /** @var array<int, string|object> */
    private array $rules = ['nullable'];

    /** @var array<string, string>|null */
    private ?array $options = null;

    private string $type = 'text';

    private ?string $help = null;

    private function __construct(
        public readonly string $key,
        public readonly string $label,
    ) {}

    public static function make(string $key, ?string $label = null): self
    {
        return new self($key, $label ?? str($key)->headline()->toString());
    }

    /**
     * @param  array<int, string|object>  $rules
     */
    public function rules(array $rules): self
    {
        $this->rules = $rules;

        return $this;
    }

    public function required(): self
    {
        $this->rules = ['required', ...array_values(array_filter(
            $this->rules,
            static fn (string|object $r): bool => $r !== 'nullable',
        ))];

        return $this;
    }

    /**
     * A closed set of choices, from a backed enum.
     *
     * Rule::enum() rather than a hand-written `in:` list — the enum is already
     * the source of truth (CLAUDE.md forbids a database enum precisely so that
     * it is), and a hand-written list is a second one that drifts.
     *
     * @param  class-string<BackedEnum>  $enum
     */
    public function enum(string $enum): self
    {
        $this->type = 'select';

        $this->options = collect($enum::cases())
            ->mapWithKeys(fn (BackedEnum $case): array => [
                (string) $case->value => method_exists($case, 'label')
                    ? $case->label()
                    : str((string) $case->value)->headline()->toString(),
            ])
            ->all();

        $this->rules = [...$this->rules, Rule::enum($enum)];

        return $this;
    }

    public function boolean(): self
    {
        $this->type = 'checkbox';
        $this->rules = ['boolean'];

        return $this;
    }

    public function textarea(): self
    {
        $this->type = 'textarea';

        return $this;
    }

    /**
     * Explanatory text shown with the control.
     *
     * Outcome language (`29` §2 rule 47): say what the setting does for the
     * business, never how it is implemented.
     */
    public function help(string $help): self
    {
        $this->help = $help;

        return $this;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function helpText(): ?string
    {
        return $this->help;
    }

    /**
     * @return array<string, string>|null
     */
    public function options(): ?array
    {
        return $this->options;
    }

    /**
     * @return array<int, string|object>
     */
    public function validationRules(): array
    {
        return $this->rules;
    }
}
