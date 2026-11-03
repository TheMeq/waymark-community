<?php

namespace App\Filament\Forms;

use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

trait ReportsFormValidation
{
    private function visibleValidation(ValidationException $exception): ValidationException
    {
        Notification::make()->danger()->title('Please check the form')
            ->body(collect($exception->errors())->flatten()->take(3)->implode(' '))->send();
        if (collect(array_keys($exception->errors()))->every(fn (string $field): bool => str_starts_with($field, 'data.'))) {
            return $exception;
        }

        $errors = [];
        foreach ($exception->errors() as $field => $messages) {
            $errors[str_starts_with($field, 'data.') ? $field : 'data.'.$field] = $messages;
        }

        return ValidationException::withMessages($errors);
    }
}
