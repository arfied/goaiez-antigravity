<div>
    <div class="p-4">
        <h2 class="text-lg font-bold mb-4 text-ink">Model Assignment Matrix</h2>
        
        <table class="w-full border-collapse border border-rule rounded">
            <thead class="bg-paper">
                <tr>
                    <th class="p-2 border border-rule text-left text-sm font-semibold text-ink">Task</th>
                    <th class="p-2 border border-rule text-left text-sm font-semibold text-ink">Primary Model</th>
                    <th class="p-2 border border-rule text-left text-sm font-semibold text-ink">Backup Model</th>
                    <th class="p-2 border border-rule text-left text-sm font-semibold text-ink">Effective Router Model</th>
                    <th class="p-2 border border-rule text-left text-sm font-semibold text-ink"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($tasks as $task)
                    <tr>
                        <td class="p-2 border border-rule align-top">
                            <span class="font-mono text-sm text-ink">{{ $task->value }}</span>
                        </td>
                        <td class="p-2 border border-rule align-top">
                            <select wire:model.defer="primaryModelForTask.{{ $task->value }}" class="text-sm border border-rule rounded p-1 w-full bg-surface text-ink">
                                <option value="">-- Select Primary --</option>
                                @foreach($models as $model)
                                    <option value="{{ $model->id }}">{{ $model->model_name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="p-2 border border-rule align-top">
                            <select wire:model.defer="backupModelForTask.{{ $task->value }}" class="text-sm border border-rule rounded p-1 w-full bg-surface text-ink">
                                <option value="">-- Select Backup --</option>
                                @foreach($models as $model)
                                    <option value="{{ $model->id }}">{{ $model->model_name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="p-2 border border-rule align-top">
                            <span class="text-sm text-ink-2 font-mono">{{ $effectiveModels[$task->value] ?? '' }}</span>
                        </td>
                        <td class="p-2 border border-rule align-top">
                            <button wire:click="assign('{{ $task->value }}')" class="px-3 py-1 bg-surface text-ink border border-rule rounded text-sm">Save</button>
                            @error('assign.'.$task->value)
                                <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                            @enderror
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
