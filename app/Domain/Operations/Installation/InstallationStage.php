<?php

namespace App\Domain\Operations\Installation;

enum InstallationStage: string
{
    case PreparingConfiguration = 'preparing_configuration';
    case CheckingDatabase = 'checking_database';
    case PreparingDatabaseSchema = 'preparing_database_schema';
    case CreatingGroupSettings = 'creating_group_settings';
    case CreatingAdministrator = 'creating_administrator';
    case ApplyingOptionalConfiguration = 'applying_optional_configuration';
    case RunningHealthChecks = 'running_health_checks';
    case CompletingInstallation = 'completing_installation';

    public function label(): string
    {
        return match ($this) {
            self::PreparingConfiguration => 'Preparing configuration',
            self::CheckingDatabase => 'Checking database',
            self::PreparingDatabaseSchema => 'Preparing database schema',
            self::CreatingGroupSettings => 'Creating group settings',
            self::CreatingAdministrator => 'Creating administrator',
            self::ApplyingOptionalConfiguration => 'Applying optional configuration',
            self::RunningHealthChecks => 'Running health checks',
            self::CompletingInstallation => 'Completing installation',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PreparingConfiguration => 'Waymark is preparing the saved settings for installation.',
            self::CheckingDatabase => 'Waymark is checking the database connection and permissions.',
            self::PreparingDatabaseSchema => 'Waymark is creating the database structure it needs.',
            self::CreatingGroupSettings => 'Waymark is saving the walking group settings.',
            self::CreatingAdministrator => 'Waymark is creating the first administrator account.',
            self::ApplyingOptionalConfiguration => 'Waymark is applying the selected optional settings.',
            self::RunningHealthChecks => 'Waymark is checking the completed installation.',
            self::CompletingInstallation => 'Waymark is finishing the installation.',
        };
    }
}
