{{--
    Autopilot settings for one location.

    ⛔ THE NO-ACCOUNT BRANCH IS THE FIX, NOT DECORATION (5733, 9236). Every read
    behind this screen is tenant-scoped and the path names only a location, so
    the screen has to be told whose account that location is — platform staff
    own no business and have no tenant, and reading under whichever tenant
    happened to be in context is the quiet wrong answer. No account named runs
    no query at all.

    ⚠️ THE DETAIL AND FORM DATA ARE HANDED IN RATHER THAN PULLED (9237). This
    view used to call `$this->detailSections()` and `$this->visibleFields()`,
    which open with `Tenancy::idOrFail()` — so the view was where the tenant
    demand lived and there was nowhere for the component to establish one. The
    component now builds both inside `Tenancy::actingAs()` and passes them.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">What runs on its own</h1>
        <p class="mt-1 text-base text-ink-2">
            One location's autopilot settings.
            Opening an account is recorded in that account's own trail.
        </p>
    </div>

    @if ($location === null)
        <form wire:submit="resolve" class="max-w-md space-y-3">
            <label class="flex flex-col gap-1">
                <span class="text-sm font-medium text-ink-2">Account number</span>
                <input
                    type="text"
                    inputmode="numeric"
                    wire:model="reference"
                    class="min-h-11 rounded-[--radius-control] border border-rule bg-card px-3 text-base text-ink"
                    placeholder="From the ticket"
                    @error('reference') aria-invalid="true" aria-describedby="reference-error" @enderror
                />
            </label>

            <p class="text-sm text-ink-3">
                Location {{ $locationId }}, once you say which account it belongs to.
            </p>

            @error('reference')
                {{-- Text, not colour alone (`22`). --}}
                <p id="reference-error" class="text-sm text-danger">{{ $message }}</p>
            @enderror

            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="resolve"
                class="min-h-11 rounded-[--radius-control] bg-ink px-4 text-base font-medium text-surface"
            >
                <span wire:loading.remove wire:target="resolve">Show this location's settings</span>
                <span wire:loading wire:target="resolve">Opening…</span>
            </button>
        </form>
    @else
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-[--radius-card] border border-rule bg-card px-4 py-3">
            <p class="text-base text-ink">
                <span class="text-ink-2">Account</span>
                <span class="font-medium">{{ $businessName }}</span>
                <span class="font-mono text-sm text-ink-3">#{{ $businessId }}</span>
                <span class="text-ink-2">·</span>
                <span class="font-medium">{{ $location->name }}</span>
            </p>

            <button
                type="button"
                wire:click="clearAccount"
                class="min-h-11 rounded-[--radius-control] border border-rule px-4 text-base text-ink-2 hover:text-ink"
            >
                Open a different account
            </button>
        </div>

        <x-admin.detail :sections="$sections" />

        <x-admin.form :fields="$fields" :saved="$saved" />
    @endif
</div>
