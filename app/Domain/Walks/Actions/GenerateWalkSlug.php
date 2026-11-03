<?php

namespace App\Domain\Walks\Actions;

use App\Domain\Events\Actions\GenerateEventSlug;
use App\Domain\Events\Enums\EventType;

final readonly class GenerateWalkSlug
{
    public function __construct(private GenerateEventSlug $generator) {}

    public function handle(string $title, mixed $startsAt): string
    {
        return $this->generator->handle($title, $startsAt, EventType::Walk);
    }
}
