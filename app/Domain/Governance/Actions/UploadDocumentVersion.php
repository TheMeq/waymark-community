<?php

namespace App\Domain\Governance\Actions;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Governance\Models\Document;
use App\Domain\Governance\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class UploadDocumentVersion
{
    private const MIME_TYPES = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip'],
    ];

    public function handle(User $actor, Document $document, UploadedFile $upload): DocumentVersion
    {
        if (! $actor->hasCapability(ModuleCapability::ManageGovernance)) {
            throw ValidationException::withMessages(['document' => 'Only governance managers may upload document versions.']);
        }
        $extension = strtolower($upload->getClientOriginalExtension());
        $mime = (string) $upload->getMimeType();
        if (! $upload->isValid() || ! isset(self::MIME_TYPES[$extension]) || ! in_array($mime, self::MIME_TYPES[$extension], true) || $upload->getSize() > 20 * 1024 * 1024) {
            throw ValidationException::withMessages(['document' => 'Choose an approved document file up to 20 MB.']);
        }

        $disk = config('filesystems.default', 'local');
        $directory = 'documents/'.Str::uuid();
        $storedPath = null;
        try {
            return DB::transaction(function () use ($actor, $document, $upload, $extension, $mime, $disk, $directory, &$storedPath): DocumentVersion {
                $locked = Document::query()->lockForUpdate()->findOrFail($document->id);
                $number = ((int) $locked->versions()->max('version_number')) + 1;
                $storedPath = Storage::disk($disk)->putFileAs($directory, $upload, "v{$number}.{$extension}");
                if (! is_string($storedPath)) {
                    throw ValidationException::withMessages(['document' => 'The document version could not be stored.']);
                }

                return $locked->versions()->create([
                    'version_number' => $number,
                    'storage_disk' => $disk,
                    'storage_path' => $storedPath,
                    'original_filename' => basename($upload->getClientOriginalName()),
                    'mime_type' => $mime,
                    'file_size_bytes' => $upload->getSize(),
                    'created_by_user_id' => $actor->id,
                ]);
            });
        } catch (\Throwable $exception) {
            if (is_string($storedPath)) {
                Storage::disk($disk)->delete($storedPath);
            }
            throw $exception;
        }
    }
}
