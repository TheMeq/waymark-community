<?php

namespace Tests\Unit\Operations;

use App\Domain\Operations\Installation\SharedHostingEnvironment;
use App\Domain\Operations\Installation\SharedHostingSecurity;
use PHPUnit\Framework\TestCase;

final class SharedHostingSecurityTest extends TestCase
{
    public function test_public_only_document_root_with_private_application_and_environment_passes(): void
    {
        $report = (new SharedHostingSecurity)->inspect(new SharedHostingEnvironment(
            documentRoot: '/srv/www/public_html',
            applicationRoot: '/srv/waymark',
            publicPath: '/srv/www/public_html',
            environmentPath: '/srv/waymark/.env',
            production: true,
            debug: false,
        ));

        $this->assertFalse($report->blocked());
        $this->assertSame('pass', $report->check('document-root')->status);
        $this->assertSame('pass', $report->check('environment-exposure')->status);
        $this->assertSame('pass', $report->check('production-debug')->status);
    }

    public function test_application_or_environment_under_document_root_is_blocked_conservatively(): void
    {
        $report = (new SharedHostingSecurity)->inspect(new SharedHostingEnvironment(
            documentRoot: '/home/account/public_html',
            applicationRoot: '/home/account/public_html/waymark',
            publicPath: '/home/account/public_html/waymark/public',
            environmentPath: '/home/account/public_html/waymark/.env',
            production: true,
            debug: false,
        ));

        $this->assertTrue($report->blocked());
        $this->assertSame('blocker', $report->check('document-root')->status);
        $this->assertSame('blocker', $report->check('environment-exposure')->status);
        $this->assertStringContainsString('outside', $report->check('environment-exposure')->remediation);
    }

    public function test_debug_mode_blocks_production_completion_but_not_local_development(): void
    {
        $production = (new SharedHostingSecurity)->inspect(new SharedHostingEnvironment(
            documentRoot: '/srv/waymark/public',
            applicationRoot: '/srv/waymark',
            publicPath: '/srv/waymark/public',
            environmentPath: '/srv/waymark/.env',
            production: true,
            debug: true,
        ));
        $local = (new SharedHostingSecurity)->inspect(new SharedHostingEnvironment(
            documentRoot: '/srv/waymark/public',
            applicationRoot: '/srv/waymark',
            publicPath: '/srv/waymark/public',
            environmentPath: '/srv/waymark/.env',
            production: false,
            debug: true,
        ));

        $this->assertTrue($production->blocked());
        $this->assertSame('blocker', $production->check('production-debug')->status);
        $this->assertSame('warning', $local->check('production-debug')->status);
    }
}
