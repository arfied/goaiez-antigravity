<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\FeedbackRateLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * What a customer may send from the hosted feedback page.
 *
 * NAME AND CONTACT ARE ALL OPTIONAL, and that is FPR-01 rather than leniency: an
 * anonymous submission is a first-class path. It produces a review with no
 * customer and no consent record, because there is nothing to consent about and
 * nobody to consent.
 *
 * A TICKED BOX WITH NO CONTACT IS AN ERROR, NOT A SILENT DROP. Recording SMS
 * consent for a customer with no phone number produces a record that can never
 * authorise anything — ConsentService::permit() returns null for a missing
 * identifier — so the person would have consented to nothing and been told
 * nothing. after() turns that into a sentence they can act on.
 */
final class StoreFeedbackRequest extends FormRequest
{
    /**
     * Public by design. The page is unauthenticated; the tenant boundary is
     * ResolveFeedbackPage's and the abuse controls are the limiter, the honeypot
     * and the timing floor below.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],

            // `accepted` is one of Laravel's implicit rules — it runs even when
            // the field is entirely absent, which an unticked checkbox always
            // is. `nullable` does not stop an implicit rule from running, so it
            // would fail every submission that left a box unticked. `sometimes`
            // does what's wanted here: skip the field (and `accepted` with it)
            // when it is missing, and enforce `accepted` only when it is sent.
            'sms_consent' => ['sometimes', 'accepted'],
            'email_consent' => ['sometimes', 'accepted'],

            // ⚠️ `sometimes`, AND DELIBERATELY NEVER `required` (2079-2081,
            // 2936). This box is a reviewer agreeing to leave health information
            // out of their own comment, and only a covered entity's page renders
            // it. Making it a condition of submitting would be coercion dressed
            // as consent — and worse, it would suppress the rating of anybody
            // who declined, which 2075's replacement build-failing test forbids
            // outright: every rating is captured and kept, none suppressed or
            // hidden. Leaving it alone costs the reviewer the automated read,
            // never their voice.
            //
            // `accepted` still applies when it *is* sent, for `sms_consent`'s
            // reason: a field that is present but falsy is a box in some state
            // other than ticked, and no such thing is ever recorded.
            'phi_analysis_consent' => ['sometimes', 'accepted'],

            // The honeypot. `prohibited` fails when the field is present and not
            // empty, which is exactly the shape wanted: a human never sees it,
            // a bot filling every input does.
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.required' => __('feedback.rating.required'),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->requireContactForConsent($validator);
                $this->requirePlausibleTiming($validator);
            },
        ];
    }

    private function requireContactForConsent(Validator $validator): void
    {
        if ($this->boolean('sms_consent') && trim((string) $this->input('phone')) === '') {
            $validator->errors()->add('phone', __('feedback.errors.sms_needs_phone'));
        }

        if ($this->boolean('email_consent') && trim((string) $this->input('email')) === '') {
            $validator->errors()->add('email', __('feedback.errors.email_needs_email'));
        }
    }

    /**
     * The timing floor.
     *
     * The start time is written to the session when the form is rendered, so a
     * client that never loaded the form has none — and that case is refused
     * rather than defaulted, because defaulting it would make the check
     * decorative for precisely the caller it exists to stop.
     */
    private function requirePlausibleTiming(Validator $validator): void
    {
        $slug = $this->route('slug');

        $startedAt = is_string($slug)
            ? $this->session()->get('feedback.started_at.'.$slug)
            : null;

        if (! is_int($startedAt) || (now()->getTimestamp() - $startedAt) < FeedbackRateLimits::MINIMUM_SECONDS) {
            // Its own field, not `rating`. Harmless while the view renders one
            // flat error list, but filing a timing complaint under a visible
            // form field would put it beside the star rating the moment
            // per-field inline errors exist.
            $validator->errors()->add('timing', __('feedback.errors.too_fast'));
        }
    }
}
