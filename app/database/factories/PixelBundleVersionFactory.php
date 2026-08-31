<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PixelBundleStatus;
use App\Models\PixelBundleVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<PixelBundleVersion>
 */
final class PixelBundleVersionFactory extends Factory
{
    protected $model = PixelBundleVersion::class;

    public function definition(): array
    {
        $contents = 'console.log("test-'.Str::random(8).'");';

        return [
            'sha' => hash('sha256', $contents.Str::random(8)),
            'build_token' => bin2hex(random_bytes(16)),
            'contents' => $contents,
            'byte_size' => strlen($contents),
            'gzip_byte_size' => strlen((string) gzencode($contents, 9)),
            'status' => PixelBundleStatus::Active->value,
            'published_at' => Carbon::now(),
            'canary_started_at' => null,
            'promoted_at' => Carbon::now(),
            'halted_at' => null,
            'halt_reason' => null,
            'actor' => 'test-factory',
        ];
    }

    public function canary(): self
    {
        return $this->state(fn (): array => [
            'status' => PixelBundleStatus::Canary->value,
            'canary_started_at' => Carbon::now(),
            'promoted_at' => null,
        ]);
    }

    public function rolledBack(): self
    {
        return $this->state(fn (): array => [
            'status' => PixelBundleStatus::RolledBack->value,
            'halted_at' => Carbon::now(),
            'halt_reason' => 'test fixture',
        ]);
    }

    public function retired(): self
    {
        return $this->state(fn (): array => [
            'status' => PixelBundleStatus::Retired->value,
        ]);
    }
}
