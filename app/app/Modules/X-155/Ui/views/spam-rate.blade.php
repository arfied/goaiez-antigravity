<div>
    <div class="spam-rate-view p-4">
        <h3 class="text-lg font-bold">Spam & Bot Filtering Rate</h3>
        @if($total === 0)
            <p>No submissions yet.</p>
        @else
            <p>Total: {{ $total }}</p>
            <p>Spam: {{ $spam }}</p>
            <p>Rate: {{ $rate }}%</p>
        @endif
    </div>
</div>
