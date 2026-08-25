import { chromium, expect } from '@playwright/test';
import { execFileSync, spawn } from 'node:child_process';
import { createServer } from 'node:net';
import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { basename, resolve } from 'node:path';

const plan = {
    source: 'verified release ZIP only',
    runtime_tools: ['php', 'web server'],
    composer_at_runtime: false,
    node_at_runtime: false,
    layouts: ['standard', 'public-html'],
    databases: ['mysql', 'mariadb'],
    deployment_prefixes: ['/', '/demo-site/ndwg/'],
    smoke: ['web installer', 'public homepage', 'admin login', 'site media upload', 'installer lockout', 'internal application protection'],
};
if (process.argv.includes('--plan')) {
    process.stdout.write(`${JSON.stringify(plan, null, 2)}\n`);
    process.exit(0);
}

const root = resolve('.');
const archiveOption = process.argv.find((argument) => argument.startsWith('--archive='));
if (archiveOption === undefined) throw new Error('Provide --archive=<shared-hosting.zip>.');
const archivePath = resolve(archiveOption.slice('--archive='.length));
const database = {
    driver: process.env.PHASE10_INSTALL_DB_DRIVER ?? 'mysql',
    host: process.env.PHASE10_INSTALL_DB_HOST ?? '127.0.0.1',
    port: process.env.PHASE10_INSTALL_DB_PORT ?? '3306',
    name: process.env.PHASE10_INSTALL_DB_NAME ?? 'waymark_release_install',
    username: process.env.PHASE10_INSTALL_DB_USERNAME ?? 'waymark',
    password: process.env.PHASE10_INSTALL_DB_PASSWORD ?? 'waymark-test-password',
};
if (!plan.databases.includes(database.driver)) throw new Error('PHASE10_INSTALL_DB_DRIVER must be mysql or mariadb.');
const emailMode = process.env.PHASE10_INSTALL_EMAIL_MODE ?? 'configured';
if (!['configured', 'later'].includes(emailMode)) throw new Error('PHASE10_INSTALL_EMAIL_MODE must be configured or later.');
const databaseScenario = process.env.PHASE10_INSTALL_DB_SCENARIO ?? 'empty';
if (!['empty', 'exact-partial', 'generic-partial', 'ambiguous'].includes(databaseScenario)) {
    throw new Error('PHASE10_INSTALL_DB_SCENARIO is unsupported.');
}

const archiveName = basename(archivePath);
const requestedLayout = archiveName.endsWith('-public-html.zip') ? 'public-html' : 'standard';
const urlPrefixOption = process.argv.find((argument) => argument.startsWith('--url-prefix='));
const urlPrefix = normalizeUrlPrefix(urlPrefixOption?.slice('--url-prefix='.length) ?? process.env.PHASE10_INSTALL_URL_PREFIX ?? '/');
if (requestedLayout !== 'public-html' && urlPrefix !== '/') {
    throw new Error('A non-root URL prefix is only available to the real Apache public-html harness.');
}
const deploymentSegments = urlPrefix.split('/').filter(Boolean);
const evidenceSuffix = urlPrefix === '/' ? 'root' : `prefix-${deploymentSegments.join('-')}`;
const evidenceRoot = resolve(`test-results/phase10-clean-install-${requestedLayout}-${database.driver}-${evidenceSuffix}-${emailMode}-${databaseScenario}`);
const configuredInstallRoot = process.env.PHASE10_INSTALL_DESTINATION === undefined
    ? null
    : resolve(process.env.PHASE10_INSTALL_DESTINATION);
const webRoot = configuredInstallRoot === null
    ? resolve(evidenceRoot, 'web-root')
    : resolve(configuredInstallRoot, ...deploymentSegments.map(() => '..'));
const installRoot = configuredInstallRoot ?? (requestedLayout === 'public-html'
    ? resolve(webRoot, ...deploymentSegments)
    : resolve(evidenceRoot, 'application'));
const portOffset = urlPrefix === '/' ? 0 : 2;
const appPort = Number(process.env.PHASE10_INSTALL_APP_PORT ?? (database.driver === 'mysql' ? 8030 + portOffset : 8031 + portOffset));
const smtpPort = Number(process.env.PHASE10_INSTALL_SMTP_PORT ?? (database.driver === 'mysql' ? 8040 + portOffset : 8041 + portOffset));
const installationTimeout = requestedLayout === 'public-html' ? 600_000 : 180_000;
const browserOrigin = requestedLayout === 'public-html'
    ? `http://host.docker.internal:${appPort}`
    : `http://127.0.0.1:${appPort}`;
const browserBaseUrl = `${browserOrigin}${urlPrefix === '/' ? '' : urlPrefix.slice(0, -1)}`;
const applicationUrl = (path = '/') => `${browserBaseUrl}${path === '/' ? '/' : `/${path.replace(/^\/+/, '')}`}`;
rmSync(evidenceRoot, { recursive: true, force: true });
mkdirSync(evidenceRoot, { recursive: true });

if (databaseScenario !== 'empty') {
    runTool('php', [
        'tests/tooling/prepare-installer-database.php',
        database.driver,
        database.host,
        database.port,
        database.name,
        database.username,
        database.password,
        databaseScenario,
    ], { cwd: root, env: process.env, stdio: 'inherit' });
}

runTool('php', ['scripts/extract-release.php', archivePath, installRoot], { cwd: root, env: process.env, stdio: 'inherit' });
const applicationRoot = requestedLayout === 'public-html' ? resolve(installRoot, 'application') : installRoot;
const publicRoot = requestedLayout === 'public-html' ? installRoot : resolve(applicationRoot, 'public');
const actualLayout = readFileSync(resolve(applicationRoot, 'DEPLOYMENT-LAYOUT'), 'utf8').trim();
if (actualLayout !== requestedLayout) throw new Error(`Release layout mismatch: expected ${requestedLayout}, found ${actualLayout}.`);
for (const forbidden of ['.git', '.env', 'node_modules', 'tests', 'package.json']) {
    if (existsSync(resolve(applicationRoot, forbidden))) throw new Error(`Release install tree contains forbidden path: ${forbidden}`);
}
for (const required of ['vendor/autoload.php', '.env.example']) {
    if (!existsSync(resolve(applicationRoot, required))) throw new Error(`Release install tree is missing: ${required}`);
}
if (!existsSync(resolve(publicRoot, 'build/manifest.json'))) {
    throw new Error('Release install tree is missing the compiled asset manifest.');
}

const smtpSockets = new Set();
const smtp = createSmtpFixture(smtpSockets);
const smtpBindHost = requestedLayout === 'public-html' ? '0.0.0.0' : '127.0.0.1';
await new Promise((resolveListening, reject) => {
    smtp.once('error', reject);
    smtp.listen(smtpPort, smtpBindHost, resolveListening);
});

const phpScan = process.env.PHP_INI_SCAN_DIR === undefined ? {} : { PHP_INI_SCAN_DIR: process.env.PHP_INI_SCAN_DIR };
const serverEnvironment = {
    ...process.env,
    ...phpScan,
    APP_ENV: 'production',
    APP_DEBUG: 'false',
    WAYMARK_INSTALLED: 'false',
    WAYMARK_CRON_AVAILABLE: 'false',
};
const containerName = `waymark-public-html-${database.driver}-${process.pid}`;
const server = requestedLayout === 'public-html'
    ? spawn('docker', [
        'run', '--rm', '--name', containerName,
        '-p', `${appPort}:80`,
        '-e', 'APP_ENV=production',
        '-e', 'APP_DEBUG=false',
        '-e', 'WAYMARK_INSTALLED=false',
        '-e', 'WAYMARK_CRON_AVAILABLE=false',
        '-v', `${webRoot}:/var/www/html`,
        process.env.PHASE10_INSTALL_APACHE_IMAGE ?? 'waymark-php83-apache:local',
    ], { cwd: root, env: process.env, stdio: ['ignore', 'pipe', 'pipe'] })
    : spawn('php', [
        '-S', `127.0.0.1:${appPort}`,
        resolve(applicationRoot, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'),
    ], { cwd: publicRoot, env: serverEnvironment, stdio: ['ignore', 'pipe', 'pipe'] });
let serverOutput = '';
server.stdout.on('data', (chunk) => { serverOutput += chunk.toString(); });
server.stderr.on('data', (chunk) => { serverOutput += chunk.toString(); });

const browser = await chromium.launch();
try {
    await waitForHttp(applicationUrl('/setup'), server, () => serverOutput);
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    page.setDefaultNavigationTimeout(60_000);
    await page.goto(applicationUrl('/setup'));
    await submitSetupStep(page, 'Begin setup', browserBaseUrl, urlPrefix);
    await submitSetupStep(page, 'Continue', browserBaseUrl, urlPrefix);

    await page.getByLabel('Database type').selectOption(database.driver);
    await page.getByLabel('Database host').fill(database.host);
    await page.getByLabel('Port').fill(database.port);
    await page.getByLabel('Database name').fill(database.name);
    await page.getByLabel('Username').fill(database.username);
    await page.getByLabel('Password').fill(database.password);

    if (databaseScenario === 'ambiguous') {
        await page.getByRole('button', { name: 'Verify database and continue' }).click();
        await expect(page.getByText('This database already contains data that Waymark cannot safely use')).toBeVisible();
        const result = {
            archive: archiveName,
            layout: requestedLayout,
            deployment_prefix: urlPrefix,
            database_scenario: databaseScenario,
            non_empty_database_blocked: true,
            composer_at_runtime: false,
            node_at_runtime: false,
        };
        writeFileSync(resolve(evidenceRoot, 'result.json'), `${JSON.stringify(result, null, 2)}\n`);
        process.stdout.write(`${JSON.stringify(result)}\n`);
    } else {
        if (['exact-partial', 'generic-partial'].includes(databaseScenario)) {
            await page.getByRole('button', { name: 'Verify database and continue' }).click();
            await expect(page.getByRole('heading', { name: 'Incomplete Waymark installation detected' })).toBeVisible();
            await page.getByLabel(/Type RESET WAYMARK INSTALLATION/).fill('RESET WAYMARK INSTALLATION');
            await submitSetupStep(page, 'Reset incomplete installation and retry', browserBaseUrl, urlPrefix);
            await expect(page.getByText('The incomplete Waymark installation was reset')).toBeVisible();
            await page.getByLabel('Database type').selectOption(database.driver);
            await page.getByLabel('Database host').fill(database.host);
            await page.getByLabel('Port').fill(database.port);
            await page.getByLabel('Database name').fill(database.name);
            await page.getByLabel('Username').fill(database.username);
            await page.getByLabel('Password').fill(database.password);
        }
        await submitSetupStep(page, 'Verify database and continue', browserBaseUrl, urlPrefix);

    await page.getByLabel('Group name').fill(`Release ${database.driver} Walking Group`);
    await page.getByLabel('Short name').fill(database.driver === 'mysql' ? 'RMG' : 'RDB');
    await page.getByLabel('Contact email').fill('contact@example.test');
    await submitSetupStep(page, 'Continue', browserBaseUrl, urlPrefix);
    await submitSetupStep(page, 'Continue', browserBaseUrl, urlPrefix);

    await page.getByLabel('Name').fill('Installation Owner');
    await page.getByLabel('Email').fill('owner@example.test');
    await page.getByLabel(/^Password\b/).fill('WaymarkRelease10!');
    await page.getByLabel('Confirm password').fill('WaymarkRelease10!');
    await submitSetupStep(page, 'Continue', browserBaseUrl, urlPrefix);

    if (emailMode === 'later') {
        await page.getByLabel('Set up email later').check();
    } else {
        await page.getByLabel('Configure email now').check();
        await page.getByLabel('SMTP host').fill(process.env.PHASE10_INSTALL_SMTP_HOST ?? (requestedLayout === 'public-html' ? 'host.docker.internal' : '127.0.0.1'));
        await page.getByLabel('Port').fill(String(smtpPort));
        await page.getByLabel('Encryption').selectOption('');
        await page.getByLabel('From address').fill('waymark@example.test');
        await page.getByLabel('Send test to').fill('owner@example.test');
    }
    await submitSetupStep(page, 'Continue', browserBaseUrl, urlPrefix);
    await submitSetupStep(page, 'Continue', browserBaseUrl, urlPrefix);

    await page.getByRole('button', { name: 'Generate secure recovery key' }).click();
    const recoveryKey = page.locator('#recovery-key');
    await expect(recoveryKey).not.toHaveValue('');
    await page.getByLabel(/I have saved the recovery key/).check();
    await submitSetupStep(page, 'Continue', browserBaseUrl, urlPrefix);
    await submitSetupStep(page, 'Install Waymark Community', browserBaseUrl, urlPrefix);
    await page.getByRole('heading', { name: 'Installation progress' }).waitFor();
    const progressStageBeforeRefresh = await page.locator('#installation-status').getAttribute('data-stage');
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Installation progress' })).toBeVisible();
    if (progressStageBeforeRefresh === null) throw new Error('Installation progress did not expose a persisted stage.');
    await page.waitForURL(/\/admin\/login(?:\?.*)?$/, { timeout: installationTimeout });

    const installedAppUrl = readEnvironmentValue(resolve(applicationRoot, '.env'), 'APP_URL');
    if (installedAppUrl !== browserBaseUrl) {
        throw new Error(`Installer persisted APP_URL=${installedAppUrl}; expected ${browserBaseUrl}.`);
    }
    const installedMailState = readEnvironmentValue(resolve(applicationRoot, '.env'), 'WAYMARK_MAIL_CONFIGURED');
    if (installedMailState !== (emailMode === 'configured' ? 'true' : 'false')) {
        throw new Error(`Installer persisted WAYMARK_MAIL_CONFIGURED=${installedMailState}; expected mode ${emailMode}.`);
    }

    await page.goto(applicationUrl('/login'));
    await page.getByLabel('Email address').fill('owner@example.test');
    await page.getByLabel('Password').fill('WaymarkRelease10!');
    await Promise.all([
        page.waitForURL(applicationUrl('/admin')),
        page.getByRole('button', { name: 'Sign in' }).click(),
    ]);
    await page.getByRole('heading', { name: 'Dashboard' }).waitFor();
    await assertApplicationDocumentUrls(page, browserBaseUrl, urlPrefix);

    const publicResponse = await page.goto(applicationUrl('/'));
    if (publicResponse?.status() !== 200) throw new Error(`Public homepage returned ${publicResponse?.status()}.`);

    const publicStatuses = {};
    for (const path of ['/', '/walks', '/whats-on', '/photos', '/search?q=walk']) {
        const response = await page.goto(applicationUrl(path));
        publicStatuses[path] = response?.status();
        if (response?.status() !== 200) throw new Error(`Public route returned ${response?.status()}: ${path}`);
        await assertApplicationDocumentUrls(page, browserBaseUrl, urlPrefix);
        await assertApplicationAssetResponses(page, browserBaseUrl, urlPrefix);
    }

    await page.goto(applicationUrl('/admin/media-library'));
    const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgYGAAAAAEAAGjChXjAAAAAElFTkSuQmCC', 'base64');
    await page.getByLabel('Site media image').setInputFiles({ name: 'release-smoke.png', mimeType: 'image/png', buffer: png });
    await page.getByLabel('Alt text').fill('Release package upload smoke');
    await page.getByRole('button', { name: 'Upload media' }).click();
    await expect(page.getByText('Release package upload smoke')).toBeVisible();
    await assertApplicationDocumentUrls(page, browserBaseUrl, urlPrefix);
    const uploadedImage = page.getByRole('img', { name: 'Release package upload smoke' }).first();
    const uploadedImageUrl = await uploadedImage.getAttribute('src');
    if (uploadedImageUrl === null || (await page.request.get(new URL(uploadedImageUrl, page.url()).href)).status() !== 200) {
        throw new Error('Uploaded site media could not be retrieved through the mounted application URL.');
    }

    const csrfToken = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    if (csrfToken === null) throw new Error('Admin layout did not expose a CSRF token for logout verification.');
    const logoutResponse = await page.request.post(applicationUrl('/logout'), {
        form: { _token: csrfToken },
        maxRedirects: 0,
    });
    const logoutLocation = logoutResponse.headers().location ?? '';
    if (![302, 303].includes(logoutResponse.status()) || !isApplicationUrl(logoutLocation, browserBaseUrl, urlPrefix)) {
        throw new Error(`Logout did not redirect within the mounted application (${logoutResponse.status()} ${logoutLocation}).`);
    }
    await page.goto(applicationUrl('/login'));
    await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();

    const setupResponse = await page.goto(applicationUrl('/setup'));
    if (setupResponse?.status() !== 404) throw new Error(`Locked installer returned ${setupResponse?.status()}.`);
    if (!existsSync(resolve(applicationRoot, '.env'))
        || !existsSync(resolve(applicationRoot, 'storage/app/private/installed.lock'))
        || existsSync(resolve(applicationRoot, 'node_modules'))) {
        throw new Error('Installed release tree did not satisfy the runtime file-state contract.');
    }

    const protectedPaths = requestedLayout === 'public-html'
        ? ['/application/.env.example', '/application/.env', '/application/vendor/autoload.php', '/application/storage/logs/laravel.log', '/.env']
        : ['/.env'];
    const protectionStatuses = {};
    for (const path of protectedPaths) {
        const response = await page.request.get(applicationUrl(path));
        protectionStatuses[path] = response.status();
        if (response.status() < 400) throw new Error(`Protected path was publicly retrievable (${response.status()}): ${path}`);
    }

    const result = {
        archive: archiveName,
        layout: requestedLayout,
        deployment_prefix: urlPrefix,
        email_mode: emailMode,
        database_scenario: databaseScenario,
        staged_install_refresh_resume: 'passed',
        version: readFileSync(resolve(applicationRoot, 'VERSION'), 'utf8').trim(),
        database: `${database.driver}@${database.host}:${database.port}/${database.name}`,
        started_without_environment_file: true,
        composer_at_runtime: false,
        node_at_runtime: false,
        public_status: publicResponse.status(),
        public_routes: publicStatuses,
        admin_login: 'passed',
        logout_location: logoutLocation,
        site_media_upload: 'passed',
        installer_locked_status: setupResponse.status(),
        protected_paths: protectionStatuses,
    };
    writeFileSync(resolve(evidenceRoot, 'result.json'), `${JSON.stringify(result, null, 2)}\n`);
    process.stdout.write(`${JSON.stringify(result)}\n`);
    }
} finally {
    await browser.close();
    for (const socket of smtpSockets) socket.destroy();
    await new Promise((resolveClosed) => smtp.close(resolveClosed));
    server.kill();
    if (requestedLayout === 'public-html') {
        try { execFileSync('docker', ['rm', '-f', containerName], { stdio: 'ignore' }); } catch { /* already stopped */ }
    } else if (process.platform === 'win32' && server.pid !== undefined) {
        try { execFileSync('taskkill', ['/pid', String(server.pid), '/T', '/F'], { stdio: 'ignore' }); } catch { /* already stopped */ }
    }
}

function normalizeUrlPrefix(value) {
    const candidate = String(value).trim();
    if (candidate === '' || candidate === '/') return '/';
    if (!candidate.startsWith('/') || /[?#\\%]/.test(candidate)) {
        throw new Error('The deployment URL prefix must be a plain absolute URL path.');
    }
    const segments = candidate.split('/').filter(Boolean);
    if (segments.some((segment) => segment === '.' || segment === '..' || !/^[A-Za-z0-9._~-]+$/.test(segment))) {
        throw new Error('The deployment URL prefix contains an unsupported path segment.');
    }

    return `/${segments.join('/')}/`;
}

async function submitSetupStep(page, buttonName, browserBaseUrl, urlPrefix) {
    await assertApplicationDocumentUrls(page, browserBaseUrl, urlPrefix);
    await Promise.all([
        page.waitForEvent('framenavigated', { predicate: (frame) => frame === page.mainFrame(), timeout: 60_000 }),
        page.getByRole('button', { name: buttonName, exact: true }).click({ timeout: 60_000 }),
    ]);
    await page.waitForLoadState('domcontentloaded', { timeout: 60_000 });
    if (!isApplicationUrl(page.url(), browserBaseUrl, urlPrefix)) {
        throw new Error(`Setup navigation escaped the mounted application: ${page.url()}`);
    }
}

async function assertApplicationDocumentUrls(page, browserBaseUrl, urlPrefix) {
    const references = await page.locator('[href], [src], [action]').evaluateAll((elements) => elements.flatMap((element) => (
        ['href', 'src', 'action']
            .map((attribute) => ({ attribute, value: element.getAttribute(attribute) }))
            .filter((reference) => reference.value !== null && reference.value.trim() !== '')
    )));

    for (const reference of references) {
        const value = reference.value.trim();
        if (/^(?:#|mailto:|tel:|data:|blob:|javascript:)/i.test(value)) continue;
        const resolved = new URL(value, page.url());
        if (resolved.origin !== new URL(browserBaseUrl).origin) continue;
        if (!isApplicationUrl(resolved.href, browserBaseUrl, urlPrefix)) {
            throw new Error(`${reference.attribute} escaped the mounted application on ${page.url()}: ${value}`);
        }
    }
}

async function assertApplicationAssetResponses(page, browserBaseUrl, urlPrefix) {
    const values = await page.locator('link[href], script[src], img[src], source[src], source[srcset]').evaluateAll((elements) => elements.flatMap((element) => {
        const sourceSet = element.getAttribute('srcset');
        if (sourceSet !== null) return sourceSet.split(',').map((entry) => entry.trim().split(/\s+/)[0]).filter(Boolean);
        return [element.getAttribute('href') ?? element.getAttribute('src')].filter(Boolean);
    }));
    const urls = [...new Set(values.map((value) => new URL(value, page.url()).href))]
        .filter((url) => isApplicationUrl(url, browserBaseUrl, urlPrefix));

    for (const url of urls) {
        const response = await page.request.get(url);
        if (response.status() >= 400) throw new Error(`Application asset returned ${response.status()}: ${url}`);
    }
}

function isApplicationUrl(value, browserBaseUrl, urlPrefix) {
    let candidate;
    try {
        candidate = new URL(value, browserBaseUrl);
    } catch {
        return false;
    }
    const base = new URL(browserBaseUrl);
    if (candidate.origin !== base.origin) return false;
    const basePath = urlPrefix === '/' ? '' : urlPrefix.slice(0, -1);

    return basePath === '' || candidate.pathname === basePath || candidate.pathname.startsWith(`${basePath}/`);
}

function readEnvironmentValue(path, key) {
    const line = readFileSync(path, 'utf8').split(/\r?\n/).find((candidate) => candidate.startsWith(`${key}=`));
    if (line === undefined) throw new Error(`Installed environment is missing ${key}.`);
    const value = line.slice(key.length + 1).trim();
    if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
        return value.slice(1, -1);
    }

    return value;
}

function createSmtpFixture(sockets) {
    return createServer((socket) => {
        sockets.add(socket);
        socket.once('close', () => sockets.delete(socket));
        let buffer = '';
        let acceptingData = false;
        socket.write('220 localhost Waymark test SMTP\r\n');
        socket.on('data', (chunk) => {
            buffer += chunk.toString();
            let boundary;
            while ((boundary = buffer.indexOf('\r\n')) >= 0) {
                const line = buffer.slice(0, boundary);
                buffer = buffer.slice(boundary + 2);
                if (acceptingData) {
                    if (line === '.') { acceptingData = false; socket.write('250 Message accepted\r\n'); }
                    continue;
                }
                if (/^(EHLO|HELO)\b/i.test(line)) socket.write('250-localhost\r\n250 PIPELINING\r\n');
                else if (/^(MAIL FROM|RCPT TO|RSET|NOOP)\b/i.test(line)) socket.write('250 OK\r\n');
                else if (/^DATA$/i.test(line)) { acceptingData = true; socket.write('354 End data with <CR><LF>.<CR><LF>\r\n'); }
                else if (/^QUIT$/i.test(line)) socket.end('221 Bye\r\n');
                else socket.write('250 OK\r\n');
            }
        });
    });
}

function runTool(name, args, options) {
    if (process.platform === 'win32') return execFileSync(process.env.ComSpec ?? 'cmd.exe', ['/d', '/s', '/c', name, ...args], options);
    return execFileSync(name, args, options);
}

async function waitForHttp(url, process, output) {
    for (let attempt = 0; attempt < 120; attempt++) {
        if (process.exitCode !== null) throw new Error(`Release web server exited before setup was ready.\n${output()}`);
        try { const response = await fetch(url); if (response.ok) return; } catch { /* starting */ }
        await new Promise((resolveWait) => setTimeout(resolveWait, 250));
    }
    throw new Error(`Timed out waiting for the release clean-install server.\n${output()}`);
}
