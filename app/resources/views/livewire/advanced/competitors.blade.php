<div class="space-y-6 sm:space-y-8">
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                    <li><a href="{{ route('advanced.home') }}" class="text-indigo-400 hover:text-indigo-300 hover:underline">Advanced</a></li>
                    <li class="text-ink-2">/</li>
                    <li class="text-ink-2">Competitors</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold text-ink sm:text-3xl">Local Competitor Intelligence Radar <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-attention-bg text-attention">Preview — not live</span></h1>
            <p class="mt-1 text-sm text-ink-2">Track competitor review velocity, rating momentum, and search rankings in your catchment area.</p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4">
            <button type="button" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                + Track New Competitor
            </button>
        </div>
    </div>

    <!-- Competitor Radar Table -->
    <div class="bg-card shadow-card rounded-card border border-rule overflow-x-auto overflow-y-hidden mb-8">
        <table class="min-w-full divide-y divide-rule">
            <thead class="bg-paper">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-ink-3 uppercase tracking-wider">Business</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-ink-3 uppercase tracking-wider">Google Rating</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-ink-3 uppercase tracking-wider">Total Reviews</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-ink-3 uppercase tracking-wider">Monthly Velocity</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-ink-3 uppercase tracking-wider">Catchment Rank</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-ink-3 uppercase tracking-wider">Trajectory</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-rule text-sm">
                <tr class="bg-paper font-medium">
                    <td class="px-6 py-4 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-ink"></span>
                        <div class="text-ink">Your Business (Rachel Taylor)</div>
                    </td>
                    <td class="px-6 py-4 text-ink font-bold">4.9 ★</td>
                    <td class="px-6 py-4 text-ink">186</td>
                    <td class="px-6 py-4 text-ok font-semibold">+18 / mo</td>
                    <td class="px-6 py-4"><span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-paper text-ink-2 border border-rule">#1 in Area</span></td>
                    <td class="px-6 py-4 text-ok font-semibold">▲ Accelerating</td>
                </tr>
                <tr>
                    <td class="px-6 py-4 text-ink">Apex Local Services</td>
                    <td class="px-6 py-4 text-ink">4.7 ★</td>
                    <td class="px-6 py-4 text-ink-2">142</td>
                    <td class="px-6 py-4 text-ink-2">+6 / mo</td>
                    <td class="px-6 py-4 text-ink-2">#2 in Area</td>
                    <td class="px-6 py-4 text-ink-2">▶ Steady</td>
                </tr>
                <tr>
                    <td class="px-6 py-4 text-ink">Metro Pro Solutions</td>
                    <td class="px-6 py-4 text-ink">4.3 ★</td>
                    <td class="px-6 py-4 text-ink-2">98</td>
                    <td class="px-6 py-4 text-ink-2">+2 / mo</td>
                    <td class="px-6 py-4 text-ink-2">#4 in Area</td>
                    <td class="px-6 py-4 text-alert">▼ Declining</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>