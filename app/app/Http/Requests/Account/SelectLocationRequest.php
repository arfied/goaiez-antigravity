<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The press that moves the account screens to another location (3060–3079).
 *
 * ⚠️ **A FORM REQUEST BECAUSE THIS IS A REAL CONTROLLER ACTION.**
 * `Account\WidgetInstall`, `Account\Knowledge` and `Account\ReviewRules` all
 * validate inline and say why — a `FormRequest` is resolved out of the container
 * for an HTTP action and a Livewire action is neither (2967). This is the other
 * case: a named POST route, so `CLAUDE.md`'s rule applies as written and the
 * validation lives here.
 *
 * ⚠️ **THE `exists` RULE CARRIES ITS OWN TENANT PREDICATE.** A validation rule
 * builds its query on the query builder rather than on the model, so **no global
 * scope reaches it** — the multi-tenancy skill names this surface by name.
 * Postgres RLS would still refuse the row, which is exactly why the predicate is
 * written rather than relied upon: the layer that must not be the only one is
 * the one nobody can see failing.
 */
final class SelectLocationRequest extends FormRequest
{
    /**
     * Signed in **and** inside a tenant.
     *
     * The route sits behind `auth`, so the first half is already true; the
     * second is not. Internal staff belong to no business by design, so a
     * signed-in support agent posting here is the ordinary way to arrive with
     * nothing resolved — and `Tenancy::idOrFail()` in the rules below would then
     * be an unhandled exception rather than a refusal.
     */
    public function authorize(): bool
    {
        return Tenancy::check();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'location' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where('business_id', Tenancy::idOrFail()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        // Outcome language (`22`): what the person was trying to do, never the
        // column or the rule that refused them.
        return [
            'location.required' => 'Choose which location you want to work on.',
            'location.integer' => 'That is not one of your locations.',
            'location.exists' => 'That is not one of your locations.',
        ];
    }
}
