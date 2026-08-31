<div class="space-y-6">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Who has touched this account</h1>
        <p class="mt-1 text-base text-ink-2">
            Every recorded action on one customer's account — ours, theirs, and the system's.
            Opening an account is itself recorded here.
        </p>
    </div>

    @if ($businessId === null)
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

            @error('reference')
                <p id="reference-error" class="text-sm text-danger">{{ $message }}</p>
            @enderror

            {{--
                The label swaps rather than a spinner appearing beside it: the
                verb survives the flow (`22`), and the disabled attribute is
                what stops a second press opening the same trail twice.
            --}}
            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="resolve"
                class="min-h-11 rounded-[--radius-control] bg-ink px-4 text-base font-medium text-surface"
            >
                <span wire:loading.remove wire:target="resolve">Open the trail</span>
                <span wire:loading wire:target="resolve">Opening…</span>
            </button>
        </form>
    @else
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-[--radius-card] border border-rule bg-card px-4 py-3">
            <p class="text-base text-ink">
                <span class="text-ink-2">Account</span>
                <span class="font-medium">{{ $businessName }}</span>
                <span class="font-mono text-sm text-ink-3">#{{ $businessId }}</span>
            </p>

            <button
                type="button"
                wire:click="clearAccount"
                class="min-h-11 rounded-[--radius-control] border border-rule px-4 text-base text-ink-2 hover:text-ink"
            >
                Open a different account
            </button>
        </div>

        <div class="flex flex-wrap items-end gap-3">
            <label class="flex flex-col gap-1">
                <span class="text-sm font-medium text-ink-2">Search</span>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    class="min-h-11 rounded-[--radius-control] border border-rule bg-card px-3 text-base text-ink"
                    placeholder="Who or what"
                />
            </label>

            <button
                type="button"
                wire:click="clearFilters"
                class="min-h-11 rounded-[--radius-control] border border-rule px-4 text-base text-ink-2 hover:text-ink"
            >
                Clear
            </button>
        </div>

        @if ($entries !== null)
            <x-admin.table
                :columns="$this->visibleColumns()"
                :rows="$entries"
                :sort-column="$sortColumn"
                :sort-direction="$sortDirection"
                caption="Audit trail"
                empty="Nothing has been recorded against this account yet."
                {{--
                    The only thing a reader can do from here, and it is honest
                    on both readings of an empty trail: a search may be hiding
                    the entries, and where it is not, clearing it costs one
                    press and shows the same empty table — which is itself the
                    answer to "has anything really happened on this account?".
                --}}
                empty-action="Clear the search"
                empty-action-target="clearFilters"
            />

            {{--
                The detail an entry carries. `recordChange()` stores before and
                after, and a trail that shows only the verb cannot answer what
                happened — which is the question `29` §2 rule 42 exists for.

                Rendered as escaped text, never as markup. `AuditLogEntry`'s own
                rule is that metadata names an entity by type and id and carries
                no customer personal data, and this is written on the assumption
                that the rule holds rather than on the assumption that it cannot
                be broken.
            --}}
            @if ($entries->isNotEmpty())
                <div class="space-y-2">
                    <h2 class="text-sm font-medium text-ink-2">Detail</h2>
                    <ul class="flex flex-wrap gap-2">
                        @foreach ($entries as $entry)
                            <li>
                                <button
                                    type="button"
                                    wire:click="inspect({{ $entry->getKey() }})"
                                    aria-expanded="{{ $inspecting === $entry->getKey() ? 'true' : 'false' }}"
                                    @class([
                                        'min-h-11 rounded-[--radius-control] border px-3 font-mono text-sm',
                                        'border-ink bg-ink text-surface' => $inspecting === $entry->getKey(),
                                        'border-rule text-ink-2 hover:text-ink' => $inspecting !== $entry->getKey(),
                                    ])
                                >
                                    #{{ $entry->getKey() }}
                                </button>
                            </li>
                        @endforeach
                    </ul>

                    @if ($metadata !== null)
                        <div class="rounded-[--radius-card] border border-rule bg-card p-4">
                            @if ($metadata === [])
                                <p class="text-base text-ink-2">Entry #{{ $inspecting }} carries no detail.</p>
                            @else
                                <pre class="overflow-x-auto text-sm text-ink">{{ json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                            @endif
                        </div>
                    @endif
                </div>
            @endif
        @endif
    @endif
</div>
