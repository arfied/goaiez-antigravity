<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\IndexingEngine;
use App\Enums\IndexingMethod;
use App\Enums\IndexingRefusal;
use App\Enums\IndexingStatus;
use App\Services\Indexing\Indexing;
use Database\Factories\IndexingSubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A URL announced to a search engine, or the recorded reason it was not
 * (DATA-MODEL §5.11).
 *
 * ⛔ **{@see Indexing} IS THE ONLY WRITER AND THE ONLY READER, AND A CHOKEPOINT
 * LINT IS WHAT SAYS SO** — `ActuationTest`'s *"only one service announces a URL
 * to a search engine"*, on `SiteChange`'s and `ContentQualityCheck`'s pattern. A
 * described chokepoint is not a chokepoint (314–316).
 *
 * ⚠️ **THE TABLE HAD ZERO WRITERS FROM STAGE 0 UNTIL 2026-08-20** — 272's shape,
 * a green isolation suite over a table nothing filled in — so the lint lands with
 * the *first* writer rather than after the second.
 *
 * ## What the chokepoint protects
 *
 * ⛔ **THE PAIRING OF `status`, `reason` AND `submitted_at`.** A refusal is
 * *ours* and a rejection is the *engine's*; `submitted_at` is non-null only when
 * a request was actually taken. A second writer is where a page we declined to
 * announce becomes a page a search engine turned down, and the two are
 * indistinguishable afterwards — which matters most on the paths that refuse
 * today, because almost every row on this deployment is one.
 */
final class IndexingSubmission extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<IndexingSubmissionFactory> */
    use HasFactory;

    /**
     * `attempted_at` and `submitted_at` are the data; the table has no
     * `created_at`/`updated_at` pair.
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'engine' => IndexingEngine::class,
            'method' => IndexingMethod::class,
            'status' => IndexingStatus::class,
            'reason' => IndexingRefusal::class,
            'response' => 'array',
            'attempted_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }
}
