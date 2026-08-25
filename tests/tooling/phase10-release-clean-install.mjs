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

const archiveName = basename(archivePath);
const requestedLayout = archiveName.endsWith('-public-html.zip') ? 'public-html' : 'standard';
const evidenceRoot = resolve(`test-results/phase10-clean-install-${requestedLayout}-${database.driver}`);
const installRoot = process.env.PHASE10_INSTALL_DESTINATION === undefined
    ? resolve(evidenceRoot, requestedLayout === 'public-html' ? 'web-root' : 'application')
    : resolve(process.env.PHASE10_INSTALL_DESTINATION);
const appPort = Number(process.env.PHASE10_INSTALL_APP_PORT ?? (database.driver === 'mysql' ? 8030 : 8031));
const smtpPort = Number(process.env.PHASE10_INSTALL_SMTP_PORT ?? (database.driver === 'mysql' ? 8040 : 8041));
const browserBaseUrl = requestedLayout === 'public-html'
    ? `http://host.docker.internal:${appPort}`
    : `http://127.0.0.1:${appPort}`;
rmSync(evidenceRoot, { recursive: true, force: true });
mkdirSync(evidenceRoot, { recursive: true });

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

const smtp = createSmtpFixture();
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
        '-v', `${installRoot}:/var/www/html`,
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
    await waitForHttp(`${browserBaseUrl}/setup`, server, () => serverOutput);
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await page.goto(`${browserBaseUrl}/setup`);
    await page.getByRole('button', { name: 'Begin setup' }).click();
    await page.getByRole('button', { name: 'Continue' }).click();

    await page.getByLabel('Database type').selectOption(database.driver);
    await page.getByLabel('Host').fill(database.host);
    await page.getByLabel('Port').fill(database.port);
    await page.getByLabel('Database name').fill(database.name);
    await page.getByLabel('Username').fill(database.username);
    await page.getByLabel('Password').fill(database.password);
    await page.getByRole('button', { name: 'Test connection and continue' }).click();

    await page.getByLabel('Group name').fill(`Release ${database.driver} Walking Group`);
    await page.getByLabel('Short name').fill(database.driver === 'mysql' ? 'RMG' : 'RDB');
    await page.getByLabel('Contact email').fill('contact@example.test');
    await page.getByRole('button', { name: 'Continue' }).click();
    await page.getByRole('button', { name: 'Continue' }).click();

    await page.getByLabel('Name').fill('Installation Owner');
    await page.getByLabel('Email').fill('owner@example.test');
    await page.getByLabel('Password', { exact: true }).fill('WaymarkRelease10!');
    await page.getByLabel('Confirm password').fill('WaymarkRelease10!');
    await page.getByRole('button', { name: 'Continue' }).click();

    await page.getByLabel('SMTP host').fill(process.env.PHASE10_INSTALL_SMTP_HOST ?? (requestedLayout === 'public-html' ? 'host.docker.internal' : '127.0.0.1'));
    await page.getByLabel('Port').fill(String(smtpPort));
    await page.getByLabel('Encryption').selectOption('');
    await page.getByLabel('From address').fill('waymark@example.test');
    await page.getByLabel('Send test to').fill('owner@example.test');
    await page.getByRole('button', { name: 'Send test and continue' }).click();
    await page.getByRole('button', { name: 'Continue' }).click();

    const recoveryToken = 'Release-Recovery-Token-10!';
    await page.getByLabel('Recovery token', { exact: true }).fill(recoveryToken);
    await page.getByLabel('Confirm recovery token').fill(recoveryToken);
    await page.getByRole('button', { name: 'Continue' }).click();
    await page.getByRole('button', { name: 'Install Waymark Community' }).click();
    await page.getByRole('heading', { name: 'Final health check' }).waitFor();
    await page.getByRole('button', { name: 'Finish setup' }).click();

    await page.goto(`${browserBaseUrl}/login`);
    await page.getByLabel('Email address').fill('owner@example.test');
    await page.getByLabel('Password').fill('WaymarkRelease10!');
    await Promise.all([
        page.waitForURL(`${browserBaseUrl}/admin`),
        page.getByRole('button', { name: 'Sign in' }).click(),
    ]);
    await page.getByRole('heading', { name: 'Dashboard' }).waitFor();

    const publicResponse = await page.goto(`${browserBaseUrl}/`);
    if (publicResponse?.status() !== 200) throw new Error(`Public homepage returned ${publicResponse?.status()}.`);

    await page.goto(`${browserBaseUrl}/admin/media-library`);
    const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgYGAAAAAEAAGjChXjAAAAAElFTkSuQmCC', 'base64');
    await page.getByLabel('Site media image').setInputFiles({ name: 'release-smoke.png', mimeType: 'image/png', buffer: png });
    await page.getByLabel('Alt text').fill('Release package upload smoke');
    await page.getByRole('button', { name: 'Upload media' }).click();
    await expect(page.getByText('Release package upload smoke')).toBeVisible();

    const setupResponse = await page.goto(`${browserBaseUrl}/setup`);
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
        const response = await page.request.get(`${browserBaseUrl}${path}`);
        protectionStatuses[path] = response.status();
        if (response.status() < 400) throw new Error(`Protected path was publicly retrievable (${response.status()}): ${path}`);
    }

    const result = {
        archive: archiveName,
        layout: requestedLayout,
        version: readFileSync(resolve(applicationRoot, 'VERSION'), 'utf8').trim(),
        database: `${database.driver}@${database.host}:${database.port}/${database.name}`,
        started_without_environment_file: true,
        composer_at_runtime: false,
        node_at_runtime: false,
        public_status: publicResponse.status(),
        admin_login: 'passed',
        site_media_upload: 'passed',
        installer_locked_status: setupResponse.status(),
        protected_paths: protectionStatuses,
    };
    writeFileSync(resolve(evidenceRoot, 'result.json'), `${JSON.stringify(result, null, 2)}\n`);
    process.stdout.write(`${JSON.stringify(result)}\n`);
} finally {
    await browser.close();
    await new Promise((resolveClosed) => smtp.close(resolveClosed));
    server.kill();
    if (requestedLayout === 'public-html') {
        try { execFileSync('docker', ['rm', '-f', containerName], { stdio: 'ignore' }); } catch { /* already stopped */ }
    } else if (process.platform === 'win32' && server.pid !== undefined) {
        try { execFileSync('taskkill', ['/pid', String(server.pid), '/T', '/F'], { stdio: 'ignore' }); } catch { /* already stopped */ }
    }
}

function createSmtpFixture() {
    return createServer((socket) => {
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
