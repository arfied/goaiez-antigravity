<div class="space-y-6 sm:space-y-8">
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                    <li><a href="{{ route('advanced.home') }}" class="text-indigo-400 hover:text-indigo-300 hover:underline">Advanced</a></li>
                    <li class="text-ink-2">/</li>
                    <li class="text-ink-2">Customer segments</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold text-ink sm:text-3xl">Customer segments</h1>
        </div>
    </div>

    <!-- Segments Grid -->
    <div class="grid grid-cols-1 gap-6">
        <div class="bg-card border border-rule rounded-card shadow-card p-6 flex flex-col justify-between">
            <div>
                <h2 class="text-lg font-bold text-ink">Dormant customers</h2>
                <p class="text-xs text-ink-2 mt-1">No activity for {{ $dormancyDays }} days or more</p>
            </div>
            <div class="mt-6 pt-4 border-t border-rule flex justify-between items-center">
                <div class="text-2xl font-bold text-ink">{{ $dormantCount }}</div>
                <a href="{{ route('advanced.broadcasts.compose') }}" class="text-xs font-semibold text-ink hover:underline p-2 min-h-[40px] inline-flex items-center">Draft a broadcast to this segment &rarr;</a>
            </div>
        </div>

        <div class="bg-card border border-rule rounded-card shadow-card p-6 flex flex-col justify-between">
            <div>
                <h2 class="text-lg font-bold text-ink">Active customers</h2>
                <p class="text-xs text-ink-2 mt-1">Seen within the last {{ $dormancyDays }} days</p>
            </div>
            <div class="mt-6 pt-4 border-t border-rule flex justify-between items-center">
                <div class="text-2xl font-bold text-ink">{{ $customersCount - $dormantCount }}</div>
            </div>
        </div>
    </div>

    <p class="text-sm text-ink-2 mt-4">Segments are computed from your customer records; the dormancy window is a platform setting.</p>
</div>{{-- {{ $customersCount }} {{ $dormantCount }} --}}
{{--
{{ $customersCount }}
{{ $dormantCount }}
{{ $dormancyDays }}
--}}
