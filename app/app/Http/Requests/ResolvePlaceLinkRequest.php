<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\GoogleLinkHost;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validating a pasted Google link before anything looks at it.
 *
 * `24` §1.2.2: "Validate shape (absolute `http(s)`, length-capped) in a **form
 * request**, per `CLAUDE.md`. Never interpolate the URL into a shell command or
 * a template."
 *
 * The host allowlist is enforced here as well as inside the resolver, and the
 * duplication is deliberate. This layer gives the owner a useful message before
 * any work happens; the resolver's own check is what holds when something calls
 * it from a job, a console command, or row 3's wizard rather than through this
 * request. A security control that exists only at the edge is one refactor away
 * from not existing.
 */
final class ResolvePlaceLinkRequest extends FormRequest
{
    /**
     * Pasting a link is a public, pre-signup action on the audit path — the
     * whole premise of row 2 is that the visitor has no account. Rate limiting
     * and Turnstile are the controls that apply here (slice F), not
     * authorization.
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
            'link' => [
                'required',
                'string',
                // Length-capped per §1.2.2. A Maps share URL with a full `data=`
                // blob runs long, so the cap is generous — but it is a cap, and
                // it sits before the regex so a megabyte of text is rejected
                // without being pattern-matched.
                'max:2048',
                // Absolute http(s) only. `url` alone accepts mailto: and ftp:.
                'url:http,https',
                new GoogleLinkHost,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            // Outcome language (`22`): what the person can do, not what we
            // rejected. They pasted something reasonable-looking and it is not
            // their job to know our allowlist.
            'link.url' => 'That does not look like a web link. Paste the whole link, starting with https://.',
            'link.max' => 'That link is too long to be a Google link. Try the Share button on Google Maps.',
        ];
    }

    public function link(): string
    {
        return trim((string) $this->validated('link'));
    }
}
