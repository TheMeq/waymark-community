<?php

namespace App\Domain\Socials\Actions;

use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Socials\Data\SocialDetailsData;
use App\Domain\Socials\Models\Social;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveSocialDetails
{
    /** @param array<string, mixed> $attributes */
    public function handle(Event $event, array $attributes): Social
    {
        if ($event->type !== EventType::Social) {
            throw ValidationException::withMessages([
                'event' => 'Social details can only be assigned to a Social event.',
            ]);
        }

        $details = SocialDetailsData::from($attributes);

        return DB::transaction(function () use ($event, $details): Social {
            $social = Social::query()->firstOrNew(['event_id' => $event->id]);
            $social->fill($details->persistenceAttributes());
            $social->save();

            return $social->refresh()->load('event.organiser');
        });
    }
}
