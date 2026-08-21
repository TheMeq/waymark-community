<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateLeaderProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'public_profile_enabled' => ['nullable', 'boolean'],
            'public_profile_slug' => [
                'nullable',
                'string',
                'max:80',
                'regex:/\\A[a-z0-9]+(?:-[a-z0-9]+)*\\z/D',
                Rule::requiredIf(fn (): bool => $this->boolean('public_profile_enabled')),
                Rule::unique('users', 'public_profile_slug')->ignore($this->user()),
            ],
            'public_profile_introduction' => ['nullable', 'string', 'max:500'],
        ];
    }
}
