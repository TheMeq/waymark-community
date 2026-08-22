import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

test('public shell exposes the manifest and safely controls public navigation', async ({ page }, testInfo) => {
    await page.goto('/');
    await page.evaluate(async () => {
        for (const registration of await navigator.serviceWorker.getRegistrations()) {
            await registration.unregister();
        }
        for (const key of await caches.keys()) {
            await caches.delete(key);
        }
    });
    await page.reload();

    const manifest = await page.locator('link[rel="manifest"]').getAttribute('href');
    expect(manifest).toBe('/manifest.webmanifest');
    await expect.poll(() => page.evaluate(() => navigator.serviceWorker?.controller?.scriptURL ?? '')).toContain('/service-worker.js');

    await page.context().setOffline(true);
    await page.goto('/walks');
    await expect(page.locator('body')).toHaveAttribute('data-pwa-offline', '');
    await expect(page.getByRole('heading', { name: "You're offline" })).toBeVisible();
    await expect(page).toHaveScreenshot(`pwa-offline-${testInfo.project.name}.png`, { fullPage: true });

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();
    expect(results.violations).toEqual([]);
    await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);

    await page.context().setOffline(false);
    await page.goto('/walks');
    await expect(page.getByRole('heading', { name: 'Walks' })).toBeVisible();
});

test('ordinary navigation remains available when service worker APIs are unsupported', async ({ browser }) => {
    const context = await browser.newContext();
    await context.addInitScript(() => {
        Object.defineProperty(navigator, 'serviceWorker', { configurable: true, value: undefined });
    });
    const page = await context.newPage();

    await page.goto('/');
    await expect(page.getByRole('heading', { name: /Great walks/i })).toBeVisible();
    await page.goto('/photos/upload');
    await expect(page).toHaveURL(/\/login$/);

    await context.close();
});
