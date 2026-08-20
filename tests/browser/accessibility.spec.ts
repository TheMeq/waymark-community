import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

test('homepage has no detectable WCAG A or AA violations', async ({ page }) => {
    await page.goto('/');

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();

    expect(results.violations).toEqual([]);
});

test('homepage does not create document overflow at 200 percent text size', async ({ page }) => {
    await page.goto('/');
    await page.evaluate(() => {
        document.documentElement.style.fontSize = '200%';
    });

    const width = await page.evaluate(() => ({
        client: document.documentElement.clientWidth,
        scroll: document.documentElement.scrollWidth,
    }));

    expect(width.scroll).toBeLessThanOrEqual(width.client);
});
