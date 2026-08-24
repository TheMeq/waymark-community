import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';

test('guided installer is accessible, responsive, and keyboard operable', async ({ page }, testInfo) => {
    await page.goto('/setup');

    await expect(page.getByRole('heading', { name: 'Set up Waymark Community' })).toBeVisible();
    await expect(page.getByText('Step 1 of 11')).toBeVisible();

    const begin = page.getByRole('button', { name: 'Begin setup' });
    await begin.focus();
    await expect(begin).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/setup\/server-checks$/);
    await expect(page.getByRole('heading', { name: 'Server checks' })).toBeVisible();

    const dimensions = await page.getByRole('button', { name: 'Continue' }).evaluate((element) => {
        const style = getComputedStyle(element);
        const rect = element.getBoundingClientRect();

        return { height: rect.height, visible: style.visibility !== 'hidden' && style.display !== 'none' };
    });
    expect(dimensions.visible).toBe(true);
    expect(dimensions.height).toBeGreaterThanOrEqual(44);

    const accessibility = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();
    expect(accessibility.violations).toEqual([]);

    await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
    expect(await page.evaluate(() => document.documentElement.scrollWidth))
        .toBeLessThanOrEqual(await page.evaluate(() => document.documentElement.clientWidth));
    await page.evaluate(() => { document.documentElement.style.fontSize = ''; });

    const screenshotDirectory = resolve('test-results/phase9/screenshots');
    await mkdir(screenshotDirectory, { recursive: true });
    await page.screenshot({
        path: resolve(screenshotDirectory, `installer-server-checks-${testInfo.project.name}.png`),
        fullPage: true,
    });
});
