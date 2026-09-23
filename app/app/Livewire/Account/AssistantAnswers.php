<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Models\AssistantBrief;
use App\Services\Assistant\PriceBook;
use App\Services\Assistant\PriceSheet;
use App\Services\Assistant\UrgentTerms;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toaster;

/**
 * Teach your assistant — what it may quote, and what wakes you (T176 §2.4, P5).
 *
 * §2.4's step is bigger than one screen: the links half is
 * {@see AssistantLinks} (P6) and the toggles are P4's. **What lands here is what
 * the assistant may *say*** — the price list with its disclaimer line (skill 4)
 * and the urgent terms with the emergency line (skill 9).
 *
 * ⛔ **EVERY SECTION SAYS WHAT STAYS SWITCHED OFF WHILE IT IS EMPTY, AND THAT IS
 * R13 RATHER THAN COPY** — `Account\AssistantLinks` records the argument and
 * this screen keeps it: an owner who cannot see that an empty price list means
 * *the assistant takes the question and passes it to you* is an owner who
 * believes quotes are going out.
 *
 * ## ⛔ THE UPLOAD PROPOSES AND A PERSON CONFIRMS — THAT IS THE WHOLE PATH
 *
 * A price sheet is read **in the request**, deterministically, by
 * {@see PriceSheet}; every row it produces is written unconfirmed and is not
 * quotable. The proposals sit in their own block on this screen with a Confirm
 * on each and a Throw these away on the set, and nothing reaches
 * `PriceBook::list()` until somebody presses one. ⚠️ **No model call happens
 * here and none is queued** — see `PriceSheet`'s docblock for why a parser
 * rather than an extraction, and note that a model-backed reader would land in
 * exactly the same unconfirmed rows.
 *
 * ⚠️ **NOT A WIZARD STEP, ON DECISION 2308's FINDING**, exactly as
 * {@see AssistantLinks} is not: inserting a step before `Done` means editing an
 * applied migration whose integer→step `CASE` records what those integers meant
 * in rows already written. A business also changes its prices every year, which
 * a one-shot step cannot offer.
 *
 * ⚠️ **IT WRITES NOTHING ITSELF.** {@see PriceBook} and {@see UrgentTerms} are
 * the only readers and writers of the three tables, held there by a lint, and
 * this component validates an answer and calls them.
 *
 * AUTHORIZATION IS A POLICY (`CLAUDE.md`), and the subject is
 * {@see AssistantBrief} for all three tables — see the policy for why one
 * governs the set. A `staff` user may see what the business quotes and may not
 * move it: a price is a figure stated to a member of the public in the
 * business's name.
 */
#[Layout('components.account.layout')]
final class AssistantAnswers extends Component
{
    use WithFileUploads;

    public string $jobName = '';

    /**
     * The price, or the bottom of a range, in whole currency units as typed.
     *
     * ⚠️ **A STRING RATHER THAN A `?float`, AND FOR TWO REASONS** —
     * `Account\AssistantLinks` records both. Livewire hydrates a public property
     * to its declared type before validation runs, so a hand-posted `price=abc`
     * on a typed property is a TypeError rather than a message anybody can act
     * on; and `18` §Money handling forbids money as a float anywhere, so this is
     * parsed to integer cents by string arithmetic in {@see minorUnits()},
     * never by `(float) $price * 100`, which is `28` for `0.29` in IEEE-754.
     */
    public string $price = '';

    /**
     * The top of the range. Empty means this job has one price.
     */
    public string $priceMax = '';

    public string $disclaimer = '';

    public string $urgentTerm = '';

    public string $emergencyLine = '';

    public ?TemporaryUploadedFile $sheet = null;

    /**
     * The sentence an owner reads when the upload endpoint refuses the sheet.
     *
     * ⚠️ THIS SCREEN IS THE ONE WHOSE OWN `max` RULE STILL WORKS, AND THAT IS
     * WHY THE SENTENCE STILL HAS A JOB (9979). `app(PriceSheet::class)->maxKilobytes()` is
     * 256, well under this deployment's `upload_max_filesize = 2M`, so a sheet
     * between 256KB and 2MB is stored, reaches `uploadSheet()`, and gets
     * `sheet.max` — the good message, working as designed. Above 2MB the file
     * never arrives and the rule is never reached, which is the gap this fills.
     * The other two upload screens sit at 2,048KB and have no working arm at
     * all.
     */
    public const string UPLOAD_REFUSED = 'We could not take that file. A price sheet needs to be '
        .'under 256KB — split yours, or type the prices in above.';

    /**
     * ⛔ WITHOUT THIS THE PERSON READS *"The sheet failed to upload."* (9980).
     * The argument for discarding the vendor's `$errorsInJson` is on
     * `ImportCustomers::_uploadErrored()`.
     */
    public function _uploadErrored(string $name, ?string $errorsInJson, bool $isMultiple): void
    {
        $this->dispatch('upload:errored', name: $name)->self();

        throw ValidationException::withMessages([$name => self::UPLOAD_REFUSED]);
    }

    public function mount(PriceBook $prices, UrgentTerms $urgent): void
    {
        abort_if(Tenancy::id() === null, 403);

        // ⚠️ THE BOX IS PRE-FILLED ONLY WITH THE BUSINESS'S OWN WORDS. Filling
        // it with the platform default would make every owner who presses Save
        // without reading it a tenant with a frozen copy of today's sentence,
        // which is the exact thing reading the seed at read time avoids.
        $this->disclaimer = $prices->disclaimerIsTheirOwn() ? $prices->disclaimer() : '';

        $this->emergencyLine = $urgent->emergencyLine() ?? '';
    }

    public function savePrice(PriceBook $prices): void
    {
        // Authorization before validation, on `Account\AssistantLinks`'
        // reasoning: telling somebody their figure is malformed and then
        // refusing them for their role is two errors for one action, and the
        // second is the one that mattered.
        Gate::authorize('create', AssistantBrief::class);

        $this->validate([
            'jobName' => ['required', 'string', 'max:120'],

            // `regex` rather than `numeric`, because `numeric` accepts `1e3` and
            // `0x1A`, and both would reach the parser as an amount nobody typed.
            'price' => ['required', 'string', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/'],
            'priceMax' => ['nullable', 'string', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/'],
        ], [
            'jobName.required' => 'Name the job the way a customer would ask for it, like "Front door lockout".',
            'jobName.max' => 'Keep the name short enough to say in a text message.',
            'price.required' => 'Say what you charge. Put 0 if you do that one free.',
            'price.regex' => 'Write the price as a plain amount, like 85 or 85.50.',
            'priceMax.regex' => 'Write the top of the range as a plain amount, like 400. Leave it empty if this job has one price.',
        ]);

        $low = $this->minorUnits($this->price);
        $high = $this->minorUnits($this->priceMax);

        if ($low === null) {
            return;
        }

        try {
            $prices->set($this->jobName, $low, $high);
        } catch (InvalidArgumentException $refusal) {
            // The service's checks are not a duplicate of the rules above: those
            // are about what somebody typed, these are about what the store
            // cannot mean — a range that goes downwards, a name with no letters
            // in it. They have to hold for every caller, so they live there, and
            // this is how they reach the screen instead of becoming a 500.
            $this->addError($high === null ? 'price' : 'priceMax', $refusal->getMessage());

            return;
        }

        $this->reset('jobName', 'price', 'priceMax');

        Toaster::success('Saved — your assistant can quote that now');
    }

    public function removePrice(PriceBook $prices, string $slug): void
    {
        Gate::authorize('create', AssistantBrief::class);

        $prices->remove($slug);

        Toaster::success('Removed — your assistant will take a message about that instead');
    }

    public function saveDisclaimer(PriceBook $prices): void
    {
        Gate::authorize('create', AssistantBrief::class);

        $this->validate(
            ['disclaimer' => ['nullable', 'string', 'max:200']],
            ['disclaimer.max' => 'Keep this to one short line — it is said in a text message beside the price.'],
        );

        $prices->setDisclaimer($this->disclaimer);

        // Re-read rather than assume: an emptied box goes back to the platform's
        // wording, and the screen has to show which one is in force.
        $this->disclaimer = $prices->disclaimerIsTheirOwn() ? $prices->disclaimer() : '';

        Toaster::success('Saved — we will say that with every price');
    }

    public function uploadSheet(PriceBook $prices): void
    {
        Gate::authorize('create', AssistantBrief::class);

        $this->validate([
            'sheet' => ['required', 'file', 'mimes:txt,csv,md,markdown', 'max:'.app(PriceSheet::class)->maxKilobytes()],
        ], [
            'sheet.required' => 'Choose your price sheet.',
            'sheet.mimes' => 'That needs to be a plain text or CSV file. We cannot read PDFs or '
                .'spreadsheets yet — export it, or type the prices in above.',
            'sheet.max' => 'That file is too big to read here. Split it, or type the prices in above.',
        ]);

        $upload = $this->sheet;

        if (! $upload instanceof TemporaryUploadedFile) {
            return;
        }

        try {
            $read = $prices->proposeFrom((string) file_get_contents($upload->getRealPath()));
        } catch (InvalidArgumentException $refusal) {
            $this->addError('sheet', $refusal->getMessage());

            return;
        }

        $this->reset('sheet');

        // ⚠️ NO FILENAME AND NO CONTENT IN THE TOAST — decision 104 keeps
        // personal data out of toast text, and a price sheet's filename is
        // often a customer's or a supplier's name. Counts are not personal data.
        Toaster::success($this->readingSummary($read));
    }

    /**
     * What an upload actually did, in one sentence.
     *
     * ⛔ **ALL THREE NUMBERS, BECAUSE THE TWO IT WOULD BE EASIEST TO DROP ARE
     * THE ONES THAT MATTER.** "We read 2" on a forty-line sheet reads as a
     * complete list, and the thirty-eight jobs it does not mention are then the
     * ones the assistant takes a message about while the owner believes they are
     * priced. A count of what did not land is the difference between a tool and
     * a trap — the same argument `PriceSheetReading` makes for keeping it.
     *
     * ⚠️ **"ALREADY ON YOUR LIST" RATHER THAN "YOU ALREADY PRICE"**, because the
     * same count also covers a name that appeared twice inside one file, and the
     * second wording would be wrong for that case.
     *
     * @param  array{proposed: int, alreadyPriced: int, unreadable: int}  $read
     */
    private function readingSummary(array $read): string
    {
        $summary = $read['proposed'] === 0
            ? 'We found no new prices in that file'
            : 'We read '.$read['proposed'].' — check each one before we quote it';

        if ($read['alreadyPriced'] > 0) {
            $summary .= '. '.$read['alreadyPriced'].' were already on your list and are unchanged';
        }

        if ($read['unreadable'] > 0) {
            $summary .= '. '.$read['unreadable'].' lines we could not read — add those yourself';
        }

        return $summary;
    }

    public function confirmProposal(PriceBook $prices, string $slug): void
    {
        Gate::authorize('create', AssistantBrief::class);

        $prices->confirm($slug);

        Toaster::success('Confirmed — your assistant can quote that now');
    }

    public function discardProposals(PriceBook $prices): void
    {
        Gate::authorize('create', AssistantBrief::class);

        $prices->discardProposals();

        Toaster::success('Thrown away — nothing from that file will be quoted');
    }

    public function addUrgentTerm(UrgentTerms $urgent): void
    {
        Gate::authorize('create', AssistantBrief::class);

        $this->validate(
            ['urgentTerm' => ['required', 'string', 'max:60']],
            [
                'urgentTerm.required' => 'Type the word a customer would use, like "lockout".',
                'urgentTerm.max' => 'That is a sentence rather than a word. Use the word itself.',
            ],
        );

        try {
            $urgent->add($this->urgentTerm);
        } catch (InvalidArgumentException $refusal) {
            $this->addError('urgentTerm', $refusal->getMessage());

            return;
        }

        $this->reset('urgentTerm');

        Toaster::success('Added — we will come straight to you when somebody says it');
    }

    public function removeUrgentTerm(UrgentTerms $urgent, string $term): void
    {
        Gate::authorize('create', AssistantBrief::class);

        $urgent->remove($term);

        Toaster::success('Removed — that one will be handled like any other message');
    }

    public function saveEmergencyLine(UrgentTerms $urgent): void
    {
        Gate::authorize('create', AssistantBrief::class);

        $this->validate(
            ['emergencyLine' => ['nullable', 'string', 'max:30']],
            ['emergencyLine.max' => 'That is longer than a phone number.'],
        );

        try {
            $this->emergencyLine = $urgent->setEmergencyLine($this->emergencyLine) ?? '';
        } catch (InvalidArgumentException $refusal) {
            $this->addError('emergencyLine', $refusal->getMessage());

            return;
        }

        Toaster::success($this->emergencyLine === ''
            ? 'Removed — we will come straight to you instead of giving out a number'
            : 'Saved — we will give that number out when it is urgent');
    }

    public function render(PriceBook $prices, UrgentTerms $urgent): View
    {
        // Refused rather than resolved when there is no tenant, on
        // `Account\AssistantLinks`' reasoning: internal staff belong to no
        // business by design, so a signed-in support agent typing this URL is
        // the ordinary way to arrive with nothing resolved, and letting
        // `Tenancy::idOrFail()` reach the renderer is a 500 that reads as our
        // page being broken.
        abort_if(Tenancy::id() === null, 403);

        return view('livewire.account.assistant-answers', [
            'list' => $prices->list(),
            'proposals' => $prices->awaitingReview(),
            'disclaimerInForce' => $prices->disclaimer(),
            'disclaimerIsTheirOwn' => $prices->disclaimerIsTheirOwn(),
            'terms' => $urgent->terms(),
            'emergency' => $urgent->emergencyLine(),
            'mayEdit' => Gate::allows('create', AssistantBrief::class),
        ]);
    }

    /**
     * A typed amount as integer minor units, or null when the field is empty.
     *
     * ⛔ **STRING ARITHMETIC, NEVER `(int) ($price * 100)`.** `(int) (75.35 *
     * 100)` is `7534` in IEEE-754 — a price a penny short, every time, silently.
     * 3994 records the mutation that found this on the fee field next door, and
     * the figure in the test was chosen by running it.
     */
    private function minorUnits(string $typed): ?int
    {
        $trimmed = trim($typed);

        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^(\d{1,7})(?:\.(\d{1,2}))?$/', $trimmed, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1] * 100 + (int) str_pad($matches[2] ?? '', 2, '0');
    }
}
