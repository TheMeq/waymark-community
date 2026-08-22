import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { waitForPageImages } from './support/images';

const holidayPath = '/weekends/coast-and-moor-long-weekend';
const galleryPath = '/photos/holidays/coast-and-moor-long-weekend';

async function expectAccessibleAtTwoHundredPercent(page: import('@playwright/test').Page) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
    expect(results.violations).toEqual([]);
    await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
    await page.evaluate(() => { document.documentElement.style.fontSize = ''; });
}

test('holiday memories are visible from the detail page and retain their child context', async ({ page }, testInfo) => {
    await page.goto(holidayPath);
    await expect(page.getByRole('heading', { name: 'Holiday memories' })).toBeVisible();
    const childMemory = page.getByAltText('Holiday memory 6');
    await expect(childMemory).toHaveAttribute('loading', 'lazy');
    await page.getByRole('link', { name: 'Clifftop circuit' }).last().focus();
    await expect(page.getByRole('link', { name: 'Clifftop circuit' }).last()).toBeFocused();
    await expectAccessibleAtTwoHundredPercent(page);
    await waitForPageImages(page);
    await expect(page).toHaveScreenshot(`holiday-detail-${testInfo.project.name}.png`, { fullPage: true });
});

test('holiday aggregate gallery is keyboard-accessible and preserves the linked child detail', async ({ page }, testInfo) => {
    await page.goto(galleryPath);
    await expect(page.getByRole('heading', { name: 'Coast and moor long weekend' })).toBeVisible();
    const childPhoto = page.locator('[data-gallery-photo]').filter({ has: page.getByAltText('Holiday memory 6') });
    await expect(childPhoto.locator('img')).toHaveAttribute('loading', 'lazy');
    await childPhoto.focus();
    await expect(childPhoto).toBeFocused();
    const photoDetail = await childPhoto.getAttribute('href');
    await page.goto(photoDetail!);
    await expect(page.getByRole('link', { name: 'Clifftop circuit' })).toHaveAttribute('href', /\/photos\/events\/clifftop-circuit$/);
    await expectAccessibleAtTwoHundredPercent(page);
    await page.goto(galleryPath);
    await waitForPageImages(page);
    await expect(page).toHaveScreenshot(`holiday-gallery-${testInfo.project.name}.png`, { fullPage: true });
});

test('homepage uses the featured holiday memory before a six-photo real-media band', async ({ page }) => {
    await page.goto('/');
    const memories = page.locator('[data-homepage-memory]');
    await expect(memories).toHaveCount(6);
    await expect(memories.first().locator('img')).toHaveAttribute('alt', 'Clifftop featured memory');
    expect(await memories.locator('img').evaluateAll((images) => images.every((image) => image.getAttribute('loading') === 'lazy'))).toBe(true);
    await expectAccessibleAtTwoHundredPercent(page);
});
