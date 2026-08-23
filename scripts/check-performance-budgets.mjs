import lighthouse from 'lighthouse';
import { launch } from 'chrome-launcher';
import { chromium } from '@playwright/test';

const baseUrl = process.env.PERFORMANCE_BASE_URL ?? 'http://127.0.0.1:8000';
const paths = (process.env.PERFORMANCE_PATHS ?? '/,/photos').split(',').map((path) => path.trim()).filter(Boolean);
const budgets = {
    stylesheet: 100 * 1024,
    script: 260 * 1024,
    image: 1024 * 1024,
    total: 1536 * 1024,
    performanceScore: 0.65,
    cumulativeLayoutShift: 0.1,
    totalBlockingTime: 500,
    largestContentfulPaint: 4000,
};

const chrome = await launch({
    chromePath: chromium.executablePath(),
    chromeFlags: ['--headless', '--no-sandbox', '--disable-dev-shm-usage'],
});

let failed = false;

try {
    for (const path of paths) {
        const url = new URL(path, baseUrl).toString();
        const result = await lighthouse(url, {
            port: chrome.port,
            logLevel: 'error',
            output: 'json',
            onlyCategories: ['performance'],
            throttlingMethod: 'provided',
        });

        if (!result) {
            throw new Error(`Lighthouse did not return a result for ${url}.`);
        }

        const { audits, categories } = result.lhr;
        const requests = audits['network-requests'].details?.items ?? [];
        const bytes = (type) => requests.filter((request) => request.resourceType === type).reduce((total, request) => total + (request.transferSize ?? 0), 0);
        const measurements = {
            stylesheet: bytes('Stylesheet'),
            script: bytes('Script'),
            image: bytes('Image'),
            total: requests.reduce((total, request) => total + (request.transferSize ?? 0), 0),
            performanceScore: categories.performance.score ?? 0,
            cumulativeLayoutShift: audits['cumulative-layout-shift'].numericValue ?? Number.POSITIVE_INFINITY,
            totalBlockingTime: audits['total-blocking-time'].numericValue ?? Number.POSITIVE_INFINITY,
            largestContentfulPaint: audits['largest-contentful-paint'].numericValue ?? Number.POSITIVE_INFINITY,
        };

        const upperBoundMetrics = [
            'stylesheet',
            'script',
            'image',
            'total',
            'cumulativeLayoutShift',
            'totalBlockingTime',
            'largestContentfulPaint',
        ];
        const failures = upperBoundMetrics
            .filter((name) => measurements[name] > budgets[name])
            .map((name) => [name, measurements[name]]);
        if (measurements.performanceScore < budgets.performanceScore) {
            failures.push(['performanceScore', measurements.performanceScore]);
        }

        console.log(JSON.stringify({ url, measurements, budgets, passed: failures.length === 0 }));
        failed ||= failures.length > 0;
    }
} finally {
    try {
        await chrome.kill();
    } catch (error) {
        console.warn(`Chrome cleanup warning: ${error.message}`);
    }
}

if (failed) {
    process.exitCode = 1;
}
