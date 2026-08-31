<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ImpersonationEnding;
use App\Services\Impersonation\Impersonation;
use Illuminate\Http\RedirectResponse;

/**
 * Getting out (`28` §9.4's "[End session]").
 *
 * A controller rather than a Livewire action, and the reason is the banner: it
 * is injected into every response as plain HTML, including responses that no
 * Livewire component rendered — error pages, the tenant's public feedback page,
 * a redirect target. A `wire:click` there would do nothing on most of the pages
 * where an agent most wants it to work.
 *
 * ⚠️ **No authorization check, on purpose.** Ending a session is the one action
 * that must never be refused: it removes access rather than granting it, and a
 * support tool that can be entered and not left is one people leave running.
 * `stop()` is safe when there is nothing to stop.
 */
final class ImpersonationController extends Controller
{
    public function __invoke(Impersonation $impersonation): RedirectResponse
    {
        $impersonation->stop(ImpersonationEnding::Ended);

        return redirect()
            ->route('support.accounts')
            ->with('status', 'Support session ended.');
    }
}
