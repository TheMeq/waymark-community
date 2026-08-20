import { defineConfig } from '@playwright/test';

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
        baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000',
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
        command: 'php artisan serve --host=127.0.0.1 --port=8000',
        url: 'http://127.0.0.1:8000',
        reuseExistingServer: !process.env.CI,
        timeout: 120_000,
    },
});
