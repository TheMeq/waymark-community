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

final class WhatsOnController
{
    public function __invoke(Request $request, PublicEventsQuery $events): View
    {
        $input = Validator::make($request->only(['type', 'month']), [
            'type' => ['nullable', 'in:walk,social,holiday'],
            'month' => ['nullable', 'date_format:Y-m'],
        ])->validate();
        $type = isset($input['type']) ? EventType::from($input['type']) : null;
        $month = isset($input['month']) ? CarbonImmutable::createFromFormat('!Y-m', $input['month']) : null;
        $query = $month === null ? $events->upcoming($type) : $events->forMonth($month, $type);
        $siteProfile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;

        return view('events.index', [
            'events' => $query->paginate(12)->withQueryString()->through(fn ($event) => PublicEventCardViewModel::fromEvent($event, $siteProfile)),
            'selectedType' => $type?->value,
            'month' => $month,
            'site' => ['name' => $siteProfile->group_name ?? 'Waymark Community', 'strapline' => 'A local walking community'],
            'theme' => BrandTheme::fromSiteProfile($siteProfile),
        ]);
    }
}
