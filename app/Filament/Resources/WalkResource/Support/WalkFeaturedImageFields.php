<?php

namespace App\Filament\Resources\WalkResource\Support;

use App\Rules\PublicImageReferenceRule;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Http\UploadedFile;

final class WalkFeaturedImageFields
{
    /** @return list<Component> */
    public static function components(): array
    {
        return [
            Radio::make('featured_image_source')
                ->label('Featured image')
                ->options([
                    'managed' => 'Upload a local image (recommended)',
                    'external' => 'Use an external image instead',
                    'none' => 'No specific image',
                ])
                ->default('managed')
                ->required()
                ->live()
                ->helperText('Local upload is recommended because the image remains under your group’s control.'),
            FileUpload::make('featured_image_upload')
                ->label('Upload a local image')
                ->image()
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif'])
                ->maxSize(max(1, (int) ceil(((int) config('gallery.processing.max_upload_bytes', 10 * 1024 * 1024)) / 1024)))
                ->storeFiles(false)
                ->live()
                ->visible(fn (Get $get): bool => $get('featured_image_source') === 'managed')
                ->helperText('Choose a JPEG, PNG, WebP or AVIF image. Your selected image is saved when you continue from Step 4.'),
            TextInput::make('featured_image_external_url')
                ->label('External image URL or site path')
                ->maxLength(2048)
                ->rule(new PublicImageReferenceRule)
                ->required(fn (Get $get): bool => $get('featured_image_source') === 'external')
                ->visible(fn (Get $get): bool => $get('featured_image_source') === 'external')
                ->helperText('Use a secure HTTPS URL or a safe root-relative site path. A local upload is more reliable.'),
            Radio::make('featured_image_remove_fallback')
                ->label('After removing the local image')
                ->options([
                    'reveal' => 'Use the saved external fallback',
                    'clear' => 'Clear both sources',
                ])
                ->default('reveal')
                ->required(fn (Get $get): bool => self::removingManagedWithFallback($get))
                ->visible(fn (Get $get): bool => self::removingManagedWithFallback($get))
                ->live(),
            Textarea::make('featured_image_alt_text')
                ->label('Image description')
                ->rows(3)
                ->maxLength(2000)
                ->required(fn (Get $get): bool => self::descriptionRequired($get))
                ->visible(fn (Get $get): bool => self::descriptionVisible($get))
                ->helperText('Describe the image for people who cannot see it.'),
            ViewField::make('featured_image_preview')
                ->label('Image preview and status')
                ->view('filament.forms.components.managed-image-preview')
                ->viewData(fn (Get $get): array => [
                    'preview' => $get('featured_image_preview'),
                    'pendingUpload' => filled($get('featured_image_upload')),
                ])
                ->dehydrated(false),
        ];
    }

    /** @return list<string> */
    public static function stateNames(): array
    {
        return [
            'featured_image_source',
            'featured_image_upload',
            'featured_image_external_url',
            'featured_image_alt_text',
            'featured_image_remove_fallback',
            'featured_image_preview',
        ];
    }

    public static function uploadedFile(mixed $state): ?UploadedFile
    {
        if ($state instanceof UploadedFile) {
            return $state;
        }

        if (! is_array($state)) {
            return null;
        }

        foreach ($state as $file) {
            if ($file instanceof UploadedFile) {
                return $file;
            }
        }

        return null;
    }

    private static function descriptionVisible(Get $get): bool
    {
        return $get('featured_image_source') !== 'none' || self::revealingFallback($get);
    }

    private static function descriptionRequired(Get $get): bool
    {
        if ($get('featured_image_source') === 'external' || self::revealingFallback($get)) {
            return true;
        }

        if ($get('featured_image_source') !== 'managed') {
            return false;
        }

        $preview = $get('featured_image_preview');

        return filled($get('featured_image_upload'))
            || (is_array($preview) && ($preview['source'] ?? null) === 'managed');
    }

    private static function removingManagedWithFallback(Get $get): bool
    {
        $preview = $get('featured_image_preview');

        return $get('featured_image_source') === 'none'
            && is_array($preview)
            && ($preview['source'] ?? null) === 'managed'
            && filled($preview['fallback_url'] ?? null);
    }

    private static function revealingFallback(Get $get): bool
    {
        return self::removingManagedWithFallback($get)
            && $get('featured_image_remove_fallback') === 'reveal';
    }
}
