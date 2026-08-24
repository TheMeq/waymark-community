<?php

declare(strict_types=1);

namespace Waymark\Release;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class V1ScopeAuditor
{
    private const array CORE_PATHS = ['app', 'config', 'database', 'resources', 'routes'];

    /** @return array{files_scanned: int, errors: list<string>} */
    public function audit(string $root): array
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $errors = [];
        $filesScanned = 0;

        foreach (self::CORE_PATHS as $corePath) {
            $directory = $root.'/'.$corePath;
            if (! is_dir($directory)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $item) {
                if (! $item->isFile() || ! in_array(strtolower($item->getExtension()), ['env', 'js', 'json', 'mjs', 'php', 'ts'], true)) {
                    continue;
                }
                $filesScanned++;
                $path = str_replace('\\', '/', $item->getPathname());
                $relative = ltrim(substr($path, strlen($root)), '/');
                $contents = (string) file_get_contents($item->getPathname());

                if (preg_match('#(?:^|/)(?:Attendance|Rsvp|Booking|Payments?|Comments?|Chat|Mailbox|Tenants?|Tenancy|Plugins?)(?:/|\.php$)#i', $relative) === 1) {
                    $errors[] = "Prohibited v1 scope path: {$relative}";
                }
                if (preg_match('/\b(?:NDWG|Nottingham|Derby|Ramblers)\b|\b20\s*[-–—]\s*50\b/iu', $contents) === 1) {
                    $errors[] = "Reference-deployment assumption in generic core: {$relative}";
                }
                if (preg_match('/Schema::create\s*\(\s*[\'\"](?:attendances|rsvps|bookings|payments|comments|chat_messages|mailboxes|tenants)[\'\"]/i', $contents) === 1) {
                    $errors[] = "Prohibited management table in generic core: {$relative}";
                }
                if (preg_match('/\b(?:tenant_id|belongsToTenant|multi[-_ ]tenan(?:t|cy))\b/i', $contents) === 1) {
                    $errors[] = "Prohibited tenancy implementation in generic core: {$relative}";
                }
            }
        }

        sort($errors);

        return ['files_scanned' => $filesScanned, 'errors' => array_values(array_unique($errors))];
    }
}
