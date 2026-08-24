<?php

namespace Tests\Unit\Operations;

use PHPUnit\Framework\TestCase;
use Waymark\Release\V1ScopeAuditor;

require_once dirname(__DIR__, 3).'/scripts/release/V1ScopeAuditor.php';

final class V1ScopeAuditorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/waymark-scope-audit-'.bin2hex(random_bytes(8));
        mkdir($this->root.'/app/Domain/Events', 0700, true);
        mkdir($this->root.'/database/migrations', 0700, true);
        file_put_contents($this->root.'/app/Domain/Events/Event.php', "<?php // External booking information only.\n");
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
        parent::tearDown();
    }

    public function test_generic_reference_free_core_passes_without_rejecting_booking_information(): void
    {
        $report = (new V1ScopeAuditor)->audit($this->root);

        $this->assertSame([], $report['errors']);
        $this->assertSame(1, $report['files_scanned']);
    }

    public function test_reference_deployment_assumption_in_generic_core_is_rejected(): void
    {
        file_put_contents($this->root.'/app/Domain/Events/Event.php', "<?php // Nottingham defaults\n");

        $report = (new V1ScopeAuditor)->audit($this->root);

        $this->assertStringContainsString('Reference-deployment assumption', $report['errors'][0]);
    }

    public function test_prohibited_management_domain_and_table_are_rejected(): void
    {
        mkdir($this->root.'/app/Domain/Attendance', 0700, true);
        file_put_contents($this->root.'/app/Domain/Attendance/Register.php', '<?php');
        file_put_contents($this->root.'/database/migrations/create_payments.php', "<?php Schema::create('payments', function () {});\n");

        $report = (new V1ScopeAuditor)->audit($this->root);

        $this->assertCount(2, $report['errors']);
        $this->assertStringContainsString('Prohibited v1 scope path', implode("\n", $report['errors']));
        $this->assertStringContainsString('Prohibited management table', implode("\n", $report['errors']));
    }

    private function remove(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $target = $path.'/'.$entry;
            is_dir($target) ? $this->remove($target) : unlink($target);
        }
        rmdir($path);
    }
}
