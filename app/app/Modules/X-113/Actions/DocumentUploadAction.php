<?php

declare(strict_types=1);

namespace App\Modules\X113\Actions;

use App\Modules\X113\Models\StaffDocument;
use App\Modules\X113\Models\StaffUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DocumentUploadAction
{
    public function handle(int $businessId, int $staffUserId, UploadedFile $file, ?int $uploadedByUserId = null): StaffDocument
    {
        $staffUser = StaffUser::where('business_id', $businessId)->find($staffUserId);

        if (! $staffUser) {
            throw new InvalidArgumentException('Staff user not found or does not belong to this tenant.');
        }

        $extension = $file->getClientOriginalExtension();
        $safeExtension = preg_match('/^[a-zA-Z0-9]+$/', $extension) ? '.'.$extension : '';
        $ulid = (string) Str::ulid();
        $filename = $ulid.$safeExtension;
        $storedPath = $file->storeAs("staff-documents/{$businessId}/{$staffUserId}", $filename, 'local');

        return StaffDocument::create([
            'business_id' => $businessId,
            'staff_user_id' => $staffUserId,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => Storage::disk('local')->size($storedPath),
            'sha256' => hash('sha256', Storage::disk('local')->get($storedPath)),
            'storage_path' => $storedPath,
            'uploaded_by_user_id' => $uploadedByUserId,
        ]);
    }
}
