<?php

namespace App\Filament\Resources\SocialResource\Pages;

use App\Domain\Socials\Actions\CreateSocial as CreateSocialAction;
use App\Filament\Pages\CreateResourceRecord as CreateRecord;
use App\Filament\Resources\SocialResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class CreateSocial extends CreateRecord
{
    protected static string $resource = SocialResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        return app(CreateSocialAction::class)->handle($user, $data);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Social draft created';
    }
}
