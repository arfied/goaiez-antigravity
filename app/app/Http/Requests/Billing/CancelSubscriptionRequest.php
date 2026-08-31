<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Enums\CancellationOutcome;
use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * The confirmation that turns a press into a cancellation (2980–2999).
 *
 * ⚠️ **CONFIRM APPLIES HERE, AND NOT FOR THE USUAL REASON.** `CLAUDE.md`
 * reserves CONFIRM for GBP identity changes, anything that spends money, and
 * the first send of a new campaign type — and cancelling spends nothing. What
 * it does is **irreversible on one of the two gateways**: Authorize.Net's own
 * documentation says a cancelled subscription "cannot be reactivated", so a
 * mis-click is a support call that ends in a new subscription and a gap in
 * billing. The confirmation box is the same control, borrowed for the property
 * that actually justifies it.
 *
 * ⚠️ **THE BOX IS UNCHECKED AND THE SCREEN NAMES THE OUTCOME BEFORE IT.** A
 * confirmation of "cancel?" is a confirmation of nothing; the cancel screen
 * renders which of the six {@see CancellationOutcome} arms applies
 * to *this* subscription — when access ends, whether a paid term stands, what
 * is charged next — and the box sits under it.
 *
 * ⛔ **AND IT MUST NOT BECOME A SECOND GATE ON THE SAME QUESTION.** The
 * authorization is `SubscriptionPolicy::cancel()` and it is asked here, once,
 * where every future caller of this request object meets it.
 */
final class CancelSubscriptionRequest extends FormRequest
{
    /**
     * ⚠️ **A CLASS-LEVEL ABILITY, ASKED BEFORE ANYTHING IS LOOKED UP.** A
     * tenant with no subscription still presses the button, and a gate that is
     * only asked when a row happens to exist is a gate whose coverage depends
     * on data.
     */
    public function authorize(): bool
    {
        return Gate::allows('cancel', Subscription::class);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            // `accepted`, not `boolean`: an unticked checkbox is not submitted
            // at all, and `boolean` would pass a missing field straight through
            // as false. This is the rule that makes the confirmation required
            // rather than decorative.
            'confirm' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirm.accepted' => 'Tick the box to confirm you want to end the plan.',
        ];
    }
}
