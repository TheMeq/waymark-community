<?php

namespace App\Domain\SiteMedia\Actions;

use App\Domain\Content\Presentation\PublicImageReference;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\SiteMedia\Enums\SiteMediaPurpose;
use App\Domain\SiteMedia\SiteMediaPresenter;
use Illuminate\Support\Facades\Storage;

final readonly class RenderInstallationIcon
{
    public function __construct(private SiteMediaPresenter $presenter, private DownloadPublicIconImage $downloads) {}

    public function available(SiteProfile $profile): bool
    {
        return $this->source($profile) !== null;
    }

    public function render(SiteProfile $profile, int $size): ?string
    {
        if (! in_array($size, [192, 512], true) || ($source = $this->source($profile)) === null) {
            return null;
        }
        $bytes = isset($source['url']) ? $this->downloads->get($source['url']) : Storage::disk($source['disk'])->get($source['path']);
        $dimensions = $bytes === null ? false : @getimagesizefromstring($bytes);
        if ($dimensions === false || $dimensions[0] * $dimensions[1] > 4000000 || ! in_array($dimensions[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_AVIF], true)) {
            return null;
        }
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return null;
        }
        $canvas = imagecreatetruecolor($size, $size);
        try {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 255, 255, 255, 127));
            $scale = ($size * .7) / max(imagesx($image), imagesy($image));
            $width = max(1, (int) round(imagesx($image) * $scale));
            $height = max(1, (int) round(imagesy($image) * $scale));
            imagecopyresampled($canvas, $image, (int) (($size - $width) / 2), (int) (($size - $height) / 2), 0, 0, $width, $height, imagesx($image), imagesy($image));
            ob_start();
            try {
                imagepng($canvas);

                return (string) ob_get_contents();
            } finally {
                ob_end_clean();
            }
        } finally {
            imagedestroy($image);
            imagedestroy($canvas);
        }
    }

    /** @return array{disk:string,path:string}|array{url:string}|null */
    private function source(SiteProfile $profile): ?array
    {
        $media = $profile->logoMedia;
        if ($media?->purpose !== SiteMediaPurpose::SiteLogo || $this->presenter->present($media, 'medium') === null) {
            $external = PublicImageReference::resolve($profile->logo_path);

            return is_string($external) && str_starts_with($external, 'https://') ? ['url' => $external] : null;
        }
        $path = $media->processed_variants['medium'];
        if (Storage::disk($media->storage_disk)->size($path) > 8 * 1024 * 1024) {
            return null;
        }

        return ['disk' => $media->storage_disk, 'path' => $path];
    }
}
