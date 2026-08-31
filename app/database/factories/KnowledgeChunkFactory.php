<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * business_id is deliberately absent — BelongsToTenant fills it from context,
 * and a factory that created its own parent would make a second tenant.
 *
 * @extends Factory<KnowledgeChunk>
 */
final class KnowledgeChunkFactory extends Factory
{
    protected $model = KnowledgeChunk::class;

    public function definition(): array
    {
        return [
            'source_id' => KnowledgeSource::factory(),
            'content' => fake()->paragraph(),
            'token_count' => fake()->numberBetween(50, 400),
            'embedding' => self::embedding(),
            'metadata' => [],
            'created_at' => now(),
        ];
    }

    /**
     * A chunk whose embedding points in a known direction.
     *
     * Lets a test assert *which* chunk came back nearest, rather than only that
     * something did — the difference between proving the ordering works and
     * proving the query ran.
     */
    public function pointingAt(int $axis): self
    {
        return $this->state(fn (): array => ['embedding' => self::embedding($axis)]);
    }

    /**
     * A 1536-dimension unit vector, matching the column.
     *
     * With no axis given the values are arbitrary but deterministic per call;
     * with one, the vector is 1 on that axis and 0 elsewhere, so cosine distance
     * between two such vectors is exactly 0 or 1.
     */
    private static function embedding(?int $axis = null): string
    {
        $values = array_fill(0, 1536, 0.0);

        if ($axis === null) {
            foreach ($values as $i => $_) {
                $values[$i] = round(sin($i) / 40, 6);
            }
        } else {
            $values[$axis % 1536] = 1.0;
        }

        return '['.implode(',', $values).']';
    }
}
