<?php

namespace App\Http\Controllers;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Operations\Maintenance\MaintenanceManager;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final readonly class MaintenanceModeController
{
    public function __construct(private MaintenanceManager $maintenance) {}

    public function enable(Request $request): RedirectResponse
    {
        $this->authorize($request);
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
            'expected_return_at' => ['nullable', 'date', 'after:now'],
            'contact_url' => ['nullable', 'url:http,https', 'max:2048'],
        ]);
        $bypass = $this->maintenance->enable(
            $validated['message'],
            isset($validated['expected_return_at']) ? Carbon::parse($validated['expected_return_at']) : null,
            $validated['contact_url'] ?? null,
        );

        return to_route('filament.admin.pages.maintenance-mode')
            ->with('status', 'Maintenance mode enabled; this browser has a private bypass.')
            ->withCookie(cookie(
                MaintenanceManager::BYPASS_COOKIE,
                $bypass,
                12 * 60,
                '/',
                null,
                $request->secure(),
                true,
                false,
                'strict',
            ));
    }

    public function disable(Request $request): RedirectResponse
    {
        $this->authorize($request);
        $this->maintenance->disable();

        return to_route('filament.admin.pages.maintenance-mode')
            ->with('status', 'Maintenance mode disabled.')
            ->withoutCookie(MaintenanceManager::BYPASS_COOKIE);
    }

    private function authorize(Request $request): void
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasCapability(ModuleCapability::ManageAccounts), 403);
    }
}
