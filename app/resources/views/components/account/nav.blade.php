@props(['maxWidth' => 'max-w-5xl'])
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

    $moreBadge = null;
    foreach ($more as $item) {
        if ($item->badge !== null && isset($badges[$item->badge])) {
            $moreBadge = ($moreBadge ?? 0) + $badges[$item->badge];
        }
    }

    $business = Tenancy::id() ? Business::find(Tenancy::id()) : null;
@endphp

{{--
    The owner's navigation. Declared in App\Support\Account\OwnerNav and rendered
    only here; this partial is used by components/account/layout and by nothing
    else, so no screen can be built without it.
--}}

<nav aria-label="Your account" class="border-b border-rule bg-card sticky top-0 z-40 backdrop-blur-md bg-card/95">
    <div class="hidden sm:block">
    <!-- Top Utility & Brand Bar -->
    <div class="border-b border-rule/60 bg-paper/40">
        <div class="mx-auto flex w-full {{ $maxWidth }} items-center justify-between px-3 sm:px-4 py-2 sm:py-2.5">
            <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                <a href="{{ route('account.home') }}" class="font-display text-sm sm:text-base font-bold tracking-tight text-ink hover:opacity-80 transition flex items-center gap-1.5 sm:gap-2 shrink-0">
                    <span class="flex h-6 w-6 items-center justify-center rounded-md bg-ink text-paper text-xs font-black">AI</span>
                    <span class="hidden xs:inline">{{ config('app.name') }}</span>
                </a>

                @if ($business)
                    <span class="text-ink-3 hidden xs:inline">/</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-rule bg-card px-2 sm:px-2.5 py-0.5 text-[11px] sm:text-xs font-semibold text-ink shadow-xs truncate max-w-[140px] sm:max-w-[200px]">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                        <span class="truncate">{{ $business->name }}</span>
                    </span>
                @endif
            </div>

            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                @if ($business?->hasAdvancedDashboard())
                    <a
                        href="{{ route('advanced.home') }}"
                        class="inline-flex items-center gap-1 px-2 sm:px-2.5 py-1 rounded-full text-[11px] sm:text-xs font-semibold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 transition shadow-xs"
                    >
                        <span>⚡ <span class="hidden sm:inline">Power Center</span><span class="sm:hidden">Advanced</span></span>
                    </a>
                @endif

                @auth
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-xs text-ink-3 hover:text-alert font-medium transition cursor-pointer px-1">
                            Sign out
                        </button>
                    </form>
                @endauth
            </div>
        </div>
    </div>

    <!-- Main Tab Navigation (Horizontal Scroll on Mobile) -->
    <div class="mx-auto flex w-full {{ $maxWidth }} items-center justify-between gap-1 sm:gap-2 px-2 sm:px-4 py-1.5">
        <ul class="flex items-center gap-1 sm:gap-1.5 min-w-0 flex-wrap">
            @foreach ($primary as $item)
                @php $current = $item->current(); @endphp
                <li class="shrink-0">
                    <a
                        href="{{ route($item->route) }}"
                        @if ($current) aria-current="page" @endif
                        @class([
                            'flex min-h-10 items-center rounded-[--radius-control] px-2.5 sm:px-3 text-xs sm:text-sm font-medium transition whitespace-nowrap focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none',
                            'bg-paper text-ink shadow-xs font-semibold border border-rule' => $current,
                            'text-ink-2 hover:text-ink hover:bg-paper/50' => ! $current,
                        ])
                    >
                        <span>{{ $item->label }}</span>
                        @if ($item->badge !== null && isset($badges[$item->badge]))
                            <span
                                data-nav-badge="{{ $item->badge }}"
                                class="ms-1.5 inline-flex min-w-4.5 items-center justify-center rounded-full bg-indigo-600 px-1 text-[10px] sm:text-[11px] font-bold text-white"
                            >{{ $badges[$item->badge] }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>

        @if ($more !== [])
            <details class="relative shrink-0 ms-1">
                <summary
                    class="flex min-h-10 cursor-pointer list-none items-center rounded-[--radius-control] px-2.5 sm:px-3 text-xs sm:text-sm font-medium transition whitespace-nowrap focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none border border-transparent text-ink-2 hover:text-ink hover:bg-paper/50"
                >
                    <span>More ▾</span>

                    @if ($moreBadge !== null)
                        <span
                            data-nav-badge="more"
                            class="ms-1.5 inline-flex min-w-4.5 items-center justify-center rounded-full bg-indigo-600 px-1 text-[10px] sm:text-[11px] font-bold text-white"
                        >{{ $moreBadge }}<span class="sr-only"> {{ OwnerNavBadges::summarySuffix() }}</span></span>
                    @endif
                </summary>

                <ul class="absolute end-0 top-full z-30 mt-1.5 min-w-56 max-w-[calc(100vw-2rem)] rounded-[--radius-card] border border-rule bg-card p-1.5 shadow-xl">
                    @php
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
                                $sections[$item->section][] = $item;
                            }
                        }
                    @endphp

                    @foreach ($nullSection as $item)
                        @php $current = $item->current(); @endphp
                        <li>
                            <a
                                href="{{ route($item->route) }}"
                                @if ($current) aria-current="page" @endif
                                @class([
                                    'flex min-h-10 items-center justify-between rounded-[--radius-control] px-3 text-xs font-medium transition focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none border border-transparent',
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
                        </li>
                    @endforeach

                    @foreach ($sections as $sectionName => $items)
                        @if (count($items) > 0)
                            <li role="presentation" class="px-3 pt-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-ink-3">
                                <span>{{ $sectionName }}</span>
                            </li>
                            @foreach ($items as $item)
                                @php $current = $item->current(); @endphp
                                <li>
                                    <a
                                        href="{{ route($item->route) }}"
                                        @if ($current) aria-current="page" @endif
                                        @class([
                                            'flex min-h-10 items-center justify-between rounded-[--radius-control] px-3 text-xs font-medium transition focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none border border-transparent',
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
                                </li>
                            @endforeach
                        @endif
                    @endforeach
                    <li class="border-t border-rule mt-1 pt-1">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button
                                type="submit"
                                class="flex w-full min-h-10 items-center rounded-[--radius-control] px-3 text-xs text-red-600 dark:text-red-400 hover:bg-paper font-medium cursor-pointer text-left transition"
                            >
                                Sign out
                            </button>
                        </form>
                    </li>
                </ul>
            </details>
        @endif
    </div>
    </div>
    
    <!-- Mobile Layout -->
    <div class="flex sm:hidden items-center justify-between px-3 py-2 gap-2">
        @if ($business)
            <span class="inline-flex min-h-10 items-center gap-1.5 rounded-full border border-rule bg-card px-3 text-sm font-semibold text-ink shadow-xs truncate max-w-[200px]">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                <span class="truncate">{{ $business->name }}</span>
            </span>
        @else
            <a href="{{ route('account.home') }}" class="font-display text-base font-bold tracking-tight text-ink flex min-h-10 items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-md bg-ink text-paper text-xs font-black">AI</span>
                <span>{{ config('app.name') }}</span>
            </a>
        @endif

        @php
            $allItems = array_merge($primary, $more);
            $mobileCurrentLabel = 'Menu';
            foreach ($allItems as $item) {
                if ($item->current()) {
                    $mobileCurrentLabel = $item->label;
                    break;
                }
            }
        @endphp

        <details class="relative shrink-0">
            <summary class="flex min-h-10 cursor-pointer list-none items-center rounded-[--radius-control] px-3 text-sm font-medium transition focus-visible:ring-2 border border-rule bg-paper text-ink shadow-xs hover:bg-paper/80">
                <span>{{ $mobileCurrentLabel }} ▾</span>
            </summary>
            <ul class="absolute end-0 top-full z-30 mt-1.5 min-w-56 rounded-[--radius-card] border border-rule bg-card p-1.5 shadow-xl max-h-[calc(100vh-4rem)] overflow-y-auto">
                @foreach ($allItems as $item)
                    @php $current = $item->current(); @endphp
                    <li>
                        <a href="{{ route($item->route) }}" @class([
                            'flex min-h-10 items-center justify-between rounded-[--radius-control] px-3 text-sm font-medium transition border border-transparent',
                            'bg-paper text-ink shadow-xs font-semibold border-rule' => $current,
                            'text-ink-2 hover:text-ink hover:bg-paper/50' => ! $current,
                        ])>
                            <span>{{ $item->label }}</span>
                            @if ($item->badge !== null && isset($badges[$item->badge]))
                                <span class="ms-2 inline-flex min-w-5 items-center justify-center rounded-full bg-indigo-600 px-1 text-[10px] font-bold text-white">{{ $badges[$item->badge] }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
                @if ($business?->hasAdvancedDashboard())
                    <li class="border-t border-rule mt-1 pt-1">
                        <a href="{{ route('advanced.home') }}" class="flex min-h-10 items-center rounded-[--radius-control] px-3 text-sm font-medium text-indigo-700 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-950/60 transition">
                            ⚡ Power Center
                        </a>
                    </li>
                @endif
                <li class="border-t border-rule mt-1 pt-1">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full min-h-10 items-center rounded-[--radius-control] px-3 text-sm text-red-600 hover:bg-paper font-medium cursor-pointer text-left transition">
                            Sign out
                        </button>
                    </form>
                </li>
            </ul>
        </details>
    </div>
</nav>
