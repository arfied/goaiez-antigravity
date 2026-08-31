<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Concerns;

use App\Services\AuditService;
use App\Support\Admin\Field;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Validation, state and saving for an admin form — once.
 *
 * A screen declares fields() and record(); it writes no rules array, no fill
 * loop and no validation.
 *
 * ⚠️ **"AND NO SAVE METHOD" WAS THE THIRD ITEM ON THAT LIST AND IT STOPPED
 * BEING TRUE ON 2026-08-24** (9237). `Admin\LocationSettings` — the only
 * composer of this trait — declares a `save()` of its own that establishes the
 * account's tenancy and calls this one through a `protected` alias, because the
 * people that screen is for hold no tenant and the client is what invokes
 * `save()`. The claim above is worth keeping in its narrowed form: the *body*
 * of a save is still written once, here.
 *
 * **Only declared fields are written.** `state` arrives from the browser, so a
 * crafted Livewire payload can carry any key at all; intersecting it with
 * fields() before saving is what stops that becoming assignment to an
 * undeclared attribute. Guarded columns already stop the worst of it, but this
 * is the layer that means a screen showing two fields cannot write a third.
 *
 * **Every save is audited**, with both sides of the change. `29` §2 rule 42
 * requires it for sensitive actions, and §19.3 requires threshold changes to
 * carry old and new values — an entry recording only the new one cannot answer
 * what happened. Doing it here rather than per screen means a new screen cannot
 * forget.
 *
 * @template TModel of Model
 */
trait AdminForm
{
    /** @var array<string, mixed> */
    public array $state = [];

    public bool $saved = false;

    /**
     * @return array<int, Field>
     */
    abstract protected function fields(): array;

    /**
     * @return TModel
     */
    abstract protected function record(): Model;

    /**
     * A stable name for the audit entry, e.g. 'autopilot_settings.updated'.
     */
    abstract protected function auditAction(): string;

    /**
     * Read the declared fields off the record into `state`.
     *
     * ⛔ **IT WAS CALLED `mountAdminForm()` AND THAT NAME MADE IT A LIVEWIRE
     * LIFECYCLE HOOK — RENAMED 2026-08-24 (9237).**
     * `SupportLifecycleHooks::callTraitHook('mount', …)` calls
     * `mount`.`class_basename($trait)` on **every** trait a component uses, so
     * this method ran on every mount whether or not anybody called it — and the
     * screen called it too, so the record was read **twice** per mount. That is
     * merely wasteful; what is not is that a screen composing this trait could
     * not decide *when* the first tenant-scoped read happens, because the
     * framework had already made it. `Admin\LocationSettings` needs exactly that
     * decision: it has no tenant until an operator names the account, so the
     * opening read has to wait for `resolve()` rather than fire at `mount()`.
     *
     * ⚠️ **PROTECTED FOR THE SAME REASON.** A public method on a component is
     * callable from `/livewire/update` with whatever snapshot the client sends,
     * and this one reads a tenant-scoped record.
     */
    protected function loadDeclaredState(): void
    {
        $record = $this->record();

        $this->state = $this->declaredKeys()
            ->mapWithKeys(fn (string $key): array => [$key => $this->readable($record->{$key})])
            ->all();
    }

    /**
     * @return array<string, array<int, string|object>>
     */
    protected function rules(): array
    {
        return collect($this->fields())
            ->mapWithKeys(fn (Field $field): array => [
                'state.'.$field->key => $field->validationRules(),
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return collect($this->fields())
            ->mapWithKeys(fn (Field $field): array => [
                'state.'.$field->key => strtolower($field->label),
            ])
            ->all();
    }

    public function save(): void
    {
        $this->validate();

        $record = $this->record();

        // Intersect rather than trust: state carries whatever the client sent.
        $changes = $this->declaredKeys()
            ->mapWithKeys(fn (string $key): array => [$key => $this->state[$key] ?? null])
            ->all();

        $before = collect($changes)
            ->mapWithKeys(fn (mixed $_, string $key): array => [$key => $this->readable($record->{$key})])
            ->all();

        $record->fill($changes)->save();

        $after = collect($changes)
            ->mapWithKeys(fn (mixed $_, string $key): array => [$key => $this->readable($record->fresh()?->{$key})])
            ->all();

        // Only when something actually moved. An audit log full of "saved, no
        // change" entries is one nobody reads, and rule 42 is about being able
        // to answer what happened.
        if ($before !== $after) {
            app(AuditService::class)->recordChange(
                $this->auditAction(),
                'user:'.(string) (auth()->id() ?? 'unknown'),
                $before,
                $after,
                $record,
            );
        }

        $this->saved = true;
    }

    /**
     * @return Collection<int, Field>
     */
    public function visibleFields(): Collection
    {
        Tenancy::idOrFail();

        return collect($this->fields());
    }

    /**
     * @return Collection<int, string>
     */
    private function declaredKeys(): Collection
    {
        return collect($this->fields())->map(fn (Field $f): string => $f->key);
    }

    /**
     * Enums and dates arrive from the model as objects; the form binds scalars.
     */
    private function readable(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
