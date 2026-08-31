<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\KnowledgeSourceStatus;
use App\Enums\KnowledgeSourceType;
use App\Models\KnowledgeSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * business_id is deliberately absent — BelongsToTenant fills it from context,
 * and a factory that created its own parent would make a second tenant.
 *
 * @extends Factory<KnowledgeSource>
 */
final class KnowledgeSourceFactory extends Factory
{
    protected $model = KnowledgeSource::class;

    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(KnowledgeSourceType::cases()),
            'url' => fake()->url(),
            'title' => fake()->sentence(3),
            'status' => KnowledgeSourceStatus::Ingested,
            'checksum' => fake()->sha256(),
            'version' => 1,
            'is_active' => true,
            'last_ingested_at' => now(),
        ];
    }

    /**
     * An uploaded file that has not been read yet.
     *
     * The state the screen actually creates, as against the definition's
     * finished one — so a test of the ingest path starts where the ingest path
     * starts rather than where it ends.
     */
    public function pendingUpload(string $path = 'knowledge/fixture.txt'): self
    {
        return $this->state(fn (): array => [
            'type' => KnowledgeSourceType::Upload,
            'url' => null,
            'file_path' => $path,
            'status' => KnowledgeSourceStatus::Pending,
            'checksum' => null,
            'last_ingested_at' => null,
        ]);
    }
}
