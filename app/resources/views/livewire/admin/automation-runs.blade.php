<div class="space-y-6">
    <div class="border-b border-rule pb-4">
        <h1 class="font-display text-2xl font-bold text-ink sm:text-3xl">Automation Runs</h1>
        <p class="mt-1 text-sm text-ink-2">
            Audit trail of every automated task executed by the platform on behalf of a tenant.
        </p>
    </div>

    {{--
        ⛔ THE NO-ACCOUNT BRANCH IS THE FIX, NOT DECORATION. A tenant-scoped
        table reached with no tenant used to throw TenantNotResolved, and this
        is the first screen a member of staff lands on after signing in — so the
        500 was on the happy path of every staff login (decision 5730). No
        account named is a state this screen renders, and no query runs at all.
    --}}
    @if ($businessId === null)
        <div class="max-w-md rounded-[--radius-card] border border-rule bg-card p-6 shadow-sm">
            <h2 class="text-base font-semibold text-ink mb-1">Select Tenant Account</h2>
            <p class="text-xs text-ink-2 mb-4">Enter a Business ID to inspect its automated activity trail.</p>

            <form wire:submit="resolve" class="space-y-4">
                <label class="flex flex-col gap-1.5">
                    <span class="text-xs font-semibold text-ink-2">Account number</span>
                    <input
                        type="text"
                        inputmode="numeric"
                        wire:model="reference"
                        class="min-h-11 rounded-[--radius-control] border border-rule-strong bg-paper px-3 text-sm text-ink focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none"
                        placeholder="e.g. 736"
                        @error('reference') aria-invalid="true" aria-describedby="reference-error" @enderror
                    />
                </label>

                @error('reference')
                    <p id="reference-error" class="text-xs text-alert font-medium">{{ $message }}</p>
                @enderror

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="resolve"
                    class="min-h-11 w-full rounded-[--radius-control] bg-ink px-4 text-sm font-semibold text-paper focus-visible:outline-2 focus-visible:outline-offset-2 hover:opacity-90 transition shadow-sm"
                >
                    <span wire:loading.remove wire:target="resolve">Inspect Automation Runs</span>
                    <span wire:loading wire:target="resolve">Opening Account…</span>
                </button>
            </form>
        </div>
    @else
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-[--radius-card] border border-rule bg-card px-5 py-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="h-9 w-9 rounded-full bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 font-bold flex items-center justify-center text-sm">
                    #{{ $businessId }}
                </div>
                <div>
                    <div class="text-xs text-ink-3 uppercase font-semibold">Active Account Context</div>
                    <div class="text-base font-bold text-ink">{{ $businessName }}</div>
                </div>
            </div>

            <button
                type="button"
                wire:click="clearAccount"
                class="min-h-9 rounded-[--radius-control] border border-rule px-3 text-xs text-ink-2 hover:text-ink font-medium bg-paper transition"
            >
                Switch Account
            </button>
        </div>

        <div class="flex flex-wrap items-end gap-3 rounded-[--radius-card] border border-rule bg-card p-4 shadow-sm">
            <label class="flex flex-col gap-1 flex-1 min-w-[200px]">
                <span class="text-xs font-semibold text-ink-2">Search Automations</span>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    class="min-h-10 rounded-[--radius-control] border border-rule-strong bg-paper px-3 text-sm text-ink"
                    placeholder="Search by automation key..."
                />
            </label>

            <label class="flex flex-col gap-1 min-w-[150px]">
                <span class="text-xs font-semibold text-ink-2">Status Filter</span>
                <select
                    wire:model.live="activeFilters.status"
                    class="min-h-10 rounded-[--radius-control] border border-rule-strong bg-paper px-3 text-sm text-ink"
                >
                    <option value="">All Statuses</option>
                    @foreach ($this->filterOptions('status') as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <button
                type="button"
                wire:click="clearFilters"
                class="min-h-10 rounded-[--radius-control] border border-rule px-4 text-xs font-medium text-ink-2 hover:text-ink bg-paper"
            >
                Reset
            </button>
        </div>

        @if ($runs !== null)
            <x-admin.table
                :columns="$this->columns()"
                :rows="$runs"
                :sort-column="$sortColumn"
                :sort-direction="$sortDirection"
                empty="No automation runs match this criteria."
            />
        @endif
    @endif
</div>
