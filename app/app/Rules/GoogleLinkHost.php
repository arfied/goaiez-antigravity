<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\Places\GoogleLinkHosts;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The pasted link points at a Google host we are willing to follow.
 *
 * `24` §1.2.2's allowlist, as a validation rule so the check happens before any
 * work — "Anything else is rejected before a socket opens." The list itself is
 * GoogleLinkHosts, shared with the resolver, because §2.5.5 decision 197 exists
 * to prevent a second copy of it.
 */
final class GoogleLinkHost implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! GoogleLinkHosts::allowsUrl($value)) {
            // Names the fix rather than the rule. Someone pasting their website
            // instead of their Google listing is the common mistake here, and
            // "invalid host" tells them nothing about what to do next.
            $fail('That is not a Google link. Open your business on Google Maps, tap Share, and paste that link.');
        }
    }
}
