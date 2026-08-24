import { defineConfig } from '@playwright/test';
import { resolve } from 'node:path';

const phpIniScanEnvironment = process.env.PHP_INI_SCAN_DIR === undefined
    ? {}
    : { PHP_INI_SCAN_DIR: process.env.PHP_INI_SCAN_DIR };

export default defineConfig({
    testDir: './tests/browser-phase9',
    outputDir: 'test-results/playwright-installer',
    fullyParallel: true,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 1 : undefined,
    reporter: [['list']],
    use: {
        baseURL: 'http://127.0.0.1:8001',
        trace: 'on-first-retry',
    },
    projects: [
        { name: 'desktop', use: { viewport: { width: 1440, height: 900 } } },
        { name: 'tablet', use: { viewport: { width: 834, height: 1112 } } },
        { name: 'mobile', use: { viewport: { width: 390, height: 844 } } },
    ],
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8001',
        env: {
            APP_ENV: 'production',
            APP_DEBUG: 'false',
            WAYMARK_INSTALLED: 'false',
            WAYMARK_INSTALLATION_LOCK: resolve('test-results/installer-installation.lock'),
            WAYMARK_CRON_AVAILABLE: 'false',
            CACHE_STORE: 'array',
            SESSION_DRIVER: 'file',
            ...phpIniScanEnvironment,
        },
        url: 'http://127.0.0.1:8001/setup',
        reuseExistingServer: false,
        timeout: 120_000,
    },
});
