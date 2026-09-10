<?php

namespace App\Filament\Pages;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Content\Presentation\PublicImageReference;
use App\Domain\Content\Queries\PublicBranding;
use App\Domain\Operations\Actions\CreateBrandingPreview;
use App\Domain\Operations\Actions\UpdateBrandingImage;
use App\Domain\Operations\Actions\UpdateSiteProfile;
use App\Domain\Operations\Data\BrandingImageInput;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Domain\SiteMedia\Enums\SiteMediaPurpose;
use App\Models\User;
use App\Rules\PublicImageReferenceRule;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

final class BrandingSettings extends Page
{
    protected static ?string $title = 'Branding';

    protected static string|\UnitEnum|null $navigationGroup = 'Group settings';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.branding-settings';

    public array $data = [];

    /** @var array{desktop: string, tablet: string, mobile: string}|array{} */
    public array $previewLinks = [];

    /** @var array<string, mixed>|null */
    public ?array $logoPreview = null;

    /** @var array<string, mixed>|null */
    public ?array $faviconPreview = null;

    public string $saveStatus = '';

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
        $profile = SiteProfile::query()
            ->with(['logoMedia', 'faviconMedia'])
            ->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;

        $this->fillFromProfile($profile);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('group_name')->label('Group name')->required()->maxLength(255),
            TextInput::make('short_name')->label('Short name')->maxLength(50),
            TextInput::make('contact_email')->label('Public contact email')->email(),
            Section::make('Logo')
                ->description('Keep your group identity reliable with a local upload, or explicitly use an external image.')
                ->schema($this->brandingImageComponents('logo')),
            Section::make('Favicon')
                ->description('This small square image appears in browser tabs and bookmarks. Choose a square image around 512 by 512 pixels or larger; the enforced minimum is 32 by 32 pixels.')
                ->schema($this->brandingImageComponents('favicon')),
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
    {
        /** @var User $actor */
        $actor = auth()->user();
        $this->saveStatus = '';
        $state = $this->form->getState();
        $profile = app(UpdateSiteProfile::class)->handle(Arr::except($state, $this->brandingImageStateNames()), $actor);

        foreach ([
            'logo' => SiteMediaPurpose::SiteLogo,
            'favicon' => SiteMediaPurpose::SiteFavicon,
        ] as $slot => $purpose) {
            if (! $this->brandingImageChangeRequested($profile, $slot, $state)) {
                continue;
            }

            $profile = $this->updateBrandingImage($actor, $profile, $purpose, $slot, $state);
        }

        $this->fillFromProfile($profile);
        $this->saveStatus = 'Branding saved.';
        Notification::make()->success()->title('Branding saved')->send();
    }

    public function preview(): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $state = $this->form->getState();
        $state['logo_upload'] = $this->uploadedFile($state['logo_upload'] ?? null);
        $state['favicon_upload'] = $this->uploadedFile($state['favicon_upload'] ?? null);
        $preview = app(CreateBrandingPreview::class)->handle($actor, $state);

        $this->previewLinks = collect(['desktop', 'tablet', 'mobile'])
            ->mapWithKeys(fn (string $viewport): array => [
                $viewport => route('branding.preview', ['token' => $preview->token, 'viewport' => $viewport]),
            ])
            ->all();
    }

    /** @return list<Component> */
    private function brandingImageComponents(string $slot): array
    {
        $isFavicon = $slot === 'favicon';
        $label = $isFavicon ? 'favicon' : 'logo';

        return [
            Radio::make($slot.'_source')
                ->label(ucfirst($label).' source')
                ->options([
                    'managed' => 'Upload a local '.$label.' (recommended)',
                    'external' => 'Use an external '.$label.' instead',
                    'none' => 'No '.$label,
                ])
                ->required()
                ->live()
                ->helperText('A local upload stays under your group’s control.'),
            FileUpload::make($slot.'_upload')
                ->label(ucfirst($label).' image')
                ->image()
                ->editableSvgs(false)
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif'])
                ->maxSize(max(1, (int) ceil(((int) config('gallery.processing.max_upload_bytes', 10 * 1024 * 1024)) / 1024)))
                ->storeFiles(false)
                ->live()
                ->visible(fn (Get $get): bool => $get($slot.'_source') === 'managed')
                ->helperText($isFavicon
                    ? 'Choose a square image around 512 by 512 pixels or larger. The minimum is 32 by 32 pixels.'
                    : 'Choose a JPEG, PNG, WebP or AVIF image. PNG transparency is preserved.'),
            TextInput::make($slot.'_path')
                ->label('External '.$label.' URL or site path')
                ->maxLength(2048)
                ->rule(new PublicImageReferenceRule)
                ->required(fn (Get $get): bool => $get($slot.'_source') === 'external')
                ->visible(fn (Get $get): bool => $get($slot.'_source') === 'external')
                ->helperText('Use a secure HTTPS URL or safe root-relative site path.'),
            Toggle::make($slot.'_remove_fallback')
                ->label('Also remove the saved external fallback')
                ->default(false)
                ->visible(fn (Get $get): bool => $this->removingManagedWithFallback($slot, $get))
                ->helperText('Leave this off to reveal the saved external image after removing the local one.'),
            ViewField::make($slot.'_preview')
                ->label(ucfirst($label).' preview and status')
                ->view('filament.forms.components.managed-image-preview')
                ->viewData(fn (Get $get): array => [
                    'slot' => $slot,
                    'preview' => $slot === 'logo' ? $this->logoPreview : $this->faviconPreview,
                    'pendingUpload' => filled($get($slot.'_upload')),
                    'pendingMessage' => 'Temporary '.$label.' preview selected. It will be saved when you save branding.',
                    'savedManagedMessage' => 'Saved local '.$label.'.',
                    'savedExternalMessage' => 'Saved external '.$label.'.',
                    'emptyMessage' => 'No saved '.$label.'.',
                ])
                ->dehydrated(false),
        ];
    }

    private function fillFromProfile(SiteProfile $profile): void
    {
        $profile->loadMissing(['logoMedia', 'faviconMedia']);
        $branding = app(PublicBranding::class)->forProfile($profile);
        [$logoState, $this->logoPreview] = $this->brandingImageState($profile, $branding, 'logo');
        [$faviconState, $this->faviconPreview] = $this->brandingImageState($profile, $branding, 'favicon');

        $this->form->fill([
            ...$profile->only(['group_name', 'short_name', 'contact_email', 'hero_default_path', 'primary_colour', 'accent_colour', 'typography_option', 'social_links', 'terminology', 'affiliation_name', 'affiliation_url']),
            ...$logoState,
            ...$faviconState,
        ]);
    }

    /** @param array<string, mixed> $branding
     * @return array{array<string, mixed>, array<string, mixed>|null}
     */
    private function brandingImageState(SiteProfile $profile, array $branding, string $slot): array
    {
        $url = $branding[$slot.'_url'] ?? null;
        $external = PublicImageReference::resolve($profile->{$slot.'_path'});
        $managedActive = filled($profile->{$slot.'_media_id'}) && filled($url) && $url !== $external;
        $activeSource = $managedActive ? 'managed' : (filled($external) && $url === $external ? 'external' : 'none');
        $source = $activeSource === 'none' ? 'managed' : $activeSource;
        $preview = filled($url) ? [
            'source' => $activeSource,
            'url' => $url,
            'alt' => '',
            'object_position' => null,
            'fallback_url' => $managedActive ? $external : null,
        ] : null;

        return [[
            $slot.'_source' => $source,
            $slot.'_upload' => null,
            $slot.'_path' => $external,
            $slot.'_remove_fallback' => false,
        ], $preview];
    }

    /** @param array<string, mixed> $state */
    private function updateBrandingImage(User $actor, SiteProfile $profile, SiteMediaPurpose $purpose, string $slot, array $state): SiteProfile
    {
        $inputState = Arr::only($state, [
            $slot.'_source',
            $slot.'_upload',
            $slot.'_path',
            $slot.'_remove_fallback',
        ]);
        $inputState[$slot.'_upload'] = $this->uploadedFile($inputState[$slot.'_upload'] ?? null);

        try {
            return app(UpdateBrandingImage::class)->handle(
                $actor,
                $profile,
                $purpose,
                BrandingImageInput::from($inputState, $purpose),
            );
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            if (isset($errors['photo'])) {
                $errors[$slot.'_upload'] = $errors['photo'];
                unset($errors['photo']);
            }

            foreach ([$slot.'_upload', $slot.'_path'] as $field) {
                if (isset($errors[$field])) {
                    $errors['data.'.$field] = $errors[$field];
                    unset($errors[$field]);
                }
            }

            throw ValidationException::withMessages($errors);
        }
    }

    /** @param array<string, mixed> $state */
    private function brandingImageChangeRequested(SiteProfile $profile, string $slot, array $state): bool
    {
        if ($this->uploadedFile($state[$slot.'_upload'] ?? null) !== null) {
            return true;
        }

        $profile->loadMissing(['logoMedia', 'faviconMedia']);
        $branding = app(PublicBranding::class)->forProfile($profile);
        $external = PublicImageReference::resolve($profile->{$slot.'_path'});
        $url = $branding[$slot.'_url'] ?? null;
        $managedActive = filled($profile->{$slot.'_media_id'}) && filled($url) && $url !== $external;
        $activeSource = $managedActive ? 'managed' : (filled($external) && $url === $external ? 'external' : 'none');
        $requestedSource = $state[$slot.'_source'] ?? 'none';

        if ($activeSource === 'none' && $requestedSource === 'managed') {
            return false;
        }

        if ($requestedSource !== $activeSource) {
            return true;
        }

        return $requestedSource === 'external'
            && ($state[$slot.'_path'] ?? null) !== $external;
    }

    private function removingManagedWithFallback(string $slot, Get $get): bool
    {
        $preview = $slot === 'logo' ? $this->logoPreview : $this->faviconPreview;

        return $get($slot.'_source') === 'none'
            && is_array($preview)
            && ($preview['source'] ?? null) === 'managed'
            && filled($preview['fallback_url'] ?? null);
    }

    private function uploadedFile(mixed $state): ?UploadedFile
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

    /** @return list<string> */
    private function brandingImageStateNames(): array
    {
        return [
            'logo_source',
            'logo_upload',
            'logo_path',
            'logo_remove_fallback',
            'logo_preview',
            'favicon_source',
            'favicon_upload',
            'favicon_path',
            'favicon_remove_fallback',
            'favicon_preview',
        ];
    }
}
