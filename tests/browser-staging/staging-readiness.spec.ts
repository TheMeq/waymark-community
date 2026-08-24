import { expect, test } from '@playwright/test';
import { waitForPageImages } from '../browser/support/images';

test('representative staging homepage preserves the approved responsive presentation', async ({ page, request }) => {
    const response = await page.goto('/');
    expect(response?.status()).toBe(200);
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex,nofollow');
    await expect(page.locator('script[src*="plausible"], script[src*="googletagmanager"]')).toHaveCount(0);

    const consent = page.getByRole('button', { name: 'Use essential only' });
    if (await consent.isVisible()) {
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'load' }),
            consent.click(),
        ]);
    }
    await waitForPageImages(page);

    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
    await expect(page).toHaveScreenshot('staging-home.png', { fullPage: true });

    const robots = await request.get('/robots.txt');
    expect(robots.status()).toBe(200);
    expect(await robots.text()).toContain('Disallow: /');
    expect((await request.get('/sitemap.xml')).status()).toBe(404);
});
