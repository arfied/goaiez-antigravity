<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">What our staff have done</h1>
        <p class="mt-1 text-base text-ink-2">
            Every time one of us went into a customer's account, and every platform setting we moved.
            Work someone did inside their own account is in that account's own trail.
        </p>
    </div>

    <div class="flex flex-wrap items-end gap-3">
        <label class="flex flex-col gap-1">
            <span class="text-sm font-medium text-ink-2">Staff member</span>
            <input
                type="text"
                inputmode="numeric"
                wire:model.live.debounce.300ms="agent"
                class="min-h-11 rounded-[--radius-control] border border-rule bg-card px-3 text-base text-ink"
                placeholder="User number — blank for everyone"
            />
        </label>

        <button
            type="button"
            wire:click="clearAgent"
            class="min-h-11 rounded-[--radius-control] border border-rule px-4 text-base text-ink-2 hover:text-ink"
        >
            Everyone
        </button>
    </div>

    <section class="space-y-3">
        <h2 class="font-display text-lg font-semibold text-ink">Visits to customers' accounts</h2>

        <div tabindex="0" class="overflow-x-auto rounded-[--radius-card] border border-rule bg-card shadow-[--shadow-card]">
            <table class="w-full min-w-[52rem] border-collapse text-left">
                <caption class="sr-only">Support sessions</caption>
                <thead>
                    <tr class="border-b border-rule">
                        <th scope="col" class="px-4 py-3 text-sm font-semibold text-ink-2">Who</th>
                        <th scope="col" class="px-4 py-3 text-sm font-semibold text-ink-2">Account</th>
                        <th scope="col" class="px-4 py-3 text-sm font-semibold text-ink-2">What they could do</th>
                        <th scope="col" class="px-4 py-3 text-sm font-semibold text-ink-2">Why</th>
                        <th scope="col" class="px-4 py-3 text-sm font-semibold text-ink-2">Started</th>
                        <th scope="col" class="px-4 py-3 text-right text-sm font-semibold text-ink-2">Read</th>
                        <th scope="col" class="px-4 py-3 text-right text-sm font-semibold text-ink-2">Changed</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sessions as $session)
                        <tr wire:key="session-{{ $session->getKey() }}" class="border-b border-rule last:border-0">
                            <td class="px-4 py-3 text-base text-ink">
                                {{ $session->agent?->name ?? 'Deleted staff account' }}
                                <span class="font-mono text-sm text-ink-3">#{{ $session->agent_id }}</span>
                            </td>
                            {{--
                                ⚠️ The number, not the name, and that is a
                                boundary rather than a missing join. `Business`
                                is tenant-scoped and a platform admin has no
                                tenant, so naming every account on this page
                                means opening each one in turn — decision 569's
                                account list, assembled as a side effect of a
                                table cell. The operator takes the number to the
                                account trail, where opening it is recorded.
                            --}}
                            <td class="px-4 py-3 font-mono text-base text-ink">#{{ $session->business_id }}</td>
                            {{--
                                The mode in the owner's terms rather than ours.
                                "view" and "act" are the column's values; what a
                                reader needs is which of them could change
                                something, so the word says that (`22`).
                            --}}
                            <td class="px-4 py-3 text-base text-ink">
                                {{ $session->mode === \App\Enums\ImpersonationMode::Act ? 'Make changes' : 'Look only' }}
                            </td>
                            <td class="px-4 py-3 text-base text-ink">
                                {{ $session->reason }}
                                @if ($session->ticket_ref !== null)
                                    <span class="font-mono text-sm text-ink-3">{{ $session->ticket_ref }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-base text-ink">{{ $session->started_at->toDayDateTimeString() }}</td>
                            <td class="px-4 py-3 text-right font-mono text-base tabular-nums text-ink">{{ $session->page_views }}</td>
                            <td class="px-4 py-3 text-right font-mono text-base tabular-nums text-ink">{{ $session->writes }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-ink-2">
                                Nobody has been into a customer's account.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($sessions->hasPages())
            <div>{{ $sessions->links() }}</div>
        @endif
    </section>

    <section class="space-y-3">
        <h2 class="font-display text-lg font-semibold text-ink">Platform settings they changed</h2>

        <div tabindex="0" class="overflow-x-auto rounded-[--radius-card] border border-rule bg-card shadow-[--shadow-card]">
            <table class="w-full min-w-[44rem] border-collapse text-left">
                <caption class="sr-only">Platform setting changes</caption>
                <thead>
                    <tr class="border-b border-rule">
                        <th scope="col" class="px-4 py-3 text-sm font-semibold text-ink-2">Who</th>
                        <th scope="col" class="px-4 py-3 text-sm font-semibold text-ink-2">Setting</th>
                        <th scope="col" class="px-4 py-3 text-sm font-semibold text-ink-2">Was</th>
                        <th scope="col" class="px-4 py-3 text-sm font-semibold text-ink-2">Now</th>
                        <th scope="col" class="px-4 py-3 text-sm font-semibold text-ink-2">When</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($settingChanges as $change)
                        <tr wire:key="change-{{ $change->getKey() }}" class="border-b border-rule last:border-0">
                            <td class="px-4 py-3 font-mono text-base text-ink">{{ $change->actor }}</td>
                            <td class="px-4 py-3 font-mono text-base text-ink">{{ $change->setting_key }}</td>
                            {{-- "was not set" rather than an empty cell: the column
                                 is nullable to distinguish an absent row from a
                                 stored null, and that distinction is the reason
                                 it is nullable at all. --}}
                            <td class="px-4 py-3 font-mono text-base text-ink-2">
                                {{ $change->value_before === null ? 'was not set' : json_encode($change->value_before, JSON_UNESCAPED_SLASHES) }}
                            </td>
                            <td class="px-4 py-3 font-mono text-base text-ink">
                                {{ json_encode($change->value_after, JSON_UNESCAPED_SLASHES) }}
                            </td>
                            <td class="px-4 py-3 text-base text-ink">{{ $change->created_at->toDayDateTimeString() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-ink-2">
                                No platform setting has been changed.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($settingChanges->hasPages())
            <div>{{ $settingChanges->links() }}</div>
        @endif
    </section>

    {{--
        `28` §9.1: "every internal login and role change → audit_log". ⚠️ It
        cannot be `audit_log` — that table is NOT NULL on business_id and RLS
        keyed on it, and internal staff belong to no business (741) — so it is
        `staff_events`, which has no tenant and therefore widens nothing.
    --}}
    <section class="space-y-3">
        <h2 class="font-display text-lg font-semibold text-ink">Access and sign-ins</h2>

        <div tabindex="0" class="overflow-x-auto rounded-[--radius-card] border border-rule bg-card shadow-[--shadow-card]">
            <table class="w-full min-w-[48rem] border-collapse text-left">
                <caption class="sr-only">Internal access changes and sign-ins</caption>
                <thead>
                    <tr class="border-b border-rule">
                        <th scope="col" class="px-4 py-3 text-sm font-semibold text-ink-2">Who did it</th>
                        <th scope="col" class="px-4 py-3 text-sm font-semibold text-ink-2">What</th>
                        <th scope="col" class="px-4 py-3 text-sm font-semibold text-ink-2">Whose account</th>
                        <th scope="col" class="px-4 py-3 text-sm font-semibold text-ink-2">Why</th>
                        <th scope="col" class="px-4 py-3 text-sm font-semibold text-ink-2">When</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($staffEvents as $event)
                        <tr wire:key="staff-event-{{ $event->getKey() }}" class="border-b border-rule last:border-0">
                            <td class="px-4 py-3 font-mono text-base text-ink">{{ $event->actor }}</td>
                            <td class="px-4 py-3 text-base text-ink">
                                {{ $event->event->label() }}
                                @if ($event->role_after !== null)
                                    <span class="block text-sm text-ink-2">
                                        {{ $event->role_before?->label() ?? 'New account' }}
                                        &rarr;
                                        {{ $event->role_after->label() }}
                                    </span>
                                @endif
                            </td>
                            {{--
                                `User` is not tenant-owned, so a name is free here
                                where a business name was not (626).
                            --}}
                            <td class="px-4 py-3 text-base text-ink">
                                {{ $event->subject?->name ?? 'Deleted account' }}
                                <span class="font-mono text-sm text-ink-3">#{{ $event->subject_user_id }}</span>
                            </td>
                            <td class="px-4 py-3 text-base text-ink-2">{{ $event->reason ?? '—' }}</td>
                            <td class="px-4 py-3 text-base text-ink">{{ $event->created_at->toDayDateTimeString() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-ink-2">
                                No internal access change or sign-in is recorded.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($staffEvents->hasPages())
            <div>{{ $staffEvents->links() }}</div>
        @endif
    </section>
</div>
