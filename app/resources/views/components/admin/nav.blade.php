@props(['groups'])

{{--
    Filtered to what the viewer may reach. Hiding an item is a courtesy and a way
    of not enumerating the platform's capabilities — never the authorization
    itself, which lives on the route.
--}}

<nav aria-label="Admin" class="space-y-6">
    <!-- Console Brand & Role Header -->
    <div class="px-3 pb-4 border-b border-rule">
        <div class="flex items-center justify-between">
            <span class="font-display text-lg font-bold tracking-tight text-ink">
                {{ config('app.name') }}
            </span>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-800 dark:bg-indigo-900/60 dark:text-indigo-300 uppercase tracking-wider">
                Staff Ops
            </span>
        </div>
        <div class="mt-1 text-xs text-ink-3">
            Internal Platform Console
        </div>
    </div>

    @forelse ($groups as $group => $items)
        <div>
            <h2 class="px-3 text-xs font-bold uppercase tracking-wider text-ink-3">{{ $group }}</h2>

            <ul class="mt-2 space-y-1">
                @foreach ($items as $item)
                    @php $current = request()->routeIs($item->route); @endphp
                    <li>
                        <a
                            href="{{ route($item->route) }}"
                            @if ($current) aria-current="page" @endif
                            class="flex min-h-10 items-center justify-between rounded-[--radius-control] px-3 text-sm transition {{ $current ? 'bg-card font-semibold text-ink border-l-2 border-indigo-600 shadow-sm' : 'text-ink-2 hover:text-ink hover:bg-card/50 font-medium' }}"
                        >
                            <span>{{ $item->label }}</span>
                            @if ($current)
                                <span class="h-1.5 w-1.5 rounded-full bg-indigo-600"></span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @empty
        <p class="px-3 text-sm text-ink-3">Nothing here for your account.</p>
    @endforelse

        <!-- Generated Operator Routes -->
    <div class="pt-4 border-t border-rule mt-4">
        <div class="px-3 text-[10px] font-bold text-indigo-400 uppercase tracking-wider mb-2">Modules (Generated)</div>
        @foreach (config('surfaces.generated.operator', []) as $group => $items)
            <div class="mt-4">
                <h2 class="px-3 text-xs font-bold uppercase tracking-wider text-ink-3">{{ $group }}</h2>
                <ul class="mt-2 space-y-1">
                    @foreach ($items as $item)
                        @php $current = request()->routeIs($item['route']); @endphp
                        <li>
                            <a
                                href="{{ route($item['route']) }}"
                                @if ($current) aria-current="page" @endif
                                class="flex min-h-10 items-center justify-between rounded-[--radius-control] px-3 text-sm transition {{ $current ? 'bg-card font-semibold text-ink border-l-2 border-indigo-600 shadow-sm' : 'text-ink-2 hover:text-ink hover:bg-card/50 font-medium' }}"
                            >
                                <span>{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>

    <!-- Current Operator Card & Sign Out -->
    <div class="pt-4 border-t border-rule px-3 space-y-3">
        @auth
            <div class="p-2.5 rounded-[--radius-control] bg-card border border-rule text-xs space-y-1">
                <div class="font-semibold text-ink truncate">{{ auth()->user()->email }}</div>
                <div class="text-[10px] text-ink-3 capitalize flex items-center gap-1">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    {{ str_replace('_', ' ', auth()->user()->role->value) }}
                </div>
            </div>
        @endauth

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button
                type="submit"
                class="flex w-full min-h-9 items-center justify-center rounded-[--radius-control] px-3 text-xs text-red-600 dark:text-red-400 hover:bg-card border border-rule hover:border-red-300 font-medium cursor-pointer transition shadow-sm"
            >
                Sign out
            </button>
        </form>
    </div>
</nav>
