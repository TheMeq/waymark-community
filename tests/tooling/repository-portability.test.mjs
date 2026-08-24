import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { copyFile, mkdir, mkdtemp, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { dirname, resolve } from 'node:path';
import test from 'node:test';

const repositoryRoot = resolve(import.meta.dirname, '../..');
const requiredFiles = [
    '.env.example',
    '.gitignore',
    'README.md',
    'artisan',
    'composer.json',
    'composer.lock',
    'package.json',
    'package-lock.json',
    'phpunit.xml',
    'playwright.config.ts',
    'vite.config.js',
    'app/Domain/Events/.gitkeep',
    'app/Domain/Gallery/.gitkeep',
    'app/Domain/Membership/.gitkeep',
    'app/Domain/Content/.gitkeep',
    'app/Domain/Governance/.gitkeep',
    'app/Domain/Operations/.gitkeep',
    'tests/Feature/Foundation/ApplicationBootTest.php',
];

async function writeFixtureFile(root, path, contents = '') {
    const destination = resolve(root, path);

    await mkdir(dirname(destination), { recursive: true });
    await writeFile(destination, contents);
}

test('repository verification rejects a developer-profile path in tracked configuration', async () => {
    const fixture = await mkdtemp(resolve(tmpdir(), 'waymark-portability-'));

    try {
        for (const path of requiredFiles) {
            await writeFixtureFile(fixture, path);
        }

        await writeFixtureFile(fixture, 'composer.json', JSON.stringify({
            require: { php: '^8.3' },
            config: { platform: { php: '8.3.0' } },
        }));
        await writeFixtureFile(fixture, 'composer.lock', JSON.stringify({
            'platform-overrides': { php: '8.3.0' },
        }));
        await writeFixtureFile(fixture, '.gitignore', [
            '.env',
            'vendor/',
            'node_modules/',
            'public/build/',
            'public/js/filament/',
            'storage/logs/',
            'test-results/',
            'dist/',
        ].join('\n'));

        const developerPath = ['C:', 'Users', 'casey', 'AppData', 'Local', 'Temp', 'php-ext'].join('\\');
        await writeFixtureFile(fixture, 'playwright.config.ts', `export const phpScan = ${JSON.stringify(developerPath)};\n`);
        await mkdir(resolve(fixture, 'scripts'), { recursive: true });
        await copyFile(
            resolve(repositoryRoot, 'scripts/verify-repository.php'),
            resolve(fixture, 'scripts/verify-repository.php'),
        );

        const init = spawnSync('git', ['init', '--quiet'], { cwd: fixture, encoding: 'utf8' });
        assert.equal(init.status, 0, init.stderr);
        const add = spawnSync('git', ['add', '.'], { cwd: fixture, encoding: 'utf8' });
        assert.equal(add.status, 0, add.stderr);

        const verification = spawnSync('php', ['scripts/verify-repository.php'], {
            cwd: fixture,
            encoding: 'utf8',
        });

        assert.notEqual(verification.status, 0, 'repository verification unexpectedly accepted a developer-profile path');
        assert.match(verification.stderr, /Developer-local absolute path is tracked: playwright\.config\.ts/);
    } finally {
        await rm(fixture, { recursive: true, force: true });
    }
});
