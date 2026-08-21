<?php

namespace App\Http\Controllers;

use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Queries\PublicEventsQuery;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\ViewModels\PublicEventCardViewModel;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class WhatsOnCalendarController
{
    public function __invoke(Request $request, PublicEventsQuery $events): View
    {
        $input = Validator::make($request->only(['type', 'month']), [
            'type' => ['nullable', 'in:walk,social,holiday'],
            'month' => ['nullable', 'date_format:Y-m'],
        ])->validate();
        $month = isset($input['month']) ? CarbonImmutable::createFromFormat('!Y-m', $input['month']) : CarbonImmutable::now()->startOfMonth();
        $type = isset($input['type']) ? EventType::from($input['type']) : null;
        $byDate = $events->forMonth($month, $type)->get()->groupBy(fn ($event): string => $event->starts_at->format('Y-m-d'));
        $first = $month->startOfMonth()->startOfWeek();
        $last = $month->endOfMonth()->endOfWeek();
        $weeks = [];
        for ($cursor = $first; $cursor <= $last; $cursor = $cursor->addDay()) {
            $week = intdiv($cursor->diffInDays($first), 7);
            $weeks[$week][] = [
                'date' => $cursor,
                'in_month' => $cursor->month === $month->month,
                'events' => $byDate->get($cursor->format('Y-m-d'), collect())->map(fn ($event) => PublicEventCardViewModel::calendar($event))->all(),
            ];
        }
        $siteProfile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;

        return view('events.calendar', [
            'month' => $month, 'weeks' => $weeks, 'selectedType' => $type?->value,
            'site' => ['name' => $siteProfile->group_name ?? 'Waymark Community', 'strapline' => 'A local walking community'],
            'theme' => BrandTheme::fromSiteProfile($siteProfile),
        ]);
    }
}
