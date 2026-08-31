<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\IndexingEngine;
use App\Enums\IndexingMethod;
use App\Enums\IndexingRefusal;
use App\Enums\IndexingStatus;
use App\Models\IndexingSubmission;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Does NOT default business_id — BelongsToTenant fills it from the tenant in
 * context (see LocationFactory).
 *
 * ⛔ **A FACTORY IS NOT A WRITER, AND THIS ONE WAS THE TABLE'S ONLY FILLER FOR
 * THE WHOLE OF STAGE 0** — `businesses.pixel_tenant_id`'s shape (4961), where
 * every test passed against rows no tenant had. `App\Services\Indexing\Indexing`
 * is the writer now; this exists so a *report* can be driven against rows it did
 * not have to publish a page to produce.
 *
 * ⚠️ **THE DEFAULT IS A REFUSAL-FREE SUBMITTED ROW BECAUSE THAT IS THE ARM WITH
 * NO REFUSAL TO GET WRONG.** The `refused()` state below sets `reason` and
 * clears `submitted_at` together, which is the invariant the CHECK constraint
 * and the writer both hold — a factory that could produce a row the writer
 * cannot is a fixture that tests something this application never builds.
 *
 * @extends Factory<IndexingSubmission>
 */
final class IndexingSubmissionFactory extends Factory
{
    protected $model = IndexingSubmission::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'url' => fake()->url(),
            'engine' => IndexingEngine::Google,
            'method' => IndexingMethod::Sitemap,
            'status' => IndexingStatus::InPlace,
            'reason' => null,
            'response' => null,
            'attempted_at' => now(),
            'submitted_at' => null,
        ];
    }

    /**
     * An engine took the request.
     */
    public function submitted(): self
    {
        return $this->state(fn (): array => [
            'engine' => IndexingEngine::IndexNow,
            'method' => IndexingMethod::IndexNow,
            'status' => IndexingStatus::Submitted,
            'reason' => null,
            'submitted_at' => now(),
        ]);
    }

    /**
     * We declined to send, and said why.
     */
    public function refused(IndexingRefusal $reason): self
    {
        return $this->state(fn (): array => [
            'status' => IndexingStatus::Refused,
            'reason' => $reason,
            'submitted_at' => null,
        ]);
    }
}
