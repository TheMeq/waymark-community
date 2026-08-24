import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

const root = resolve(import.meta.dirname, '../..');

test('staging browser gate uses isolated safe services and representative responsive views', async () => {
    const config = await readFile(resolve(root, 'playwright.staging.config.ts'), 'utf8');
    const spec = await readFile(resolve(root, 'tests/browser-staging/staging-readiness.spec.ts'), 'utf8');
    const packageJson = JSON.parse(await readFile(resolve(root, 'package.json'), 'utf8'));

    assert.match(config, /WAYMARK_STAGING: 'true'/);
    assert.match(config, /MAIL_MAILER: 'array'/);
    assert.match(config, /CACHE_STORE: 'array'/);
    assert.match(config, /QUEUE_CONNECTION: 'sync'/);
    assert.doesNotMatch(config, /C:\\Users\\|\/Users\/[^/]+|\/home\/[^/]+/);
    for (const name of ['desktop', 'tablet', 'mobile']) assert.match(config, new RegExp(`name: '${name}'`));
    assert.match(spec, /noindex,nofollow/);
    assert.match(spec, /Disallow: \/|Disallow:\\s\*\/|robots\.txt/);
    assert.match(spec, /toHaveScreenshot/);
    assert.equal(packageJson.scripts['test:e2e:staging'], 'playwright test --config=playwright.staging.config.ts');
});

test('manual reference content checklist explicitly forbids scraping', async () => {
    const checklist = await readFile(resolve(root, 'docs/deployment/reference-content-entry-checklist.md'), 'utf8');

    assert.match(checklist, /manual clean rebuild/i);
    assert.match(checklist, /do not scrape/i);
    assert.match(checklist, /approved-homepage-concept\.png/);
    assert.match(checklist, /copyright|permission/i);
});
