<?php

declare(strict_types=1);

namespace App\Modules\X113\Ui;

use App\Modules\X113\Actions\DocumentUploadAction;
use App\Modules\X113\Models\Role;
use App\Modules\X113\Models\RolePermission;
use App\Modules\X113\Models\StaffDocument;
use App\Modules\X113\Models\StaffUser;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('components.account.layout', ['heading' => 'Document vault'])]
class DocumentVault extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $businessId = 0;

    public ?TemporaryUploadedFile $file = null;

    public ?int $selectedStaffId = null;

    public string $success = '';

    public string $error = '';

    public const string UPLOAD_REFUSED = 'We could not take that file. A document needs to be under 2MB.';

    public function _uploadErrored(string $name, ?string $errorsInJson, bool $isMultiple): void
    {
        $this->dispatch('upload:errored', name: $name)->self();

        throw ValidationException::withMessages([$name => self::UPLOAD_REFUSED]);
    }

    public function mount(): void
    {
        $this->businessId = Tenancy::id() ?? 0;
    }

    public function uploadDocument(DocumentUploadAction $action): void
    {
        $this->error = '';
        $this->success = '';

        if (! $this->selectedStaffId || ! $this->file) {
            $this->error = 'Please select a staff member and choose a file.';

            return;
        }

        $this->validate([
            'file' => ['required', 'file', 'max:2048'],
        ], [
            'file.max' => self::UPLOAD_REFUSED,
        ]);

        try {
            $document = $action->handle(
                Tenancy::idOrFail(),
                (int) $this->selectedStaffId,
                $this->file,
                auth()->id()
            );
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->success = 'Uploaded document '.$document->original_filename.'. This feeds the vault list; nothing downstream is wired to it yet.';
        $this->reset('file', 'selectedStaffId');
    }

    public function render()
    {
        $staff = collect();

        if ($this->businessId !== 0) {
            $staffList = StaffUser::where('business_id', $this->businessId)->get();
            $roles = Role::where('business_id', $this->businessId)->get()->keyBy('id');
            $permissions = RolePermission::where('business_id', $this->businessId)
                ->where('permission', 'view_employee_documents')
                ->pluck('role_id')
                ->toArray();

            $documents = StaffDocument::where('business_id', $this->businessId)->get()->groupBy('staff_user_id');

            foreach ($staffList as $s) {
                $roleName = 'no role';
                $canView = false;

                if ($s->role_id && $roles->has($s->role_id)) {
                    $roleName = $roles->get($s->role_id)->name;
                    $canView = in_array($s->role_id, $permissions, true);
                }

                $staff->push((object) [
                    'id' => $s->id,
                    'name' => $s->name,
                    'role_name' => $roleName,
                    'can_view' => $canView,
                    'documents' => $documents->get($s->id, collect()),
                ]);
            }
        }

        return view('x-113::document-vault', [
            'staff' => $staff,
            'hasDocuments' => isset($documents) ? $documents->isNotEmpty() : false,
        ]);
    }

    public function download(int $documentId)
    {
        $businessId = Tenancy::idOrFail();

        $document = StaffDocument::where('business_id', $businessId)
            ->findOrFail($documentId);

        $staff = StaffUser::where('business_id', $businessId)->findOrFail($document->staff_user_id);

        $hasPermission = RolePermission::where('business_id', $businessId)
            ->where('role_id', $staff->role_id)
            ->where('permission', 'view_employee_documents')
            ->exists();

        abort_unless($hasPermission, 403);

        return Storage::disk('local')->download($document->storage_path, $document->original_filename);
    }

    public function downloadDocument(int $businessId, int $staffUserId, int $documentId): string
    {
        $staff = StaffUser::where('business_id', $businessId)->findOrFail($staffUserId);

        $hasPermission = RolePermission::where('business_id', $businessId)
            ->where('role_id', $staff->role_id)
            ->where('permission', 'view_employee_documents')
            ->exists();

        if (! $hasPermission) {
            throw new \Exception('INSUFFICIENT_ROLE_PERMISSIONS');
        }

        $document = StaffDocument::where('business_id', $businessId)
            ->where('staff_user_id', $staffUserId)
            ->findOrFail($documentId);

        return Storage::disk('local')->get($document->storage_path);
    }
}
