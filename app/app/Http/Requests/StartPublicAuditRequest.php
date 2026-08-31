<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validating a request to audit a business, before anything spends money.
 *
 * `29` §6.2's body is `{place_query | place_id}` — one or the other, never
 * neither. `place_query` is what the visitor typed; `place_id` is what they
 * chose from the candidates a first request returned.
 */
final class StartPublicAuditRequest extends FormRequest
{
    /**
     * The premise of row 2 is that the visitor has no account, so there is
     * nobody to authorize. The controls on this endpoint are the rate limit,
     * Turnstile and the daily budget — none of which are about *who* is asking,
     * which is why none of them live here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'place_query' => [
                'required_without:place_id',
                'string',
                // Three characters before we will pay Google to search. Below
                // that, half the businesses in the country match and the result
                // is noise we were billed for.
                'min:3',
                'max:120',
            ],
            'place_id' => [
                'required_without:place_query',
                'string',
                // Google documents no format guarantee for a place id beyond it
                // being an opaque string, so this caps length and character set
                // rather than pattern-matching a shape that is theirs to change.
                'max:255',
                'regex:/^[A-Za-z0-9_\-]+$/',
            ],
            'turnstile_token' => [
                'nullable',
                'string',
                'max:2048',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            // Outcome language (`22`): what the person can do about it.
            'place_query.required_without' => 'Enter your business name so we know what to look at.',
            'place_query.min' => 'Type a little more of the name — three letters at least.',
            'place_id.regex' => 'That business could not be identified. Search by name instead.',
        ];
    }

    public function placeQuery(): ?string
    {
        $value = $this->validated('place_query');

        return is_string($value) ? trim($value) : null;
    }

    public function placeId(): ?string
    {
        $value = $this->validated('place_id');

        return is_string($value) ? $value : null;
    }

    /**
     * The Turnstile token, if the client sent one.
     *
     * Nullable at this layer on purpose. Whether a token was *required* depends
     * on how many audits this visitor has already run — a question about stored
     * state rather than about the request body — so the controller decides that,
     * and TurnstileVerifier decides whether what arrived is genuine.
     */
    public function turnstileToken(): ?string
    {
        $value = $this->validated('turnstile_token');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
