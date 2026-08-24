#!/usr/bin/env php
<?php

declare(strict_types=1);

use Waymark\Release\V1ScopeAuditor;

require_once __DIR__.'/release/V1ScopeAuditor.php';

$root = realpath(dirname(__DIR__));
if ($root === false) {
    fwrite(STDERR, "Unable to resolve repository root.\n");
    exit(1);
}

$report = (new V1ScopeAuditor)->audit($root);
if ($report['errors'] !== []) {
    foreach ($report['errors'] as $error) {
        fwrite(STDERR, "ERROR: {$error}\n");
    }
    exit(1);
}

fwrite(STDOUT, json_encode([
    'status' => 'passed',
    'files_scanned' => $report['files_scanned'],
    'reference_assumptions' => 0,
    'prohibited_scope_findings' => 0,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
