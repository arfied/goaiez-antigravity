<div class="space-y-6 sm:space-y-8">
    <div class="mb-8">
        <nav class="flex mb-2" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                <li><a href="{{ route('advanced.home') }}" class="text-indigo-400 hover:text-indigo-300 hover:underline">Advanced</a></li>
                <li class="text-ink-2">/</li>
                <li class="text-ink-2">SEO Changes</li>
            </ol>
        </nav>
        <h1 class="text-2xl font-bold text-ink sm:text-3xl">Automated SEO & Schema Changes Log <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-attention-bg text-attention">Preview — not live</span></h1>
        <p class="mt-1 text-sm text-ink-2">Live feed of automated speed optimizations, JSON-LD rich snippet schema injections, and metadata improvements with instant rollback.</p>
    </div>

    <!-- Changes Table -->
    <div class="bg-card border border-rule rounded-card shadow-card overflow-x-auto overflow-y-hidden">
        <table class="min-w-full divide-y divide-rule text-sm">
            <thead class="bg-paper border border-rule">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-ink-2 uppercase">Change Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-ink-2 uppercase">Affected URL</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-ink-2 uppercase">Optimization Detail</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-ink-2 uppercase">Control</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-rule">
                @if($cards->isEmpty())
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-ink-2">
                            No automated changes yet. Changes appear here after the first site change runs.
                        </td>
                    </tr>
                @else
                    @foreach($cards as $card)
                    <tr>
                        <td class="px-6 py-4"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-paper text-ink-2 border border-rule whitespace-nowrap">{{ $card->heading() }}</span></td>
                        <td class="px-6 py-4 font-mono text-xs text-ink-2">{{ $card->displayUrl() }}</td>
                        <td class="px-6 py-4 text-ink">{{ $card->resultSentence() ?? 'not measured yet' }}</td>
                        <td class="px-6 py-4 text-right">
                            <x-ui.status-pill :state="$card->state->signal()" :label="$card->state->label()" />
                        </td>
                    </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
    </div>
</div>
