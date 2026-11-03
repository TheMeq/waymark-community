<?php

namespace App\Filament\Forms;

use App\Domain\Content\Presentation\PublicImageReference;
use App\Domain\SiteMedia\ContentImagePresenter;
use App\Domain\SiteMedia\Enums\ContentImageSlot;
use App\Rules\PublicImageReferenceRule;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;

final class ContentImageFields
{
    public static function components(string $label = 'Image'): array
    {
        return [
            Radio::make('content_image_source')->label($label)->options([
                'managed' => 'Upload a local image (recommended)', 'external' => 'Use an external image instead', 'none' => 'No image',
            ])->default('managed')->required()->live(),
            FileUpload::make('content_image_upload')->label('Upload image')->image()->editableSvgs(false)
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif'])
                ->maxSize(max(1, (int) ceil(config('gallery.processing.max_upload_bytes', 10485760) / 1024)))
                ->storeFiles(false)->live()->visible(fn (Get $get): bool => $get('content_image_source') === 'managed'),
            TextInput::make('content_image_url')->label('External image URL or site path')->maxLength(2048)
                ->rule(new PublicImageReferenceRule)->required(fn (Get $get): bool => $get('content_image_source') === 'external')
                ->visible(fn (Get $get): bool => $get('content_image_source') === 'external'),
            Textarea::make('content_image_alt_text')->label('Image description')->maxLength(2000)->rows(2)
                ->required(fn (Get $get): bool => $get('content_image_source') === 'external' || filled($get('content_image_upload')))
                ->visible(fn (Get $get): bool => $get('content_image_source') !== 'none'),
            Toggle::make('content_image_remove_fallback')->label('Also remove the saved external fallback')->default(false)
                ->visible(fn (Get $get): bool => $get('content_image_source') === 'none' && filled($get('content_image_preview.fallback_url'))),
            ViewField::make('content_image_preview')->label('Image preview and status')
                ->view('filament.forms.components.managed-image-preview')
                ->viewData(fn (Get $get): array => [
                    'preview' => $get('content_image_preview'), 'pendingUpload' => filled($get('content_image_upload')),
                    'pendingMessage' => 'Your selected image will be saved with this form.',
                    'emptyMessage' => 'No saved image.',
                ])->dehydrated(false),
        ];
    }

    public static function fill(Model $owner, ContentImageSlot $slot): array
    {
        $image = app(ContentImagePresenter::class)->present($owner, $slot);
        $managed = $image !== null && $image->id !== 0;
        $path = $owner->{$slot->pathColumn()};
        $alt = $owner->{$slot->altColumn()} ?: $image?->alt;
        if (! filled($alt) && $slot === ContentImageSlot::Holiday && $image !== null) {
            $alt = $owner->event?->title;
        }

        return [
            'content_image_source' => $image === null ? 'none' : ($managed ? 'managed' : 'external'),
            'content_image_upload' => null, 'content_image_url' => $path,
            'content_image_alt_text' => $alt,
            'content_image_remove_fallback' => false,
            'content_image_preview' => $image === null ? null : [
                'source' => $managed ? 'managed' : 'external', 'url' => $image->url, 'alt' => $image->alt,
                'object_position' => $image->objectPosition(),
                'fallback_url' => $managed ? PublicImageReference::resolve($path) : null,
            ],
        ];
    }

    public static function stateNames(): array
    {
        return ['content_image_source', 'content_image_upload', 'content_image_url', 'content_image_alt_text', 'content_image_remove_fallback', 'content_image_preview'];
    }
}
