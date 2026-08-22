<?php

namespace App\Domain\SiteMedia\Actions;

use App\Domain\Gallery\Actions\IngestCommunityPhoto;
use App\Domain\SiteMedia\Data\SiteMediaMetadata;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class UploadSiteMedia
{
    use ManagesSiteMedia;

    public function __construct(private readonly IngestCommunityPhoto $ingest) {}

    public function handle(User $actor, UploadedFile $upload, SiteMediaMetadata $metadata): SiteMedia
    {
        $this->authorizeSiteMedia($actor);
        $values = $this->validateMetadata($metadata);
        $diskName = (string) config('gallery.photos.disk', 'local');
        $disk = Storage::disk($diskName);
        $key = Str::uuid()->toString();
        $targetDirectory = 'site-media/'.$key;
        try {
            $processed = $this->ingest->handle($upload, $targetDirectory);
            $variants = [];
            foreach ($processed->variants as $name => $variant) {
                $variants[$name] = $variant->path;
            }
            $source = $processed->retainedSource ?? $processed->variants['master'];
            $media = DB::transaction(function () use ($actor, $values, $key, $diskName, $variants, $source): SiteMedia {
                $media = SiteMedia::query()->create(array_merge($values, ['created_by_user_id' => $actor->id, 'storage_key' => $key, 'storage_disk' => $diskName, 'processed_variants' => $variants, 'mime_type' => $source->mimeType, 'width' => $source->width, 'height' => $source->height, 'file_size_bytes' => $source->fileSizeBytes, 'processing_status' => 'complete', 'health_status' => 'healthy']));
                $this->audit($actor, $media, 'uploaded', [], $this->snapshot($media));

                return $media;
            });
        } catch (\Throwable $exception) {
            $disk->deleteDirectory($targetDirectory);
            throw $exception;
        }

        return $media;
    }
}
