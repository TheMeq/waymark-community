import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

async function expectAccessibleAtTwoHundredPercent(page: Page) {
    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();
    expect(results.violations).toEqual([]);

    await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
    const width = await page.evaluate(() => ({
        client: document.documentElement.clientWidth,
        scroll: document.documentElement.scrollWidth,
    }));
    expect(width.scroll).toBeLessThanOrEqual(width.client);
}

for (const publicPage of [
    { path: '/search?q=walk', heading: 'Search' },
    { path: '/cookie-settings', heading: 'Cookie settings' },
    { path: '/contact', heading: 'Contact' },
    { path: '/missing-phase-eight-page', heading: 'We could not find that page' },
]) {
    test(`${publicPage.heading} passes the Phase 8 public accessibility gate`, async ({ page }) => {
        await page.goto(publicPage.path);
        await expect(page.getByRole('heading', { level: 1, name: publicPage.heading })).toBeVisible();
        await expectAccessibleAtTwoHundredPercent(page);
    });
}

test('cookie choices expose keyboard focus and mobile-sized primary controls', async ({ page }, testInfo) => {
    await page.goto('/');
    const banner = page.getByRole('complementary', { name: 'Cookie choices' });
    await expect(banner).toBeVisible();

    const essential = banner.getByRole('button', { name: 'Use essential only' });
    await essential.focus();
    await expect(essential).toBeFocused();

    if (testInfo.project.name === 'mobile') {
        for (const button of [essential, banner.getByRole('button', { name: 'Allow analytics' })]) {
            const box = await button.boundingBox();
            expect(box?.height).toBeGreaterThanOrEqual(44);
        }
    }
});

test('reduced-motion preference suppresses decorative transition duration', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/');
    const duration = await page.getByRole('link', { name: 'Upcoming walks', exact: true }).first().evaluate((element) => getComputedStyle(element).transitionDuration);
    expect(Number.parseFloat(duration)).toBeLessThanOrEqual(0.00001);
});
