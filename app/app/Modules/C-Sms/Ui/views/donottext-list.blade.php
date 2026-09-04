<div>
    <x-surface.sample-state module="C-Sms" screen="donottext_list" />
    <div class="dnt-list-container p-4">
        <h3 class="text-lg font-bold">Do-Not-Text / Suppression Registry</h3>
        @if($suppressions->isEmpty())
            <p class="text-gray-500">No numbers on DNT list.</p>
        @else
            <ul>
                @foreach($suppressions as $s)
                    <li>{{ $s->recipient_phone }} ({{ $s->reason }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
