<div>
    <div class="margin-job-view p-4">
        <h3 class="text-lg font-bold">Job Margins & Costing</h3>
        @if($costs->isEmpty())
            <p class="text-gray-500">No job costing records available.</p>
        @else
            <ul>
                @foreach($costs as $c)
                    <li>Job #{{ $c->job_id }} (v{{ $c->price_book_version }}): {{ $c->gross_margin_pct }}% margin</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
