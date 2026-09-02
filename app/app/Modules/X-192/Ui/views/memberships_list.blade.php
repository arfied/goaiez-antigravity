<div class="space-y-6 sm:space-y-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
        <div>
            <h1 class="font-display text-xl sm:text-2xl lg:text-3xl font-bold text-ink">Directory Memberships</h1>
            <p class="mt-0.5 sm:mt-1 text-xs sm:text-sm text-ink-2">
                Where your business is listed across the web.
            </p>
        </div>
    </div>

    @if ($memberships->isEmpty())
        <div class="rounded-[--radius-panel] border border-rule bg-card p-6 shadow-xs text-center">
            <h3 class="text-sm font-semibold text-ink">No memberships found</h3>
            <p class="mt-1 text-sm text-ink-2">You don't have any active directory memberships at this time.</p>
        </div>
    @else
        <div class="grid gap-3 sm:gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($memberships as $membership)
                <div class="rounded-[--radius-panel] border border-rule bg-card p-4 sm:p-6 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <p class="text-sm font-semibold text-ink">{{ $membership->directory_name }}</p>
                            @if($membership->is_noindex)
                                <span class="text-[10px] sm:text-xs text-ink-3 px-2 py-0.5 border border-rule rounded-full bg-paper">Hidden</span>
                            @else
                                <span class="text-[10px] sm:text-xs text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">Visible</span>
                            @endif
                        </div>
                        @if($membership->is_noindex)
                            <p class="mt-2 text-xs text-ink-2">Google can't see this</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
