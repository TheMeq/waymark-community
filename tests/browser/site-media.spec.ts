import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

async function signIn(page: Page) {
    await page.goto('/login');
    await page.getByLabel('Email address').fill('media.admin@example.test');
    await page.getByLabel('Password').fill('password');
    await Promise.all([page.waitForURL('**/new-here'), page.getByRole('button', { name: 'Sign in' }).click()]);
}

test('site media library is accessible and promotes deliberate approved-photo copies', async ({ page }) => {
    await signIn(page);
    await page.goto('/admin/media-library');
    await expect(page.getByRole('heading', { name: 'Media library' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Site media', exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Promote an approved gallery photo' })).toBeVisible();
    await page.getByRole('button', { name: 'Promote' }).first().click();
    await expect(page.getByText('Photo promoted to the media library')).toBeVisible();
    await page.getByRole('button', { name: 'Edit' }).first().click();
    await page.getByLabel('Alt text').last().fill('A deliberately promoted walking memory');
    await page.getByRole('button', { name: 'Save details' }).click();
    await expect(page.getByText('Media details saved')).toBeVisible();
    await page.getByRole('button', { name: 'Check repair state' }).first().focus();
    await page.keyboard.press('Enter');
    await expect(page.getByText('Media health checked')).toBeVisible();
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Remove' }).first().click();
    await expect(page.getByText('Media removed')).toBeVisible();
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
    expect(results.violations).toEqual([]);
    await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(await page.evaluate(() => document.documentElement.clientWidth));
});
