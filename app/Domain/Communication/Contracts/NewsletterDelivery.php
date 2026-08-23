<?php

namespace App\Domain\Communication\Contracts;

use App\Domain\Communication\Models\Newsletter;
use App\Models\User;

interface NewsletterDelivery
{
    public function send(Newsletter $newsletter, User $recipient): void;
}
