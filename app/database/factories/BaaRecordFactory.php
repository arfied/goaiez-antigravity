<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BaaStatus;
use App\Enums\LegalDocumentType;
use App\Models\BaaRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Defaults to a pending record — the state `BaaRecords::open()` writes, and the
 * one every PHI tenant is in until somebody signs something.
 *
 * Does NOT default `business_id`: BelongsToTenant fills it from the tenant in
 * context, so a test with no tenant established fails loudly rather than
 * inventing one.
 *
 * ⚠️ THE SIGNER NAMES BELOW ARE OBVIOUSLY FICTIONAL AND MUST STAY THAT WAY.
 * These columns hold PII of named individuals in production; a fixture carrying
 * a real person's name would put one in the repository forever.
 *
 * `status` and every evidence column are guarded on the model, so this factory
 * reaches the unguarded path deliberately — a factory is allowed to construct
 * states the service refuses, which is exactly what the CHECK constraint tests
 * need.
 *
 * @extends Factory<BaaRecord>
 */
final class BaaRecordFactory extends Factory
{
    protected $model = BaaRecord::class;

    /**
     * Distinguishes each template this factory publishes — see executed().
     */
    private static int $mintedVersions = 0;

    public function definition(): array
    {
        return [
            'legal_document_id' => null,
            'status' => BaaStatus::Pending,
            'tenant_signed_at' => null,
            'tenant_signer_name' => null,
            'tenant_signer_title' => null,
            'goaiez_signed_at' => null,
            'goaiez_signer' => null,
            'revoked_at' => null,
            'revoke_reason' => null,
        ];
    }

    /**
     * Executed against a published, non-placeholder BAA template.
     *
     * Every evidence column is filled, because the CHECK constraint refuses a
     * row where any one of them is missing — which is the constraint's whole
     * point, and means a shorter state here would fail at the database rather
     * than in the test that meant to use it.
     *
     * ⚠️ THE VERSION IS MINTED, NEVER THE FACTORY DEFAULT OF `1.0`.
     * `legal_documents` carries a unique index on (doc_type, version), so a test
     * that publishes its own `1.0` and then uses this state collides — and the
     * failure lands as a SQLSTATE 23505 inside a factory, twenty frames from the
     * test that caused it.
     */
    public function executed(): self
    {
        return $this->state(fn (): array => [
            'legal_document_id' => LegalDocumentFactory::new()
                ->ofType(LegalDocumentType::Baa)
                ->published()
                // createOne(), not create(): create() is typed as returning a
                // model *or* a collection, so reading ->id off it is an error
                // static analysis is right to report.
                ->createOne([
                    'version' => '9.'.self::$mintedVersions++,
                    'is_placeholder' => false,
                ])
                ->id,
            'status' => BaaStatus::Executed,
            'tenant_signed_at' => now(),
            'tenant_signer_name' => 'Sample Signer',
            'tenant_signer_title' => 'Practice Manager',
            'goaiez_signed_at' => now(),
            'goaiez_signer' => 'Sample Platform Signer',
        ]);
    }

    /**
     * Executed and then ended. Both sets of columns stay filled: a revocation
     * does not unsign anything, it records that the agreement stopped.
     */
    public function revoked(): self
    {
        return $this->executed()->state(fn (): array => [
            'status' => BaaStatus::Revoked,
            'revoked_at' => now(),
            'revoke_reason' => 'The practice closed.',
        ]);
    }
}
