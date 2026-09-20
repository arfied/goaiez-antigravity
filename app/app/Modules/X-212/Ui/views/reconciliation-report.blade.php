<div>
    <div class="recon-report-view p-4">
        <h2 class="text-lg font-bold">Reconciliation Report</h2>
        <ul>
            @foreach($rejects as $reject)
                <li>{{ $reject->rejection_reason }}</li>
            @endforeach
        </ul>
    </div>
</div>
