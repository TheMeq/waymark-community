<?php

namespace App\Domain\Operations\Installation;

enum SetupStep: string
{
    case Welcome = 'welcome';
    case ServerChecks = 'server-checks';
    case Database = 'database';
    case GroupDetails = 'group-details';
    case Branding = 'branding';
    case FirstAdministrator = 'first-administrator';
    case Mail = 'mail';
    case Modules = 'modules';
    case Advanced = 'advanced';
    case Install = 'install';
    case HealthCheck = 'health-check';

    public function number(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }

    public function heading(): string
    {
        return match ($this) {
            self::Welcome => 'Set up Waymark Community',
            self::ServerChecks => 'Server checks',
            self::Database => 'Database connection',
            self::GroupDetails => 'Group details',
            self::Branding => 'Branding preview',
            self::FirstAdministrator => 'First administrator',
            self::Mail => 'Email delivery',
            self::Modules => 'Choose modules',
            self::Advanced => 'Advanced services',
            self::Install => 'Ready to install',
            self::HealthCheck => 'Final health check',
        };
    }

    public function next(): ?self
    {
        return self::cases()[$this->number()] ?? null;
    }
}
