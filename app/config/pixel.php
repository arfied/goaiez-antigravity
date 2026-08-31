<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | The address a tenant's install snippet points at
    |--------------------------------------------------------------------------
    |
    | `GOAIEZ_PIXEL_MASTER_BUILD.md` §10's Install block names it literally:
    | `<script async src="https://t.goaiez.com/p.js" data-k="pk_live_XXXXXXXX">`.
    | Named here rather than written into a Blade view so the install screen and
    | this file can point at it once, instead of the address living in whichever
    | template happened to render it first.
    |
    | ⛔ **"THIS IS A SEAM, NOT A DELIVERY" WAS TRUE WHEN THIS FILE WAS WRITTEN
    | AND IS NOT — CORRECTED 2026-08-18 (decision 5147).** It read: *"§10 also
    | specifies an immutable `/v/<sha>/p.js` artefact behind a short-lived
    | `/p.js` pointer, a 1% canary and a one-command rollback — none of it built
    | as of this file (decision 4979 item 1). Nothing here serves that route or
    | claims to."* **All of it is built**: `PixelBundleController` serves both
    | routes out of `pixel_bundle_versions`, and `pixel:publish`,
    | `pixel:rollback` and `pixel:watch-canary` are the three commands
    | (4980–4998). Both readings are kept and dated on 4368's rule.
    |
    | ⚠️ **WHAT SURVIVES OF IT IS THE HALF THAT MATTERS TO WHOEVER SETS THIS
    | VALUE.** This is still only the host and path an install screen renders
    | into a snippet — it points nothing at anything. **`/p.js` serves an empty
    | 200 until somebody runs `pixel:publish`, and a deploy does not run it**
    | (4990, 4997), so a correct value here and no publish is a live address
    | that serves no bundle. ⚠️ **And no real page has ever named this address**
    | (5141): a route existing is not traffic arriving.
    |
    | ⛔ **THIS VALUE'S *ORIGIN* IS ALSO THE COLLECTOR'S ADDRESS, WHICH IS NOT
    | OBVIOUS FROM ITS NAME AND IS THE ONE THING TO GET RIGHT** (decision 5052).
    | `resources/js/pixel.js` does not compile an endpoint in — it derives one:
    | `new URL(self.src, location.href).origin + '/api/pixel/e'`, deliberately,
    | so that this application answering on more than one hostname over its life
    | cannot strand a tenant's page on the wrong one. The consequence runs the
    | other way too: **whatever host is named here must serve both the bundle
    | and `POST /api/pixel/e`.** Point this at a CDN that serves only the file
    | and every install collects nothing.
    |
    | ⛔ **AND THAT FAILURE IS SILENT FROM BOTH ENDS**, which is why it is
    | written here rather than left to be discovered. The bundle swallows every
    | transport error by design — §10's *"the right behaviour on somebody else's
    | website is to say nothing at all"* — and the collector answers `204` to an
    | unknown key. Nobody gets an error: not the visitor, not the tenant, not
    | us. The install screen shows a correct-looking line throughout.
    |
    */

    'install_script_url' => env('PIXEL_SCRIPT_URL', 'https://t.goaiez.com/p.js'),

];
