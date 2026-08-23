import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

test('public photo reporting form is usable at every review viewport', async ({ page }, testInfo) => {
    await page.goto('/photos/22/report');
    await expect(page.getByRole('heading', { name: 'Report a photo' })).toBeVisible();
    await page.waitForLoadState('networkidle');
    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();
    expect(results.violations).toEqual([]);

    await page.keyboard.press('Tab');
    await expect(page.getByRole('link', { name: 'Skip to main content' })).toBeFocused();
    await page.getByLabel('Reason').focus();
    await page.keyboard.press('Tab');
    await expect(page.getByLabel('Details')).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(page.getByLabel('Contact email (optional)')).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(page.getByRole('button', { name: 'Send report' })).toBeFocused();

    await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
    await page.evaluate(() => { document.documentElement.style.fontSize = ''; });
    await page.getByLabel('Reason').selectOption('privacy');
    await page.getByLabel('Details').fill('Please review this photo.');
    const [response] = await Promise.all([
        page.waitForResponse((candidate) => candidate.request().method() === 'POST' && new URL(candidate.url()).pathname === '/photos/22/report'),
        page.waitForNavigation({ waitUntil: 'load' }),
        page.getByRole('button', { name: 'Send report' }).press('Enter'),
    ]);
    expect(response.status()).toBe(302);
    await expect(page.getByText('Thank you. Your report has been received.')).toBeVisible();
    await expect(page).toHaveScreenshot(`photo-report-${testInfo.project.name}.png`, { fullPage: true });
});
