<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalDocument>
 */
final class LegalDocumentFactory extends Factory
{
    protected $model = LegalDocument::class;

    /**
     * A draft, because that is the state a document starts in and the only one
     * a test can then move.
     *
     * ⚠️ No `published_at` here on purpose. A factory defaulting to published
     * would produce rows the trigger refuses to touch, and every test that tried
     * to modify one would fail for a reason that has nothing to do with what it
     * was testing.
     */
    public function definition(): array
    {
        return [
            'doc_type' => LegalDocumentType::Terms,
            'version' => '1.0',
            'title' => LegalDocumentType::Terms->title(),
            'body' => 'The agreed text.',
            'is_placeholder' => true,
            'published_at' => null,
            'published_by' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }

    public function reviewed(string $reviewer = 'A. Counsel'): self
    {
        return $this->state(fn (): array => [
            'reviewed_by' => $reviewer,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Published, and therefore frozen — the trigger will refuse any later write.
     */
    public function published(string $publisher = 'user:1'): self
    {
        return $this->reviewed()->state(fn (): array => [
            'published_at' => now(),
            'published_by' => $publisher,
        ]);
    }

    public function ofType(LegalDocumentType $type): self
    {
        return $this->state(fn (): array => [
            'doc_type' => $type,
            'title' => $type->title(),
        ]);
    }
}
