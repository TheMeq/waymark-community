import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';
import { waitForPageImages } from './support/images';

async function expectNoHorizontalOverflow(page: Page) {
    const width = await page.evaluate(() => ({
        client: document.documentElement.clientWidth,
        scroll: document.documentElement.scrollWidth,
    }));

    expect(width.scroll).toBeLessThanOrEqual(width.client);
}

test('published walks have a browseable list and public detail page', async ({ page }) => {
    await page.goto('/walks');

    await expect(page.getByRole('heading', { name: 'Upcoming walks' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Ridge and reservoir' })).toBeVisible();
    await page.getByRole('link', { name: 'Ridge and reservoir' }).click();

    await expect(page.getByRole('heading', { name: 'Ridge and reservoir' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Meeting point' })).toBeVisible();
});

test('public walk list and detail preserve the approved responsive rhythm', async ({ page }, testInfo) => {
    await page.goto('/walks');
    await expect(page.getByRole('link', { name: 'Ridge and reservoir' })).toBeVisible();
    await waitForPageImages(page);
    await expect(page).toHaveScreenshot(`walk-list-${testInfo.project.name}.png`, { fullPage: true });

    await page.getByRole('link', { name: 'Ridge and reservoir' }).click();
    await expect(page.getByRole('heading', { name: 'Ridge and reservoir' })).toBeVisible();
    await waitForPageImages(page);
    await expect(page).toHaveScreenshot(`walk-detail-${testInfo.project.name}.png`, { fullPage: true });
});

test('walk list and detail have no detectable WCAG A or AA violations, support keyboard filtering, and remain within the viewport at 200 percent text size', async ({ page }) => {
    await page.goto('/walks');

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();

    expect(results.violations).toEqual([]);

    await page.getByLabel('Location').focus();
    await page.keyboard.press('Tab');
    await expect(page.getByLabel('Time')).toBeFocused();

    await page.evaluate(() => {
        document.documentElement.style.fontSize = '200%';
    });

    await expectNoHorizontalOverflow(page);

    await page.goto('/walks/ridge-and-reservoir');

    const detailResults = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();

    expect(detailResults.violations).toEqual([]);

    await page.evaluate(() => {
        document.documentElement.style.fontSize = '200%';
    });

    await expectNoHorizontalOverflow(page);
});
