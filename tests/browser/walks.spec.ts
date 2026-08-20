import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

test('published walks have a browseable list and public detail page', async ({ page }) => {
    await page.goto('/walks');

    await expect(page.getByRole('heading', { name: 'Upcoming walks' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Ridge and reservoir' })).toBeVisible();
    await page.getByRole('link', { name: 'Ridge and reservoir' }).click();

    await expect(page.getByRole('heading', { name: 'Ridge and reservoir' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Meeting point' })).toBeVisible();
});

test('walk pages have no detectable WCAG A or AA violations and remain within the viewport at 200 percent text size', async ({ page }) => {
    await page.goto('/walks');

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();

    expect(results.violations).toEqual([]);

    await page.evaluate(() => {
        document.documentElement.style.fontSize = '200%';
    });

    const width = await page.evaluate(() => ({
        client: document.documentElement.clientWidth,
        scroll: document.documentElement.scrollWidth,
    }));

    expect(width.scroll).toBeLessThanOrEqual(width.client);
});
