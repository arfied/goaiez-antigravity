<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Services\Ops\WebhookMaterialCensus;
use App\Support\WebhookMaterial;

/**
 * A verifier that can say what it judges callers with, without being called
 * (11640–11651).
 *
 * ⛔ **STATIC, AND THAT IS THE WHOLE POINT RATHER THAN A CONVENIENCE.** The
 * question this interface answers has to be answerable **at rest, with zero
 * traffic** — before any request, from a console command running at deploy — so
 * it may not depend on a request, on an instance the container has configured
 * for one, or on anything the caller sends. A verifier is reached from a route
 * by reflection over the controller's own parameter types
 * ({@see WebhookMaterialCensus}), which never constructs it.
 *
 * ⚠️ **REFLECTION RATHER THAN A GREP, AND THAT IS NOT A STYLE CHOICE** (10203).
 * A needle that is a PHP identifier is matched case-insensitively by PHP and
 * case-sensitively by `grep`, so a textual census over verifier class names
 * narrows silently the day somebody spells one differently. Nothing here is
 * matched textually: the route names a controller, PHP resolves the controller's
 * parameter types, and `instanceof` answers. **The failure mode a grep has is
 * structurally absent rather than guarded against.**
 *
 * ⛔ **DECLARING IS NOT VERIFYING AND NOTHING HERE CLAIMS OTHERWISE.** This says
 * *what would be needed*; it does not say the value is correct, that the vendor
 * is signing with it, or that a delivery would be accepted. A wrong secret and a
 * right one are indistinguishable from here and always will be — the only thing
 * that tells them apart is a genuine delivery, which is exactly the traffic this
 * interface exists to stop being the first reporter.
 */
interface VerifiesWebhookSenders
{
    /**
     * The material this verifier judges callers with.
     */
    public static function verifyingMaterial(): WebhookMaterial;
}
