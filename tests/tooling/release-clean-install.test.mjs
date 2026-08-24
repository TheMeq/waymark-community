import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
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
    assert.deepEqual(plan.runtime_tools, ['php']);
    assert.equal(plan.composer_at_runtime, false);
    assert.equal(plan.node_at_runtime, false);
    assert.deepEqual(plan.databases, ['mysql', 'mariadb']);
    assert.deepEqual(plan.smoke, ['web installer', 'public homepage', 'admin login', 'site media upload']);
});
