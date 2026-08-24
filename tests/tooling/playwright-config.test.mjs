import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import test from 'node:test';

const repositoryRoot = resolve(import.meta.dirname, '../..');
const configUrl = pathToFileURL(resolve(repositoryRoot, 'playwright.config.ts')).href;
const inspectConfig = `
    import config from ${JSON.stringify(configUrl)};
    const webServer = Array.isArray(config.webServer) ? config.webServer[0] : config.webServer;
    process.stdout.write(JSON.stringify(webServer?.env ?? {}));
`;

function loadWebServerEnvironment(phpIniScanDir) {
    const environment = { ...process.env };

    delete environment.PHP_INI_SCAN_DIR;

    if (phpIniScanDir !== undefined) {
        environment.PHP_INI_SCAN_DIR = phpIniScanDir;
    }

    const result = spawnSync(process.execPath, ['--input-type=module', '--eval', inspectConfig], {
        cwd: repositoryRoot,
        env: environment,
        encoding: 'utf8',
    });

    assert.equal(result.status, 0, result.stderr);

    return JSON.parse(result.stdout);
}

test('Playwright does not inject a PHP scan directory when the caller did not supply one', () => {
    const environment = loadWebServerEnvironment(undefined);

    assert.equal(Object.hasOwn(environment, 'PHP_INI_SCAN_DIR'), false);
});

test('Playwright preserves an explicitly supplied PHP scan directory', () => {
    const supplied = resolve(repositoryRoot, 'test-results/php-scan');
    const environment = loadWebServerEnvironment(supplied);

    assert.equal(environment.PHP_INI_SCAN_DIR, supplied);
});
