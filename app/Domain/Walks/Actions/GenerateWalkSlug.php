<?php

namespace App\Domain\Walks\Actions;

use App\Domain\Events\Models\Event;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

final class GenerateWalkSlug
{
    public function handle(string $title, mixed $startsAt): string
    {
        $date = $startsAt instanceof CarbonInterface
            ? $startsAt
            : CarbonImmutable::parse((string) $startsAt);
        $dateSuffix = '-'.$date->format('Y-m-d');
        $titleSlug = Str::slug($title) ?: 'walk';
        $slug = Str::limit($titleSlug, 255 - strlen($dateSuffix), '').$dateSuffix;

        for ($suffix = 2; Event::query()->where('slug', $slug)->exists(); $suffix++) {
            $suffixText = '-'.$suffix;
            $slug = Str::limit($titleSlug, 255 - strlen($dateSuffix) - strlen($suffixText), '').$dateSuffix.$suffixText;
        }

        return $slug;
    }
}
