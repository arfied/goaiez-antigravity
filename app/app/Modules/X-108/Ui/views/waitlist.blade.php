<div>
    <x-surface.sample-state module="**two modes — APPOINTMENT and JOB — the profile picks (§29.3)**" screen="waitlist" />
    <div class="waitlist-container p-4">
        <h3 class="text-lg font-bold">Active Waitlists</h3>
        @if($waitlists->isEmpty())
            <p class="text-gray-500">No customers on waitlist.</p>
        @else
            <ul>
                @foreach($waitlists as $w)
                    <li>{{ $w->customer_name }} [{{ $w->status }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
