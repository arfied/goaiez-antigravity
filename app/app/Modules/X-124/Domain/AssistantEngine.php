<?php
namespace App\Modules\X124\Domain;

class AssistantEngine
{
    public function process(array $message): array
    {
        if (($message['is_internal'] ?? false) === true) {
            return ['status' => 'refused', 'refusal_code' => 'internal_only'];
        }
        if (($message['intent'] ?? '') === 'configure') {
            return ['status' => 'handled', 'action' => 'configure_platform'];
        }
        if (($message['intent'] ?? '') === 'escalate' || ($message['text'] ?? '') === 'HUMAN') {
            return ['status' => 'escalated', 'target' => 'X-111'];
        }
        if (($message['intent'] ?? '') === 'help') {
            return ['status' => 'handled', 'source' => 'generated_help_registry'];
        }
        return ['status' => 'unsupported'];
    }
}
