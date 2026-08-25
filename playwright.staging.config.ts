import { defineConfig } from '@playwright/test';
import { resolve } from 'node:path';

const database = resolve('test-results/playwright-staging.sqlite');
const phpScan = process.env.PHP_INI_SCAN_DIR === undefined ? {} : { PHP_INI_SCAN_DIR: process.env.PHP_INI_SCAN_DIR };

export default defineConfig({
    testDir: './tests/browser-staging',
    outputDir: 'test-results/playwright-staging',
    fullyParallel: false,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 2 : 0,
    workers: 1,
    reporter: [['list']],
    expect: { toHaveScreenshot: { animations: 'disabled', maxDiffPixelRatio: 0.01 } },
    use: {
        baseURL: 'http://127.0.0.1:8051',
        trace: 'on-first-retry',
    },
    projects: [
        { name: 'desktop', use: { viewport: { width: 1440, height: 900 } } },
        { name: 'tablet', use: { viewport: { width: 834, height: 1112 } } },
        { name: 'mobile', use: { viewport: { width: 390, height: 844 } } },
    ],
    webServer: {
        command: 'node tests/browser/prepare-browser-db.mjs && php artisan serve --host=127.0.0.1 --port=8051',
        env: {
            APP_ENV: 'browser-testing',
            ...phpScan,
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: database,
            CACHE_STORE: 'array',
            SESSION_DRIVER: 'file',
            QUEUE_CONNECTION: 'sync',
            MAIL_MAILER: 'array',
            WAYMARK_MAIL_CONFIGURED: 'true',
            WAYMARK_STAGING: 'true',
            WAYMARK_TEST_NOW: '2026-08-20 12:00:00',
        },
        url: 'http://127.0.0.1:8051',
        reuseExistingServer: false,
        timeout: 120_000,
    },
});
