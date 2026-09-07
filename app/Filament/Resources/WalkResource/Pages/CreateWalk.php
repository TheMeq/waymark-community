<?php

namespace App\Filament\Resources\WalkResource\Pages;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Walks\Actions\CreateWalk as CreateWalkAction;
use App\Domain\Walks\Actions\SaveWalkDraft;
use App\Domain\Walks\Models\Walk;
use App\Filament\Components\AccessibleWizard;
use App\Filament\Resources\WalkResource;
use App\Filament\Resources\WalkResource\Support\WalkFormData;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\View as ViewComponent;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Throwable;

final class CreateWalk extends CreateRecord
{
    use HasWizard;

    private const CHECKPOINT_FIELDS = [
        1 => [
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
        ],
        2 => [
            'grade_id',
            'tag_ids',
            'distance',
            'ascent',
            'estimated_duration_minutes',
            'capacity',
            'availability',
            'terrain_notes',
        ],
        3 => [
            'directions',
            'is_public_transport_friendly',
            'public_transport_station_stop',
            'public_transport_notes',
            'public_transport_url',
            'parking_notes',
            'toilet_information',
            'cafe_pub_information',
            'dog_guidance',
            'accessibility_notes',
            'kit_checklist',
            'kit_notes',
        ],
        4 => [
            'summary',
            'description',
            'featured_image_path',
            'attachments',
        ],
    ];

    protected static string $resource = WalkResource::class;

    #[Url(as: 'draft')]
    public ?int $draftId = null;

    public ?string $draftSaveStatus = null;

    public ?string $draftSaveError = null;

    public function mount(): void
    {
        parent::mount();

        if ($this->draftId !== null) {
            $this->form->fill(WalkFormData::from($this->resolveDraft()));
        }
    }

    public function hydrate(): void
    {
        parent::hydrate();

        if ($this->draftId !== null) {
            $this->resolveDraft();
        }
    }

    /** @return list<Step> */
    public function getSteps(): array
    {
        return [
            Step::make('When and where')
                ->id('when-and-where')
                ->schema($this->fields(self::CHECKPOINT_FIELDS[1]))
                ->afterValidation(fn () => $this->checkpointStep(1)),
            Step::make('Walk details')
                ->id('walk-details')
                ->schema($this->fields(self::CHECKPOINT_FIELDS[2]))
                ->afterValidation(fn () => $this->checkpointStep(2)),
            Step::make('Travel and practical information')
                ->id('travel-and-practical-information')
                ->schema($this->fields(self::CHECKPOINT_FIELDS[3]))
                ->afterValidation(fn () => $this->checkpointStep(3)),
            Step::make('Description, route and image')
                ->id('description-route-and-image')
                ->schema($this->fields(self::CHECKPOINT_FIELDS[4]))
                ->afterValidation(fn () => $this->checkpointStep(4)),
            Step::make('Leader and publishing')
                ->id('leader-and-publishing')
                ->schema($this->fields([
                    'primary_leader_id', 'co_leader_ids', 'private_organiser_notes',
                ])),
        ];
    }

    public function getWizardComponent(): Component
    {
        return AccessibleWizard::make($this->getSteps())
            ->persistStepInQueryString('step')
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

    private function checkpointStep(int $step): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $attributes = Arr::only($this->data, self::CHECKPOINT_FIELDS[$step]);

        if ($this->draftId === null && $step !== 1) {
            $this->draftSaveStatus = null;
            $this->draftSaveError = "We couldn't save your draft. Your entries are still on this page. Try again.";
            $this->dispatch('walk-draft-save-failed');

            throw new Halt;
        }

        try {
            $draft = $this->draftId === null
                ? app(SaveWalkDraft::class)->create($actor, $attributes)
                : app(SaveWalkDraft::class)->update(
                    $this->resolveDraft(),
                    $actor,
                    $attributes,
                );
        } catch (Throwable $exception) {
            report($exception);

            $this->draftSaveStatus = null;
            $this->draftSaveError = "We couldn't save your draft. Your entries are still on this page. Try again.";
            $this->dispatch('walk-draft-save-failed');

            throw new Halt;
        }

        $this->draftId ??= $draft->id;
        $this->data['primary_leader_id'] ??= $actor->id;
        $this->draftSaveError = null;
        $this->draftSaveStatus = 'Draft saved';
    }

    private function resolveDraft(): Walk
    {
        /** @var User $actor */
        $actor = auth()->user();
        $walk = WalkResource::getEloquentQuery()
            ->with(['event', 'coLeaders', 'tags'])
            ->whereKey($this->draftId)
            ->firstOrFail();

        Gate::forUser($actor)->authorize('update', $walk);
        abort_unless($walk->event->status === EventStatus::Draft, 404);

        return $walk;
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        return app(CreateWalkAction::class)->handle($user, $data);
    }
}
