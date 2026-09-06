<?php

namespace App\Filament\Resources\WalkResource\Pages;

use App\Domain\Walks\Actions\CreateWalk as CreateWalkAction;
use App\Filament\Components\AccessibleWizard;
use App\Filament\Resources\WalkResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Database\Eloquent\Model;

final class CreateWalk extends CreateRecord
{
    use HasWizard;

    protected static string $resource = WalkResource::class;

    /** @return list<Step> */
    public function getSteps(): array
    {
        return [
            Step::make('When and where')->schema($this->fields([
                'title', 'starts_at', 'ends_at', 'meeting_location_name', 'meeting_address',
                'meeting_postcode', 'latitude', 'longitude', 'what3words', 'os_grid_reference',
            ])),
            Step::make('Walk details')->schema($this->fields([
                'grade_id', 'tag_ids', 'distance', 'ascent', 'estimated_duration_minutes',
                'capacity', 'availability', 'terrain_notes',
            ])),
            Step::make('Travel and practical information')->schema($this->fields([
                'directions', 'is_public_transport_friendly', 'public_transport_station_stop',
                'public_transport_notes', 'public_transport_url', 'parking_notes', 'toilet_information',
                'cafe_pub_information', 'dog_guidance', 'accessibility_notes', 'kit_checklist', 'kit_notes',
            ])),
            Step::make('Description, route and image')->schema($this->fields([
                'summary', 'description', 'featured_image_path', 'attachments',
            ])),
            Step::make('Leader and publishing')->schema($this->fields([
                'primary_leader_id', 'co_leader_ids', 'private_organiser_notes',
            ])),
        ];
    }

    public function getWizardComponent(): Component
    {
        return AccessibleWizard::make($this->getSteps())
            ->startOnStep($this->getStartStep())
            ->cancelAction($this->getCancelFormAction())
            ->submitAction($this->getSubmitFormAction())
            ->alpineSubmitHandler("\$wire.{$this->getSubmitFormLivewireMethodName()}()")
            ->skippable($this->hasSkippableSteps())
            ->contained(false);
    }

    /**
     * @param  list<string>  $names
     * @return list<Component>
     */
    private function fields(array $names): array
    {
        $fields = collect(WalkResource::formComponents())->keyBy(
            fn (Component $component): string => $component->getName(),
        );

        return collect($names)->map(fn (string $name): Component => $fields->get($name))->all();
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        return app(CreateWalkAction::class)->handle($user, $data);
    }
}
