<?php

namespace App\Filament\Pages;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Operations\Actions\UpdateSiteProfile;
use App\Domain\Operations\Models\SiteProfile;
use App\Models\User;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Schema;

final class BrandingSettings extends Page
{
    protected static ?string $title = 'Branding';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuration';

    protected string $view = 'filament.pages.branding-settings';

    public array $data = [];

    public static function getSlug(?Panel $panel = null): string
    {
        return 'branding';
    }

    public static function canAccess(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->hasCapability(ModuleCapability::ManageContent);
    }

    public function mount(): void
    {
        $profile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;
        $this->form->fill($profile->only(['logo_path', 'favicon_path', 'hero_default_path', 'primary_colour', 'accent_colour', 'typography_option', 'affiliation_name', 'affiliation_url']));
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('logo_path'), TextInput::make('favicon_path'), TextInput::make('hero_default_path'), ColorPicker::make('primary_colour'), ColorPicker::make('accent_colour'), Select::make('typography_option')->options(['instrument' => 'Instrument Sans', 'system' => 'System sans'])->required(), TextInput::make('affiliation_name'), TextInput::make('affiliation_url')])->statePath('data');
    }

    public function save(): void
    { /** @var User $actor */ $actor = auth()->user();
        app(UpdateSiteProfile::class)->handle($this->form->getState(), $actor);
        Notification::make()->success()->title('Branding saved')->send();
    }
}
