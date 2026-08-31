<?php

namespace App\Filament\Pages;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Operations\Actions\CreateBrandingPreview;
use App\Domain\Operations\Actions\UpdateSiteProfile;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Models\User;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Schema;

final class BrandingSettings extends Page
{
    protected static ?string $title = 'Branding';

    protected static string|\UnitEnum|null $navigationGroup = 'Group settings';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.branding-settings';

    public array $data = [];

    /** @var array{desktop: string, tablet: string, mobile: string}|array{} */
    public array $previewLinks = [];

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
        $this->form->fill($profile->only(['group_name', 'short_name', 'contact_email', 'logo_path', 'favicon_path', 'hero_default_path', 'primary_colour', 'accent_colour', 'typography_option', 'social_links', 'terminology', 'affiliation_name', 'affiliation_url']));
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('group_name')->label('Group name')->required()->maxLength(255),
            TextInput::make('short_name')->label('Short name')->maxLength(50),
            TextInput::make('contact_email')->label('Public contact email')->email(),
            TextInput::make('logo_path')->label('Logo URL')->maxLength(2048),
            TextInput::make('favicon_path')->label('Favicon URL')->maxLength(2048),
            TextInput::make('hero_default_path')->label('Default hero image URL')->maxLength(2048),
            ColorPicker::make('primary_colour')
                ->label('Primary colour')
                ->live()
                ->helperText(fn (?string $state): ?string => BrandTheme::contrastGuidance($state)),
            ColorPicker::make('accent_colour')
                ->label('Accent colour')
                ->live()
                ->helperText(fn (?string $state): ?string => BrandTheme::contrastGuidance($state)),
            Select::make('typography_option')->options(['instrument' => 'Instrument Sans', 'system' => 'System sans'])->required(),
            KeyValue::make('social_links')->label('Social links')->keyLabel('Label')->valueLabel('Secure URL'),
            KeyValue::make('terminology')->label('Terminology aliases')->keyLabel('Approved key')->valueLabel('Public label'),
            TextInput::make('affiliation_name')->label('Affiliation name')->maxLength(255),
            TextInput::make('affiliation_url')->label('Affiliation URL')->url()->maxLength(2048),
        ])->statePath('data');
    }

    public function save(): void
    { /** @var User $actor */ $actor = auth()->user();
        app(UpdateSiteProfile::class)->handle($this->form->getState(), $actor);
        Notification::make()->success()->title('Branding saved')->send();
    }

    public function preview(): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $preview = app(CreateBrandingPreview::class)->handle($actor, $this->form->getState());

        $this->previewLinks = collect(['desktop', 'tablet', 'mobile'])
            ->mapWithKeys(fn (string $viewport): array => [
                $viewport => route('branding.preview', ['token' => $preview->token, 'viewport' => $viewport]),
            ])
            ->all();
    }
}
