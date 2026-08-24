<?php

namespace Tests\Unit\Operations;

use App\Domain\Operations\Installation\InstallationState;
use PHPUnit\Framework\TestCase;

final class InstallationStateTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'waymark-installation-state-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $marker = $this->temporaryDirectory.DIRECTORY_SEPARATOR.'installed.lock';

        if (is_file($marker)) {
            unlink($marker);
        }

        if (is_dir($this->temporaryDirectory)) {
            rmdir($this->temporaryDirectory);
        }

        parent::tearDown();
    }

    public function test_an_empty_pre_install_environment_is_detected_without_a_database(): void
    {
        $state = new InstallationState($this->markerPath(), null, null);

        $this->assertFalse($state->installed());
        $this->assertTrue($state->installationRequired());
    }

    public function test_an_explicit_uninstalled_setting_wins_while_the_wizard_is_in_progress(): void
    {
        $state = new InstallationState($this->markerPath(), false, 'base64:legacy-application-key');

        $this->assertFalse($state->installed());
    }

    public function test_an_explicit_installed_setting_is_honoured(): void
    {
        $state = new InstallationState($this->markerPath(), true, null);

        $this->assertTrue($state->installed());
    }

    public function test_an_existing_application_key_keeps_pre_phase_nine_installations_available(): void
    {
        $state = new InstallationState($this->markerPath(), null, 'base64:legacy-application-key');

        $this->assertTrue($state->installed());
    }

    public function test_completing_installation_creates_a_private_marker_atomically(): void
    {
        $state = new InstallationState($this->markerPath(), null, null);

        $state->complete();

        $this->assertTrue($state->installed());
        $this->assertFileExists($this->markerPath());
        $this->assertStringContainsString('installed_at', (string) file_get_contents($this->markerPath()));
        $this->assertFileDoesNotExist($this->markerPath().'.tmp');
    }

    private function markerPath(): string
    {
        return $this->temporaryDirectory.DIRECTORY_SEPARATOR.'installed.lock';
    }
}
