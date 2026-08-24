import { defineConfig } from '@playwright/test';
import { resolve } from 'node:path';

const browserDatabase = resolve('test-results/playwright-browser.sqlite');
const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000';
const reuseDedicatedServer = process.env.PLAYWRIGHT_REUSE_DEDICATED_SERVER === '1'
    && baseURL === 'http://127.0.0.1:8000'
    && process.env.PLAYWRIGHT_DEDICATED_TEST_DATABASE === browserDatabase;
const phpIniScanEnvironment = process.env.PHP_INI_SCAN_DIR === undefined
    ? {}
    : { PHP_INI_SCAN_DIR: process.env.PHP_INI_SCAN_DIR };

export default defineConfig({
    testDir: './tests/browser',
    outputDir: 'test-results/playwright',
    snapshotPathTemplate: '{testDir}/../visual/baselines/{arg}{ext}',
    fullyParallel: true,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 1 : undefined,
    reporter: [
        ['list'],
        ['html', { outputFolder: 'playwright-report', open: 'never' }],
    ],
    use: {
        baseURL,
        trace: 'on-first-retry',
    },
    expect: {
        toHaveScreenshot: {
            animations: 'disabled',
            maxDiffPixelRatio: 0.015,
        },
    },
    projects: [
        {
            name: 'desktop',
            use: { viewport: { width: 1440, height: 900 } },
        },
        {
            name: 'tablet',
            use: { viewport: { width: 834, height: 1112 } },
        },
        {
            name: 'mobile',
            use: { viewport: { width: 390, height: 844 } },
        },
    ],
    webServer: {
        command: 'node tests/browser/prepare-browser-db.mjs && php artisan serve --host=127.0.0.1 --port=8000',
        env: {
            APP_ENV: 'browser-testing',
            ...phpIniScanEnvironment,
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: browserDatabase,
            CACHE_STORE: 'array',
            SESSION_DRIVER: 'file',
            SESSION_EXPIRE_ON_CLOSE: 'true',
            QUEUE_CONNECTION: 'sync',
            GALLERY_DEFERRED_PROCESSING_MANUAL_FALLBACK: 'false',
            GALLERY_PUBLIC_PER_PAGE: '6',
            MAIL_MAILER: 'log',
            WAYMARK_TEST_NOW: '2026-08-20 12:00:00',
        },
        url: 'http://127.0.0.1:8000',
        reuseExistingServer: reuseDedicatedServer,
        timeout: 120_000,
    },
});
