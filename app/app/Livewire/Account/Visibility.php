<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Models\Location;
use App\Services\Tenant\LocationContext;
use App\Services\Visibility\LocalVisibility;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Normal Local Visibility — `28` §5.3 + §5.5.
 *
 * Search movement (GSC), competitor sentence (Places, unnamed), and honest
 * unavailable copy for Maps impressions and the catchment map. Never renders
 * an empty map as a finding (BUILD-PLAN row 15 gate).
 *
 * ⚠️ **THE NUMBERS ARE PER LOCATION AND WERE ONCE SHOWN FOR AN ARBITRARY ONE.**
 * This screen took `orderBy('id')->first()`, so every figure on the page —
 * search movement, the competitor average, our own rating — silently described
 * whichever location was created first, under a heading that says "you". A wrong
 * number presented confidently is worse than an absent one (1220), so the whole
 * report was suppressed for a multi-location tenant with an honest sentence in
 * its place.
 *
 * ✅ **AND THIS FILE ASKED FOR THE FIX IT NOW HAS**: *"this application has no
 * location picker anywhere; when one exists, this is one of the screens that
 * gets it."* It exists (3060–3079). The suppression sentence is replaced by
 * `<x-account.location-picker>`, so the figures are shown for a location the
 * owner named rather than for one nobody chose — which is the distinction 1220
 * was drawing, not a rule against defaults.
 */
#[Layout('components.account.layout')]
final class Visibility extends Component
{
    public function render(LocalVisibility $visibility, LocationContext $locations): View
    {
        abort_if(Tenancy::id() === null, 403);

        $location = $locations->current();

        return view('livewire.account.visibility', [
            'location' => $location,
            'report' => $location instanceof Location
                ? $visibility->for($location)
                : null,
            'locationOptions' => $locations->options(),
            'selectedLocation' => $location,
        ]);
    }
}
