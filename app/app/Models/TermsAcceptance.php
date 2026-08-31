<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\LegalDocumentType;
use App\Enums\TermsAcceptanceMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * The business's acceptance of one legal document, at signup (T176 P22).
 *
 * ⛔ **A TENANT'S RECORD, NEVER A CUSTOMER'S** (terms of service acceptance is not customer consent). The account holder accepted
 * GO AI EZ's Terms, SMS & Communications Terms and Privacy Policy in order to
 * open an account. An end customer never sees a terms flow anywhere, and this
 * table carries no column that could name one.
 *
 * ⚠️ **IT PERMITS NOTHING.** It is not a consent to be contacted, it does not
 * derive a messaging lane, and it is not a sending basis for anybody — which is
 * exactly why it does not live in `consent_records`. It is evidence that the
 * person who opened the account was shown a named version of a named document
 * and agreed to it.
 *
 * ⚠️ **AND IT IS NOT PROOF THAT ANYBODY READ ANYTHING.** It is the record of a
 * disclosure made at a moment, in words this application can still produce.
 *
 * @property-read int $id
 * @property int $business_id
 * @property LegalDocumentType $doc_type
 * @property int $legal_document_id
 * @property string $version
 * @property TermsAcceptanceMethod $method
 * @property string $accepted_by
 * @property array<string, mixed> $proof
 * @property ?Carbon $created_at
 */
final class TermsAcceptance extends Model implements TenantScoped
{
    use BelongsToTenant;

    /**
     * Acceptance history is events; rows are written once.
     */
    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    /**
     * Append-only, enforced rather than asserted — `AutoRenewalAcknowledgement`'s
     * reasoning, and it applies here with the same force.
     *
     * This is the record a carrier reviewer and counsel both ask for. A row that
     * can be edited after the fact proves nothing about what was on the page,
     * whatever it says, and the one thing an UPDATE here could do is manufacture
     * an acceptance for a tenant who was never shown the terms.
     */
    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException(
                'terms_acceptances is append-only. An acceptance is a dated event: a new '
                .'version of a document is a new row. Editing one rewrites the evidence, and '
                .'the only thing an edit could add is agreement nobody gave.'
            );
        });

        self::deleting(function (): never {
            throw new LogicException(
                'terms_acceptances is append-only. Rows are never deleted; the business\'s own '
                .'erasure cascades them away in the database, which is the only way one goes.'
            );
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'doc_type' => LegalDocumentType::class,
            'method' => TermsAcceptanceMethod::class,
            'proof' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
