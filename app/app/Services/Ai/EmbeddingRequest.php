<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\AiTask;
use InvalidArgumentException;

/**
 * A batch of texts to turn into vectors, in provider-neutral terms.
 *
 * BATCHED BY CONSTRUCTION RATHER THAN BY CONVENIENCE. A document of forty chunks
 * embedded one call at a time is forty round trips, forty `ai_calls` rows and
 * forty debits for one upload — so a per-chunk shape would make one owner's PDF
 * look like forty incidents and, since 3424, would charge it forty times over
 * with forty ledger movements to read through. The provider accepts an array and
 * prices per token either way. ⚠️ This paragraph has named its ceiling three times
 * and been overtaken twice, so it now names the *asker* rather than the ceiling:
 * *"rule 43's cap"* was deleted at 3293, `ai.monthly_cap_per_tenant` was removed
 * at 3608 and restored behind the balance gate at 3820. **`AiSpend::allows()` is
 * what bounds this path**, before the batch is sent, and it is the place to read
 * what it currently asks — a docblock one class over is not.
 *
 * REFUSES AN EMPTY BATCH RATHER THAN SENDING ONE. An empty `input` is a 400 at
 * the vendor, which arrives as a failure the caller cannot distinguish from an
 * outage; a caller with nothing to embed has a bug worth naming here.
 *
 * NO BUSINESS ID, DELIBERATELY — AiRequest's reasoning verbatim. The ledger is
 * tenant-owned and the tenant is read from `Tenancy` at the point of record, so
 * a caller cannot name a business other than the ambient one. A value that
 * cannot be supplied cannot be supplied wrongly.
 */
final readonly class EmbeddingRequest
{
    /**
     * @param  list<string>  $inputs  One text per vector wanted, in order.
     */
    public function __construct(
        public AiTask $task,
        public array $inputs,
    ) {
        if ($inputs === []) {
            throw new InvalidArgumentException(
                'An embedding request needs at least one input. An empty batch is a 400 at '
                .'the vendor, which reaches the caller as an outage rather than as the '
                .'programming mistake it is.',
            );
        }
    }

    public function count(): int
    {
        return count($this->inputs);
    }
}
