import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { resolve } from 'node:path';
import test from 'node:test';

const repositoryRoot = resolve(import.meta.dirname, '../..');

test('shared-hosting builder plan uses a clean commit, locked dependencies, full tests and production-only packaging', () => {
    const result = spawnSync('php', ['scripts/build-release.php', '--version=1.0.0', '--plan'], {
        cwd: repositoryRoot,
        encoding: 'utf8',
        env: process.env,
    });

    assert.equal(result.status, 0, result.stderr);
    const plan = JSON.parse(result.stdout);
    assert.equal(plan.version, '1.0.0');
    assert.equal(plan.archive, 'waymark-community-1.0.0-shared-hosting.zip');
    assert.equal(plan.source, 'git archive HEAD');
    assert.deepEqual(plan.verification, [
        'composer validate --strict',
        'composer prohibits php 8.3',
        'composer verify:repository',
        'composer audit:v1',
        'php -d memory_limit=512M vendor/bin/phpunit',
        'npm run test:pwa',
        'npm run test:tooling',
    ]);
    assert.ok(plan.build.includes('composer install --no-interaction --prefer-dist'));
    assert.deepEqual(plan.verification_environment, [
        'copy .env.example to an untracked disposable .env',
        'generate an ephemeral APP_KEY without inheriting developer configuration',
        'exclude .env from the release package',
    ]);
    assert.ok(plan.build.includes('npm ci'));
    assert.ok(plan.build.includes('npm run build'));
    assert.ok(plan.package.includes('composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader'));
    assert.ok(plan.excludes.includes('.env'));
    assert.ok(plan.excludes.includes('node_modules'));
    assert.ok(plan.excludes.includes('tests'));
});

test('builder can produce an exact prior-commit release candidate for upgrade testing', () => {
    const commit = '0ce6c82d9f9d055052fb4697f8838de4c4df92c7';
    const result = spawnSync('php', [
        'scripts/build-release.php', '--version=0.9.0', `--commit=${commit}`, '--plan',
    ], { cwd: repositoryRoot, encoding: 'utf8', env: process.env });

    assert.equal(result.status, 0, result.stderr);
    const plan = JSON.parse(result.stdout);
    assert.equal(plan.source, `git archive ${commit}`);
    assert.equal(plan.archive, 'waymark-community-0.9.0-shared-hosting.zip');
});

test('exact-commit workspace supplies repository verification metadata without packaging it', async () => {
    const source = await import('node:fs/promises').then(({ readFile }) => readFile(resolve(repositoryRoot, 'scripts/build-release.php'), 'utf8'));

    assert.match(source, /git init --quiet/);
    assert.match(source, /git add --all/);
    assert.match(source, /git -c user\.name=Waymark -c user\.email=release@waymark\.invalid commit --quiet/);
    assert.match(source, /\\\.git\|\\\.github/);
    assert.match(source, /normalized !== '\.env\.example'/);
    assert.match(source, /RuntimePathFilter::excludes\(\$path\)/);
    assert.match(source, /RuntimePathFilter::purge\(\$application\)/);
});
