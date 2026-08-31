{{--
    Ops → Platform → internal users & roles (`28` §9.2, §9.1).

    ⚠️ THIS IS THE ONLY PLACE A ROLE CAN BE GRANTED, AND UNTIL 2026-08-04 THERE
    WAS NO PLACE AT ALL (decision 740). Everything the console gates — the audit
    explorer, the support gate, both impersonation modes, mandatory two-factor —
    reads a column nothing could write.

    OUTCOME LANGUAGE (`22`): the roles are named for what the person may do, and
    revocation says "No access" rather than "none". Colour is not the signal —
    a revoked account is named in words, not in red.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">People who work here</h1>
        <p class="mt-1 text-base text-ink-2">
            Everyone with access to this console, and what each of them may do. Adding
            somebody here gives them standing access to customer accounts, so every
            change is recorded with the reason you give.
        </p>
    </div>

    <div>
        <x-ui.button wire:click="startCreate" type="button">Add someone</x-ui.button>
    </div>

    @if ($creating)
        <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">Add someone</h2>

            {{--
                ⚠️ NO PASSWORD FIELD, AND THERE WILL NOT BE ONE (decision
                747). An operator setting somebody else's first credential
                has to send it to them, and the account it opens reaches
                every customer. They sign in with a link instead.
            --}}
            <p class="mt-1 text-base text-ink-2">
                They will sign in with an emailed link and set up two-factor
                authentication before they can reach anything. No password is set here.
            </p>

            <form wire:submit="createAccount" class="mt-4 space-y-4">
                <label class="flex max-w-md flex-col gap-1">
                    <span class="text-sm font-medium text-ink-2">Name</span>
                    <input
                        type="text"
                        wire:model="name"
                        autocomplete="off"
                        class="min-h-11 rounded-[--radius-control] border border-rule bg-card px-3 text-base text-ink"
                    />
                    @error('name')
                        <span class="text-sm text-danger">{{ $message }}</span>
                    @enderror
                </label>

                <label class="flex max-w-md flex-col gap-1">
                    <span class="text-sm font-medium text-ink-2">Work email</span>
                    <input
                        type="email"
                        wire:model="email"
                        autocomplete="off"
                        class="min-h-11 rounded-[--radius-control] border border-rule bg-card px-3 text-base text-ink"
                    />
                    @error('email')
                        <span class="text-sm text-danger">{{ $message }}</span>
                    @enderror
                </label>

                <label class="flex max-w-md flex-col gap-1">
                    <span class="text-sm font-medium text-ink-2">Role</span>
                    <select
                        wire:model="createRole"
                        class="min-h-11 rounded-[--radius-control] border border-rule bg-card px-3 text-base text-ink"
                    >
                        <option value="">Choose a role</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}">{{ $role->label() }}</option>
                        @endforeach
                    </select>
                    @error('createRole')
                        <span class="text-sm text-danger">{{ $message }}</span>
                    @enderror
                </label>

                <label class="flex max-w-md flex-col gap-1">
                    <span class="text-sm font-medium text-ink-2">Why they need it</span>
                    <textarea
                        wire:model="createReason"
                        rows="2"
                        class="rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                    ></textarea>
                    @error('createReason')
                        <span class="text-sm text-danger">{{ $message }}</span>
                    @enderror
                </label>

                <div class="flex flex-wrap gap-3">
                    <x-ui.submit target="createAccount" busy="Adding…">Add them</x-ui.submit>
                    <button
                        type="button"
                        wire:click="cancel"
                        class="min-h-11 rounded-[--radius-control] border border-rule px-4 text-base text-ink-2 hover:text-ink"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </section>
    @endif

    <div class="overflow-x-auto rounded-[--radius-card] border border-rule bg-card shadow-[--shadow-card]">
        <table class="w-full min-w-[48rem] border-collapse text-left">
            <caption class="sr-only">Internal accounts and their roles</caption>
            <thead>
                <tr class="border-b border-rule">
                    <th scope="col" class="px-4 py-3 text-sm font-semibold text-ink-2">Who</th>
                    <th scope="col" class="px-4 py-3 text-sm font-semibold text-ink-2">What they may do</th>
                    <th scope="col" class="px-4 py-3 text-sm font-semibold text-ink-2">Last signed in</th>
                    <th scope="col" class="px-4 py-3 text-right text-sm font-semibold text-ink-2">Change</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($staff as $member)
                    <tr wire:key="staff-{{ $member->getKey() }}" class="border-b border-rule last:border-0">
                        <td class="px-4 py-3 text-base text-ink">
                            {{ $member->name }}
                            <span class="block text-sm text-ink-3">{{ $member->email }}</span>
                        </td>
                        <td class="px-4 py-3 text-base text-ink">{{ $member->role->label() }}</td>
                        <td class="px-4 py-3 text-base text-ink-2">
                            {{ $member->last_login_at?->diffForHumans() ?? 'Never' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button
                                type="button"
                                wire:click="startChange({{ $member->getKey() }})"
                                class="min-h-11 text-base text-ink-2 underline hover:text-ink"
                            >
                                Change role
                            </button>
                        </td>
                    </tr>

                    @if ($editing === $member->getKey())
                        <tr wire:key="editing-{{ $member->getKey() }}" class="border-b border-rule last:border-0">
                            <td colspan="4" class="px-4 py-4">
                                <form wire:submit="changeRole" class="space-y-4">
                                    <p class="text-base text-ink">
                                        Change what {{ $member->name }} may do.
                                    </p>

                                    <label class="flex max-w-md flex-col gap-1">
                                        <span class="text-sm font-medium text-ink-2">New role</span>
                                        <select
                                            wire:model="newRole"
                                            class="min-h-11 rounded-[--radius-control] border border-rule bg-card px-3 text-base text-ink"
                                        >
                                            <option value="">Choose a role</option>
                                            @foreach ($roles as $role)
                                                <option value="{{ $role->value }}">{{ $role->label() }}</option>
                                            @endforeach
                                        </select>
                                        @error('newRole')
                                            <span class="text-sm text-danger">{{ $message }}</span>
                                        @enderror
                                    </label>

                                    <label class="flex max-w-md flex-col gap-1">
                                        <span class="text-sm font-medium text-ink-2">Why</span>
                                        <textarea
                                            wire:model="changeReason"
                                            rows="2"
                                            class="rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                                        ></textarea>
                                        @error('changeReason')
                                            <span class="text-sm text-danger">{{ $message }}</span>
                                        @enderror
                                    </label>

                                    <div class="flex flex-wrap gap-3">
                                        <x-ui.submit target="changeRole" busy="Saving…">Save the change</x-ui.submit>
                                        <button
                                            type="button"
                                            wire:click="cancel"
                                            class="min-h-11 rounded-[--radius-control] border border-rule px-4 text-base text-ink-2 hover:text-ink"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-base text-ink-2">
                            {{--
                                ⚠️ Unreachable in practice and rendered anyway: reading
                                this screen requires an account that would appear on it.
                                It is here so the empty branch is not the untested one
                                the day somebody opens it another way.
                            --}}
                            Nobody has an internal role yet. Run
                            <code class="font-mono text-sm">php artisan staff:grant</code>
                            on the server to grant the first one.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="text-base text-ink-2">
        Every change here is recorded with who made it and why. Read it back under
        <a href="{{ route('admin.audit-staff') }}" class="underline">What our staff have done</a>.
    </p>
</div>
