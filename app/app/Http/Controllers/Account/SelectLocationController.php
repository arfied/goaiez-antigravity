<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\SelectLocationRequest;
use App\Models\Location;
use App\Services\Tenant\LocationContext;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Point the account screens at one of this tenant's locations (3060–3079).
 *
 * ⚠️ **A NAMED POST ROUTE RATHER THAN A LIVEWIRE ACTION**, which is
 * `CancelSubscriptionController`'s and `TenantExportRequestController`'s shape
 * and is taken here for the reason those two state: **`Livewire::test()` runs no
 * middleware** (809), so a Livewire action leaves `auth`, CSRF, `ResolveTenant`
 * and the form request's `authorize()` unexercised by anything the suite runs.
 * A picker whose tenant check has never met a real request is a picker whose
 * tenant check is a claim.
 *
 * ⚠️ **AND IT MEANS THE CONTROL WORKS WITHOUT JAVASCRIPT.** The picker is a
 * plain `<form method="POST">` with a `<select>` and a submit — every account
 * screen reloads under the new location, which is the honest behaviour, because
 * the *whole screen's* meaning changes rather than one panel of it. A
 * `wire:model.live` would have swapped one panel and left the rest of the page
 * describing the location the owner had just left.
 *
 * ⚠️ **`back()` RATHER THAN A NAMED DESTINATION.** The picker renders on four
 * different screens and the owner expects to stay on the one they were reading.
 * Laravel's `back()` resolves the previous URL from the session rather than from
 * the `Referer` header, so this is not a redirect a caller can aim.
 */
final class SelectLocationController extends Controller
{
    public function __invoke(SelectLocationRequest $request, LocationContext $locations): RedirectResponse
    {
        // The scoped read, which is the *outer* guard: the global scope and RLS
        // beneath it both refuse another tenant's row, so this is already null
        // long before LocationContext::select() is reached. It is written anyway
        // because 398's rule cuts both ways — the inner guard being the one
        // worth driving red does not make the outer one optional.
        $location = Location::query()->whereKey($request->integer('location'))->first();

        abort_if(! $location instanceof Location, Response::HTTP_NOT_FOUND);

        $locations->select($location);

        // No toast. The reloaded page shows the location's name in the very
        // control the owner just used, so a toast would announce an outcome that
        // is already visible in the thing they pressed — and decision 104 keeps
        // toasts for outcomes somebody would otherwise not see.
        return back();
    }
}
