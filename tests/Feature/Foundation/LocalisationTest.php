<?php

namespace Tests\Feature\Foundation;

use Illuminate\Support\Facades\View;
use Tests\TestCase;

final class LocalisationTest extends TestCase
{
    public function test_english_system_copy_is_used_as_the_locale_fallback(): void
    {
        app()->setLocale('fr');
        config()->set('app.fallback_locale', 'en');

        $this->assertSame('Back to home', __('common.actions.back_home'));
        $this->assertSame('Active', __('common.statuses.active'));
    }

    public function test_public_not_found_response_is_safe_and_translation_backed(): void
    {
        config()->set('app.debug', false);

        $this->get('/a-page-that-does-not-exist')
            ->assertNotFound()
            ->assertSeeText('Page not found')
            ->assertDontSee('NotFoundHttpException')
            ->assertDontSee('Stack trace');
    }

    public function test_maintenance_error_view_is_structurally_available(): void
    {
        $this->assertTrue(View::exists('errors.503'));

        $rendered = View::make('errors.503')->render();

        $this->assertStringContainsString('Temporarily unavailable', $rendered);
        $this->assertStringNotContainsString('Stack trace', $rendered);
    }
}
