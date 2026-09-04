<x-surface.sample-state module="good/better/best presentation" screen="estimates_list" />
<div>
    <div class="estimates-list-container p-4">
        <h3 class="text-lg font-bold">Estimates & Contracts</h3>
        @if($estimates->isEmpty())
            <p class="text-gray-500">No estimates drafted.</p>
        @else
            <ul>
                @foreach($estimates as $est)
                    <li>#{{ $est->estimate_number }} - ${{ number_format($est->total_cents / 100, 2) }} [{{ $est->status }}] (v{{ $est->price_book_version }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
