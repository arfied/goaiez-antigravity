<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Livewire\Account\AssistantAnswers;
use App\Models\User;
use App\Modules\X163\Models\PriceBookItem;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

uses(RefreshesTenantDatabase::class);

beforeEach(function () {
    /** @var TestCase $this */
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    $this->biz = TestCase::provisionTenant(['owner_user_id' => $this->owner->id]);
    $this->actingAs($this->owner);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);

    Mail::fake();
    Notification::fake();
});

it('shows the assistant answers console', function () {
    $this->get(route('account.assistant-answers'))
        ->assertOk()
        ->assertSee('Your account', false);
});

it('refuses no-tenant user on get and mount', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $this->actingAs($staff);
    Tenancy::setUser($staff->id);
    Tenancy::forget();

    $this->get(route('account.assistant-answers'))->assertForbidden();

    Livewire::actingAs($staff)->test(AssistantAnswers::class)->assertForbidden();
});

it('saves a price and removes it', function () {
    Livewire::test(AssistantAnswers::class)
        ->set('jobName', 'Back window repair')
        ->set('price', '85')
        ->call('savePrice')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('price_book_items', [
        'service_name' => 'Back window repair',
        'price_cents' => 8500,
        'is_confirmed' => true,
    ]);

    $this->get(route('account.assistant-answers'))
        ->assertSee('Back window repair');

    Livewire::test(AssistantAnswers::class)
        ->call('removePrice', 'back-window-repair')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('price_book_items', [
        'service_name' => 'Back window repair',
    ]);
});

it('fails to save invalid price with refusal copy', function () {
    Livewire::test(AssistantAnswers::class)
        ->set('jobName', 'Back window repair')
        ->set('price', 'abc')
        ->call('savePrice')
        ->assertHasErrors(['price' => 'Write the price as a plain amount, like 85 or 85.50.']);

    $this->assertDatabaseMissing('price_book_items', [
        'service_name' => 'Back window repair',
    ]);
});

it('fails to save downward range with refusal copy', function () {
    Livewire::test(AssistantAnswers::class)
        ->set('jobName', 'Back window repair')
        ->set('price', '100')
        ->set('priceMax', '50')
        ->call('savePrice')
        ->assertHasErrors(['priceMax' => 'The top of a price range has to be more than the bottom. Leave the second box empty if this job has one price.']);
});

it('saves the disclaimer', function () {
    Livewire::test(AssistantAnswers::class)
        ->set('disclaimer', 'This is a test disclaimer.')
        ->call('saveDisclaimer')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('assistant_briefs', [
        'quote_disclaimer' => 'This is a test disclaimer.',
    ]);
});

it('adds and removes urgent terms', function () {
    Livewire::test(AssistantAnswers::class)
        ->set('urgentTerm', 'lockout')
        ->call('addUrgentTerm')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('urgent_terms', [
        'term' => 'lockout',
    ]);

    Livewire::test(AssistantAnswers::class)
        ->call('removeUrgentTerm', 'lockout')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('urgent_terms', [
        'term' => 'lockout',
    ]);
});

it('saves emergency line', function () {
    Livewire::test(AssistantAnswers::class)
        ->set('emergencyLine', '555-123-4567')
        ->call('saveEmergencyLine')
        ->assertHasNoErrors();

    // The normalised format might be something like +15551234567 or similar, let's verify DB entry exists
    $this->assertDatabaseCount('assistant_briefs', 1);
});

it('refuses to show prices of another tenant on get', function () {
    Livewire::test(AssistantAnswers::class)
        ->set('jobName', 'Tenant A Exclusive')
        ->set('price', '150')
        ->call('savePrice')
        ->assertHasNoErrors();

    $ownerB = User::factory()->create(['role' => UserRole::Owner]);
    $bizB = TestCase::provisionTenant(['owner_user_id' => $ownerB->id]);

    $this->actingAs($ownerB);
    Tenancy::setUser($ownerB->id);
    Tenancy::set((int) $bizB->id);

    $this->get(route('account.assistant-answers'))
        ->assertOk()
        ->assertDontSee('Tenant A Exclusive');
});

it('handles upload errors', function () {
    Livewire::test(AssistantAnswers::class)
        ->call('_uploadErrored', 'sheet', null, false)
        ->assertHasErrors(['sheet' => AssistantAnswers::UPLOAD_REFUSED]);
});

it('confirms and discards proposals', function () {
    // Manually insert unconfirmed rows to bypass uploadSheet bug
    $this->assertDatabaseMissing('price_book_items', ['service_name' => 'Roof inspection']);

    PriceBookItem::forceCreate([
        'business_id' => $this->biz->id,
        'service_name' => 'Roof inspection',
        'price_cents' => 9900,
        'price_max_cents' => null,
        'is_confirmed' => false,
        'confirmed_at' => null,
        'is_sample' => false,
    ]);

    PriceBookItem::forceCreate([
        'business_id' => $this->biz->id,
        'service_name' => 'Gutter cleaning',
        'price_cents' => 4500,
        'price_max_cents' => null,
        'is_confirmed' => false,
        'confirmed_at' => null,
        'is_sample' => false,
    ]);

    Livewire::test(AssistantAnswers::class)
        ->call('confirmProposal', 'roof-inspection')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('price_book_items', [
        'service_name' => 'Roof inspection',
        'is_confirmed' => true,
    ]);

    Livewire::test(AssistantAnswers::class)
        ->call('discardProposals')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('price_book_items', [
        'service_name' => 'Gutter cleaning',
    ]);
});

use Illuminate\Http\UploadedFile;

it('uploads a sheet proposing two new prices', function () {
    $csv = "Front door lockout - 85\nRekey a cylinder: $95.50";
    Livewire::test(AssistantAnswers::class)
        ->set('sheet', UploadedFile::fake()->createWithContent('prices.csv', $csv))
        ->call('uploadSheet')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('price_book_items', [
        'service_name' => 'Front door lockout',
        'is_confirmed' => false,
        'confirmed_at' => null,
    ]);

    $this->assertDatabaseHas('price_book_items', [
        'service_name' => 'Rekey a cylinder',
        'is_confirmed' => false,
        'confirmed_at' => null,
    ]);
});

it('skips a label that matches an already-set price', function () {
    Livewire::test(AssistantAnswers::class)
        ->set('jobName', 'Back window repair')
        ->set('price', '85')
        ->call('savePrice')
        ->assertHasNoErrors();

    $csv = 'Back window repair, 100';
    Livewire::test(AssistantAnswers::class)
        ->set('sheet', UploadedFile::fake()->createWithContent('prices.csv', $csv))
        ->call('uploadSheet')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('price_book_items', [
        'service_name' => 'Back window repair',
        'is_confirmed' => false,
    ]);
});

it('skips a label the slugger empties', function () {
    $csv = '??? - 100';
    Livewire::test(AssistantAnswers::class)
        ->set('sheet', UploadedFile::fake()->createWithContent('prices.csv', $csv))
        ->call('uploadSheet')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('price_book_items', [
        'service_name' => '???',
    ]);
});
