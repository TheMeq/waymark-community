<?php

namespace App\Rules;

use App\Domain\Content\Presentation\PublicImageReference;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

final class PublicImageReferenceRule implements ValidationRule
{
    /** @param Closure(string, ?string=): PotentiallyTranslatedString $fail */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! PublicImageReference::isAllowed($value)) {
            $fail('Use a secure HTTPS image URL or a safe site-relative image path.');
        }
    }
}
