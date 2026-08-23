<?php

use App\Domain\Accounts\Actions\FlagStaleAccountsForReview;
use App\Domain\Accounts\Actions\ProcessPersonalDataExports;
use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Communication\Actions\SendDueNewsletters;
use App\Domain\Gallery\Actions\ProcessDeferredCommunityPhotos;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('accounts:process-personal-data-exports {--limit=25}', function (ProcessPersonalDataExports $exports): void {
    $this->info((string) $exports->handle((int) $this->option('limit')).' export request(s) processed.');
})->purpose('Process pending personal-data export requests without a permanent worker');

Artisan::command('accounts:flag-stale {--days=365} {--limit=100}', function (FlagStaleAccountsForReview $accounts): void {
    $this->info((string) $accounts->handle((int) $this->option('days'), (int) $this->option('limit')).' account(s) flagged for review.');
})->purpose('Flag inactive accounts for manual review without changing them');

Artisan::command('gallery:process-deferred-photos {--limit=25}', function (ProcessDeferredCommunityPhotos $photos): void {
    $this->info((string) $photos->handle((int) $this->option('limit')).' photo processing job(s) handled.');
})->purpose('Process deferred community photos without a permanent worker');

Artisan::command('communications:send-due-newsletters', function (SendDueNewsletters $newsletters): int {
    $administrator = User::query()->where('role', AccountRole::Administrator->value)->orderBy('id')->first();

    if (! $administrator instanceof User) {
        $this->error('No administrator account is available to authorise scheduled newsletters.');

        return 1;
    }

    $this->info((string) $newsletters->handle($administrator).' newsletter recipient(s) sent.');

    return 0;
})->purpose('Send newsletters whose schedule is due without a permanent worker');

Schedule::command('gallery:process-deferred-photos --limit=25')
    ->everyMinute()
    ->withoutOverlapping(max(1, min(59, (int) config('gallery.deferred.schedule_lock_minutes', 5))));

Schedule::command('communications:send-due-newsletters')->hourly()->withoutOverlapping();
