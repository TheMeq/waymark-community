<?php

namespace App\Domain\Content\Actions;

use App\Domain\Content\Models\PublicRedirect;
use Illuminate\Support\Facades\DB;

final class RecordAutomaticSlugRedirect
{
    public function handle(string $sourcePath, string $targetPath): void
    {
        DB::transaction(function () use ($sourcePath, $targetPath): void {
            PublicRedirect::query()->where('target_url', $sourcePath)->update(['target_url' => $targetPath]);
            PublicRedirect::query()->updateOrCreate(
                ['source_path' => $sourcePath],
                ['target_url' => $targetPath, 'status_code' => 301, 'enabled' => true, 'automatic' => true, 'created_by_user_id' => null],
            );
        });
    }
}
