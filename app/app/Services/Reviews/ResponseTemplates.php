<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Enums\SupportWriteSubject;
use App\Exceptions\ResponseTemplateRefused;
use App\Http\Middleware\Impersonating;
use App\Models\Business;
use App\Models\Location;
use App\Models\ResponseTemplate;
use App\Services\AuditService;
use App\Services\Config\DefaultsRegistry;
use App\Services\Impersonation\Impersonation;
use App\Support\Tenancy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * The example replies a tenant curates, and the only class in `app/` that
 * touches `response_templates`.
 *
 * ⛔ **THIS TABLE WAS READ ON EVERY REPLY DRAFT AND WRITTEN BY NOTHING** (1732,
 * 272's shape for the fifteenth time). `ReplyGenerator::draft()` has queried it
 * since GBP-03 shipped, `grep -rn 'ResponseTemplate::create' app/` returned
 * zero, and the only writer in the repository was the factory — so every
 * tenant's few-shot block has rendered *"(no curated examples)"* since Stage 0
 * and has never carried a row in production. **A read table with no writer is
 * worse than an unread one**: the consumer is built, tested and green, so
 * nobody looking at the suite would see anything missing.
 *
 * ⛔ **THE TEMPLATE BODY IS A SECOND UNTRUSTED INPUT, NOT A FIXTURE, AND THAT
 * IS THE WHOLE OF THIS SLICE.** What an owner types here is interpolated into a
 * prompt whose output publishes under their own business name on their public
 * Google listing, beside a stranger's review comment. Three things stand
 * between the two:
 *
 *   1. **`PromptFence` already covers it** — `ReplyGenerator` mints the marker
 *      over the composed example block and wraps it, and has since 1727. That
 *      is the one half of this that was built before the writer existed, and it
 *      was verified against the tree rather than assumed before this class was
 *      written.
 *   2. **`ReplyGuardrails::allows()` runs on the model's output**, at the
 *      generator and again at `ReviewReplies::recordSuggestion()`. A template
 *      that teaches the model to offer a refund does not get a refund
 *      published; it gets every draft it touches silently replaced by the safe
 *      template.
 *   3. **And that silence is why {@see add()} asks the same question of the
 *      body**, before it is stored. Without it the owner's own words would
 *      disable their reply drafting with no error, no screen and no trace but
 *      `from_model => false` in a run record — 1735's law-firm failure arriving
 *      through a new door. Here they are told at the moment they type it.
 *
 * ⚠️ **WHAT NONE OF THE THREE STOPS, SAID PLAINLY** (352's rule). A body that
 * carries no forbidden phrase and no instruction can still *style* a reply into
 * something the tenant would not have written — the prompt calls the block
 * *"style only"* and a model is not obliged to agree. What bounds that is that
 * the author is the business whose listing it publishes on, which is decision
 * 1730's line: this platform governs what **we** write under a tenant's name,
 * not what the tenant chooses to say on their own listing.
 *
 * ⚠️ **THE READ MOVED HERE RATHER THAN STAYING IN `ReplyGenerator`** so that
 * the chokepoint has exactly one entry. A permit list of two is an allowlist
 * (`indexingSubmissionChokepointOffences()`'s rule), and the owner's panel
 * would have made it three.
 */
final class ResponseTemplates
{
    /**
     * How many example replies a tenant may hold.
     *
     * ⛔ **THREE BECAUSE THE READER TAKES THREE, AND A FOURTH WOULD BE A LIE.**
     * {@see examples()} is `orderBy('id')->limit(3)` — inherited from the query
     * this class replaced — so a fourth template would be stored, listed on the
     * owner's own screen, and **never read by anything**: the three oldest win
     * for ever. That is a support ticket with a screenshot attached. Capping at
     * the number the reader actually uses is the honest shape, and the screen
     * says the number out loud.
     *
     * ⚠️ **A CLASS CONSTANT RATHER THAN A REGISTRY KEY, DELIBERATELY.** A seeded
     * figure is an Ops-editable number and needs its own argument (`CLAUDE.md`
     * on `DefaultsManifest`); this one is not a business policy but a fact about
     * a query two methods below it, and putting it in the registry would let an
     * operator raise it to five and silently reintroduce the invisible fourth.
     */
    public const int MAX_TEMPLATES = 3;

    /**
     * The longest label a template may carry.
     *
     * The name is never sent to the model and never published — {@see
     * examples()} returns bodies alone. It is a handle on the owner's own
     * screen, so this is a storage bound rather than a safety one.
     */
    public const int MAX_NAME_LENGTH = 60;

    /**
     * The longest body a template may carry.
     *
     * ⚠️ **A PROMPT BUDGET, NOT A STYLE RULE.** Three of these are interpolated
     * into every reply draft, so the ceiling is paid on every call for the life
     * of the account, and the AI pool is a real balance since 3424. What is
     * being exemplified is a two-to-four-sentence Google reply, which
     * `ReplyGenerator`'s system prompt already bounds; 600 is comfortably above
     * the longest reply this application will ever draft.
     */
    public const int MAX_BODY_LENGTH = 600;

    public function __construct(
        private readonly ReplyGuardrails $guardrails,
        private readonly AuditService $audit,
        private readonly Impersonation $impersonation,
        private readonly DefaultsRegistry $registry,
    ) {}

    public function maxNameLength(): int
    {
        return $this->registry->int('reviews.templates.max_name_length');
    }

    public function maxBodyLength(): int
    {
        return $this->registry->int('reviews.templates.max_body_length');
    }

    /**
     * Refuse a role that may not curate what the drafter copies.
     *
     * ⛔ **THE POLICY QUESTION LIVES ON THE CHOKEPOINT, AND THAT IS NOT WHERE
     * THIS SLICE FIRST PUT IT.** `Account\ReplyExamples` originally asked
     * `Gate::authorize('create', ResponseTemplate::class)` itself, written
     * fully qualified so as not to import the model and trip the lint below —
     * and **`composer lint` turned it straight back into an import**, because
     * Pint's `fully_qualified_strict_types` fixer does exactly that. The
     * formatter settling an architecture argument is a better outcome than the
     * argument: a chokepoint you can only stay inside by dodging your own
     * formatter is not one. So every question about this table is asked here,
     * *including who may ask it*, and the screen holds no reference to the
     * model at all.
     *
     * ⚠️ **`create` RATHER THAN A SECOND ABILITY FOR THE AFFORDANCE.** One role
     * predicate answers add and remove both — see `ResponseTemplatePolicy` — so
     * a screen that offered Remove on `delete` and the form on `create` would be
     * two questions that agree until somebody changes one.
     */
    public function assertMayCurate(): void
    {
        Gate::authorize('create', ResponseTemplate::class);
    }

    /**
     * Whether this user may curate, for the screen's affordance.
     *
     * The sibling panels pair `Gate::authorize()` in the writer with
     * `Gate::allows()` in the view, so somebody who may not do this is told who
     * can rather than shown a control that 403s.
     */
    public function mayCurate(): bool
    {
        return Gate::allows('create', ResponseTemplate::class);
    }

    /**
     * The bodies `ReplyGenerator` puts in front of the model.
     *
     * ⚠️ **BODIES, NOT MODELS, AND THE NARROWING IS THE POINT.** The query this
     * replaced selected `['body', 'name']` and the block it fed used `body`
     * alone — so every tenant's template *names* crossed into the generator on
     * every draft for nothing. Returning strings means the generator cannot
     * start sending one by accident, and it is what lets the chokepoint hold a
     * single file.
     *
     * ⚠️ **`orderBy('id')` IS THE INHERITED ORDER AND IT IS KEPT ON PURPOSE.**
     * `latest('id')` would read better on a screen and would move the few-shot
     * block under a tenant every time they edited anything; a stable block is
     * what makes one draft comparable with the next.
     *
     * @return list<string>
     */
    public function examples(): array
    {
        // ⚠️ `array_values()` RATHER THAN A CAST. `pluck()->all()` returns an
        // `array<string>` that PHPStan will not accept as a `list<string>`, and
        // the honest repair is to make it one — the caller composes a numbered
        // block out of it, so a gap in the keys would be a real defect rather
        // than a type-checker complaint.
        return array_values(
            ResponseTemplate::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->limit(self::MAX_TEMPLATES)
                ->pluck('body')
                ->map(static fn (mixed $body): string => (string) $body)
                ->all(),
        );
    }

    /**
     * What the owner sees on their own screen.
     *
     * Oldest first, matching {@see examples()} — the list on screen is in the
     * order the model is shown them, so "these three" means the same thing in
     * both places.
     *
     * @return Collection<int, ResponseTemplate>
     */
    public function forOwner(): Collection
    {
        return ResponseTemplate::query()->orderBy('id')->get();
    }

    /**
     * Store one example reply.
     *
     * ⚠️ **EVERY REFUSAL IS AN EXCEPTION RATHER THAN A RETURN VALUE, AND THE
     * PANEL VALIDATES FIRST.** The blank and length rules are stated again in
     * the component's `validate()` call, so an owner gets a field-level message
     * rather than a 500; these are the backstop for a second caller that does
     * not validate, which is the entire job of a chokepoint.
     * `GrowthPageRefused`'s rule applies — reaching one of these from a screen
     * that validated is a programming error.
     *
     * @throws ResponseTemplateRefused
     */
    public function add(string $name, string $body, string $actor): ResponseTemplate
    {
        Tenancy::idOrFail();

        // ⚠️ **ASKED AGAIN HERE EVEN THOUGH THE PANEL ASKS FIRST**, and the
        // repetition is the chokepoint working rather than a redundancy. The
        // panel asks before it validates so that a `staff` user is told about
        // their role rather than about their punctuation; this one is what a
        // second caller — a command, a job, an import — gets whether or not it
        // remembered to.
        $this->assertMayCurate();

        $name = trim($name);
        $body = trim($body);

        if ($name === '' || $body === '') {
            throw ResponseTemplateRefused::blank();
        }

        if (mb_strlen($name) > $this->maxNameLength() || mb_strlen($body) > $this->maxBodyLength()) {
            throw ResponseTemplateRefused::tooLong();
        }

        if (ResponseTemplate::query()->count() >= self::MAX_TEMPLATES) {
            throw ResponseTemplateRefused::full();
        }

        if (! $this->guardrails->allows($body, $this->exemptions())) {
            throw ResponseTemplateRefused::guardrailed();
        }

        $template = new ResponseTemplate;

        // `forceFill` on a new instance rather than `create()`: the model
        // `$guarded`s `business_id`, which `BelongsToTenant` fills from the
        // tenant in context — and nothing here may ever name a tenant id.
        $template->forceFill([
            'name' => $name,
            'body' => $body,

            // ⚠️ **NULL, NOT A CHOICE.** The column is nullable and the creating
            // migration says why: a template without a voice inherits the
            // location's setting. A per-template voice picker would be a
            // tenant-facing toggle (`CLAUDE.md`) on top of a setting they
            // already have, and `ReplyGenerator` reads the *location's*
            // `brand_voice` and never the template's — so the control would not
            // do anything at all.
            'brand_voice' => null,

            // Every stored template is read. `is_active` is in the schema from
            // Stage 0 and keeps no switch — see {@see remove()}.
            'is_active' => true,
        ])->save();

        // ⚠️ **THE NAME AND THE LENGTH, NEVER THE BODY** (operating rule 2 —
        // less stored PII). `audit_log` is append-only and nothing in this
        // application prunes it, so a body copied here outlives the row it
        // describes and survives a deletion the owner asked for. The body is
        // free text an owner can paste a customer's name into; what an auditor
        // needs from this entry is that a person added an example and which one,
        // and the live row answers the rest for as long as it exists.
        $this->audit->record('response_template.added', $actor, $template, [
            'name' => $name,
            'body_length' => mb_strlen($body),
        ]);

        $this->recordSupportWrite(['name' => $name]);

        return $template;
    }

    /**
     * Remove one example reply.
     *
     * ⚠️ **DELETE RATHER THAN DEACTIVATE, AND `is_active` KEEPS NO SWITCH.**
     * The column exists from Stage 0 and this class writes it `true` once. A
     * visible on/off beside a delete is a tenant-facing toggle (`CLAUDE.md`)
     * whose only effect is the one the delete already has, and it doubles the
     * support surface of a three-row list — operating rule 1. What deletion
     * costs that deactivation would not is history, and `audit_log` holds that.
     *
     * ⚠️ **AND `KnowledgeSourcePolicy`'s ARGUMENT CARRIES OVER VERBATIM**:
     * removing a bad example is how an owner takes back something they should
     * not have written, and making that impossible leaves a support ticket as
     * the only remedy.
     *
     * ⛔ **THE POLICY IS ASKED HERE RATHER THAN AT THE SCREEN, AND IT IS ASKED
     * ABOUT THE ROW.** `Account\ReviewRules` records the rule: a policy answers
     * *"may this role do this"* and deliberately does not compare tenants,
     * which is safe **because a resolved model has already passed the global
     * scope and row-level security** — and a class name has not. The row exists
     * only after the lookup two lines below, so this is the one place that ask
     * can be made honestly. The screen holds the affordance and no more.
     *
     * @throws ResponseTemplateRefused
     */
    public function remove(int $id, string $actor): void
    {
        Tenancy::idOrFail();

        // ⚠️ **THE ID IS RESOLVED HERE RATHER THAN ARRIVING AS A MODEL.** A
        // component that bound one would hand over a row somebody else's
        // request had resolved; a `find()` under the global scope and FORCE
        // row-level security answers *"is this yours"* with the same query that
        // answers *"does it exist"*, and `GrowthPageRefused::missing()` records
        // why the two are deliberately not told apart.
        $template = ResponseTemplate::query()->find($id);

        if (! $template instanceof ResponseTemplate) {
            throw ResponseTemplateRefused::missing($id);
        }

        // ⚠️ **AFTER THE TENANT ANSWER AND BEFORE THE WRITE.** Asking the policy
        // first would mean asking it about a row that might be nobody's; asking
        // it after the delete would be asking whether something already done was
        // allowed. ⚠️ **And the ordering leaks nothing** — a foreign id and a
        // deleted id both raise `missing()` with the same words, so a `staff`
        // user probing ids learns their role is wrong and never whose row it was.
        Gate::authorize('delete', $template);

        $this->audit->record('response_template.removed', $actor, $template, [
            'name' => (string) $template->name,
            'body_length' => mb_strlen((string) $template->body),
        ]);

        $this->recordSupportWrite(['name' => (string) $template->name]);

        $template->delete();
    }

    /**
     * Add support's own audit row and owner-facing feed entry when this write
     * happened inside an act-as session — {@see Impersonation::recordWrite()}.
     *
     * ⚠️ **INERT FOR AN ORDINARY OWNER WRITE.** `current()` returns null the
     * moment nobody is impersonating. A view-only session cannot reach here at
     * all: the read-only connection {@see Impersonating}
     * engages refuses the `response_templates` write above before this line
     * runs, so a non-null session here is always act-as.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function recordSupportWrite(array $metadata): void
    {
        $session = $this->impersonation->current();

        if ($session === null) {
            return;
        }

        $this->impersonation->recordWrite($session, SupportWriteSubject::ReplyExamples, $metadata);
    }

    /**
     * The tenant's own configured names, which may not trip the guardrail.
     *
     * ⛔ **1735's LAW FIRM, AT A NEW DOOR.** `ReplyGuardrails` is a substring
     * match over sixteen compensation-and-legal phrases, so *Smith & Jones
     * Attorneys at Law* contains `attorney` and *Refund King Electronics*
     * contains `refund`. Without this, those two tenants could not save an
     * example reply that mentioned their own business — and they are exactly the
     * tenants a legal-language rule sounds most important for.
     *
     * ⚠️ **EVERY LOCATION, NOT THE ONE IN CONTEXT, AND THE ASYMMETRY IS
     * DELIBERATE.** `ReplyGenerator` exempts the business and the *one* location
     * whose review it is answering. A template is business-wide and is shown to
     * the model on a draft for any of them, so exempting fewer names here than
     * generation will exempt later would refuse a body that generation would
     * have allowed. **Wider at the write, never narrower** — the generator and
     * the writer chokepoint stay the tighter of the two, which is the only
     * direction 1741's drift may safely run in.
     *
     * ⚠️ **BUILT FROM `ReplyGuardrails::tenantValues()` RATHER THAN FROM
     * `->name`.** That helper exists so two call sites cannot disagree about
     * what an exemption *is*; hand-rolling the array here would be a third
     * definition of it.
     *
     * @return list<string>
     */
    private function exemptions(): array
    {
        $values = ReplyGuardrails::tenantValues(
            Business::query()->find(Tenancy::idOrFail()),
            null,
        );

        foreach (Location::query()->get() as $location) {
            foreach (ReplyGuardrails::tenantValues(null, $location) as $value) {
                $values[] = $value;
            }
        }

        return array_values(array_unique($values));
    }
}
