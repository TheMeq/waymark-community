<?php

namespace App\Domain\Walks\Actions;

use App\Domain\Walks\Exceptions\InvalidGpxException;
use App\Domain\Walks\Models\Walk;
use App\Domain\Walks\Services\GpxRouteParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class StoreWalkGpx
{
    public function __construct(private GpxRouteParser $parser) {}

    public function handle(Walk $walk, UploadedFile $upload): Walk
    {
        $this->validateUpload($upload);

        try {
            $metadata = $this->parser->parse((string) $upload->getRealPath());
        } catch (InvalidGpxException $exception) {
            throw ValidationException::withMessages(['gpx' => $exception->getMessage()]);
        }

        $diskName = (string) config('walks.gpx.disk', 'local');
        $directory = trim((string) config('walks.gpx.directory', 'walks/gpx'), '/');
        $path = $directory.'/'.Str::uuid().'.gpx';
        $disk = Storage::disk($diskName);
        $stream = fopen((string) $upload->getRealPath(), 'rb');

        if ($stream === false) {
            throw ValidationException::withMessages(['gpx' => 'The uploaded GPX file could not be read.']);
        }

        try {
            if (! $disk->writeStream($path, $stream)) {
                throw new RuntimeException('The GPX file could not be stored.');
            }
        } finally {
            fclose($stream);
        }

        $previousPath = $walk->gpx_path;

        try {
            DB::transaction(function () use ($walk, $path, $metadata): void {
                $walk->forceFill([
                    'gpx_path' => $path,
                    'gpx_derived_metadata' => $metadata,
                ])->save();
            });
        } catch (\Throwable $exception) {
            $disk->delete($path);

            throw $exception;
        }

        if ($previousPath !== null && $previousPath !== $path) {
            $disk->delete($previousPath);
        }

        return $walk->refresh();
    }

    private function validateUpload(UploadedFile $upload): void
    {
        if ($upload->getError() !== UPLOAD_ERR_OK) {
            throw ValidationException::withMessages(['gpx' => 'The GPX upload failed.']);
        }

        if (strtolower($upload->getClientOriginalExtension()) !== 'gpx') {
            throw ValidationException::withMessages(['gpx' => 'The uploaded file must use the .gpx extension.']);
        }

        if (($upload->getSize() ?? 0) > (int) config('walks.gpx.max_bytes', 5 * 1024 * 1024)) {
            throw ValidationException::withMessages(['gpx' => 'The GPX file is too large.']);
        }

        $mimeType = $upload->getMimeType();
        $contentStart = ltrim((string) file_get_contents((string) $upload->getRealPath(), false, null, 0, 1024));

        if (! in_array($mimeType, ['application/gpx+xml', 'application/xml', 'text/xml', 'text/plain'], true)
            && ! str_starts_with($contentStart, '<')) {
            throw ValidationException::withMessages(['gpx' => 'The uploaded file is not recognised as GPX or XML content.']);
        }
    }
}
