<div>
    <x-surface.sample-state module="**two modes — APPOINTMENT and JOB — the profile picks (§29.3)**" screen="calendar" />
    <div class="calendar-container p-4">
        <h3 class="text-lg font-bold">Appointment Calendar</h3>
        @if($appointments->isEmpty())
            <p class="text-gray-500">No scheduled appointments.</p>
        @else
            <ul>
                @foreach($appointments as $apt)
                    <li>{{ $apt->service_name }} ({{ $apt->start_time }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
