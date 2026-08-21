<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class UpdateLeaderProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && Gate::forUser($user)->allows('manageLeaderHub', $user);
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
