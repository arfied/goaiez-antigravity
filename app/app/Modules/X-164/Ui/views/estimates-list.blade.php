<div>
    <div class="p-4">
        <h2 class="text-xl font-bold text-ink">Every estimate, newest first</h2>
        @if($estimates->isEmpty())
            <x-ui.empty-state icon="○" heading="No estimates yet">
                When you draft an estimate for a customer, it appears here.
            </x-ui.empty-state>
        @else
            <ul class="mt-3 space-y-2">
                @foreach($estimates as $est)
                    <li class="text-ink">{{ $est->estimate_number }} · ${{ number_format($est->total_cents / 100, 2) }} · {{ ucfirst($est->status) }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
