<?php

namespace App\Domain\SiteMedia\Actions;

use App\Domain\SiteMedia\Data\SiteMediaMetadata;
use App\Domain\SiteMedia\Enums\ContentImageSlot;
use App\Domain\SiteMedia\Enums\SiteMediaPurpose;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Domain\SiteMedia\Models\SiteMediaAudit;
use App\Domain\SiteMedia\Queries\SiteMediaUsage;
use App\Models\User;
use App\Rules\PublicImageReferenceRule;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class SaveContentImage
{
    public function __construct(private CreateSiteMedia $createMedia, private MarkSiteMediaOrphaned $markOrphaned) {}

    public function prepare(User $actor, ContentImageSlot $slot, array $state, ?Model $owner = null): array
    {
        $this->authorize($actor, $slot, $owner);
        $source = $state['content_image_source'] ?? 'none';
        $upload = $state['content_image_upload'] ?? null;
        if (is_array($upload)) {
            $upload = collect($upload)->first(fn ($file): bool => $file instanceof UploadedFile);
        }
        $url = $source === 'external' ? ($state['content_image_url'] ?? null) : null;
        $alt = trim((string) ($state['content_image_alt_text'] ?? '')) ?: null;
        $values = Validator::make([
            'content_image_source' => $source, 'content_image_upload' => $upload,
            'content_image_url' => $url, 'content_image_alt_text' => $alt,
        ], [
            'content_image_source' => ['required', 'in:managed,external,none'],
            'content_image_upload' => ['nullable', 'file'],
            'content_image_url' => [$source === 'external' ? 'required' : 'nullable', new PublicImageReferenceRule],
            'content_image_alt_text' => [($source === 'external' || $upload !== null) ? 'required' : 'nullable', 'string', 'max:2000'],
        ])->validate();
        $mediaId = $owner?->{$slot->mediaColumn()};
        $path = $owner?->{$slot->pathColumn()};
        if ($source === 'managed' && $upload !== null) {
            try {
                $media = $this->createMedia->handle($actor, $upload, new SiteMediaMetadata($alt, false), SiteMediaPurpose::ContentImage);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages(['content_image_upload' => collect($exception->errors())->flatten()->all()]);
            }
            $mediaId = $media->id;
        } elseif ($source === 'external') {
            $mediaId = null;
            $path = $values['content_image_url'];
        } elseif ($source === 'none') {
            $mediaId = null;
            if (! $owner?->{$slot->mediaColumn()} || ($state['content_image_remove_fallback'] ?? false)) {
                $path = null;
            }
            $alt = $path === null ? null : ($owner?->{$slot->altColumn()} ?? $alt);
        } elseif ($mediaId === null && filled($path)) {
            throw ValidationException::withMessages(['content_image_upload' => 'Choose an image to upload.']);
        }

        return [$slot->mediaColumn() => $mediaId, $slot->pathColumn() => $path, $slot->altColumn() => $alt];
    }

    public function complete(User $actor, ContentImageSlot $slot, Model $owner, ?int $previousId): void
    {
        $this->authorize($actor, $slot, $owner);
        $owner->refresh();
        $currentId = $owner->{$slot->mediaColumn()};
        if ($currentId !== null) {
            $media = SiteMedia::query()->findOrFail($currentId);
            if ($media->orphaned_at !== null) {
                $media->forceFill(['orphaned_at' => null])->save();
                SiteMediaAudit::query()->create([
                    'site_media_id' => $media->id, 'actor_user_id' => $actor->id, 'action' => 'attached',
                    'context' => ['owner_type' => $slot->value, 'owner_id' => $owner->id],
                ]);
            }
        }
        if ($previousId !== null && $previousId !== $currentId) {
            $former = SiteMedia::query()->find($previousId);
            if ($former !== null) {
                $this->markOrphaned->handle($former);
            }
        }
    }

    public function prepareBlock(User $actor, ContentImageSlot $slot, array $block): array
    {
        $model = $slot->value;
        $owner = new $model([
            $slot->mediaColumn() => $block['media_id'] ?? null,
            $slot->pathColumn() => $block['image_path'] ?? null,
            $slot->altColumn() => $block['image_alt_text'] ?? null,
        ]);
        $values = $this->prepare($actor, $slot, $block, $owner);

        return [
            ...Arr::except($block, ['content_image_source', 'content_image_upload', 'content_image_url', 'content_image_alt_text', 'content_image_preview', 'content_image_remove_fallback']),
            'media_id' => $values[$slot->mediaColumn()], 'image_path' => $values[$slot->pathColumn()],
            'image_alt_text' => $values[$slot->altColumn()],
        ];
    }

    public function completeBlocks(User $actor, ContentImageSlot $slot, Model $owner, array $previousIds): void
    {
        $this->authorize($actor, $slot, $owner);
        $ids = collect((array) $owner->getAttribute('blocks'))->pluck('media_id')->filter()->map(fn ($id): int => (int) $id)->all();
        foreach (SiteMedia::query()->whereIn('id', $ids)->whereNotNull('orphaned_at')->get() as $media) {
            if (app(SiteMediaUsage::class)->isUsed($media)) {
                $media->forceFill(['orphaned_at' => null])->save();
            }
        }
        foreach (SiteMedia::query()->whereIn('id', array_diff($previousIds, $ids))->get() as $media) {
            $this->markOrphaned->handle($media);
        }
    }

    private function authorize(User $actor, ContentImageSlot $slot, ?Model $owner): void
    {
        if (! $actor->hasCapability($slot->capability()) || ($owner !== null && get_class($owner) !== $slot->value)) {
            throw new AuthorizationException;
        }
    }
}
