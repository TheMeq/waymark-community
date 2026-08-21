<?php

namespace App\Domain\Accounts\Data;

use App\Http\Requests\UpdateLeaderProfileRequest;

final readonly class LeaderProfileData
{
    public function __construct(
        public bool $isPublic,
        public ?string $slug,
        public ?string $introduction,
    ) {}

    public static function fromRequest(UpdateLeaderProfileRequest $request): self
    {
        return new self(
            isPublic: $request->boolean('public_profile_enabled'),
            slug: self::nullableTrimmed($request->string('public_profile_slug')->toString()),
            introduction: self::nullableTrimmed($request->string('public_profile_introduction')->toString()),
        );
    }

    private static function nullableTrimmed(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
