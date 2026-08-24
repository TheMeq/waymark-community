<?php

namespace App\Domain\Operations\Health\Actions;

use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Governance\Models\DocumentVersion;
use App\Domain\Operations\Health\Models\MissingMediaRepair;
use App\Domain\Operations\Health\ScanMissingMediaResult;
use App\Domain\SiteMedia\Models\SiteMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

final class ScanMissingMedia
{
    public function handle(int $limit = 500): ScanMissingMediaResult
    {
        $limit = max(1, min($limit, 1000));
        $checked = 0;
        $missing = 0;

        SiteMedia::query()->where('processing_status', 'complete')->orderBy('id')->limit($limit)->get()
            ->each(function (SiteMedia $media) use (&$checked, &$missing): void {
                foreach ((array) $media->processed_variants as $path) {
                    if (is_string($path)) {
                        $this->check($media, (string) $media->storage_disk, $path, $checked, $missing);
                    }
                }
            });

        $remaining = max(0, $limit - $checked);
        CommunityPhoto::query()->where('processing_status', 'complete')->orderBy('id')->limit($remaining)->get()
            ->each(function (CommunityPhoto $photo) use (&$checked, &$missing): void {
                foreach ((array) $photo->processed_variants as $path) {
                    if (is_string($path)) {
                        $this->check($photo, (string) $photo->storage_disk, $path, $checked, $missing);
                    }
                }
            });

        $remaining = max(0, $limit - $checked);
        DocumentVersion::query()->orderBy('id')->limit($remaining)->get()
            ->each(function (DocumentVersion $version) use (&$checked, &$missing): void {
                $this->check($version, (string) $version->storage_disk, (string) $version->storage_path, $checked, $missing);
            });

        return new ScanMissingMediaResult($checked, $missing);
    }

    private function check(Model $record, string $disk, string $path, int &$checked, int &$missing): void
    {
        $checked++;
        $hash = MissingMediaRepair::referenceHash($record::class, (int) $record->getKey(), $disk, $path);
        $exists = Storage::disk($disk)->exists($path);

        if ($exists) {
            MissingMediaRepair::query()->where('reference_hash', $hash)->where('status', 'queued')->update([
                'status' => 'resolved', 'last_checked_at' => now(), 'resolved_at' => now(), 'updated_at' => now(),
            ]);

            return;
        }

        $missing++;
        $repair = MissingMediaRepair::query()->firstOrNew(['reference_hash' => $hash]);
        $repair->fill([
            'media_type' => $record::class,
            'record_id' => $record->getKey(),
            'storage_disk' => $disk,
            'path' => $path,
            'status' => 'queued',
            'last_checked_at' => now(),
            'resolved_at' => null,
        ]);
        if (! $repair->exists) {
            $repair->detected_at = now();
        }
        $repair->save();

        if ($record instanceof SiteMedia && $record->health_status !== 'repair_required') {
            $record->forceFill(['health_status' => 'repair_required'])->save();
        }
    }
}
