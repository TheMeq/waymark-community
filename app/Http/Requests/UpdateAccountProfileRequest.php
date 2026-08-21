<?php

namespace App\Http\Requests;

use App\Domain\Accounts\Models\CommunicationPreference;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'preferences' => ['nullable', 'array:'.implode(',', CommunicationPreference::categoryKeys())],
            'preferences.*' => ['nullable', 'boolean'],
        ];
    }
}
