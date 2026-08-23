<?php

namespace App\Filament\Resources\PolicyPageResource\Pages;

use App\Filament\Resources\PolicyPageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListPolicyPages extends ListRecords
{
    protected static string $resource = PolicyPageResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()]; }
}
