import { chromium } from '@playwright/test';
import { execFileSync, spawn } from 'node:child_process';
import { createServer } from 'node:net';
import { existsSync, mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const root = resolve('.');
const evidenceRoot = resolve('test-results/phase9-clean-install');
const applicationRoot = resolve(evidenceRoot, 'release-tree');
const archivePath = resolve(evidenceRoot, 'tracked-tree.tar');
const resultPath = resolve(evidenceRoot, 'result.json');
const appPort = Number(process.env.PHASE9_INSTALL_APP_PORT ?? 8010);
const smtpPort = Number(process.env.PHASE9_INSTALL_SMTP_PORT ?? 8025);
const database = {
    driver: process.env.PHASE9_INSTALL_DB_DRIVER ?? 'mysql',
    host: process.env.PHASE9_INSTALL_DB_HOST ?? '127.0.0.1',
    port: process.env.PHASE9_INSTALL_DB_PORT ?? '3306',
    name: process.env.PHASE9_INSTALL_DB_NAME ?? 'waymark_install_test',
    username: process.env.PHASE9_INSTALL_DB_USERNAME ?? 'waymark',
    password: process.env.PHASE9_INSTALL_DB_PASSWORD ?? 'waymark-test-password',
};

if (!['mysql', 'mariadb'].includes(database.driver)) {
    throw new Error('PHASE9_INSTALL_DB_DRIVER must be mysql or mariadb.');
}

mkdirSync(evidenceRoot, { recursive: true });
rmSync(applicationRoot, { recursive: true, force: true });
rmSync(archivePath, { force: true });
mkdirSync(applicationRoot, { recursive: true });

const commit = execFileSync('git', ['rev-parse', 'HEAD'], { cwd: root, encoding: 'utf8' }).trim();
execFileSync('git', ['archive', '--format=tar', `--output=${archivePath}`, commit], { cwd: root, stdio: 'inherit' });
execFileSync('tar', ['-xf', archivePath, '-C', applicationRoot], { stdio: 'inherit' });

if (existsSync(resolve(applicationRoot, '.git')) || existsSync(resolve(applicationRoot, '.env'))) {
    throw new Error('The release-like tree must start without .git or .env.');
}

runTool('composer', ['install', '--no-interaction', '--prefer-dist'], {
    cwd: applicationRoot,
    env: process.env,
    stdio: 'inherit',
});
runTool('npm', ['ci'], { cwd: applicationRoot, env: process.env, stdio: 'inherit' });
runTool('npm', ['run', 'build'], { cwd: applicationRoot, env: process.env, stdio: 'inherit' });
rmSync(resolve(applicationRoot, 'node_modules'), { recursive: true, force: true });

if (existsSync(resolve(applicationRoot, '.env'))) {
    throw new Error('Dependency installation unexpectedly created .env before web setup.');
}

const smtp = createSmtpFixture();
await new Promise((resolveListening, reject) => {
    smtp.once('error', reject);
    smtp.listen(smtpPort, '127.0.0.1', resolveListening);
});

const scanEnvironment = process.env.PHP_INI_SCAN_DIR === undefined
    ? {}
    : { PHP_INI_SCAN_DIR: process.env.PHP_INI_SCAN_DIR };
const php = spawn('php', [
    '-S',
    `127.0.0.1:${appPort}`,
    resolve(applicationRoot, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'),
], {
    cwd: resolve(applicationRoot, 'public'),
    env: {
        ...process.env,
        ...scanEnvironment,
        APP_ENV: 'production',
        APP_DEBUG: 'false',
        WAYMARK_INSTALLED: 'false',
        WAYMARK_CRON_AVAILABLE: 'false',
    },
    stdio: ['ignore', 'pipe', 'pipe'],
});
let serverOutput = '';
php.stdout.on('data', (chunk) => { serverOutput += chunk.toString(); });
php.stderr.on('data', (chunk) => { serverOutput += chunk.toString(); });

const browser = await chromium.launch();

try {
    await waitForHttp(`http://127.0.0.1:${appPort}/setup`, php, () => serverOutput);
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await page.goto(`http://127.0.0.1:${appPort}/setup`);
    await page.getByRole('button', { name: 'Begin setup' }).click();
    await page.getByRole('button', { name: 'Continue' }).click();

    await page.getByLabel('Database type').selectOption(database.driver);
    await page.getByLabel('Host').fill(database.host);
    await page.getByLabel('Port').fill(database.port);
    await page.getByLabel('Database name').fill(database.name);
    await page.getByLabel('Username').fill(database.username);
    await page.getByLabel('Password').fill(database.password);
    await page.getByRole('button', { name: 'Test connection and continue' }).click();

    await page.getByLabel('Group name').fill('Release-like Walking Group');
    await page.getByLabel('Short name').fill('RWG');
    await page.getByLabel('Contact email').fill('contact@example.test');
    await page.getByRole('button', { name: 'Continue' }).click();
    await page.getByRole('button', { name: 'Continue' }).click();

    await page.getByLabel('Name').fill('Installation Owner');
    await page.getByLabel('Email').fill('owner@example.test');
    await page.getByLabel('Password', { exact: true }).fill('WaymarkInstall9!');
    await page.getByLabel('Confirm password').fill('WaymarkInstall9!');
    await page.getByRole('button', { name: 'Continue' }).click();

    await page.getByLabel('SMTP host').fill('127.0.0.1');
    await page.getByLabel('Port').fill(String(smtpPort));
    await page.getByLabel('Encryption').selectOption('');
    await page.getByLabel('From address').fill('waymark@example.test');
    await page.getByLabel('Send test to').fill('owner@example.test');
    await page.getByRole('button', { name: 'Send test and continue' }).click();

    await page.getByRole('button', { name: 'Continue' }).click();
    const recoveryToken = 'ReleaseLike-Recovery-Token-9!';
    await page.getByLabel('Recovery token', { exact: true }).fill(recoveryToken);
    await page.getByLabel('Confirm recovery token').fill(recoveryToken);
    await page.getByRole('button', { name: 'Continue' }).click();
    await page.getByRole('button', { name: 'Install Waymark Community' }).click();

    await page.getByRole('heading', { name: 'Final health check' }).waitFor();
    await page.getByRole('button', { name: 'Finish setup' }).click();
    await page.getByLabel('Email address').fill('owner@example.test');
    await page.getByRole('textbox', { name: /^Password/ }).fill('WaymarkInstall9!');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await page.goto(`http://127.0.0.1:${appPort}/admin`);
    await page.getByText('Dashboard').first().waitFor();

    const publicResponse = await page.goto(`http://127.0.0.1:${appPort}/`);
    if (publicResponse?.status() !== 200) {
        throw new Error(`Public homepage returned ${publicResponse?.status()}.`);
    }
    const setupResponse = await page.goto(`http://127.0.0.1:${appPort}/setup`);
    if (setupResponse?.status() !== 404) {
        throw new Error(`Locked installer returned ${setupResponse?.status()}.`);
    }
    if (!existsSync(resolve(applicationRoot, '.env'))
        || !existsSync(resolve(applicationRoot, 'storage/app/private/installed.lock'))
        || existsSync(resolve(applicationRoot, 'node_modules'))) {
        throw new Error('Installed release tree did not satisfy the expected file-state contract.');
    }

    const result = {
        commit,
        database: `${database.driver}@${database.host}:${database.port}/${database.name}`,
        started_without_environment_file: true,
        dependencies_from_lockfiles: true,
        node_absent_at_runtime: true,
        public_status: publicResponse.status(),
        admin_login: 'passed',
        installer_locked_status: setupResponse.status(),
    };
    writeFileSync(resultPath, `${JSON.stringify(result, null, 2)}\n`);
    process.stdout.write(`${JSON.stringify(result)}\n`);
} finally {
    await browser.close();
    await new Promise((resolveClosed) => smtp.close(resolveClosed));
    php.kill();
    if (process.platform === 'win32' && php.pid !== undefined) {
        try {
            execFileSync('taskkill', ['/pid', String(php.pid), '/T', '/F'], { stdio: 'ignore' });
        } catch {
            // The PHP process may already have exited.
        }
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
                    if (line === '.') {
                        acceptingData = false;
                        socket.write('250 Message accepted\r\n');
                    }
                    continue;
                }
                if (/^(EHLO|HELO)\b/i.test(line)) socket.write('250-localhost\r\n250 PIPELINING\r\n');
                else if (/^(MAIL FROM|RCPT TO|RSET|NOOP)\b/i.test(line)) socket.write('250 OK\r\n');
                else if (/^DATA$/i.test(line)) {
                    acceptingData = true;
                    socket.write('354 End data with <CR><LF>.<CR><LF>\r\n');
                } else if (/^QUIT$/i.test(line)) {
                    socket.end('221 Bye\r\n');
                } else socket.write('250 OK\r\n');
            }
        });
    });
}

function runTool(name, args, options) {
    if (process.platform === 'win32') {
        return execFileSync(process.env.ComSpec ?? 'cmd.exe', ['/d', '/s', '/c', name, ...args], options);
    }

    return execFileSync(name, args, options);
}

async function waitForHttp(url, process, output) {
    for (let attempt = 0; attempt < 120; attempt++) {
        if (process.exitCode !== null) {
            throw new Error(`PHP server exited before setup was ready.\n${output()}`);
        }
        try {
            const response = await fetch(url);
            if (response.ok) return;
        } catch {
            // Server is still starting.
        }
        await new Promise((resolveWait) => setTimeout(resolveWait, 250));
    }
    throw new Error(`Timed out waiting for the clean-install server.\n${output()}`);
}
