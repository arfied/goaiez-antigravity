<div>
    <div class="customers-list p-4">
        <h3 class="text-lg font-bold">Customers Directory</h3>
        @if($persons->isEmpty())
            <p class="text-gray-500">No customers found.</p>
        @else
            <ul>
                @foreach($persons as $p)
                    <li>{{ $p->first_name }} {{ $p->last_name }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
