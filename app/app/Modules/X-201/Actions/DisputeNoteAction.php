<?php

declare(strict_types=1);

namespace App\Modules\X201\Actions;

use App\Modules\X201\Domain\DisputeDefenseEngine;
use App\Modules\X201\Models\Dispute;

final class DisputeNoteAction
{
    public function __construct(private readonly DisputeDefenseEngine $engine = new DisputeDefenseEngine) {}

    /**
     * Adds the tenant's note to the bundle. An opened dispute compiles the
     * invoice line with it; a compiled one takes the note alone. The engine
     * refuses a submitted one — a submitted bundle is sealed.
     *
     * @return array{status:string,dispute_id:int,evidence_count:int,has_signature:bool}
     */
    public function handle(int $businessId, int $disputeId, string $note): array
    {
        $note = trim($note);
        if ($note === '') {
            throw new \DomainException('Write the note first; an empty note adds nothing to the bundle.');
        }

        $dispute = Dispute::where('business_id', $businessId)->findOrFail($disputeId);

        $items = [];
        if ($dispute->status === 'opened') {
            $items[] = ['type' => 'invoice', 'content' => sprintf('Invoice #%d — %s disputed as %s', $dispute->invoice_id, number_format($dispute->chargeback_amount_cents / 100, 2), $dispute->reason)];
        }
        $items[] = ['type' => 'note', 'content' => $note];

        return $this->engine->compile($businessId, $disputeId, $items);
    }
}
