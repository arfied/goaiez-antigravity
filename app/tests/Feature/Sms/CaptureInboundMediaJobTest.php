<?php

declare(strict_types=1);

use App\Enums\InboundKeyword;
use App\Enums\InboundMediaOutcome;
use App\Enums\OutreachChannel;
use App\Jobs\Sms\CaptureInboundMediaJob;
use App\Models\Business;
use App\Models\InboundMedia;
use App\Models\InboundMessage;
use App\Services\Sms\InboundMediaCapture;
use App\Services\Sms\InboundMediaFetcher;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->message = InboundMessage::query()->forceCreate([
        'provider_message_id' => Str::random(),
        'identifier_type' => OutreachChannel::Sms,
        'value_hash' => hash('sha256', '+15551234567'),
        'keyword' => InboundKeyword::None,
    ]);
    Config::set('services.infobip.media_hosts', ['example.com']);
});

it('stores media at the path the job records and updates the row', function () {
    Storage::fake('s3');
    $bytes = "\xFF\xD8\xFFfake-jpeg";
    Http::fake(['https://example.com/*' => Http::response($bytes, 200, [
        'Content-Type' => 'image/jpeg',
        'Content-Length' => (string) strlen($bytes),
    ])]);

    $job = new CaptureInboundMediaJob($this->business->id, $this->message->id, 1, 'https://example.com/media/1');
    $job->handle(app(InboundMediaFetcher::class), app(InboundMediaCapture::class));

    $path = $job->storagePath();
    Storage::disk('s3')->assertExists($path);

    $media = InboundMedia::query()->where('inbound_message_id', $this->message->id)->first();
    expect($media)->not->toBeNull()
        ->outcome->toBe(InboundMediaOutcome::Stored)
        ->storage_path->toBe($path)
        ->storage_disk->toBe('s3');
});

it('returns if missing message, nothing stored', function () {
    Storage::fake('s3');
    $job = new CaptureInboundMediaJob($this->business->id, 99999, 1, 'https://example.com/media/1');
    $job->handle(app(InboundMediaFetcher::class), app(InboundMediaCapture::class));

    expect(Storage::disk('s3')->allFiles())->toBeEmpty();
});

it('returns if already captured, Storage untouched', function () {
    Storage::fake('s3');
    InboundMedia::query()->forceCreate([
        'business_id' => $this->business->id,
        'inbound_message_id' => $this->message->id,
        'ordinal' => 1,
        'outcome' => InboundMediaOutcome::Stored,
        'storage_disk' => 's3',
        'storage_path' => 'fake/path',
        'content_type' => 'image/jpeg',
        'byte_size' => 10,
        'checksum' => 'fake-checksum',
    ]);

    $job = new CaptureInboundMediaJob($this->business->id, $this->message->id, 1, 'https://example.com/media/1');
    $job->handle(app(InboundMediaFetcher::class), app(InboundMediaCapture::class));

    expect(Storage::disk('s3')->allFiles())->toBeEmpty();
});

it('throws RuntimeException on carrier 404 which is the documented outcome', function () {
    Http::fake(['https://example.com/*' => Http::response('', 404)]);

    $job = new CaptureInboundMediaJob($this->business->id, $this->message->id, 1, 'https://example.com/media/1');

    expect(fn () => $job->handle(app(InboundMediaFetcher::class), app(InboundMediaCapture::class)))
        ->toThrow(RuntimeException::class, 'could not be fetched. Retrying.');
});

it('throws RuntimeException if Storage::put is false', function () {
    $bytes = "\xFF\xD8\xFFfake-jpeg";
    Http::fake(['https://example.com/*' => Http::response($bytes, 200, [
        'Content-Type' => 'image/jpeg',
        'Content-Length' => (string) strlen($bytes),
    ])]);

    Storage::shouldReceive('disk')
        ->with('s3')
        ->andReturn(
            Mockery::mock(Filesystem::class, function ($mock) {
                $mock->shouldReceive('put')->andReturn(false);
            })
        );

    $job = new CaptureInboundMediaJob($this->business->id, $this->message->id, 1, 'https://example.com/media/1');

    expect(fn () => $job->handle(app(InboundMediaFetcher::class), app(InboundMediaCapture::class)))
        ->toThrow(RuntimeException::class, 'could not be written to the object store. Retrying.');
});
