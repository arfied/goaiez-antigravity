<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validating a keystroke before it becomes a billable Google request.
 *
 * The cheapest control on this endpoint, and the one doing the most work. Every
 * request that gets past this class costs 0.283c (PlacesSku::AutocompleteRequests),
 * so the minimum length is a spend decision first and a relevance decision
 * second — one or two characters match everything, which means we would be
 * paying for a list nobody can use.
 */
final class SuggestPlacesRequest extends FormRequest
{
    /**
     * Pre-signup, by definition. See StartPublicAuditRequest.
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
            'query' => [
                'required',
                'string',
                'min:3',
                'max:120',
            ],
        ];
    }

    /**
     * The typed fragment, whitespace-collapsed.
     *
     * Normalised here as well as in the client's cache key, because the two do
     * different jobs: this is what Google is asked, and that is what we
     * remember being asked. Collapsing it in both places means "Joes  Pizza"
     * and "Joes Pizza" are one question with one cached answer.
     */
    public function searchQuery(): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $this->validated('query')));
    }
}
