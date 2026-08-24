import { expect, test } from '@playwright/test';
import { spawn, type ChildProcess } from 'node:child_process';
import { createServer } from 'node:net';
import { resolve } from 'node:path';

async function availablePort(): Promise<number> {
    return await new Promise((resolvePort, reject) => {
        const server = createServer();

        server.once('error', reject);
        server.listen(0, '127.0.0.1', () => {
            const address = server.address();

            if (typeof address !== 'object' || address === null) {
                server.close();
                reject(new Error('Unable to allocate a staging server port.'));
                return;
            }

            server.close((error) => error ? reject(error) : resolvePort(address.port));
        });
    });
}

async function waitForRobots(url: string, process: ChildProcess): Promise<Response> {
    for (let attempt = 0; attempt < 50; attempt++) {
        if (process.exitCode !== null) {
            throw new Error(`Staging server exited with code ${process.exitCode}.`);
        }

        try {
            return await fetch(url);
        } catch {
            await new Promise((resolveWait) => setTimeout(resolveWait, 100));
        }
    }

    throw new Error('Staging server did not become ready.');
}

test('the environment-aware robots route is authoritative through the public document root', async ({ request }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'One real-server assertion covers the shared public document root.');

    const production = await request.get('/robots.txt');
    const productionBody = await production.text();

    expect(production.ok()).toBe(true);
    expect(productionBody).toContain('Allow: /');
    expect(productionBody).toMatch(/^Sitemap: https?:\/\/[^\s]+\/sitemap\.xml$/m);
    expect(productionBody).not.toContain('Disallow: /');

    const port = await availablePort();
    const database = resolve('test-results/playwright-browser.sqlite');
    const phpExtensionScanDir = process.env.PHP_INI_SCAN_DIR;
    const server = spawn('php', ['artisan', 'serve', '--no-reload', '--host=127.0.0.1', `--port=${port}`], {
        cwd: process.cwd(),
        env: {
            ...process.env,
            APP_ENV: 'staging',
            WAYMARK_STAGING: 'true',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: database,
            CACHE_STORE: 'array',
            SESSION_DRIVER: 'array',
            ...(phpExtensionScanDir === undefined ? {} : { PHP_INI_SCAN_DIR: phpExtensionScanDir }),
        },
        stdio: 'ignore',
    });

    try {
        const staging = await waitForRobots(`http://127.0.0.1:${port}/robots.txt`, server);

        expect(staging.ok).toBe(true);
        expect(await staging.text()).toBe('User-agent: *\nDisallow: /\n');
    } finally {
        server.kill();
    }
});
