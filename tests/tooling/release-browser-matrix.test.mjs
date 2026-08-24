import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

const root = resolve(import.meta.dirname, '../..');

test('release browser gate spans Chromium Firefox WebKit and a mobile WebKit profile', async () => {
    const config = await readFile(resolve(root, 'playwright.release.config.ts'), 'utf8');
    const packageJson = JSON.parse(await readFile(resolve(root, 'package.json'), 'utf8'));
    const workflow = await readFile(resolve(root, '.github/workflows/ci.yml'), 'utf8');

    for (const name of ['chromium-desktop', 'firefox-desktop', 'webkit-desktop', 'webkit-mobile']) {
        assert.match(config, new RegExp(`name: '${name}'`));
    }
    assert.equal(packageJson.scripts['test:e2e:release'], 'playwright test --config=playwright.release.config.ts');
    assert.match(workflow, /playwright install --with-deps chromium firefox webkit/);
    assert.match(workflow, /npm run test:e2e:release/);
});

test('release-critical browser test retains accessibility resize keyboard and page coverage', async () => {
    const spec = await readFile(resolve(root, 'tests/browser-release/release-critical.spec.ts'), 'utf8');

    for (const path of ['/', '/walks', '/whats-on', '/photos']) assert.match(spec, new RegExp(`'${path.replace('/', '\\/')}'`));
    assert.match(spec, /AxeBuilder/);
    assert.match(spec, /wcag22aa/);
    assert.match(spec, /200%/);
    assert.match(spec, /keyboard\.press/);
});
