<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Services\Consent\ContactFile;
use App\Services\Consent\CustomerImports;
use App\Services\Consent\ImportAttestation;
use App\Services\Consent\ImportStatement;
use App\Services\Consent\ImportStatementUnavailable;
use App\Support\HashedIp;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toaster;

/**
 * The owner's way to bring their existing customers in — `29` §11.2 row 5's
 * **Import**, and the caller `CustomerImports` never had (decision 840).
 *
 * ⚠️ **THE SERVICE WAS THE CAREFUL HALF AND IT WAS ALREADY BUILT.** This screen
 * changes none of its rules: it does not touch `Customer` or `ConsentRecord`, it
 * does not decide a `ConsentType`, and it cannot skip an attestation because
 * `import()` takes one by type. What it adds is a door — which is the entire
 * defect, since a control nobody can reach is decision 272's shape and this is
 * that shape's fourteenth instance.
 *
 * ## Why it can refuse to render its own form
 *
 * `ImportAttestation` requires *"the published wording the tenant saw, exactly
 * as published"* and, until this slice, nothing published any such wording.
 * Rather than invent it — decision 502's rejected move, on something far more
 * expensive than a price — `ImportStatement` resolves it from a published
 * `LegalDocument` and this screen shows a *waiting on published wording* panel
 * when there is none, the way the Ops registry screen lists a withheld figure
 * instead of defaulting it.
 *
 * ⚠️ **DECISIONS 549 AND 475 ARE UNTOUCHED BY ANY OF THIS.** The owner's EBR
 * ruling is his, the wording is counsel's, and `29` §12.2's counsel-review gate
 * remains a human gate this code cannot verify — all a build can check is that a
 * recorded reviewer published a non-placeholder version. This screen refuses
 * until that exists; it never asserts that it is right.
 *
 * ## The checkbox
 *
 * Unchecked by default with the full statement beside it, which is `CLAUDE.md`'s
 * consent rule applied to the one attestation a tenant makes rather than a
 * customer. The version is read from the published document at submit time, so
 * the record names words that exist rather than a string somebody typed.
 */
#[Layout('components.account.layout')]
final class ImportCustomers extends Component
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $file = null;

    /**
     * ⚠️ FALSE, ALWAYS, AND NEVER PRE-TICKED. `CLAUDE.md`: every consent
     * checkbox is unchecked by default with the full disclosure. This is a
     * tenant attesting rather than a customer consenting, but the reason is the
     * same one and it matters more here — decision 549's position is only as
     * good as the evidence that somebody read this and chose to affirm it.
     */
    public bool $attested = false;

    /**
     * The sentence an owner reads when the upload endpoint refuses the file.
     *
     * ⛔ `file.max` BELOW SAYS THIS ALREADY AND CANNOT EVER SAY IT (9979). That
     * rule is evaluated inside `import()`, which runs only after Livewire's
     * upload endpoint has stored the file — and this deployment's PHP refuses
     * at `upload_max_filesize = 2M`, which is 2,097,152 bytes and is EXACTLY
     * `max:2048`. So the rule and the ini threshold are the same number, the
     * ini is upstream, and the message it guards is unreachable by one byte.
     * The rule stays because raising the ini makes it live again; this is what
     * covers the gap while it does not.
     */
    public const string UPLOAD_REFUSED = 'We could not take that file. A customer list needs to be '
        .'under 2MB — split yours and import each part.';

    /**
     * ⛔ WITHOUT THIS THE PERSON READS *"The file failed to upload."* (9980) —
     * Livewire's own fallback, which names neither the cause nor anything the
     * owner controls, and `22`'s rule is outcome language only. Measured after
     * the `bootstrap/app.php` fix: the endpoint answers
     * `{"errors":{"files.0":["The files.0 failed to upload."]}}` and the trait
     * rewrites `files.0` to `file`, so that fallback is what would ship.
     *
     * ⚠️ THE VENDOR'S `$errorsInJson` IS DELIBERATELY DISCARDED AND THAT IS A
     * TRADE, NOT AN OVERSIGHT. Every arm that reaches here on this deployment
     * is a size arm: the endpoint's rules are `required|file|max:12288`, its
     * `max` is unreachable (any file large enough to trip 12MB is past
     * `post_max_size` and answered `413` before the validator runs), and a
     * `413` arrives with `$errorsInJson` NULL — so a discriminator on that
     * argument would send the MOST size-certain case down the least specific
     * branch. A session expiry, a `429` from the endpoint's `throttle:60,1` and
     * a dropped connection also land here and this sentence does not claim
     * their cause; it states the limit, which stays true whatever brought them.
     *
     * ⚠️ Strictly safer than the vendor's, which interpolates the endpoint's
     * own JSON into the message; nothing caller-supplied reaches the string.
     */
    public function _uploadErrored(string $name, ?string $errorsInJson, bool $isMultiple): void
    {
        // Livewire's `markUploadErrored()` is bound to this event and is what
        // clears the upload out of the client's bag. Dropping it is the stall
        // this slice exists to fix, wearing a different hat.
        $this->dispatch('upload:errored', name: $name)->self();

        throw ValidationException::withMessages([$name => self::UPLOAD_REFUSED]);
    }

    public function import(CustomerImports $imports, ImportStatement $statement): void
    {
        // Refused before the file is even validated when there is no wording:
        // telling somebody their spreadsheet is fine and then refusing them is
        // the shape `CustomerImports::import()`'s own guard comment rejects.
        try {
            $version = $statement->requireCurrent();
        } catch (ImportStatementUnavailable $e) {
            $this->addError('file', $e->getMessage());

            return;
        }

        $this->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            // `accepted` rather than `boolean`: an unticked box must fail
            // validation, not import a list with `attested = false` recorded
            // nowhere.
            'attested' => ['accepted'],
        ], [
            'attested.accepted' => 'Confirm these are your own customers before importing them.',
            'file.mimes' => 'That needs to be a CSV file — most spreadsheets can save one.',
            'file.max' => 'That file is larger than 2MB. Split it and import each part.',
        ]);

        $upload = $this->file;

        if ($upload === null) {
            return;
        }

        try {
            $contacts = ContactFile::parse((string) file_get_contents($upload->getRealPath()));

            $import = $imports->import(
                new ImportAttestation(
                    statementVersion: $version->version,
                    attestedBy: $this->actor(),
                    proof: $this->proof(),
                ),
                $contacts,
                source: $upload->getClientOriginalName(),
            );
        } catch (InvalidArgumentException|ImportStatementUnavailable $e) {
            // ⚠️ SHOWN RATHER THAN SWALLOWED. Every message these two throw is
            // written for the person who has to act on it — an empty file, no
            // recognisable column, nobody reachable, wording not published — and
            // a generic "something went wrong" would send each of them to
            // support. The service refuses inside a transaction, so nothing is
            // half-imported when one of these lands.
            $this->addError('file', $e->getMessage());

            return;
        }

        $this->reset('file', 'attested');

        // Outcome language, and no customer names or counts of anything
        // personal in the toast (decision 104).
        Toaster::success(trans_choice(
            '{1} 1 customer imported|[2,*] :count customers imported',
            $import->row_count,
            ['count' => number_format($import->row_count)],
        ));
    }

    public function render(ImportStatement $statement): View
    {
        // Refused rather than resolved when there is no tenant — `AccountSettings`'
        // reasoning exactly: internal staff belong to no business by design, so a
        // signed-in support agent typing this URL is the ordinary way to arrive
        // with nothing resolved, and letting `Tenancy::idOrFail()` reach the
        // renderer is a 500 that reads as our page being broken.
        abort_if(Tenancy::id() === null, 403);

        return view('livewire.account.import-customers', [
            'statement' => $statement->current(),
        ]);
    }

    /**
     * Who attested, in the vocabulary every other service here takes.
     */
    private function actor(): string
    {
        $id = auth()->id();

        return $id === null ? 'owner' : 'user:'.$id;
    }

    /**
     * @return array<string, mixed>
     */
    private function proof(): array
    {
        $request = request();

        // ⚠️ NEVER `$request->ip()`. `29` §2 forbids storing a raw IP, and
        // `ImportAttestation`'s constructor throws on an `ip` or `ip_address`
        // key precisely because reaching for it is the likely mistake and the
        // result would look correct in the column forever.
        //
        // The URL is here so `proof` is never empty: `HashedIp::of()` returns
        // null when there is no usable address and a user agent can be absent,
        // and an attestation with an empty proof array is refused outright —
        // which would turn a missing header into a failed import.
        return [
            'ip_hash' => HashedIp::of($request),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500) ?: null,
            'url' => $request->fullUrl(),
        ];
    }
}
