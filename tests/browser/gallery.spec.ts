import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

test('public gallery retains an accessible no-JS detail flow and enhanced lightbox', async ({ page }, testInfo) => {
    await page.goto('/photos');
    await expect(page.getByRole('heading', { name: 'Gallery' })).toBeVisible();
    const firstPhoto = page.locator('[data-gallery-photo]').first();
    await expect(firstPhoto.locator('img')).toHaveAttribute('loading', 'lazy');

    const noScriptHref = await firstPhoto.getAttribute('href');
    expect(noScriptHref).toBeTruthy();
    await page.goto(noScriptHref!);
    await expect(page.getByRole('link', { name: 'Report photo' })).toBeVisible();
    const detailResults = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
    expect(detailResults.violations).toEqual([]);
    await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
    await page.evaluate(() => { document.documentElement.style.fontSize = ''; });
    await expect(page).toHaveScreenshot(`gallery-detail-${testInfo.project.name}.png`, { fullPage: true });

    await page.goto('/photos');
    await firstPhoto.click();
    const dialog = page.getByRole('dialog', { name: 'Photo viewer' });
    await expect(dialog).toBeVisible();
    await expect(dialog).toHaveScreenshot(`gallery-lightbox-${testInfo.project.name}.png`);
    const firstDialogImage = await dialog.locator('img').getAttribute('src');
    await expect(dialog.getByRole('button', { name: 'Previous' })).toBeDisabled();
    await expect(dialog.locator('img')).toHaveAttribute('src', firstDialogImage!);
    const closeButton = dialog.getByRole('button', { name: 'Close photo' });
    await closeButton.focus();
    await page.keyboard.press('Shift+Tab');
    expect(await dialog.evaluate((element) => element.contains(document.activeElement))).toBe(true);
    await page.keyboard.press('ArrowRight');
    const activePhoto = page.locator('[data-gallery-photo]').nth(1);
    await dialog.dispatchEvent('touchstart', { changedTouches: [{ identifier: 1, screenX: 220 }] });
    await dialog.dispatchEvent('touchend', { changedTouches: [{ identifier: 1, screenX: 120 }] });
    await expect(dialog).toBeVisible();
    const dialogResults = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
    expect(dialogResults.violations).toEqual([]);
    await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
    await page.evaluate(() => { document.documentElement.style.fontSize = ''; });
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(activePhoto).toBeFocused();

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();
    expect(results.violations).toEqual([]);

    await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
    await page.evaluate(() => { document.documentElement.style.fontSize = ''; });
    await expect(page).toHaveScreenshot(`gallery-index-${testInfo.project.name}.png`, { fullPage: true });
});

test('gallery context page is responsive and accessible', async ({ page }, testInfo) => {
    await page.goto('/photos');
    const context = page.getByRole('region', { name: /explore albums/i }).getByRole('link').first();
    await context.click();
    await expect(page.getByRole('heading')).toBeVisible();
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
    expect(results.violations).toEqual([]);
    await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
    await page.evaluate(() => { document.documentElement.style.fontSize = ''; });
    await expect(page).toHaveScreenshot(`gallery-context-${testInfo.project.name}.png`, { fullPage: true });
});

test('gallery pagination has a normal load-more link without duplicate photos', async ({ page }) => {
    await page.goto('/photos');
    const firstPageIds = await page.locator('[data-gallery-photo]').evaluateAll((links) => links.map((link) => link.getAttribute('href')));
    const loadMore = page.getByRole('link', { name: 'Load more' });
    const href = await loadMore.getAttribute('href');
    expect(href).toContain('page=2');
    await loadMore.click();
    await expect(page).toHaveURL(/page=2/);
    const nextPageIds = await page.locator('[data-gallery-photo]').evaluateAll((links) => links.map((link) => link.getAttribute('href')));
    expect(nextPageIds.some((id) => firstPageIds.includes(id))).toBe(false);
});
