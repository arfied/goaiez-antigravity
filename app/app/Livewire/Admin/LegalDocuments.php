<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\LegalDocumentType;
use App\Enums\StepState;
use App\Models\LegalDocument;
use App\Models\User;
use App\Services\Legal\LegalDocuments as Documents;
use App\Support\Admin\AdminAccess;
use App\Support\LineDiff;
use App\Support\NextLegalVersion;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

/**
 * Where the legal documents are drafted, reviewed and published.
 *
 * The owner's instruction was that these be "easily stored in admin and editable
 * to update anytime". This is that screen, with one departure written into the
 * service it calls: **a published version is never edited.** Editing published
 * text does not update a document, it silently changes what every stored consent
 * record claims somebody agreed to (decision 330). So the button on a published
 * document says *New version*, and it starts a draft seeded from the current
 * text.
 *
 * ⚠️ **IT WAS "DELIBERATELY SMALL … SO `LegalDocuments` HAS A CALLER" AND THE
 * OWNER IS NOW THE CALLER** (decisions 5387–5399). That sentence was honest when
 * it was written and it stopped being the right design the day somebody had to
 * change a clause: the version box was blank, the reviewer box was blank, four
 * thousand words scrolled past in an eighteen-row monospace box with nothing
 * saying what had changed, and three buttons in three places read as three
 * unrelated acts. What is added is four things and **no new rule** — a suggested
 * version, a suggested reviewer, a diff against the published text, and the
 * three acts shown as the one sequence they are.
 *
 * ⛔ **NEITHER SUGGESTION IS A RECORD.** The reviewer's name is the record that
 * a review happened (`39`'s checklist step 3), so pre-filling the box changes
 * nothing about what is stored until somebody presses *Record review* — and
 * `LegalDocumentAdminTest` pins exactly that, because a pre-fill that quietly
 * became a record would turn the one piece of evidence this table holds into a
 * default.
 *
 * NOT IN AdminNav yet: the nav is a static list and this screen takes a document
 * type, the same reason `LocationSettings` and `ReviewQueue` are absent. The
 * index at `admin.legal-index` is what links here.
 */
final class LegalDocuments extends Component
{
    /**
     * How many changed lines the diff prints.
     *
     * ⚠️ A DIFF OF TWO UNRELATED FOUR-THOUSAND-WORD DOCUMENTS IS EVERY LINE OF
     * BOTH, and rendering that is a slow screen nobody reads to the bottom of.
     * The cap is stated in the markup rather than hidden — a reader who is told
     * "and 412 more changed lines" knows the diff is partial, which a silently
     * truncated one does not.
     */
    private const int DIFF_ROWS_SHOWN = 300;

    public string $docType = '';

    public string $body = '';

    public bool $isPlaceholder = true;

    public string $newVersion = '';

    public string $reviewer = '';

    public function mount(string $doc, Documents $documents): void
    {
        $this->authorize(AdminAccess::GATE);

        $type = LegalDocumentType::tryFrom($doc);

        if ($type === null) {
            abort(404);
        }

        $this->docType = $type->value;

        $draft = $documents->draft($type);

        if ($draft !== null) {
            $this->body = $draft->body;
            $this->isPlaceholder = $draft->is_placeholder;
        }

        $this->suggest($documents);
    }

    public function startDraft(Documents $documents): void
    {
        $this->act(function () use ($documents): string {
            $draft = $documents->startDraft($this->type(), trim($this->newVersion));

            $this->body = $draft->body;
            $this->isPlaceholder = $draft->is_placeholder;
            $this->suggest($documents);

            return "Version {$draft->version} started";
        });
    }

    public function saveDraft(Documents $documents): void
    {
        $this->act(function () use ($documents): string {
            $documents->saveDraft($this->requireDraft($documents), $this->body, $this->isPlaceholder);

            return 'Draft saved';
        });
    }

    public function recordReview(Documents $documents): void
    {
        $this->act(function () use ($documents): string {
            $documents->recordReview($this->requireDraft($documents), $this->reviewer);
            $this->suggest($documents);

            return 'Review recorded';
        });
    }

    public function publish(Documents $documents): void
    {
        $this->act(function () use ($documents): string {
            $published = $documents->publish($this->requireDraft($documents), $this->actor());

            $this->suggest($documents);

            return "Version {$published->version} published";
        });
    }

    public function render(Documents $documents): View
    {
        $draft = $documents->draft($this->type());
        $current = $documents->current($this->type());

        $diff = $draft === null || $current === null
            ? []
            : LineDiff::between($current->body, $this->body);

        $changes = LineDiff::changes($diff);

        return view('livewire.admin.legal-documents', [
            'type' => $this->type(),
            'draft' => $draft,
            'current' => $current,
            'history' => $this->history($documents),
            'steps' => $draft === null ? [] : $this->flowSteps($draft),
            'diff' => array_slice($changes, 0, self::DIFF_ROWS_SHOWN),
            'diffTotals' => LineDiff::totals($diff),
            'diffHidden' => max(0, count($changes) - self::DIFF_ROWS_SHOWN),
        ]);
    }

    /**
     * The three acts of publishing a version, with where the reader stands in
     * them.
     *
     * ⚠️ **IT DESCRIBES, IT DOES NOT GATE.** Every refusal still lives in
     * `LegalDocuments` and the buttons this screen draws are unchanged: the
     * publish control appears only once a review is recorded, because the screen
     * never offers an action the service will refuse. What this adds is the
     * answer to "why is there no Publish button", which the old screen left
     * somebody to infer from its absence.
     *
     * ⚠️ **STEP ONE READS THE BOX AND NOT THE ROW, AND THAT IS THE USEFUL
     * HALF.** `wire:model` is deferred, so `$this->body` is what the person has
     * typed and `$draft->body` is what is stored; comparing them is the only way
     * this screen can say "the text you are looking at is not the text a
     * reviewer would be approving". Recording a review against unsaved text is
     * the mistake this flow exists to make visible.
     *
     * @return list<array{number:int,title:string,detail:string,state:StepState}>
     */
    private function flowSteps(LegalDocument $draft): array
    {
        $saved = $this->body === $draft->body && $this->isPlaceholder === $draft->is_placeholder;
        $reviewed = $draft->reviewed_at !== null;

        return [
            [
                'number' => 1,
                'title' => 'Save the text',
                'detail' => $saved
                    ? "Version {$draft->version} is saved as a draft. Nothing is public until it is published."
                    : 'The box holds changes that are not saved yet. Save them before anybody reviews this version.',
                'state' => $saved ? StepState::Done : StepState::Now,
            ],
            [
                'number' => 2,
                'title' => 'Record the review',
                'detail' => $reviewed
                    ? $draft->reviewed_by.' reviewed this version on '.$draft->reviewed_at->format('j F Y').'.'
                    : 'Name the person who read this version. The name is the record that the review happened.',
                'state' => match (true) {
                    $reviewed => StepState::Done,
                    $saved => StepState::Now,
                    default => StepState::Next,
                },
            ],
            [
                'number' => 3,
                'title' => "Publish version {$draft->version}",
                'detail' => $reviewed
                    ? 'Publishing freezes this text permanently. Later changes are published as a new version.'
                    : 'Available once the review above is recorded.',
                'state' => $reviewed && $saved ? StepState::Now : StepState::Next,
            ],
        ];
    }

    /**
     * Fill the two boxes a person would otherwise have to invent an answer for.
     *
     * ⚠️ **RE-READ FROM STORAGE RATHER THAN STEPPED IN MEMORY**, for
     * `requireDraft()`'s reason: another tab may have published a version since
     * this component was mounted, and a suggestion derived from a stale property
     * is the one the service refuses.
     *
     * ⚠️ **NOT CALLED FROM `saveDraft()`, AND THAT ABSENCE IS DELIBERATE.**
     * Saving is the one act somebody performs *while* the reviewer box is on
     * screen with a half-typed name in it, and refreshing the suggestion there
     * would wipe what they had typed.
     */
    private function suggest(Documents $documents): void
    {
        $type = $this->type();

        $this->newVersion = NextLegalVersion::after(
            $documents->current($type)?->version,
            array_values($documents->history($type)
                ->map(fn (LegalDocument $document): string => $document->version)
                ->all()),
        );

        $this->reviewer = $this->defaultReviewer();
    }

    /**
     * The signed-in admin's name, as the reviewer this screen offers.
     *
     * ⚠️ **EDITABLE, BECAUSE THE REVIEWER IS FREQUENTLY NOT THE PUBLISHER.**
     * `reviewed_by` is a string rather than a foreign key precisely because
     * counsel usually has no account here (the migration says so), so the
     * suggestion is the common case and never the only one.
     *
     * Empty rather than a placeholder when there is no user: a screen that
     * cannot name the reader must not put a word in the box that would be stored
     * as a reviewer.
     */
    private function defaultReviewer(): string
    {
        $user = auth()->user();

        return $user instanceof User ? trim($user->name) : '';
    }

    /**
     * @return Collection<int, LegalDocument>
     */
    private function history(Documents $documents): Collection
    {
        return $documents->history($this->type());
    }

    private function type(): LegalDocumentType
    {
        return LegalDocumentType::from($this->docType);
    }

    /**
     * The open draft, or a refusal that names why there isn't one.
     *
     * ⚠️ Re-read from the service on every action rather than held in a property.
     * A Livewire property survives between requests and is client-visible; a
     * draft that was published in another tab would still look editable here,
     * and the write would then be refused by the trigger with a SQLSTATE instead
     * of by the service with a sentence.
     */
    private function requireDraft(Documents $documents): LegalDocument
    {
        return $documents->draft($this->type()) ?? throw new InvalidArgumentException(
            'There is no open draft. Start a new version first.'
        );
    }

    /**
     * Run one action, and show the service's own refusal if it declines.
     *
     * ⚠️ The service's messages explain a rule rather than report a failure — a
     * generic "something went wrong" here would turn "published text is frozen"
     * into a mystery, which is `ReviewQueue`'s stated reasoning applied to the
     * rule that is harder to guess at.
     *
     * @param  callable(): string  $action
     */
    private function act(callable $action): void
    {
        $this->authorize(AdminAccess::GATE);

        try {
            $outcome = $action();
        } catch (InvalidArgumentException $e) {
            Toaster::error($e->getMessage());

            return;
        }

        // Outcome language, verb surviving the flow (`22`): Publish → Published.
        Toaster::success($outcome);
    }

    /**
     * Who performed the act, for `published_by`.
     *
     * A label rather than a user id, matching `ReviewQueue::actor()` and the
     * `reviewed_by`/`published_by` columns — this row is standing in for an
     * audit entry that `AuditService` cannot write, because that service is
     * tenant-scoped and publishing the platform's terms belongs to no tenant.
     */
    private function actor(): string
    {
        $id = auth()->id();

        return $id === null ? 'admin' : 'user:'.$id;
    }
}
