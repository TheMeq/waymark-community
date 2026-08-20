<?php

namespace Tests\Feature\Foundation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ApplicationBootTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_boots_and_home_route_responds(): void
    {
        $this->get('/')->assertSuccessful();
    }
}
