<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Exceptions\KnowledgeUploadRefused;
use App\Models\KnowledgeSource;
use App\Models\Location;
use App\Services\Knowledge\DocumentText;
use App\Services\Knowledge\KnowledgeUploads;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toaster;

/**
 * Where an owner gives us something to answer customers from.
 *
 * ⛔ **THIS WAS A WIZARD STEP FOR MOST OF THIS SLICE, AND MOVING IT OUT WAS A
 * FINDING RATHER THAN A PREFERENCE.** `WizardStep`'s own docblock says *"ADDING
 * A STEP LATER: add the case in its §7.2 position, add it to ordered(), add its
 * route, and add its label. **Nothing else reads position numbers.**"* — and
 * that last sentence is false. `2026_08_03_161908_change_wizard_progress_
 * current_step_to_string` carries a `CASE` mapping every integer to its step,
 * and a test iterates `WizardStep::cases()` asserting the migration knows each
 * one's position. Inserting a step before `Done` shifts `Done` from 5 to 6, and
 * the only way to make that test pass is to **edit an applied migration whose
 * `WHEN 5 THEN 'done'` is a record of what the integer 5 meant in rows written
 * before it ran**. A rollback and re-migrate would then move every finished
 * wizard to a step that did not exist when those rows were written. Not worth a
 * screen placement — see decision 2308.
 *
 * ⚠️ **AND `/account` IS ARGUABLY THE BETTER HOME ANYWAY**, on the reasoning
 * `Account\ReviewRules` already records: a control that exists only inside setup
 * is a choice a tenant makes once and can never revisit, because `SetupFlow`
 * will not re-enter a completed step. Documents are added over years.
 *
 * ⚠️ **NO MODEL CALL HAPPENS ON THIS SCREEN, AND THAT IS ENFORCED RATHER THAN
 * INTENDED.** `29` §2 forbids an LLM call on the synchronous path, and embedding
 * a document is one — so this component stores bytes and dispatches a job, and
 * `ArchitectureTest` fails the build on any mention of the model router under
 * `app/Livewire`. That lint matches the class name anywhere in the file,
 * comments included, so this paragraph deliberately does not spell it: an
 * earlier draft that did reddened the build, which is the lint working. The
 * visible consequence for an owner is that an upload appears as *waiting to be
 * read* rather than as finished, which the screen states plainly instead of
 * spinning.
 *
 * ⚠️ **PDFs ARE REFUSED AT THE PICKER AND AGAIN AT INGEST.** The `mimes` rule
 * here is convenience; `DocumentText` is the check that matters, because a
 * `.txt` containing a PDF passes every extension test there is. Saying it on
 * screen rather than only in the log is the difference between an owner
 * choosing another file and an owner filing a ticket.
 *
 * AUTHORIZATION IS A POLICY (`CLAUDE.md`). A `staff` user may see what the Brain
 * was taught and may not change it — an upload is the material an automated
 * agent answers customers from in the business's own name, which is the same
 * class of act as changing what the automation does.
 */
#[Layout('components.account.layout')]
final class Knowledge extends Component
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $file = null;

    /**
     * The sentence an owner reads when the upload endpoint refuses the file.
     *
     * ⛔ `file.max` BELOW SAYS THIS ALREADY AND CANNOT EVER SAY IT (9979).
     * `KnowledgeUploads::MAX_KILOBYTES` is 2,048, which is 2,097,152 bytes and
     * is EXACTLY this deployment's `upload_max_filesize = 2M` — so PHP, which
     * is upstream of every Laravel rule, refuses one byte before the rule can.
     * The rule is kept rather than deleted: raising the ini makes it live
     * again, and removing it would open a hole on the day somebody does.
     *
     * ⚠️ The figure is stated in prose here and as a constant in the rule.
     * `LivewireUploadValidationTest` re-reads the two against each other on
     * every run rather than leaving it to whoever next edits one.
     */
    public const string UPLOAD_REFUSED = 'We could not take that file. A document needs to be '
        .'under 2MB — split yours and upload each part.';

    /**
     * ⛔ WITHOUT THIS THE PERSON READS *"The file failed to upload."* (9980),
     * which names neither the cause nor anything they control. The full
     * argument for discarding the vendor's `$errorsInJson` — including why
     * discriminating on it would send the most size-certain case down the least
     * specific branch — is on `ImportCustomers::_uploadErrored()`.
     */
    public function _uploadErrored(string $name, ?string $errorsInJson, bool $isMultiple): void
    {
        $this->dispatch('upload:errored', name: $name)->self();

        throw ValidationException::withMessages([$name => self::UPLOAD_REFUSED]);
    }

    public function upload(KnowledgeUploads $uploads): void
    {
        // Authorization before validation: telling somebody their file is the
        // wrong type and then refusing them for their role is two errors for one
        // action, and the second is the one that mattered.
        Gate::authorize('create', KnowledgeSource::class);

        $this->validate([
            'file' => [
                'required',
                'file',
                'mimes:'.implode(',', DocumentText::ACCEPTED_EXTENSIONS),
                'max:'.KnowledgeUploads::MAX_KILOBYTES,
            ],
        ], [
            'file.required' => 'Choose a file to add.',
            'file.mimes' => 'That needs to be a plain text or Markdown file. '
                .'We cannot read PDFs or Word documents yet — copy the text into a '
                .'.txt file instead.',
            'file.max' => 'That file is larger than 2MB. Split it and upload each part.',
        ]);

        $upload = $this->file;

        if (! $upload instanceof TemporaryUploadedFile) {
            return;
        }

        try {
            $uploads->accept($upload, $this->locationId());
        } catch (KnowledgeUploadRefused $refusal) {
            // ⛔ **CAUGHT SO THE SUCCESS TOAST BELOW CANNOT RUN** (9406). The
            // service refuses *before* it writes a row, so at this point nothing
            // was added and nothing on the screen should say it was. Letting the
            // refusal escape would be a 500 on a signed-in owner's own screen,
            // which is the population `CLAUDE.md` records six entry points
            // already answering that way.
            //
            // ⚠️ **OUR SENTENCE, NOT THE EXCEPTION'S** — `SiteChanges`' pattern.
            // The refusal's message names a disk and a path for the log; `22`'s
            // rule is that every string names what the person controls, and
            // what they control here is trying again with the same file.
            //
            // ⚠️ **THE FILE IS DELIBERATELY NOT RESET.** Everything below this
            // block assumes the upload landed; leaving the picker holding their
            // document means "try again" is one click rather than one
            // re-selection, and it is the only thing on this screen that
            // distinguishes a refusal from a success.
            report($refusal);

            Toaster::error(
                'We could not store that just now — nothing was added. '
                .'Try again in a few minutes, or send us the file if it keeps happening.'
            );

            return;
        }

        $this->reset('file');

        // Outcome language (`22`), and nothing about queues or embeddings. No
        // document content and no filename in the toast — decision 104 keeps
        // personal data out of toast text, and a filename is customer data
        // often enough that it is not worth deciding case by case.
        Toaster::success('Added. We are reading it now.');
    }

    public function render(): View
    {
        // Refused rather than resolved when there is no tenant — `ImportCustomers`'
        // reasoning exactly: internal staff belong to no business by design, so a
        // signed-in support agent typing this URL is the ordinary way to arrive
        // with nothing resolved, and letting `Tenancy::idOrFail()` reach the
        // renderer is a 500 that reads as our page being broken.
        abort_if(Tenancy::id() === null, 403);

        return view('livewire.account.knowledge', [
            // Newest first: the thing an owner just uploaded is the thing they
            // are looking for.
            'sources' => KnowledgeSource::query()->latest('id')->get(),
            'mayUpload' => Gate::allows('create', KnowledgeSource::class),
        ]);
    }

    /**
     * The location this document belongs to, when there is exactly one.
     *
     * ⚠️ NULL RATHER THAN A GUESS ON A MULTI-LOCATION TENANT. `location_id` is
     * nullable on `knowledge_sources` and means "the whole business"; picking
     * the first of several would file a document under one branch and hide it
     * from the others, which is worse than the honest null.
     */
    private function locationId(): ?int
    {
        $locations = Location::query()->limit(2)->pluck('id');

        return $locations->count() === 1 ? (int) $locations->first() : null;
    }
}
