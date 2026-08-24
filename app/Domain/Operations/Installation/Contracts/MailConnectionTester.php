<?php

namespace App\Domain\Operations\Installation\Contracts;

use App\Domain\Operations\Installation\MailConfiguration;
use App\Domain\Operations\Installation\MailConnectionResult;

interface MailConnectionTester
{
    public function test(MailConfiguration $configuration): MailConnectionResult;
}
