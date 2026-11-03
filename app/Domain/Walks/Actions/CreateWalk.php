<?php

namespace App\Domain\Walks\Actions;

use App\Domain\Walks\Models\Walk;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateWalk
{
    /** @param array<string, mixed> $attributes */
    public function __construct(
        private SaveWalkDraft $saveWalkDraft,
        private SubmitWalkForPublication $submitWalkForPublication,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $organiser, array $attributes, ?Walk $draft = null): Walk
    {
        return DB::transaction(function () use ($organiser, $attributes, $draft): Walk {
            $draft ??= $this->saveWalkDraft->create($organiser, $attributes);
            $draft = $this->saveWalkDraft->update($draft, $organiser, $attributes);

            return $this->submitWalkForPublication->handle($draft, $organiser);
        });
    }
}
