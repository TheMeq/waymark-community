<?php

namespace App\Domain\Communication\Queries;

use App\Domain\Communication\Models\ContactDepartment;

final class ContactDestination
{
    public function for(ContactDepartment $department, string $message): string
    {
        $haystack = mb_strtolower($message);
        foreach ((array) $department->routing_rules as $keyword => $destination) {
            if (is_string($keyword) && is_string($destination) && str_contains($haystack, mb_strtolower(trim($keyword)))) {
                return $destination;
            }
        }

        return $department->destination_email;
    }
}
