import { expect, test } from '@playwright/test';

for (const detail of [
    { path: '/walks/ridge-and-reservoir', heading: 'Ridge and reservoir' },
    { path: '/documents/walking-guide', heading: 'Walking guide' },
]) {
    test(`${detail.heading} has a print-friendly detail layout`, async ({ page }) => {
        await page.emulateMedia({ media: 'print' });
        await page.goto(detail.path);

        await expect(page.getByRole('heading', { level: 1, name: detail.heading })).toBeVisible();
        await expect(page.locator('body > header')).toBeHidden();
        await expect(page.locator('body > footer')).toBeHidden();

        const presentation = await page.locator('[data-print-detail]').evaluate((element) => {
            const style = getComputedStyle(element);

            return {
                background: style.backgroundColor,
                boxShadow: style.boxShadow,
                width: document.documentElement.scrollWidth - document.documentElement.clientWidth,
            };
        });

        expect(presentation.background).toBe('rgb(255, 255, 255)');
        expect(presentation.boxShadow).toBe('none');
        expect(presentation.width).toBeLessThanOrEqual(0);
    });
}
