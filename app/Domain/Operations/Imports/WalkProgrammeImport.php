<?php

namespace App\Domain\Operations\Imports;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Operations\Queries\CurrentSiteProfile;
use App\Domain\Walks\Actions\GenerateWalkSlug;
use App\Domain\Walks\Actions\SaveWalkDetails;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class WalkProgrammeImport
{
    public function __construct(private ReadWalkProgramme $reader, private NormaliseWalkProgrammeRow $normalizer) {}

    /** @param array<string, mixed> $leaderMapping
     * @param  list<int|string>|null  $selectedRows
     */
    public function preview(string $path, array $leaderMapping = [], ?array $selectedRows = null): ImportPreview
    {
        $profile = app(CurrentSiteProfile::class)->get();
        $identities = [];
        $rows = [];
        foreach ($this->reader->read($path) as $source) {
            $row = ['row' => $source['row'], ...$this->normalizer->assess($source['values'], $profile, $leaderMapping)];
            if ($selectedRows !== null && ! in_array((string) $source['row'], array_map('strval', $selectedRows), true)) {
                if ($row['status'] !== 'excluded') {
                    $row['status'] = 'excluded';
                    $row['messages'][] = 'Not selected for import.';
                }
            } elseif ($row['status'] === 'valid') {
                $identity = $this->titleKey($row['normalized']['title']).'|'.$row['normalized']['starts_at']->format('Y-m-d H:i:s');
                if (isset($identities[$identity]) || $this->duplicateExists($row['normalized'])) {
                    $row['status'] = 'duplicate';
                    $row['messages'][] = 'A matching walk already exists in Waymark or this workbook; it will be skipped.';
                }
                $identities[$identity] = true;
            }
            $rows[] = $row;
        }
        if ($selectedRows !== null && array_diff(array_map('strval', $selectedRows), array_map(static fn (array $row): string => (string) $row['row'], $rows)) !== []) {
            throw ValidationException::withMessages(['selection' => 'The selected programme rows have changed. Read the workbook again.']);
        }

        return new ImportPreview(['title', 'starts_at', 'leader_name', 'grade', 'distance', 'ascent', 'location'], $rows);
    }

    /** @param array<string, mixed> $leaderMapping
     * @param  list<int|string>  $selectedRows
     */
    public function execute(string $path, array $leaderMapping, array $selectedRows, User $actor): ImportResult
    {
        if (! $actor->hasVerifiedEmail() || ! $actor->hasCapability(ModuleCapability::ManageAccounts) || ! $actor->hasCapability(ModuleCapability::ManageAllWalks)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($path, $leaderMapping, $selectedRows, $actor): ImportResult {
            // One shared row serializes programme imports across administrators on MySQL/MariaDB.
            User::query()->oldest('id')->lockForUpdate()->firstOrFail();
            $preview = $this->preview($path, $leaderMapping, $selectedRows);
            if ($preview->invalidRows() > 0) {
                return new ImportResult(0, $preview->duplicateRows(), $preview->invalidRows(), false, $preview);
            }
            $imported = 0;
            foreach ($preview->rows as $row) {
                if ($row['status'] !== 'valid') {
                    continue;
                }
                $data = $row['normalized'];
                $event = Event::query()->create([
                    ...Arr::only($data, ['title', 'starts_at', 'ends_at', 'description']),
                    'type' => EventType::Walk,
                    'slug' => app(GenerateWalkSlug::class)->handle($data['title'], $data['starts_at']),
                    'status' => EventStatus::Draft,
                    'is_public' => false,
                    'published_at' => null,
                    'organiser_id' => $data['primary_leader_id'] ?? $actor->id,
                ]);
                app(SaveWalkDetails::class)->handle($event, $data);
                $imported++;
            }

            return new ImportResult($imported, $preview->duplicateRows(), 0, true, $preview);
        });
    }

    private function duplicateExists(array $data): bool
    {
        $titles = Event::query()->where('type', EventType::Walk)
            ->where('starts_at', $data['starts_at'])
            ->whereHas('walk', fn ($query) => $query->withTrashed())
            ->pluck('title');

        return $titles->contains(fn (string $title): bool => $this->titleKey($title) === $this->titleKey($data['title']));
    }

    private function titleKey(string $title): string
    {
        return mb_strtolower(Str::squish($title));
    }
}
