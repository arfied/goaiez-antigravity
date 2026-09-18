@php
    use App\Support\Account\OwnerNav;
    use App\Support\Account\OwnerNavBadges;
    use App\Support\Tenancy;
    use App\Models\Business;

    $primary = OwnerNav::primary();
    $more = OwnerNav::more();
    $moreIsCurrent = OwnerNav::moreIsCurrent();
    $moreCurrentLabel = OwnerNav::currentMoreLabel();
    $badges = OwnerNavBadges::counts();

    $business = Tenancy::id() ? Business::find(Tenancy::id()) : null;

    $nullSection = [];
    $sections = [
        'Customers' => [],
        'Messages & follow-ups' => [],
        'Reviews & your website' => [],
        'Phone & texting' => [],
        'Your account' => [],
    ];
    foreach ($more as $item) {
        if ($item->section === null) {
            $nullSection[] = $item;
        } else {
            if (!isset($sections[$item->section])) {
                $sections[$item->section] = [];
            }
            $sections[$item->section][] = $item;
        }
    }

    $allItems = array_merge($primary, $more);
    $mobileCurrentLabel = 'Menu';
    foreach ($allItems as $item) {
        if ($item->current()) {
            $mobileCurrentLabel = $item->label;
            break;
        }
    }
@endphp

<nav aria-label="Your account" class="hidden lg:flex flex-col w-64 shrink-0 border-r border-rule bg-card h-screen sticky top-0 overflow-y-auto z-40">
    <div class="p-4 border-b border-rule/60 bg-paper/40 flex flex-col gap-3">
        <a href="{{ route('account.home') }}" class="font-display text-base font-bold tracking-tight text-ink hover:opacity-80 transition flex items-center gap-2">
            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-ink text-paper text-xs font-black">AI</span>
            <span>{{ config('app.name') }}</span>
        </a>

        @if ($business)
            <span class="inline-flex items-center gap-1.5 rounded-full border border-rule bg-card px-2.5 py-1 text-xs font-semibold text-ink shadow-xs truncate w-full">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                <span class="truncate">{{ $business->name }}</span>
            </span>
        @endif
    </div>

    <div class="flex-1 p-3 flex flex-col gap-1">
        @foreach ($primary as $item)
            @php $current = $item->current(); @endphp
            <a
                href="{{ route($item->route) }}"
                @if ($current) aria-current="page" @endif
                @class([
                    'flex min-h-10 w-full items-center justify-between rounded-[--radius-control] px-3 text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none border border-transparent',
                    'bg-paper text-ink shadow-xs font-semibold border-rule' => $current,
                    'text-ink-2 hover:text-ink hover:bg-paper/50' => ! $current,
                ])
            >
                <span>{{ $item->label }}</span>
                @if ($item->badge !== null && isset($badges[$item->badge]))
                    <span
                        data-nav-badge="{{ $item->badge }}"
                        class="ms-2 inline-flex min-w-5 items-center justify-center rounded-full bg-indigo-600 px-1 text-[10px] font-bold text-white"
                    >{{ $badges[$item->badge] }}<span class="sr-only"> {{ OwnerNavBadges::screenReaderSuffix($item->badge) }}</span></span>
                @endif
            </a>
        @endforeach

        @foreach ($nullSection as $item)
            @php $current = $item->current(); @endphp
            <a
                href="{{ route($item->route) }}"
                @if ($current) aria-current="page" @endif
                @class([
                    'flex min-h-10 w-full items-center justify-between rounded-[--radius-control] px-3 text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none border border-transparent',
                    'bg-paper text-ink shadow-xs font-semibold border-rule' => $current,
                    'text-ink-2 hover:text-ink hover:bg-paper/50' => ! $current,
                ])
            >
                <span>{{ $item->label }}</span>
                @if ($item->badge !== null && isset($badges[$item->badge]))
                    <span
                        data-nav-badge="{{ $item->badge }}"
                        class="ms-2 inline-flex min-w-5 items-center justify-center rounded-full bg-indigo-600 px-1 text-[10px] font-bold text-white"
                    >{{ $badges[$item->badge] }}<span class="sr-only"> {{ OwnerNavBadges::screenReaderSuffix($item->badge) }}</span></span>
                @endif
            </a>
        @endforeach

        @foreach ($sections as $sectionName => $items)
            @if (count($items) > 0)
                <div class="px-3 pt-5 pb-2 text-[10px] font-bold uppercase tracking-wider text-ink-3">
                    {{ $sectionName }}
                </div>
                @foreach ($items as $item)
                    @php $current = $item->current(); @endphp
                    <a
                        href="{{ route($item->route) }}"
                        @if ($current) aria-current="page" @endif
                        @class([
                            'flex min-h-10 w-full items-center justify-between rounded-[--radius-control] px-3 text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none border border-transparent',
                            'bg-paper text-ink shadow-xs font-semibold border-rule' => $current,
                            'text-ink-2 hover:text-ink hover:bg-paper/50' => ! $current,
                        ])
                    >
                        <span>{{ $item->label }}</span>
                        @if ($item->badge !== null && isset($badges[$item->badge]))
                            <span
                                data-nav-badge="{{ $item->badge }}"
                                class="ms-2 inline-flex min-w-5 items-center justify-center rounded-full bg-indigo-600 px-1 text-[10px] font-bold text-white"
                            >{{ $badges[$item->badge] }}<span class="sr-only"> {{ OwnerNavBadges::screenReaderSuffix($item->badge) }}</span></span>
                        @endif
                    </a>
                @endforeach
            @endif
        @endforeach
    </div>

    <div class="p-3 border-t border-rule bg-paper/40 flex flex-col gap-1">
        @if ($business?->hasAdvancedDashboard())
            <a
                href="{{ route('advanced.home') }}"
                class="flex min-h-10 w-full items-center justify-center gap-1 rounded-[--radius-control] px-3 text-sm font-semibold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 transition shadow-xs"
            >
                <span>⚡ Power Center</span>
            </a>
        @endif
        @auth
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex w-full min-h-10 items-center justify-center rounded-[--radius-control] px-3 text-sm text-red-600 hover:bg-paper font-medium cursor-pointer transition">
                    Sign out
                </button>
            </form>
        @endauth
    </div>
</nav>

<div class="lg:hidden block border-b border-rule bg-card sticky top-0 z-40">
    <div class="px-3 py-2 flex items-center justify-between">
        <a href="{{ route('account.home') }}" class="font-display text-base font-bold tracking-tight text-ink flex items-center gap-2">
            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-ink text-paper text-xs font-black">AI</span>
            <span>{{ config('app.name') }}</span>
        </a>

        <details class="group relative">
            <summary class="flex min-h-10 cursor-pointer list-none items-center rounded-[--radius-control] px-3 text-sm font-medium transition focus-visible:ring-2 border border-rule bg-paper text-ink shadow-xs hover:bg-paper/80 focus-visible:outline-none focus-visible:ring-ink">
                <span>{{ $mobileCurrentLabel }} ▾</span>
            </summary>
            
            <div class="fixed inset-y-0 right-0 z-50 w-72 bg-card border-l border-rule shadow-2xl flex flex-col transition-transform translate-x-full group-open:translate-x-0" style="display: none;">
                <div class="p-4 border-b border-rule flex items-center justify-between">
                    <span class="font-bold text-ink">Menu</span>
                    <button type="button" class="p-2 text-ink-2 hover:text-ink focus:outline-none focus:ring-2 focus:ring-ink rounded-md" onclick="this.closest('details').removeAttribute('open')">
                        ✕
                    </button>
                </div>
                
                <div class="flex-1 overflow-y-auto p-3 flex flex-col gap-1">
                    @foreach ($primary as $item)
                        @php $current = $item->current(); @endphp
                        <a href="{{ route($item->route) }}" @if($current) aria-current="page" @endif @class([
                            'flex min-h-10 w-full items-center justify-between rounded-[--radius-control] px-3 text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-ink border border-transparent focus-visible:outline-none',
                            'bg-paper text-ink shadow-xs font-semibold border-rule' => $current,
                            'text-ink-2 hover:text-ink hover:bg-paper/50' => ! $current,
                        ])>
                            <span>{{ $item->label }}</span>
                            @if ($item->badge !== null && isset($badges[$item->badge]))
                                <span data-nav-badge="{{ $item->badge }}" class="ms-2 inline-flex min-w-5 items-center justify-center rounded-full bg-indigo-600 px-1 text-[10px] font-bold text-white">{{ $badges[$item->badge] }}<span class="sr-only"> {{ OwnerNavBadges::screenReaderSuffix($item->badge) }}</span></span>
                            @endif
                        </a>
                    @endforeach

                    @foreach ($nullSection as $item)
                        @php $current = $item->current(); @endphp
                        <a href="{{ route($item->route) }}" @if($current) aria-current="page" @endif @class([
                            'flex min-h-10 w-full items-center justify-between rounded-[--radius-control] px-3 text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-ink border border-transparent focus-visible:outline-none',
                            'bg-paper text-ink shadow-xs font-semibold border-rule' => $current,
                            'text-ink-2 hover:text-ink hover:bg-paper/50' => ! $current,
                        ])>
                            <span>{{ $item->label }}</span>
                            @if ($item->badge !== null && isset($badges[$item->badge]))
                                <span data-nav-badge="{{ $item->badge }}" class="ms-2 inline-flex min-w-5 items-center justify-center rounded-full bg-indigo-600 px-1 text-[10px] font-bold text-white">{{ $badges[$item->badge] }}<span class="sr-only"> {{ OwnerNavBadges::screenReaderSuffix($item->badge) }}</span></span>
                            @endif
                        </a>
                    @endforeach

                    @foreach ($sections as $sectionName => $items)
                        @if (count($items) > 0)
                            <div class="px-3 pt-5 pb-2 text-[10px] font-bold uppercase tracking-wider text-ink-3">
                                {{ $sectionName }}
                            </div>
                            @foreach ($items as $item)
                                @php $current = $item->current(); @endphp
                                <a href="{{ route($item->route) }}" @if($current) aria-current="page" @endif @class([
                                    'flex min-h-10 w-full items-center justify-between rounded-[--radius-control] px-3 text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-ink border border-transparent focus-visible:outline-none',
                                    'bg-paper text-ink shadow-xs font-semibold border-rule' => $current,
                                    'text-ink-2 hover:text-ink hover:bg-paper/50' => ! $current,
                                ])>
                                    <span>{{ $item->label }}</span>
                                    @if ($item->badge !== null && isset($badges[$item->badge]))
                                        <span data-nav-badge="{{ $item->badge }}" class="ms-2 inline-flex min-w-5 items-center justify-center rounded-full bg-indigo-600 px-1 text-[10px] font-bold text-white">{{ $badges[$item->badge] }}<span class="sr-only"> {{ OwnerNavBadges::screenReaderSuffix($item->badge) }}</span></span>
                                    @endif
                                </a>
                            @endforeach
                        @endif
                    @endforeach
                </div>
                
                <div class="p-3 border-t border-rule bg-paper/40 flex flex-col gap-1">
                    @if ($business?->hasAdvancedDashboard())
                        <a href="{{ route('advanced.home') }}" class="flex min-h-10 w-full items-center justify-center gap-1 rounded-[--radius-control] px-3 text-sm font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 transition shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ink">
                            ⚡ Power Center
                        </a>
                    @endif
                    @auth
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full min-h-10 items-center justify-center rounded-[--radius-control] px-3 text-sm text-red-600 hover:bg-paper font-medium cursor-pointer transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ink">
                                Sign out
                            </button>
                        </form>
                    @endauth
                </div>
            </div>
        </details>
    </div>
</div>

<style>
    details[open] > div.fixed {
        display: flex !important;
        transform: translateX(0);
    }
</style>
