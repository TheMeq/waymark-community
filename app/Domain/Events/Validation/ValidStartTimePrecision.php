<?php

namespace App\Domain\Events\Validation;

use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Validation\ValidationRule;
use Throwable;

final readonly class ValidStartTimePrecision implements ValidationRule
{
    public function __construct(private mixed $startsAt) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! in_array($value, [true, 1, '1'], true) || $this->startsAt === null) {
            return;
        }
        try {
            $time = $this->startsAt instanceof DateTimeInterface ? $this->startsAt : CarbonImmutable::parse((string) $this->startsAt);
            if ($time->format('H:i:s') !== '00:00:00') {
                $fail('Use a date without a time, or uncheck Start time not recorded when confirming a start time.');
            }
        } catch (Throwable) {
            // The date rule reports an invalid start independently.
        }
    }
}
