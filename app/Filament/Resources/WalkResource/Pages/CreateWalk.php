<?php

namespace App\Filament\Resources\WalkResource\Pages;

use App\Domain\Walks\Actions\CreateWalk as CreateWalkAction;
use App\Domain\Walks\Actions\SaveWalkDraft;
use App\Domain\Walks\Models\Walk;
use App\Filament\Components\AccessibleWizard;
use App\Filament\Resources\WalkResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\View as ViewComponent;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Livewire\Attributes\Url;

final class CreateWalk extends CreateRecord
{
    use HasWizard;

    private const STEP_ONE_FIELDS = [
        'title',
        'starts_at',
        'ends_at',
        'meeting_location_name',
        'meeting_address',
        'meeting_postcode',
        'latitude',
        'longitude',
        'what3words',
        'os_grid_reference',
    ];

    protected static string $resource = WalkResource::class;

    #[Url(as: 'draft')]
    public ?int $draftId = null;

    public ?string $draftSaveStatus = null;

    public ?string $draftSaveError = null;

    /** @return list<Step> */
    public function getSteps(): array
    {
        return [
            Step::make('When and where')
                ->schema($this->fields(self::STEP_ONE_FIELDS))
                ->afterValidation(fn () => $this->checkpointStepOne()),
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

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
            ViewComponent::make('filament.walks.draft-save-status'),
        ]);
    }

    /**
     * @param  list<string>  $names
     * @return list<Component>
     */
    private function fields(array $names): array
    {
        $fields = collect(WalkResource::formComponents(allowInlineSupportingDataCreation: true))->keyBy(
            fn (Component $component): string => $component->getName(),
        );

        return collect($names)->map(fn (string $name): Component => $fields->get($name))->all();
    }

    private function checkpointStepOne(): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $attributes = Arr::only($this->data, self::STEP_ONE_FIELDS);

        $draft = $this->draftId === null
            ? app(SaveWalkDraft::class)->create($actor, $attributes)
            : app(SaveWalkDraft::class)->update(
                Walk::query()->findOrFail($this->draftId),
                $actor,
                $attributes,
            );

        $this->draftId ??= $draft->id;
        $this->data['primary_leader_id'] ??= $actor->id;
        $this->draftSaveError = null;
        $this->draftSaveStatus = 'Draft saved';
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        return app(CreateWalkAction::class)->handle($user, $data);
    }
}
