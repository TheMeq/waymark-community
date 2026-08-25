import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';

const repositoryRoot = resolve(import.meta.dirname, '../..');

test('release clean-install plan uses only the verified ZIP and runtime PHP', () => {
    const result = spawnSync('node', [
        'tests/tooling/phase10-release-clean-install.mjs',
        '--archive=dist/waymark-community-1.0.0-shared-hosting.zip',
        '--plan',
    ], { cwd: repositoryRoot, encoding: 'utf8', env: process.env });

    assert.equal(result.status, 0, result.stderr);
    const plan = JSON.parse(result.stdout);
    assert.equal(plan.source, 'verified release ZIP only');
    assert.deepEqual(plan.runtime_tools, ['php', 'web server']);
    assert.equal(plan.composer_at_runtime, false);
    assert.equal(plan.node_at_runtime, false);
    assert.deepEqual(plan.layouts, ['standard', 'public-html']);
    assert.deepEqual(plan.databases, ['mysql', 'mariadb']);
    assert.deepEqual(plan.smoke, ['web installer', 'public homepage', 'admin login', 'site media upload', 'installer lockout', 'internal application protection']);
});

test('public-html clean-install fixture uses PHP 8.3 Apache with htaccess overrides', () => {
    const dockerfile = readFileSync(resolve(repositoryRoot, 'tests/fixtures/apache-shared-hosting/Dockerfile'), 'utf8');

    assert.match(dockerfile, /^FROM php:8\.3-apache/m);
    assert.match(dockerfile, /a2enmod rewrite/);
    assert.match(dockerfile, /AllowOverride All/);
    assert.match(dockerfile, /pdo_mysql/);
});

test('public-html clean-install mail fixture is reachable from the Apache container', () => {
    const harness = readFileSync(resolve(repositoryRoot, 'tests/tooling/phase10-release-clean-install.mjs'), 'utf8');

    assert.match(harness, /const smtpBindHost = requestedLayout === 'public-html' \? '0\.0\.0\.0' : '127\.0\.0\.1';/);
    assert.match(harness, /smtp\.listen\(smtpPort, smtpBindHost, resolveListening\);/);
});
