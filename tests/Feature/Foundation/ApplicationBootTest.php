<?php

namespace Tests\Feature\Foundation;

use Tests\TestCase;

final class ApplicationBootTest extends TestCase
{
    public function test_application_boots_and_home_route_responds(): void
    {
        $this->get('/')->assertSuccessful();
    }
}
