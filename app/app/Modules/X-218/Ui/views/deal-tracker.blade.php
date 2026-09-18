<div>
    <div class="deal-tracker-view p-4">
        <h3 class="text-lg font-bold">Influencer Sponsorship Deal Tracker</h3>
        <ul>
            @foreach($deals as $deal)
                <li>{{ $deal->influencer->handle ?? 'unknown' }}</li>
            @endforeach
        </ul>
    </div>
</div>
