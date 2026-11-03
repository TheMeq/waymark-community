import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

test('footer privacy link opens useful website privacy information', async ({ page }) => {
    await page.goto('/walks');
    await page.getByRole('button', { name: 'Use essential only', exact: true }).click();
    await page.getByRole('contentinfo').getByRole('link', { name: 'Privacy', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Privacy', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'cookie settings', exact: true }).first()).toBeVisible();
    await expect(page.getByRole('link', { name: 'account privacy settings', exact: true })).toHaveAttribute('href', /\/account\/privacy$/);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    expect((await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze()).violations).toEqual([]);
});
