<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Enums\BillingTerm;
use App\Support\PlanSelection;
use Illuminate\Contracts\Validation\Rule as RuleContract;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Which term a billing screen is quoting (decision 2680).
 *
 * ⚠️ **A FORM REQUEST ON TWO `GET` ROUTES, WHICH LOOKS ODD AND IS THE RULE.**
 * `CLAUDE.md`: validation lives in form requests, never inline in a controller.
 * Both of these routes take their term from the query string — the Stripe
 * checkout redirect and the Accept.js card page — and a query parameter is as
 * attacker-controlled as a form field. What it decides here is which price is
 * quoted to somebody, so "read it with `request()->query()` and hope" is the one
 * shortcut worth refusing.
 *
 * ⚠️ **AN UNKNOWN TERM FAILS VALIDATION RATHER THAN FALLING BACK TO MONTHLY.**
 * The fallback reads as the safe direction and is not: somebody sent here with
 * `?term=anual` would be charged the monthly price on a page that says nothing
 * about it, and neither they nor we would have a record that a different thing
 * was asked for. An **absent** term is the ordinary case and is monthly, which is
 * what every link into these routes has meant since they existed.
 */
final class BillingTermRequest extends FormRequest
{
    /**
     * The most additional locations one checkout may be built with.
     *
     * ⚠️ **A BOUND ON A FORM FIELD, NOT A LIMIT ON THE PRODUCT, AND THAT IS WHY
     * IT IS A CONSTANT RATHER THAN A REGISTRY KEY.** `CLAUDE.md` keeps the
     * registry for figures an operator may move and 3293 has just finished
     * deleting a family of them; a ceiling an operator could raise would be a
     * support surface offering to quote somebody an unbounded number. What this
     * refuses is the hand-edited query string, and the honest ceiling for that is
     * "more than any real business has, and small enough that the arithmetic
     * cannot run away".
     *
     * ⚠️ **A TENANT WHO GENUINELY HAS MORE THAN THIS IS A CONVERSATION, NOT A
     * FORM.** Nothing in this application provisions locations in bulk, the
     * numbers pool assigns one number per tenant (T137 R8), and an enterprise
     * quote is not a checkout page. Refusing here sends that person to support
     * rather than to a card form quoting a figure nobody reviewed.
     */
    public const int MAX_ADDITIONAL_LOCATIONS = 50;

    /**
     * Both routes sit behind `auth` and resolve the tenant from the session, so
     * the person is buying for their own business or nobody's.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * ⚠️ The union carries `RuleContract` because `Rule::enum()` still returns a
     * **legacy** `Illuminate\Contracts\Validation\Rule`, not a `ValidationRule` —
     * verified in the installed framework rather than assumed, and annotated
     * honestly instead of widened to `mixed` to quiet the analyser.
     *
     * @return array<string, list<RuleContract|ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'term' => ['nullable', 'string', Rule::enum(BillingTerm::class)],

            // ⚠️ ACCEPTED ON EVERY TERM AND MEANINGFUL ON ONE. `PlanSelection`
            // ignores it on the monthly plan rather than refusing, because a
            // stale page posting `term=monthly&instalments=1` is a cosmetic
            // mismatch and turning it into a failed checkout helps nobody.
            'instalments' => ['nullable', 'boolean'],

            // ⛔ **BOUNDED, AND THE CEILING IS A FORM BOUND RATHER THAN A
            // POLICY.** `PlanSelection` already refuses a negative, which is the
            // half that would hand out a discount; this is the half that stops a
            // hand-edited query string quoting somebody six figures. See
            // `MAX_ADDITIONAL_LOCATIONS` for why the number is here and not in the
            // registry.
            'locations' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_ADDITIONAL_LOCATIONS],
        ];
    }

    /**
     * The validated input as the value the gateways take.
     *
     * ⛔ **THE LOCATION COUNT IS NOW AN INPUT, AND IT IS *SIGNUP-TIME ONLY* —
     * THIS METHOD'S DOCBLOCK SAID THE OPPOSITE UNTIL THIS SLICE AND THE REASON IT
     * GAVE IS STILL LIVE.** It read *"the location count is zero and is not an
     * input … 'adding a location mid-cycle' is open question K and is still
     * undecided (147–149). A quantity accepted from a form here would answer that
     * question in the place least likely to be read."* **Open question K is still
     * open and nothing here answers it**: both routes this request serves are the
     * *first* purchase — `BillingCheckout::sessionUrlFor()` refuses outright once a
     * Stripe subscription exists, and `AuthorizeNetGateway::subscribe()` refuses
     * once an ARB one does — so no cycle is in progress when this count is read,
     * and there is nothing to prorate. **Adding a location to a live subscription
     * is a different path that does not exist**, and building it means amending a
     * subscription at the vendor, which is K's question and the owner's.
     *
     * ⚠️ **WHAT CHANGED IS THAT THE OTHER BLOCKER 2753 NAMED IS GONE.** It cited
     * two: open question K, and 153's unset monthly add-on cost cap. 3293 deleted
     * the per-tenant dollar cap outright, so there is no cap left to be unset
     * (3317 keeps the key withheld because withheld is the fail-closed state, and
     * 3365 records that the family is removed with the production step). One of
     * the two is answered; this method moves only as far as that answer reaches.
     */
    public function selection(): PlanSelection
    {
        return PlanSelection::fromInput(
            $this->string('term')->toString() ?: null,
            $this->boolean('instalments'),
            $this->integer('locations'),
        );
    }
}
