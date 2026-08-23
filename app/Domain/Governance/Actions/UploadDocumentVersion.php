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
        if (! $upload->isValid() || ! isset(self::MIME_TYPES[$extension]) || ! in_array($mime, self::MIME_TYPES[$extension], true) || $upload->getSize() > 20 * 1024 * 1024 || ! $this->contentMatchesExtension($upload, $extension)) {
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
                    'original_filename' => $this->safeOriginalFilename($upload),
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

    private function contentMatchesExtension(UploadedFile $upload, string $extension): bool
    {
        $path = $upload->getRealPath();
        if (! is_string($path) || ! is_file($path)) {
            return false;
        }

        $header = (string) file_get_contents($path, false, null, 0, 8);
        if ($extension === 'pdf') {
            return str_starts_with($header, '%PDF-');
        }
        if (in_array($extension, ['doc', 'xls'], true)) {
            return $header === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
        }

        $archive = new \ZipArchive;
        if ($archive->open($path) !== true) {
            return false;
        }

        try {
            return match ($extension) {
                'docx' => $archive->locateName('[Content_Types].xml') !== false && $archive->locateName('word/document.xml') !== false,
                'xlsx' => $archive->locateName('[Content_Types].xml') !== false && $archive->locateName('xl/workbook.xml') !== false,
                'odt' => $archive->getFromName('mimetype') === 'application/vnd.oasis.opendocument.text',
                default => false,
            };
        } finally {
            $archive->close();
        }
    }

    private function safeOriginalFilename(UploadedFile $upload): string
    {
        $filename = preg_replace('/[\x00-\x1F\x7F]/', '', basename($upload->getClientOriginalName())) ?? '';

        return mb_substr($filename !== '' ? $filename : 'document', 0, 255);
    }
}
