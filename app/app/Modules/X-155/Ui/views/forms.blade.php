<div>
    <x-surface.sample-state module="the form runtime shared by every site and the widget: capture, validate, write `Person` + `Conversation` + `Job`/`Message` as the form declares, brokered webhooks, spam and bot filtering, the abandon point *(with the pixel)*." screen="forms" />
    <div class="forms-view p-4">
        <h3 class="text-lg font-bold">Multi-Step Form Builder</h3>
        @if($forms->isEmpty())
            <p class="text-gray-500">No forms constructed yet.</p>
        @else
            <ul class="space-y-4">
                @foreach($forms as $f)
                    <li class="border p-4 rounded flex flex-col md:flex-row md:justify-between md:items-center">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold">{{ $f->form_name }}</span>
                                <span class="text-xs px-2 py-1 rounded bg-gray-100">{{ $f->slug }}</span>
                            </div>
                            <div class="text-sm mt-1 text-gray-600">
                                {{ is_array($f->steps) ? count($f->steps) : 0 }} step(s)
                            </div>
                            <div class="text-sm mt-1 text-gray-500">
                                {{ $f->submissions_count }} submissions • {{ $f->spam_count }} spam
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
