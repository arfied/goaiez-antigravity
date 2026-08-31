<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Asking for a sign-in link.
 *
 * Validation lives here rather than in the controller, per CLAUDE.md.
 *
 * Note what is deliberately NOT validated: whether the address belongs to an
 * account. `exists:users,email` is the obvious rule and it would turn this
 * unauthenticated endpoint into an account-enumeration oracle — a 422 for
 * unknown addresses and a 200 for known ones answers "does this person use GO AI
 * EZ?" for anyone who asks. The service handles the unknown case by doing
 * nothing, and the response is identical either way.
 */
final class MagicLinkRequest extends FormRequest
{
    /**
     * Open by design: this is how someone with no session signs in.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
        ];
    }
}
