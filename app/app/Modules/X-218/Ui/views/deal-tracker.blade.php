<div>
    <div class="deal-tracker-view p-4">
        <h2 class="text-lg font-bold">Influencer Sponsorship Deal Tracker</h2>
        <ul>
            @foreach($deals as $deal)
                <li>{{ $deal->influencer->handle ?? 'unknown' }}</li>
            @endforeach
        </ul>
    </div>
</div>
