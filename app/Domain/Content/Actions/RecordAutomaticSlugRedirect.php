<?php

namespace App\Domain\Content\Actions;

use App\Domain\Content\Models\PublicRedirect;
use Illuminate\Support\Facades\DB;

final class RecordAutomaticSlugRedirect
{
    public function handle(string $sourcePath, string $targetPath): void
    {
        DB::transaction(function () use ($sourcePath, $targetPath): void {
            PublicRedirect::query()
                ->where('source_path', $targetPath)
                ->lockForUpdate()
                ->first()
                ?->delete();

            PublicRedirect::query()
                ->where('target_url', $sourcePath)
                ->lockForUpdate()
                ->get()
                ->each(function (PublicRedirect $redirect) use ($targetPath): void {
                    $redirect->target_url = $targetPath;
                    $redirect->save();
                });

            $redirect = PublicRedirect::query()
                ->where('source_path', $sourcePath)
                ->lockForUpdate()
                ->first() ?? new PublicRedirect;
            $redirect->fill([
                'source_path' => $sourcePath,
                'target_url' => $targetPath,
                'status_code' => 301,
                'enabled' => true,
                'automatic' => true,
                'created_by_user_id' => null,
            ])->save();
        });
    }
}
