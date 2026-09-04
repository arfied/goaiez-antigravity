<div>
    <x-surface.sample-state module="the form runtime shared by every site and the widget: capture, validate, write `Person` + `Conversation` + `Job`/`Message` as the form declares, brokered webhooks, spam and bot filtering, the abandon point *(with the pixel)*." screen="spam_rate" />
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
