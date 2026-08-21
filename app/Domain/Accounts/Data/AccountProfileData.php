<?php

namespace App\Domain\Accounts\Data;

use App\Domain\Accounts\Models\CommunicationPreference;
use App\Http\Requests\UpdateAccountProfileRequest;

final readonly class AccountProfileData
{
    /** @param array<string, bool> $preferences */
    public function __construct(
        public string $name,
        public ?string $displayName,
        public ?string $phone,
        public array $preferences,
    ) {}

    public static function fromRequest(UpdateAccountProfileRequest $request): self
    {
        $preferences = [];

        foreach (CommunicationPreference::categoryKeys() as $category) {
            $preferences[$category] = $request->boolean('preferences.'.$category);
        }

        return new self(
            name: trim($request->string('name')->toString()),
            displayName: self::nullableTrimmed($request->string('display_name')->toString()),
            phone: self::nullableTrimmed($request->string('phone')->toString()),
            preferences: $preferences,
        );
    }

    private static function nullableTrimmed(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
