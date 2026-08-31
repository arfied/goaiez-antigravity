<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\AuditCheckKey;
use App\Services\Audit\AuditContext;
use App\Services\Audit\CheckResult;

/**
 * One of the four checks the free instant audit runs (`29` §6.2).
 *
 * DELIBERATELY WITHOUT A NETWORK. An implementation receives an AuditContext and
 * returns a CheckResult; it has no client, no gateway and no way to reach
 * anything. That is what makes the cost of an audit knowable in advance (see
 * AuditContext) and what makes `BUILD-PLAN` §2.5.3's determinism test mean
 * something.
 *
 * IT IS ALSO WHY THIS IS AN INTERFACE. Four classes with no dependencies and one
 * method are close to being four functions — the interface earns its place by
 * letting the engine hold them as a list, so adding a fifth check is a container
 * binding rather than an edit to a match statement. `29` §6.2's check list is
 * explicitly "deterministic, cheap, honest" rather than fixed forever, and the
 * cheapest of the three to lose is fixed.
 *
 * AN IMPLEMENTATION NEVER THROWS. Missing data is CheckResult::unavailable()
 * with a reason, because a thrown exception in the third of four checks would
 * lose the two that already succeeded — and `29` §6.2 requires findings to
 * stream as they are computed, which means they must already be persisted by
 * then.
 */
interface AuditCheck
{
    public function key(): AuditCheckKey;

    public function run(AuditContext $context): CheckResult;
}
