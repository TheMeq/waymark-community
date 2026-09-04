import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

const root = resolve(import.meta.dirname, '../..');

async function document(path) {
    return readFile(resolve(root, path), 'utf8');
}

test('root documentation describes the current v1 release-candidate workflow', async () => {
    const readme = await document('README.md');
    const contributing = await document('CONTRIBUTING.md');
    const security = await document('SECURITY.md');
    const changelog = await document('CHANGELOG.md');

    assert.doesNotMatch(readme, /Phase 1 foundation is implemented/);
    for (const phrase of ['Source/developer checkout', 'Shared Hosting Release ZIP', 'Runtime requirements', 'Build requirements', 'Repository map']) {
        assert.match(readme, new RegExp(phrase, 'i'));
    }
    assert.match(contributing, /test-driven/i);
    assert.match(contributing, /focused commit/i);
    assert.match(contributing, /acceptance gate/i);
    assert.match(security, /report.*privately/i);
    assert.match(security, /supported versions/i);
    assert.match(changelog, /## \[Unreleased\]/);
    assert.match(changelog, /Keep a Changelog/i);
    assert.match(changelog, /Semantic Versioning/i);
});

test('operator and release guides cover distinct install update backup and disaster-recovery paths', async () => {
    const required = [
        'docs/deployment/shared-hosting.md',
        'docs/deployment/staging.md',
        'docs/deployment/updates.md',
        'docs/deployment/backups.md',
        'docs/deployment/disaster-recovery.md',
        'docs/deployment/release-packaging.md',
        'docs/development/release-process.md',
    ];
    const documents = await Promise.all(required.map(document));

    assert.match(documents[0], /installer|setup wizard/i);
    assert.match(documents[2], /safety backup/i);
    assert.match(documents[3], /off-host/i);
    assert.match(documents[4], /exact.*version/i);
    assert.match(documents[5], /verify-release\.php/);
    assert.match(documents[0], /waymark-community-1\.0\.2-public-html\.zip/);
    assert.match(documents[0], /extract.*directly.*public_html/is);
    assert.doesNotMatch(documents[0], /copy only the contents of its `public` directory/i);
    assert.match(documents[5], /runtime application.*operator documentation/is);
    assert.match(documents[5], /public-html/i);
    assert.match(documents[5], /production-safe.*\.env\.example/is);
    assert.match(documents[6], /build-release\.php/);
    assert.match(documents[6], /Do not tag|independent acceptance/i);
});
