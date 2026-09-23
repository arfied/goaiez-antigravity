<?php

declare(strict_types=1);

use App\Enums\ExportStatus;
use App\Enums\UserRole;
use App\Jobs\BuildTenantExportJob;
use App\Models\TenantExport;
use App\Models\User;
use App\Notifications\TenantExportReady;
use App\Services\Export\ExportBuilder;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

beforeEach(function () {
    /** @var TestCase $this */
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    $this->biz = TestCase::provisionTenant(['owner_user_id' => $this->owner->id]);
    $this->actingAs($this->owner);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);

    Notification::fake();
    // @phpstan-ignore-next-line
    mailerCanDeliver();
    Config::set('queue.default', 'sync');
});

it('request creates a queued export and dispatches the job', function () {
    /** @var TestCase $this */
    Bus::fake([BuildTenantExportJob::class]);

    $response = $this->post(route('account.data-export.request'));
    $response->assertRedirect();

    $export = TenantExport::first();
    expect($export)->not->toBeNull()
        ->and($export->status)->toBe(ExportStatus::Queued)
        ->and($export->business_id)->toBe($this->biz->id);

    Bus::assertDispatched(BuildTenantExportJob::class, function (BuildTenantExportJob $job) use ($export) {
        return $job->businessId === $this->biz->id && $job->tenantExportId === $export->id;
    });
});

it('the job builds it', function () {
    /** @var TestCase $this */
    Storage::fake('s3');

    $export = TenantExport::factory()->create([
        'business_id' => $this->biz->id,
        'requested_by' => $this->owner->id,
        'status' => ExportStatus::Queued,
    ]);

    $job = new BuildTenantExportJob($this->biz->id, $export->id);
    app()->call([$job, 'handle']);

    $export->refresh();

    expect($export->status)->toBe(ExportStatus::Ready)
        ->and($export->storage_path)->not->toBeNull();

    Storage::disk('s3')->assertExists($export->storage_path);

    Notification::assertSentOnDemand(TenantExportReady::class);
});

it('a Ready export is not rebuilt', function () {
    /** @var TestCase $this */
    Storage::fake('s3');

    $export = TenantExport::factory()->ready()->create([
        'business_id' => $this->biz->id,
        'requested_by' => $this->owner->id,
        'storage_path' => 'exports/test/test.zip',
    ]);

    Storage::disk('s3')->put($export->storage_path, 'dummy zip content');
    $mtime = Storage::disk('s3')->lastModified($export->storage_path);

    $job = new BuildTenantExportJob($this->biz->id, $export->id);
    app()->call([$job, 'handle']);

    $export->refresh();

    expect(Storage::disk('s3')->get($export->storage_path))->toBe('dummy zip content')
        ->and(Storage::disk('s3')->lastModified($export->storage_path))->toBe($mtime);

    Notification::assertNothingSent();
});

it('the owner downloads it', function () {
    /** @var TestCase $this */
    Storage::fake('s3');

    $export = TenantExport::factory()->ready()->create([
        'business_id' => $this->biz->id,
        'requested_by' => $this->owner->id,
        'storage_path' => 'exports/test/test.zip',
    ]);

    Storage::disk('s3')->put($export->storage_path, 'dummy zip content');

    $url = app(ExportBuilder::class)->downloadUrl($export);

    $response = $this->get($url);
    $response->assertOk();
    $response->assertHeader('Content-Disposition');
});

it('another tenant\'s owner gets 404', function () {
    /** @var TestCase $this */
    Storage::fake('s3');

    $export = TenantExport::factory()->ready()->create([
        'business_id' => $this->biz->id,
        'requested_by' => $this->owner->id,
        'storage_path' => 'exports/test/test.zip',
    ]);

    Storage::disk('s3')->put($export->storage_path, 'dummy zip content');

    $url = app(ExportBuilder::class)->downloadUrl($export);

    $ownerB = User::factory()->create(['role' => UserRole::Owner]);
    $bizB = TestCase::provisionTenant(['owner_user_id' => $ownerB->id]);

    $this->actingAs($ownerB);
    Tenancy::setUser($ownerB->id);
    Tenancy::set((int) $bizB->id);

    $response = $this->get($url);
    $response->assertNotFound();
});

it('unsigned is 403', function () {
    /** @var TestCase $this */
    $export = TenantExport::factory()->ready()->create([
        'business_id' => $this->biz->id,
        'requested_by' => $this->owner->id,
    ]);

    $response = $this->get(route('account.data-export.download', $export));
    $response->assertForbidden();
});
