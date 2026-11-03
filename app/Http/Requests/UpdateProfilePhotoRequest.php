<?php

namespace App\Http\Requests;

use App\Domain\Accounts\Data\ProfilePhotoInput;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateProfilePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() && $this->user()?->hasVerifiedEmail();
    }

    public function rules(): array
    {
        return ProfilePhotoInput::rules();
    }
}
