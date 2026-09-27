{{--
    The owner's Home (`28` §3.3) — three numbers, never reframed.

    ⚠️ A ZERO IS RENDERED AS `0`. Never a dash, never an em-dash, never "no data
    yet", never a hidden tile. §3.3: "If a number would be zero, show zero — the
    First 7-Day path exists to fix that, not the copy." A test asserts the digit
    is present and that an em-dash is absent, because every instinct on a fresh
    account pushes the other way.

    COLOUR IS NOT THE SIGNAL (`22`). The numbers carry no red or green: a proof
    number is a count, not a judgement, and tinting one green the moment it rises
    would be the system grading its own work.
--}}

<div class="space-y-6 sm:space-y-8">
    <!-- Today: what needs you -->
    @livewire('x-124.todays-recommendation-strip', ['businessId' => \App\Support\Tenancy::id()])
    @livewire('x-110.today', ['businessId' => \App\Support\Tenancy::id()])
    @livewire('x-199.money-paid-today', ['businessId' => \App\Support\Tenancy::id()])
    @livewire('x-199.unpaid', ['businessId' => \App\Support\Tenancy::id()])
    @livewire('x-199.declines', ['businessId' => \App\Support\Tenancy::id()])

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
        <div>
            <h1 class="font-display text-xl sm:text-2xl lg:text-3xl font-bold text-ink">Your results</h1>
            <p class="mt-0.5 sm:mt-1 text-xs sm:text-sm text-ink-2">
                Counted from what actually happened — never estimated.
            </p>
        </div>

        {{-- §3.3's period toggle: two options, responsive on mobile --}}
        <div class="grid grid-cols-2 sm:flex gap-1.5 sm:gap-2" role="group" aria-label="Which period">
            <button
                type="button"
                wire:click="showThisMonth"
                @class([
                    'min-h-10 sm:min-h-11 rounded-[--radius-control] border px-3 sm:px-4 text-xs sm:text-sm font-medium transition text-center',
                    'border-ink bg-card text-ink shadow-xs font-semibold' => $isThisMonth,
                    'border-rule text-ink-2 hover:text-ink bg-paper' => ! $isThisMonth,
                ])
                @if ($isThisMonth) aria-current="true" @endif
            >This month</button>

            <button
                type="button"
                wire:click="showAllTime"
                @class([
                    'min-h-10 sm:min-h-11 rounded-[--radius-control] border px-3 sm:px-4 text-xs sm:text-sm font-medium transition text-center',
                    'border-ink bg-card text-ink shadow-xs font-semibold' => ! $isThisMonth,
                    'border-rule text-ink-2 hover:text-ink bg-paper' => $isThisMonth,
                ])
                @if (! $isThisMonth) aria-current="true" @endif
            >All time</button>
        </div>
    </div>

    <!-- Proof Numbers Grid -->
    <div class="grid gap-3 sm:gap-4 grid-cols-1 sm:grid-cols-3">
        @foreach ([
            'google_reviews' => ['New Google reviews', '⭐'],
            'leads' => ['Leads captured', '🎯'],
            'recovered' => ['Unhappy customers recovered', '🛡️'],
        ] as $key => [$label, $icon])
            <div class="rounded-[--radius-panel] border border-rule bg-card p-4 sm:p-6 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <p class="text-xs sm:text-sm font-medium text-ink-2">{{ $label }}</p>
                        <span class="text-base sm:text-lg">{{ $icon }}</span>
                    </div>

                    <p class="mt-1 sm:mt-2 font-display text-3xl sm:text-4xl font-bold text-ink" data-proof-number="{{ $key }}">{{ number_format($numbers->{$key}) }}</p>

                    <p class="mt-2.5 sm:mt-3 text-[11px] sm:text-xs text-ink-2 leading-relaxed border-t border-rule/50 pt-2">{{ $definitions[$key] }}</p>
                </div>

                @isset ($notes[$key])
                    <p class="mt-2 text-[11px] sm:text-xs text-ink-3" data-proof-note="{{ $key }}">{{ $notes[$key] }}</p>
                @endisset
            </div>
        @endforeach
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 text-[11px] sm:text-xs text-ink-3">
        <span>
            @if ($numbers->computed_at)
                Telemetry calculated {{ $numbers->computed_at->diffForHumans() }}.
            @else
                Not worked out yet — these are the counts we have.
            @endif
        </span>
        @if ($isPaused)<span class="flex items-center gap-1.5 text-ink-2">Paused — nothing runs for you until you resume in Settings</span>@endif
    </div>

    <!-- Quick Navigation & Growth Shortcuts -->
    <div class="rounded-[--radius-card] border border-rule bg-card p-4 sm:p-6 shadow-xs">
        <h2 class="font-display text-sm sm:text-base font-bold text-ink mb-3 sm:mb-4">Quick Actions & Shortcuts</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 sm:gap-3">
            <a href="{{ route('account.inbox') }}" class="p-3 sm:p-3.5 rounded-[--radius-control] border border-rule bg-paper hover:border-rule-strong text-center transition group">
                <div class="text-lg sm:text-xl mb-1">📥</div>
                <div class="text-xs font-semibold text-ink group-hover:underline">Review Inbox</div>
                <div class="text-[10px] text-ink-3 mt-0.5 hidden xs:block">AI Triage & Drafts</div>
            </a>
            <a href="{{ route('account.customers') }}" class="p-3 sm:p-3.5 rounded-[--radius-control] border border-rule bg-paper hover:border-rule-strong text-center transition group">
                <div class="text-lg sm:text-xl mb-1">👥</div>
                <div class="text-xs font-semibold text-ink group-hover:underline">Customer List</div>
                <div class="text-[10px] text-ink-3 mt-0.5 hidden xs:block">Outreach Channels</div>
            </a>
            <a href="{{ route('account.website') }}" class="p-3 sm:p-3.5 rounded-[--radius-control] border border-rule bg-paper hover:border-rule-strong text-center transition group">
                <div class="text-lg sm:text-xl mb-1">⭐</div>
                <div class="text-xs font-semibold text-ink group-hover:underline">Website Badges</div>
                <div class="text-[10px] text-ink-3 mt-0.5 hidden xs:block">Live Review Feed</div>
            </a>
            @if ($hasAdvanced)
                <a href="{{ route('advanced.citations') }}" class="p-3 sm:p-3.5 rounded-[--radius-control] border border-rule bg-paper hover:border-rule-strong text-center transition group">
                    <div class="text-lg sm:text-xl mb-1">📍</div>
                    <div class="text-xs font-semibold text-ink group-hover:underline">Directory Citations</div>
                    <div class="text-[10px] text-ink-3 mt-0.5 hidden xs:block">NAP Consistency</div>
                </a>
            @endif
            <a href="{{ route('x-199.invoices') }}" class="p-3 sm:p-3.5 rounded-[--radius-control] border border-rule bg-paper hover:border-rule-strong text-center transition group">
                <div class="text-lg sm:text-xl mb-1">🧾</div>
                <div class="text-xs font-semibold text-ink group-hover:underline">Invoices</div>
                <div class="text-[10px] text-ink-3 mt-0.5 hidden xs:block">Billing & Payments</div>
            </a>
            <a href="{{ route('x-199.credits') }}" class="p-3 sm:p-3.5 rounded-[--radius-control] border border-rule bg-paper hover:border-rule-strong text-center transition group">
                <div class="text-lg sm:text-xl mb-1">💰</div>
                <div class="text-xs font-semibold text-ink group-hover:underline">Credits</div>
                <div class="text-[10px] text-ink-3 mt-0.5 hidden xs:block">Account Balance</div>
            </a>
        </div>
    </div>
</div>
