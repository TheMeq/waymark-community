import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

const root = resolve(import.meta.dirname, '../..');

test('v1 scope audit is a required release command with complete success-criterion traceability', async () => {
    const composer = JSON.parse(await readFile(resolve(root, 'composer.json'), 'utf8'));
    const builder = await readFile(resolve(root, 'scripts/build-release.php'), 'utf8');
    const report = await readFile(resolve(root, 'docs/release/v1-scope-audit.md'), 'utf8');

    assert.deepEqual(composer.scripts['audit:v1'], ['@php scripts/audit-v1-scope.php']);
    assert.match(builder, /composer audit:v1/);
    for (let number = 1; number <= 11; number += 1) {
        assert.match(report, new RegExp(`\\| SC-${String(number).padStart(2, '0')} \\|`));
    }
    for (const excluded of ['attendance', 'RSVP', 'payments', 'comments/chat', 'mailbox', 'native app', 'plugin marketplace', 'multi-tenancy']) {
        assert.match(report, new RegExp(excluded.replace('/', '\\/'), 'i'));
    }
});
