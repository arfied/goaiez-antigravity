<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Exceptions\ResponseTemplateRefused;
use App\Services\Reviews\ResponseTemplates;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

/**
 * The owner's own examples of a good reply — the writer for a table that has
 * been read on every draft since Stage 0 and written by nothing (1732).
 *
 * ⚠️ **NESTED, WITH NO ROUTE OF ITS OWN**, the way `Account\ReviewRules` is. The
 * hyphenated alias is load-bearing rather than stylistic: Livewire 4 validates a
 * nested child's tag against letters, numbers and hyphens, so the auto-discovered
 * `account.reply-examples` throws *"Invalid Livewire child tag name"* and takes
 * the whole of `/account` down with it — Pause Everything included — on the first
 * **update** rather than the first render. See
 * `AppServiceProvider::registerNestableLivewireComponents()`.
 *
 * ⚠️ **IT WRITES NOTHING ITSELF.** `ResponseTemplates` is the only class in
 * `app/` that touches `response_templates`, held there by a lint in
 * `Architecture\ReviewsTest`. This component validates an answer and calls one
 * of two service methods; the guardrail, the cap and the audit entry all belong
 * to the service. That is what stops a second screen becoming a second set of
 * rules — `Account\ReviewRules`' own shape.
 *
 * ⛔ **THIS COMPONENT NEVER NAMES `ResponseTemplate`, AND THE FORMATTER IS WHY.**
 * The first draft asked `Gate::authorize('create', ResponseTemplate::class)`
 * here, written fully qualified so the chokepoint's import arm would not see it
 * — and `composer lint` rewrote it into an import on the first run, because
 * Pint's `fully_qualified_strict_types` fixer does exactly that. So the policy
 * question moved onto the service, which is the better home anyway: the class
 * that owns the table owns *who may ask about it* too. See
 * `ResponseTemplates::assertMayCurate()`.
 *
 * ⚠️ **AUTHORIZATION BEFORE VALIDATION** (`Account\Knowledge`'s reasoning):
 * telling somebody their example is too long and then refusing them for their
 * role is two errors for one action, and the second is the one that mattered.
 * `add()` asks it again inside the service, which is what a second caller gets.
 *
 * ⚠️ **DELETION IS AUTHORIZED AGAINST THE ROW, IN THE SERVICE.**
 * `Account\ReviewRules` records the rule this follows — ask the policy about a
 * **row** rather than about a class, because a model that has been resolved has
 * already passed the global scope and row-level security. The row exists only
 * after `ResponseTemplates::remove()` has looked it up under that scope, so the
 * ask lives there.
 *
 * ⚠️ **NO PROPERTY HERE NAMES A RECORD, SO NOTHING TAKES `#[Locked]`.** `$name`
 * and `$body` are the two form fields and are meant to be writable from the page;
 * the id passed to {@see remove()} is an action **argument**, which is
 * attacker-controlled either way and is resolved under the tenant scope by the
 * service, which refuses a stranger's id by name.
 */
final class ReplyExamples extends Component
{
    public string $name = '';

    public string $body = '';

    public function add(ResponseTemplates $templates): void
    {
        abort_if(Tenancy::id() === null, 403);

        // ⚠️ **THE SERVICE HOLDS THE POLICY QUESTION, NOT THIS SCREEN.** See
        // `ResponseTemplates::assertMayCurate()` — naming the model here means
        // importing it, and the import is what the chokepoint lint refuses.
        $templates->assertMayCurate();

        // ⛔ **THE SERVICE'S CEILINGS, NOT A SECOND SET.** Every rule below cites
        // a constant on the class that enforces it, so a screen that validated
        // 800 characters into a 600-character ceiling cannot exist. The cap on
        // how many is checked here too, because an owner who has three deserves a
        // sentence rather than a 500 from the backstop.
        $this->validate([
            'name' => ['required', 'string', 'max:'.app(ResponseTemplates::class)->maxNameLength()],
            'body' => ['required', 'string', 'max:'.app(ResponseTemplates::class)->maxBodyLength()],
        ], [
            'name.required' => 'Give this example a short name so you can find it again.',
            'name.max' => 'That name is too long — keep it to a few words.',
            'body.required' => 'Paste the reply you would like us to write more like.',
            'body.max' => 'That is longer than we can use as an example. '
                .'A reply of two to four sentences is what we copy the style of.',
        ]);

        try {
            $templates->add($this->name, $this->body, 'user:'.(auth()->id() ?? 'unknown'));
        } catch (ResponseTemplateRefused) {
            // ⚠️ **ONE MESSAGE FOR EVERY REFUSAL, IN OUTCOME LANGUAGE, AND NEVER
            // THE EXCEPTION'S OWN TEXT.** Those messages name `ReplyGuardrails`,
            // decision numbers and a Google listing; they are written for whoever
            // is reading a stack trace. What an owner needs is the sentence that
            // tells them what to change. ⚠️ **And it deliberately does not say
            // *which* phrase tripped**: the list is sixteen compensation and legal
            // phrases, and printing the matched one turns a guardrail into a
            // puzzle somebody solves by rewording rather than by rethinking.
            $this->addError('body', 'We cannot use that as an example. '
                .'It offers something — money back, compensation, or a legal position — that we will '
                .'not promise to a customer on your behalf. Take that part out and we can copy the rest.');

            return;
        }

        $this->reset(['name', 'body']);

        // Outcome language (`22`), and no personal data in the toast (104).
        Toaster::success('Saved. We will write more like that.');
    }

    public function remove(int $id, ResponseTemplates $templates): void
    {
        abort_if(Tenancy::id() === null, 403);

        // ⚠️ **NOT AUTHORIZED HERE — see the class docblock.** The row is
        // resolved and the policy asked about it inside the service, which is the
        // only place a `ResponseTemplate` exists in this flow.
        $templates->remove($id, 'user:'.(auth()->id() ?? 'unknown'));

        Toaster::success('Removed. We will stop copying that one.');
    }

    public function render(ResponseTemplates $templates): View
    {
        // Refused rather than resolved when there is no tenant —
        // `Account\Knowledge`'s reasoning: internal staff belong to no business
        // by design, so a signed-in support agent reaching this panel is the
        // ordinary way to arrive with nothing resolved, and letting
        // `Tenancy::idOrFail()` reach the renderer is a 500 that reads as our
        // page being broken.
        abort_if(Tenancy::id() === null, 403);

        $examples = $templates->forOwner();

        return view('livewire.account.reply-examples', [
            'examples' => $examples,

            // ⚠️ **THE AFFORDANCE, ASKED THE SAME WAY THE ACTION IS.** The
            // sibling panels pair `Gate::authorize()` in the writer with
            // `Gate::allows()` in the view, so a person who may not do this is
            // told who can rather than shown a control that 403s. The blade never
            // disables a field — `Account\Calls` records why.
            'mayCurate' => $templates->mayCurate(),

            // The cap is stated on screen rather than discovered by being
            // refused — see `ResponseTemplates::MAX_TEMPLATES` for why a fourth
            // would be stored and never read.
            'atCapacity' => $examples->count() >= ResponseTemplates::MAX_TEMPLATES,
            'capacity' => ResponseTemplates::MAX_TEMPLATES,

            // ⚠️ **THE `maxlength` ATTRIBUTES COME FROM THE SAME CONSTANTS THE
            // `validate()` RULES DO.** A browser hint that disagreed with the
            // server rule would let a paste through the field and refuse it on
            // submit, which reads as our page losing what somebody typed. It is
            // a hint either way — the server rule and the service's ceiling are
            // what enforce it.
            'nameLimit' => app(ResponseTemplates::class)->maxNameLength(),
            'bodyLimit' => app(ResponseTemplates::class)->maxBodyLength(),
        ]);
    }
}
