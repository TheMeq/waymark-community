import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

const criticalPages = ['/', '/walks', '/whats-on', '/photos'];

for (const path of criticalPages) {
    test(`${path} remains responsive and WCAG A/AA clean`, async ({ page }) => {
        const response = await page.goto(path);
        expect(response?.status()).toBe(200);

        const consent = page.getByRole('button', { name: 'Use essential only' });
        if (await consent.isVisible()) {
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
                consent.click(),
            ]);
        }

        const results = await new AxeBuilder({ page })
            .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
            .disableRules(['target-size'])
            .analyze();
        expect(results.violations).toEqual([]);

        await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
    });
}

test('critical navigation and PWA endpoints survive keyboard-only operation', async ({ page, request }, testInfo) => {
    await page.goto('/');
    const consent = page.getByRole('button', { name: 'Use essential only' });
    if (await consent.isVisible()) {
        await consent.click();
        await page.waitForLoadState('domcontentloaded');
        await page.goto('/');
    }
    const skipLink = page.getByRole('link', { name: 'Skip to main content' });
    if (testInfo.project.name.startsWith('webkit')) await skipLink.focus();
    else await page.keyboard.press('Tab');
    await expect(skipLink).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.locator('#main-content')).toBeFocused();

    expect((await request.get('/manifest.webmanifest')).status()).toBe(200);
    expect((await request.get('/service-worker.js')).status()).toBe(200);
    expect((await request.get('/offline')).status()).toBe(200);
});
