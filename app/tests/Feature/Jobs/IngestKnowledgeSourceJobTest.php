<?php

declare(strict_types=1);

use App\Enums\CreditKind;
use App\Enums\CreditProduct;
use App\Enums\KnowledgeSourceStatus;
use App\Enums\UserRole;
use App\Jobs\IngestKnowledgeSourceJob;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeSource;
use App\Models\Location;
use App\Models\User;
use App\Services\Billing\CreditLedger;
use App\Services\Knowledge\KnowledgeUploads;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    $this->biz = TestCase::provisionTenant(['owner_user_id' => $this->owner->id]);
    /** @var TestCase $this */
    $this->actingAs($this->owner);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);

    $this->location = Location::factory()->create(['business_id' => $this->biz->id]);
});

afterEach(function () {
    Tenancy::forget();
});

it('ingests one pending source and writes chunks', function () {
    app(CreditLedger::class)->record(CreditProduct::Ai, CreditKind::Grant, 1000000, 'test');

    Storage::fake(config('filesystems.default'));
    $longText = str_repeat('This is some knowledge. ', 50);
    Storage::disk(config('filesystems.default'))->put('test.txt', $longText);

    Http::fake([
        '*' => Http::response([
            'data' => [
                ['embedding' => array_fill(0, 1536, 0.1)],
            ],
            'usage' => ['prompt_tokens' => 10],
        ], 200),
    ]);

    $source = KnowledgeSource::factory()->create([
        'business_id' => $this->biz->id,
        'location_id' => $this->location->id,
        'status' => KnowledgeSourceStatus::Pending,
        'file_path' => 'test.txt',
    ]);

    $job = new IngestKnowledgeSourceJob((int) $this->biz->id, (int) $this->location->id, $source->id);
    $job->handle();

    $source->refresh();
    expect($source->status->value)->toBe(KnowledgeSourceStatus::Ingested->value);
    expect(KnowledgeChunk::query()->where('source_id', $source->id)->count())->toBeGreaterThan(0);
});

it('leaves a source of another tenant untouched', function () {
    Storage::fake(config('filesystems.default'));
    Storage::disk(config('filesystems.default'))->put('test2.txt', str_repeat('This is some knowledge. ', 50));

    $ownerB = User::factory()->create(['role' => UserRole::Owner]);
    $bizB = TestCase::provisionTenant(['owner_user_id' => $ownerB->id]);

    Tenancy::setUser($ownerB->id);
    Tenancy::set((int) $bizB->id);

    $sourceB = KnowledgeSource::factory()->create([
        'business_id' => $bizB->id,
        'location_id' => Location::factory()->create(['business_id' => $bizB->id])->id,
        'status' => KnowledgeSourceStatus::Pending,
        'file_path' => 'test2.txt',
    ]);

    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);

    $job = new IngestKnowledgeSourceJob((int) $this->biz->id, (int) $this->location->id, $sourceB->id);
    $job->handle();

    Tenancy::setUser($ownerB->id);
    Tenancy::set((int) $bizB->id);

    $sourceB->refresh();
    expect($sourceB->status->value)->toBe(KnowledgeSourceStatus::Pending->value);
});

it('is dispatched by KnowledgeUploads', function () {
    Queue::fake();
    Storage::fake(config('filesystems.default'));

    $file = UploadedFile::fake()->createWithContent('test3.txt', str_repeat('This is some knowledge. ', 50));
    $source = app(KnowledgeUploads::class)->accept($file, $this->location->id);

    Queue::assertPushed(IngestKnowledgeSourceJob::class, fn ($j) => $j->sourceId === $source->id);
});
