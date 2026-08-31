<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LegalDocumentType;
use Carbon\CarbonImmutable;
use Database\Factories\LegalDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One version of one legal document.
 *
 * NOT TENANT-OWNED, on the `TenancyTest` allowlist with its reason there:
 * these are the terms under which every tenant uses the platform, so a
 * `business_id` would mean each business holding its own copy of the agreement
 * it is bound by.
 *
 * ⚠️ A PUBLISHED ROW IS FROZEN. The hooks below are the developer-facing layer of
 * the rule the migration's trigger enforces in the database — see there for why
 * it earns both. A published body that can be edited silently rewrites the words
 * every consent record written against it points at, and nothing anywhere
 * records what the text used to be.
 *
 * NOT BLANKET APPEND-ONLY, unlike `ConsentRecord`, `AuditLogEntry` and
 * `ActivityFeedItem`. A draft is a work in progress and is meant to be edited;
 * publication is the moment the text becomes citable and therefore the moment it
 * stops moving. The distinction is the whole design — an append-only table would
 * force a new row per typo during drafting, and counsel would edit in a word
 * processor instead, which is how the reviewed text and the served text drift
 * apart.
 *
 * ⚠️ `$doc_type` IS ANNOTATED BECAUSE STATIC ANALYSIS COULD NOT SEE THE CAST —
 * `Business::$data_classification`'s problem, on a second model. `casts()`
 * returns `LegalDocumentType::class` and the enum arrives at runtime, but
 * Larastan resolves the property from the column and reports a comparison
 * against a case as always true. Taking that at face value — comparing against
 * `->value` to satisfy it — makes the comparison wrong at *runtime*, and the
 * comparison in question is `BaaRecords::refuseUnusableVersion()`, which is what
 * stops a covered entity being bound to the wrong document. The annotation
 * states what the cast already does, so both agree.
 *
 * ⚠️ `$published_at` AND `$reviewed_at` ARE ANNOTATED FOR THE SAME REASON, one
 * fix wave later. `casts()` makes them `CarbonImmutable` and Larastan resolves
 * them from the column as `string`, so the first caller to do date arithmetic on
 * one — `BaaRecords::refuseUnusableVersion()`, comparing a version's publication
 * against the date a tenant signed — was reported as calling a method on a
 * string. Taking that at face value and formatting the string by hand would make
 * the comparison lexical, which is wrong the moment a timezone or a format
 * changes. The annotation states what the cast already does, so both agree.
 *
 * @property LegalDocumentType $doc_type
 * @property ?CarbonImmutable $published_at
 * @property ?CarbonImmutable $reviewed_at
 */
final class LegalDocument extends Model
{
    /** @use HasFactory<LegalDocumentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * The two writes a published row must refuse.
     *
     * ⚠️ `getOriginal()` rather than the current attribute value, deliberately:
     * by the time `updating` fires, `published_at` already holds whatever the
     * caller set. Reading the attribute would let a published row be edited by
     * any update that blanked `published_at` in the same statement — and
     * blanking it is exactly what an "unpublish" button would do.
     *
     * The draft → published transition is therefore permitted, because the
     * *original* is null. That is the one update this table takes.
     */
    protected static function booted(): void
    {
        self::updating(function (self $document): void {
            if ($document->getOriginal('published_at') !== null) {
                throw new LogicException(
                    'legal_documents rows are immutable once published. The words a '
                    .'consent record points at may never change: publish a new version.'
                );
            }
        });

        self::deleting(function (self $document): void {
            if ($document->getOriginal('published_at') !== null) {
                throw new LogicException(
                    'legal_documents rows are immutable once published. A published '
                    .'version stays addressable forever — supersede it with a new one.'
                );
            }
        });
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'doc_type' => LegalDocumentType::class,
            'is_placeholder' => 'boolean',
            'published_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
        ];
    }
}
