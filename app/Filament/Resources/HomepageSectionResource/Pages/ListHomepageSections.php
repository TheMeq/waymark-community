<?php

namespace App\Filament\Resources\HomepageSectionResource\Pages;

use App\Domain\Content\Actions\SnapshotHomepageSectionReorder;
use App\Domain\Content\Models\HomepageSection;
use App\Filament\Resources\HomepageSectionResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListHomepageSections extends ListRecords
{
    protected static string $resource = HomepageSectionResource::class;

    /** @var array<int, array<string, mixed>> */
    public array $homepageSectionValuesBeforeReorder = [];

    /** @param array<int|string> $order */
    public function captureHomepageSectionState(array $order): void
    {
        $this->homepageSectionValuesBeforeReorder = HomepageSection::query()
            ->whereKey($order)
            ->get()
            ->mapWithKeys(fn (HomepageSection $section): array => [$section->id => $section->getAttributes()])
            ->all();
    }

    public function snapshotHomepageSectionReorder(): void
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        app(SnapshotHomepageSectionReorder::class)->handle($actor, $this->homepageSectionValuesBeforeReorder);
        $this->homepageSectionValuesBeforeReorder = [];
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
