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

    const overflowing = await page.evaluate(() => Array.from(document.querySelectorAll('body *'))
        .filter((element) => {
            const bounds = element.getBoundingClientRect();

            return bounds.right > document.documentElement.clientWidth + 1
                || bounds.left + element.scrollWidth > document.documentElement.clientWidth + 1;
        })
        .slice(0, 10)
        .map((element) => ({
            tag: element.tagName,
            className: element.className,
            text: element.textContent?.trim().slice(0, 80),
            right: element.getBoundingClientRect().right,
            clientWidth: element.clientWidth,
            scrollWidth: element.scrollWidth,
        })));

    expect(width.scroll, JSON.stringify(overflowing)).toBeLessThanOrEqual(width.client);
});
