<x-surface.sample-state module="C-Sms" screen="thread" />
<div>
    <div class="thread-container p-4">
        <h3 class="text-lg font-bold">SMS Conversation Thread</h3>
        @if($messages->isEmpty())
            <p class="text-gray-500">No SMS messages in thread.</p>
        @else
            <ul class="divide-y divide-gray-200">
                @foreach($messages as $msg)
                    <li class="py-2">
                        <span class="font-mono text-sm">{{ $msg->recipient_phone }}</span>: {{ $msg->body }}
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
