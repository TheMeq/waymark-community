import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { waitForPageImages } from './support/images';

test('walk photos are browsable from the walk detail page', async ({ page }, testInfo) => {
    await page.goto('/walks/clifftop-circuit');
    await page.getByRole('button', { name: 'Use essential only', exact: true }).click();
    const gallery = page.getByRole('region', { name: 'Photos from this walk' });
    await expect(gallery).toBeVisible();
    await expect(gallery.locator('img')).toHaveCount(4);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    expect((await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze()).violations).toEqual([]);
    await gallery.scrollIntoViewIfNeeded();
    await waitForPageImages(page);
    await page.evaluate(() => (document.activeElement as HTMLElement | null)?.blur());
    await expect(gallery).toHaveScreenshot(`walk-gallery-${testInfo.project.name}.png`);
    const photoUrl = await gallery.locator('a').filter({ has: page.locator('img') }).first().getAttribute('href');
    await gallery.getByRole('link', { name: 'View all photos' }).click();
    await expect(page).toHaveURL(/\/photos\/events\/clifftop-circuit$/);
    await page.goto(photoUrl!);
    await expect(page.getByRole('link', { name: 'Clifftop circuit', exact: true })).toBeVisible();
});
