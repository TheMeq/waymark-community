import { defineConfig, devices } from '@playwright/test';
import { resolve } from 'node:path';

const database = resolve('test-results/playwright-release.sqlite');
const phpScan = process.env.PHP_INI_SCAN_DIR === undefined ? {} : { PHP_INI_SCAN_DIR: process.env.PHP_INI_SCAN_DIR };

export default defineConfig({
    testDir: './tests/browser-release',
    outputDir: 'test-results/playwright-release',
    fullyParallel: true,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 2 : 0,
    workers: 1,
    reporter: [['list']],
    use: {
        baseURL: 'http://127.0.0.1:8050',
        trace: 'on-first-retry',
    },
    projects: [
        { name: 'chromium-desktop', use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } } },
        { name: 'firefox-desktop', use: { ...devices['Desktop Firefox'], viewport: { width: 1440, height: 900 } } },
        { name: 'webkit-desktop', use: { ...devices['Desktop Safari'], viewport: { width: 1440, height: 900 } } },
        { name: 'webkit-mobile', use: { ...devices['iPhone 13'] } },
    ],
    webServer: {
        command: 'node tests/browser/prepare-browser-db.mjs && php artisan serve --host=127.0.0.1 --port=8050',
        env: {
            APP_ENV: 'browser-testing',
            ...phpScan,
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: database,
            CACHE_STORE: 'array',
            SESSION_DRIVER: 'file',
            QUEUE_CONNECTION: 'sync',
            MAIL_MAILER: 'log',
            WAYMARK_MAIL_CONFIGURED: 'true',
            WAYMARK_TEST_NOW: '2026-08-20 12:00:00',
        },
        url: 'http://127.0.0.1:8050',
        reuseExistingServer: false,
        timeout: 120_000,
    },
});
