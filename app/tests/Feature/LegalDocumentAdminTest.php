<?php

declare(strict_types=1);

use App\Enums\LegalDocumentType;
use App\Livewire\Admin\LegalDocuments as LegalDocumentsScreen;
use App\Models\LegalDocument;
use App\Models\User;
use App\Services\Legal\LegalDocuments;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| The admin screen — the owner's "editable anytime", with the one departure
|--------------------------------------------------------------------------
|
| The instruction was that the documents be stored in admin and editable at
| any time. Drafts are. Published text is not, and this file pins the
| difference at the surface an admin actually touches — because a rule that
| only exists in a service is a rule the next screen will not know about.
|
| The screen exists at all so `LegalDocuments` has a caller: a service written,
| documented and never invoked is the most repeated defect in this codebase
| (272, 377, 399), and it has been found five times.
|
*/

beforeEach(function (): void {
    Gate::define(AdminAccess::GATE, fn (): bool => true);
    $this->admin = User::factory()->create();
});

test('an admin drafts, has it reviewed, and publishes it', function (): void {
    Livewire::actingAs($this->admin)
        ->test(LegalDocumentsScreen::class, ['doc' => 'terms'])
        ->set('newVersion', '1.0')
        ->call('startDraft')
        ->set('body', 'The agreed clauses.')
        ->set('isPlaceholder', false)
        ->call('saveDraft')
        ->set('reviewer', 'A. Counsel')
        ->call('recordReview')
        ->call('publish')
        ->assertHasNoErrors();

    $published = LegalDocument::query()->firstOrFail();

    expect($published->body)->toBe('The agreed clauses.')
        ->and($published->isPublished())->toBeTrue()
        ->and($published->reviewed_by)->toBe('A. Counsel')
        ->and($published->published_by)->toBe('user:'.$this->admin->id);
});

test('the screen never offers to edit a published version', function (): void {
    // ⚠️ A button that exists and then explains why it cannot work is how
    // somebody concludes the rule is a bug. The published state renders the
    // "start a new version" control instead — no textarea, no Save.
    LegalDocument::factory()->published()->create(['version' => '1.0']);

    Livewire::actingAs($this->admin)
        ->test(LegalDocumentsScreen::class, ['doc' => 'terms'])
        ->assertSee('Start a new version')
        ->assertDontSee('Save draft');
});

test('the screen surfaces the service\'s refusal rather than swallowing it', function (): void {
    // The service's messages explain a rule; a generic failure notice would
    // turn "published text is frozen" into a mystery. ReviewQueue's reasoning,
    // applied to the rule that is harder to guess at.
    LegalDocument::factory()->published()->create(['version' => '1.0']);

    Livewire::actingAs($this->admin)
        ->test(LegalDocumentsScreen::class, ['doc' => 'terms'])
        ->set('newVersion', '1.0')
        ->call('startDraft')
        ->assertHasNoErrors();

    // Refused, and nothing was written.
    expect(LegalDocument::query()->count())->toBe(1);
});

test('publishing is refused until a reviewer is named', function (): void {
    Livewire::actingAs($this->admin)
        ->test(LegalDocumentsScreen::class, ['doc' => 'terms'])
        ->set('newVersion', '1.0')
        ->call('startDraft')
        ->call('publish')
        ->assertHasNoErrors();

    expect(LegalDocument::query()->firstOrFail()->isPublished())->toBeFalse();
});

test('an unknown document type 404s rather than reaching storage', function (): void {
    Livewire::actingAs($this->admin)
        ->test(LegalDocumentsScreen::class, ['doc' => 'not-a-document'])
        ->assertNotFound();
});

test('the screen is behind the admin gate', function (): void {
    Gate::define(AdminAccess::GATE, fn (): bool => false);

    Livewire::actingAs($this->admin)
        ->test(LegalDocumentsScreen::class, ['doc' => 'terms'])
        ->assertForbidden();
});

test('every document type the enum names is reachable', function (LegalDocumentType $type): void {
    // Guards the screen against a fourteenth case being added to the enum with
    // no way to draft it — which would look exactly like the document simply
    // not existing.
    Livewire::actingAs($this->admin)
        ->test(LegalDocumentsScreen::class, ['doc' => $type->value])
        ->assertOk()
        ->assertSee($type->title());
})->with(fn (): array => array_map(
    fn (LegalDocumentType $type): array => [$type],
    LegalDocumentType::cases(),
));

test('a draft the admin has to edit is actually rendered in the box they edit it in', function (): void {
    // ⛔ FOUND BY THE OWNER USING THE SCREEN, NOT BY THIS FILE. The textarea was
    // written as `<textarea wire:model="body"></textarea>` — correct for the two
    // reason boxes on the internal-users screen, which start empty, and wrong
    // here, because a textarea's value IS its content. The component's $body
    // held the whole document and the box rendered blank.
    //
    // ⚠️ EVERY TEST ABOVE PASSED THROUGHOUT. They all `set('body', …)` first,
    // which writes the property directly and never asks what the browser was
    // shown — 411's shape, an assertion passing for a reason that has nothing to
    // do with the claim. `assertSee` is the whole difference.
    //
    // ⚠️ AND THE FAILURE WAS NOT COSMETIC. `wire:model` sends what the control
    // holds, so typing a paragraph into an apparently-empty box replaces the
    // document with that paragraph — and `legal:seed` refuses to restore a
    // document that already has a version (721), so the original text is gone
    // from the application entirely.
    $documents = app(LegalDocuments::class);
    $draft = $documents->startDraft(LegalDocumentType::Terms, '1.0');
    $documents->saveDraft($draft, 'The clauses as counsel wrote them.', false);

    Livewire::actingAs($this->admin)
        ->test(LegalDocumentsScreen::class, ['doc' => 'terms'])
        ->assertSee('The clauses as counsel wrote them.');
});

test('every version in the history says whether counsel approved it, and when', function (): void {
    // ⚠️ CC-4 §3 ASKED FOR A NULLABLE `counsel_approved_at` COLUMN AND THE
    // COLUMN IS REFUSED. `reviewed_at` + `reviewed_by` already are that record:
    // a CHECK constraint keeps the pair consistent, `LegalDocuments::publish()`
    // refuses a version without them, and the screen already showed them for the
    // open draft. A second timestamp for one act would be a second source of
    // truth on a row this schema's first trigger makes unrepairable.
    //
    // What was genuinely missing is the *history*: every published version
    // showed who published it and never who approved it, so "which versions has
    // counsel signed off" had no answer on any screen. Decision 5168.
    //
    // ⚠️ **MUTATION**: delete the `reviewed_at` block from
    // `legal-documents.blade.php`'s "Every version" list.
    $documents = app(LegalDocuments::class);

    $draft = $documents->startDraft(LegalDocumentType::Cookies, '1.0');
    $documents->saveDraft($draft, 'The clauses as counsel wrote them.', isPlaceholder: false);
    $documents->recordReview($draft->refresh(), 'A. Counsel');
    $documents->publish($draft->refresh(), 'admin:1');

    Livewire::actingAs($this->admin)
        ->test(LegalDocumentsScreen::class, ['doc' => 'cookies'])
        ->assertSee('Counsel approved')
        ->assertSee('A. Counsel');
});

test('a seeded draft says plainly that counsel has not approved it yet', function (): void {
    // ⚠️ THE OTHER HALF, AND THE ONE THAT MATTERS ON EVERY FRESH INSTALL. R53
    // ships every text live and stamped DRAFT-FOR-COUNSEL, so unreviewed is the
    // ordinary state here — and a screen that printed nothing for it would read
    // as a screen that forgot to say, which is how a placeholder gets published.
    //
    // ⚠️ **MUTATION**: delete the `@else` arm of the same block.
    $this->artisan('legal:seed')->assertSuccessful();

    Livewire::actingAs($this->admin)
        ->test(LegalDocumentsScreen::class, ['doc' => 'terms'])
        ->assertSee('Draft for counsel — not yet approved');
});

/*
|--------------------------------------------------------------------------
| The editor the owner actually has to use (5387–5399)
|--------------------------------------------------------------------------
|
| The screen's own docblock used to say it was "deliberately small … it exists
| so `LegalDocuments` has a caller", which was true and stopped being the right
| design the day somebody had to change a clause with it. Changing one sentence
| took ten steps, two of which were inventing a version string and retyping
| one's own name, and none of which said what was different from the text that
| is live.
|
| ⛔ NOTHING BELOW RELAXES A RULE. Published text is still frozen (decision
| 330), review is still a separate act from publication, and the screen still
| never offers an action the service will refuse. Every test here is about what
| the screen *says* before somebody presses something.
|
*/

test('the version box arrives filled in with the next version', function (): void {
    // ⚠️ **MUTATION**: return '' from NextLegalVersion::increment().
    LegalDocument::factory()->published()->create(['version' => '1.0']);

    Livewire::actingAs($this->admin)
        ->test(LegalDocumentsScreen::class, ['doc' => 'terms'])
        ->assertSet('newVersion', '1.1');
});

test('the first version of a document is suggested as 1.0', function (): void {
    Livewire::actingAs($this->admin)
        ->test(LegalDocumentsScreen::class, ['doc' => 'terms'])
        ->assertSet('newVersion', '1.0');
});

test('a suggested version the service would refuse as taken is never offered', function (): void {
    // ⚠️ THE ORDER OF THESE TWO ROWS IS THE WHOLE FIXTURE. `current()` is the
    // newest published row by id, so the *later* row is 1.0 and the obvious next
    // version is 1.1 — which the earlier row already took. The screen's standing
    // rule is that it never offers an action the service will refuse.
    //
    // ⚠️ **MUTATION**: drop the `$taken` argument at the `NextLegalVersion::after()`
    // call site in `suggest()`.
    LegalDocument::factory()->published()->create(['version' => '1.1']);
    LegalDocument::factory()->published()->create(['version' => '1.0']);

    Livewire::actingAs($this->admin)
        ->test(LegalDocumentsScreen::class, ['doc' => 'terms'])
        ->assertSet('newVersion', '1.2');
});

test('starting a draft without touching the version box uses the suggestion', function (): void {
    // The whole point of the suggestion: open the screen, press the button. If
    // the box were merely pre-filled for display and the component read
    // something else, this is the test that would notice.
    LegalDocument::factory()->published()->create(['version' => '1.0']);

    Livewire::actingAs($this->admin)
        ->test(LegalDocumentsScreen::class, ['doc' => 'terms'])
        ->call('startDraft')
        ->assertHasNoErrors();

    expect(app(LegalDocuments::class)->draft(LegalDocumentType::Terms)?->version)->toBe('1.1');
});

test('the version suggested after publishing is the one after the version just published', function (): void {
    Livewire::actingAs($this->admin)
        ->test(LegalDocumentsScreen::class, ['doc' => 'terms'])
        ->call('startDraft')
        ->set('body', 'The agreed clauses.')
        ->set('reviewer', 'A. Counsel')
        ->call('recordReview')
        ->call('publish')
        ->assertSet('newVersion', '1.1');
});

test('the reviewer box arrives filled in with the signed-in admin', function (): void {
    // ⚠️ **MUTATION**: return '' from `defaultReviewer()`.
    $documents = app(LegalDocuments::class);
    $documents->startDraft(LegalDocumentType::Terms, '1.0');

    Livewire::actingAs($this->admin)
        ->test(LegalDocumentsScreen::class, ['doc' => 'terms'])
        ->assertSet('reviewer', $this->admin->name);
});

test('a pre-filled reviewer is not a recorded review until somebody records it', function (): void {
    // ⛔ THE CONSTRAINT ON THIS WHOLE SLICE. `39`'s checklist step 3 makes the
    // named reviewer the record that a review happened, and a pre-fill that
    // quietly became that record would turn the one piece of evidence this table
    // holds into a default nobody chose. Mounting the screen writes nothing.
    //
    // ⚠️ **MUTATION**: call `recordReview` from `mount()`.
    $documents = app(LegalDocuments::class);
    $documents->startDraft(LegalDocumentType::Terms, '1.0');

    $screen = Livewire::actingAs($this->admin)
        ->test(LegalDocumentsScreen::class, ['doc' => 'terms']);

    $draft = LegalDocument::query()->firstOrFail();

    expect($draft->reviewed_at)->toBeNull()
        ->and($draft->reviewed_by)->toBeNull();

    // And it is the press, not the mount, that writes it.
    $screen->call('recordReview')->assertHasNoErrors();

    $draft->refresh();

    expect($draft->reviewed_by)->toBe($this->admin->name)
        ->and($draft->reviewed_at)->not->toBeNull();
});

test('the reviewer stays editable, because counsel has no account here', function (): void {
    // `reviewed_by` is a string rather than a foreign key precisely because the
    // reviewer is usually outside the company. Overwriting the suggestion is the
    // ordinary case, not an exception.
    $documents = app(LegalDocuments::class);
    $documents->startDraft(LegalDocumentType::Terms, '1.0');

    Livewire::actingAs($this->admin)
        ->test(LegalDocumentsScreen::class, ['doc' => 'terms'])
        ->set('reviewer', 'Outside Counsel LLP')
        ->call('recordReview')
        ->assertHasNoErrors();

    expect(LegalDocument::query()->firstOrFail()->reviewed_by)->toBe('Outside Counsel LLP');
});

test('the draft body is rendered between the textarea tags, not in an attribute', function (): void {
    // ⛔ `assertSee` CANNOT MAKE THIS CLAIM AND THE TEST ABOVE IT ONLY LOOKS AS
    // THOUGH IT DOES. A textarea written as `<textarea value="…"></textarea>`
    // renders an empty box over a full document — the defect that shipped — and
    // every `assertSee` in this file would pass against it, because the words
    // are on the page either way. The tag pair is what has to be asserted.
    //
    // ⚠️ **MUTATION**: move `{{ $body }}` into a `value` attribute.
    $documents = app(LegalDocuments::class);
    $draft = $documents->startDraft(LegalDocumentType::Terms, '1.0');
    $documents->saveDraft($draft, 'The clauses as counsel wrote them.', false);

    $html = Livewire::actingAs($this->admin)
        ->test(LegalDocumentsScreen::class, ['doc' => 'terms'])
        ->html();

    expect($html)->toMatch('/<textarea[^>]*>The clauses as counsel wrote them\.<\/textarea>/');
});

test('the draft is diffed against the published text, line by line', function (): void {
    // ⚠️ **MUTATION**: return [] from `LineDiff::between()`.
    LegalDocument::factory()->published()->create([
        'version' => '1.0',
        'body' => "One.\nTwo.\nThree.",
    ]);

    app(LegalDocuments::class)->startDraft(LegalDocumentType::Terms, '1.1');

    Livewire::actingAs($this->admin)
        ->test(LegalDocumentsScreen::class, ['doc' => 'terms'])
        ->set('body', "One.\nTwo, revised.\nThree.")
        // The changed lines, both halves of the replacement.
        ->assertSee('Two, revised.')
        // ⚠️ THE WORD, NOT THE COLOUR (`22`). Every row carries its kind as a
        // marker and as a sentence a screen reader announces; a test that
        // asserted only the text would pass on a diff that was green and red and
        // nothing else.
        ->assertSee('Removed, line 2:')
        ->assertSee('Added, line 2:')
        // And the unchanged lines are not printed — a legal document is
        // thousands of words and printing them buries the three that matter.
        ->assertSee('Unchanged lines are not shown.');
});

test('a draft that matches the published text says so rather than showing an empty diff', function (): void {
    // An empty diff is good news, so it is a sentence rather than a blank panel
    // somebody has to interpret — and it is the state a draft is in the moment
    // it is started, because `startDraft()` seeds it from the published text.
    LegalDocument::factory()->published()->create([
        'version' => '1.0',
        'body' => "One.\nTwo.\nThree.",
    ]);

    app(LegalDocuments::class)->startDraft(LegalDocumentType::Terms, '1.1');

    Livewire::actingAs($this->admin)
        ->test(LegalDocumentsScreen::class, ['doc' => 'terms'])
        ->assertSee('This draft matches published version 1.0 word for word.');
});

test('the three acts read as one sequence, with the step that is still required named', function (): void {
    // ⚠️ **MUTATION**: return [] from `flowSteps()`.
    app(LegalDocuments::class)->startDraft(LegalDocumentType::Terms, '1.0');

    Livewire::actingAs($this->admin)
        ->test(LegalDocumentsScreen::class, ['doc' => 'terms'])
        ->assertSee('Save the text')
        ->assertSee('Record the review')
        ->assertSee('Publish version 1.0')
        // Where the reader is, in words rather than in a filled circle.
        ->assertSee('Do this now')
        ->assertSee('Still to do');
});

test('the sequence marks the review done and moves the reader on to publishing', function (): void {
    $documents = app(LegalDocuments::class);
    $draft = $documents->startDraft(LegalDocumentType::Terms, '1.0');
    $documents->recordReview($draft, 'A. Counsel');

    Livewire::actingAs($this->admin)
        ->test(LegalDocumentsScreen::class, ['doc' => 'terms'])
        ->assertSee('Done')
        ->assertSee('A. Counsel reviewed this version on '.now()->format('j F Y').'.')
        ->assertSee('Publishing freezes this text permanently.');
});

test('text in the box that has not been saved is named as unsaved', function (): void {
    // ⛔ THE MISTAKE THIS EXISTS TO MAKE VISIBLE: recording a review against text
    // nobody saved. `recordReview()` stamps the stored row, and the stored row is
    // not what the reviewer was reading if the box has moved on since.
    //
    // ⚠️ **MUTATION**: hard-code `$saved = true` in `flowSteps()`.
    app(LegalDocuments::class)->startDraft(LegalDocumentType::Terms, '1.0');

    Livewire::actingAs($this->admin)
        ->test(LegalDocumentsScreen::class, ['doc' => 'terms'])
        ->set('body', 'A clause nobody has saved.')
        ->assertSee('The box holds changes that are not saved yet.')
        ->call('saveDraft')
        ->assertDontSee('The box holds changes that are not saved yet.')
        ->assertSee('Version 1.0 is saved as a draft.');
});
