<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use App\Enums\T3InjectionKind;

/**
 * One thing the T3 module will do to a page, with every part of it named.
 *
 * ⛔ **THERE IS NO `html` IMPLEMENTATION AND THERE CANNOT BE ONE WITHOUT A CASE
 * ON {@see T3InjectionKind} TO NAME IT.** That enum is closed, the payload
 * builder maps a change set's `change_type` through it, and an unrecognised type
 * yields no operation at all — so *"serve some markup"* is not a thing this
 * pipeline can express, rather than a thing it rejects. `ChangeSet`'s own
 * docblock states the rule where it starts; this is where it is enforced.
 *
 * ⚠️ **THE FIVE IMPLEMENTATIONS ARE ENUMERATED BY A LINT**
 * (`tests/Feature/Architecture/ActuationTest.php`), because an interface is
 * exactly where a sixth shape gets added quietly — 5530's argument about
 * `CmsAdapter::execute()`, one layer down and with the same consequence.
 *
 * ⚠️ **`fromFields()` IS DELIBERATELY NOT ON THIS INTERFACE.** PHP cannot
 * express *"a static constructor returning `static`"* in a way a caller can use
 * without naming the class, and a `?self` on the interface would type the return
 * as the interface. {@see T3Payloads} maps the kind to the class and calls it;
 * the lint above is what keeps the five in step.
 */
interface T3Operation
{
    public function kind(): T3InjectionKind;

    /**
     * The operation as it travels to the module.
     *
     * ⚠️ **KEYS ARE SHORT BECAUSE THIS IS FETCHED FROM A STRANGER'S PAGE** —
     * every byte is on somebody else's page-load budget. The first key is always
     * `t`, which is the only thing the module switches on.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array;
}
