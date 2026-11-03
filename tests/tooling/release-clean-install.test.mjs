import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';

const repositoryRoot = resolve(import.meta.dirname, '../..');

test('release clean-install plan uses only the verified ZIP and runtime PHP', () => {
    const result = spawnSync('node', [
        'tests/tooling/phase10-release-clean-install.mjs',
        '--archive=dist/waymark-community-1.0.2-shared-hosting.zip',
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
    assert.deepEqual(plan.deployment_prefixes, ['/', '/demo-site/ndwg/']);
    assert.deepEqual(plan.smoke, ['web installer', 'public homepage', 'admin login', 'site media upload', 'installer lockout', 'internal application protection']);
});

test('public-html clean-install fixture uses PHP 8.3 Apache with htaccess overrides', () => {
    const dockerfile = readFileSync(resolve(repositoryRoot, 'tests/fixtures/apache-shared-hosting/Dockerfile'), 'utf8');

    assert.match(dockerfile, /^FROM php:8\.3-apache/m);
    assert.match(dockerfile, /a2enmod rewrite/);
    assert.match(dockerfile, /AllowOverride All/);
    assert.match(dockerfile, /pdo_mysql/);
});

test('public-html clean-install copies the exact package into a stopped native Apache container', () => {
    const harness = readFileSync(resolve(repositoryRoot, 'tests/tooling/phase10-release-clean-install.mjs'), 'utf8');

    assert.match(harness, /execFileSync\('docker', \['create', '--name', containerName/);
    assert.match(harness, /execFileSync\('docker', \['cp', `\$\{webRoot\}\/\.`, `\$\{containerName\}:\/var\/www\/html`\]/);
    assert.match(harness, /chown www-data:www-data \$\{containerInstallRoot\}\/application && chown -R www-data:www-data \$\{containerInstallRoot\}\/application\/storage \$\{containerInstallRoot\}\/application\/bootstrap\/cache/);
    assert.match(harness, /spawn\('docker', \['start', '-a', containerName\]/);
    assert.doesNotMatch(harness, /'-v', `\$\{webRoot\}:/);
    assert.match(harness, /syncContainerRuntimeFile\(containerName, containerApplicationRoot, applicationRoot, '\.env'\)/);
    assert.match(harness, /syncContainerRuntimeFile\(containerName, containerApplicationRoot, applicationRoot, 'storage\/app\/private\/installed\.lock'\)/);
});

test('public-html clean-install mail fixture is reachable from the Apache container', () => {
    const harness = readFileSync(resolve(repositoryRoot, 'tests/tooling/phase10-release-clean-install.mjs'), 'utf8');

    assert.match(harness, /const smtpBindHost = requestedLayout === 'public-html' \? '0\.0\.0\.0' : '127\.0\.0\.1';/);
    assert.match(harness, /smtp\.listen\(smtpPort, smtpBindHost, resolveListening\);/);
});

test('public-html clean-install checks compiled assets in the public web root', () => {
    const harness = readFileSync(resolve(repositoryRoot, 'tests/tooling/phase10-release-clean-install.mjs'), 'utf8');

    assert.match(harness, /existsSync\(resolve\(publicRoot, 'build\/manifest\.json'\)\)/);
    assert.doesNotMatch(harness, /\['vendor\/autoload\.php', 'public\/build\/manifest\.json', '\.env\.example'\]/);
});

test('public-html clean-install uses a host name reachable by the Apache self-protection probe', () => {
    const harness = readFileSync(resolve(repositoryRoot, 'tests/tooling/phase10-release-clean-install.mjs'), 'utf8');

    assert.match(harness, /const browserOrigin = requestedLayout === 'public-html'\s+\? `http:\/\/host\.docker\.internal:\$\{appPort\}`/);
    assert.match(harness, /waitForHttp\(applicationUrl\('\/setup'\)/);
});

test('clean-install mail fixture destroys active container connections during teardown', () => {
    const harness = readFileSync(resolve(repositoryRoot, 'tests/tooling/phase10-release-clean-install.mjs'), 'utf8');

    assert.match(harness, /for \(const socket of smtpSockets\) socket\.destroy\(\);\s+await new Promise\(\(resolveClosed\) => smtp\.close\(resolveClosed\)\);/);
});

test('public-html clean install can mount the package beneath a deployment prefix', () => {
    const harness = readFileSync(resolve(repositoryRoot, 'tests/tooling/phase10-release-clean-install.mjs'), 'utf8');

    assert.match(harness, /--url-prefix=/);
    assert.match(harness, /normalizeUrlPrefix/);
    assert.match(harness, /resolve\(webRoot, \.\.\.deploymentSegments\)/);
    assert.match(harness, /deployment_prefix: urlPrefix/);
});

test('prefixed clean install audits generated application URLs and persisted app url', () => {
    const harness = readFileSync(resolve(repositoryRoot, 'tests/tooling/phase10-release-clean-install.mjs'), 'utf8');

    assert.match(harness, /assertApplicationDocumentUrls/);
    assert.match(harness, /assertApplicationAssetResponses/);
    assert.match(harness, /readEnvironmentValue\(.*'APP_URL'/s);
    assert.match(harness, /logout_location/);
});

test('real-server setup submissions wait for navigation before the next step', () => {
    const harness = readFileSync(resolve(repositoryRoot, 'tests/tooling/phase10-release-clean-install.mjs'), 'utf8');

    assert.match(harness, /page\.setDefaultNavigationTimeout\(60_000\)/);
    assert.match(harness, /async function submitSetupStep[\s\S]*?Promise\.all\(\[[\s\S]*?page\.waitForEvent\('framenavigated'/);
    assert.match(harness, /frame === page\.mainFrame\(\)/);
    assert.doesNotMatch(harness, /url\.href !== previousUrl/);
    assert.match(harness, /getByRole\('button',[\s\S]*?\.click\(\{ timeout: 60_000 \}\)/);
});

test('release install drives the persisted installer and verifies refresh resume', () => {
    const harness = readFileSync(resolve(repositoryRoot, 'tests/tooling/phase10-release-clean-install.mjs'), 'utf8');

    assert.match(harness, /Installation progress/);
    assert.match(harness, /progressStageBeforeRefresh/);
    assert.match(harness, /page\.reload/);
    assert.match(harness, /page\.waitForURL\(/);
    assert.match(harness, /admin\\\/login/);
    assert.doesNotMatch(harness, /Final health check/);
});

test('release install locates the required administrator password without an obsolete exact label', () => {
    const harness = readFileSync(resolve(repositoryRoot, 'tests/tooling/phase10-release-clean-install.mjs'), 'utf8');

    assert.match(harness, /getByLabel\(\/\^Password\\b\//);
    assert.doesNotMatch(harness, /getByLabel\('Password', \{ exact: true \}\)/);
});

test('public-html staged installation allows for a slow shared-host filesystem fixture', () => {
    const harness = readFileSync(resolve(repositoryRoot, 'tests/tooling/phase10-release-clean-install.mjs'), 'utf8');

    assert.match(harness, /const installationTimeout = requestedLayout === 'public-html' \? 1_200_000 : 180_000;/);
    assert.match(harness, /page\.waitForURL\(\/\\\/admin\\\/login[\s\S]*?timeout: installationTimeout/);
});

test('public-html post-install sign in allows for a bounded cold admin boot', () => {
    const harness = readFileSync(resolve(repositoryRoot, 'tests/tooling/phase10-release-clean-install.mjs'), 'utf8');

    assert.match(harness, /const interactionTimeout = requestedLayout === 'public-html' \? 120_000 : 60_000;/);
    assert.match(harness, /page\.waitForURL\(applicationUrl\('\/admin'\), \{ timeout: interactionTimeout \}\)/);
    assert.match(harness, /getByRole\('button', \{ name: 'Sign in' \}\)\.click\(\{ timeout: interactionTimeout \}\)/);
});

test('release install waits for slow recovery-key generation before asserting the value', () => {
    const harness = readFileSync(resolve(repositoryRoot, 'tests/tooling/phase10-release-clean-install.mjs'), 'utf8');

    assert.match(harness, /const generatedRecoveryResponse = page\.waitForResponse\([\s\S]*?timeout: interactionTimeout/);
    assert.match(harness, /await generatedRecoveryResponse;/);
    assert.match(harness, /expect\(recoveryKey\)\.not\.toHaveValue\('', \{ timeout: interactionTimeout \}\)/);
});

test('release install covers configured and deliberately skipped email modes', () => {
    const harness = readFileSync(resolve(repositoryRoot, 'tests/tooling/phase10-release-clean-install.mjs'), 'utf8');

    assert.match(harness, /PHASE10_INSTALL_EMAIL_MODE/);
    assert.match(harness, /Set up email later/);
    assert.match(harness, /WAYMARK_MAIL_CONFIGURED/);
});

test('release install supports controlled partial and ambiguous database fixtures', () => {
    const harness = readFileSync(resolve(repositoryRoot, 'tests/tooling/phase10-release-clean-install.mjs'), 'utf8');

    assert.match(harness, /PHASE10_INSTALL_DB_SCENARIO/);
    assert.match(harness, /exact-partial/);
    assert.match(harness, /generic-partial/);
    assert.match(harness, /ambiguous/);
    assert.match(harness, /Reset incomplete installation and retry/);
});
