import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

async function signIn(page: Page, projectName: string) {
    const email = `photo-upload-${projectName}@example.test`;

    await page.goto('/register');
    await page.getByLabel('Your name').fill('Taylor Walker');
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByLabel('Confirm password').fill('password');
    await Promise.all([
        page.waitForURL('**/new-here'),
        page.getByRole('button', { name: 'Create account' }).click(),
    ]);
}

test('photo upload keeps each failed file available for an accessible retry', async ({ page }, testInfo) => {
    await signIn(page, testInfo.project.name);
    await page.goto('/photos/upload?event=1');

    await expect(page.getByRole('heading', { name: 'Share photos' })).toBeVisible();
    await expect(page.getByLabel('Add to')).toHaveValue('event:1');
    await page.getByLabel('Photos', { exact: true }).setInputFiles({
        name: 'ridge.png',
        mimeType: 'image/png',
        buffer: Buffer.from('not a raster'),
    });
    await expect(page.getByText('ridge.png')).toBeVisible();
    await expect(page.getByText('Ready')).toBeVisible();
    await page.getByRole('checkbox').check();

    await page.getByRole('button', { name: 'Upload photos' }).click();
    await expect(page.getByRole('button', { name: 'Retry' })).toBeVisible();
    await page.getByRole('button', { name: 'Retry' }).click();
    await expect(page.getByRole('button', { name: 'Retry' })).toBeVisible();

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();
    expect(results.violations).toEqual([]);

    await page.goto('/photos/upload?event=1');
    await page.keyboard.press('Tab');
    await expect(page.getByRole('link', { name: 'Skip to main content' })).toBeFocused();
    await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
    await expect(page).toHaveScreenshot(`photo-upload-${testInfo.project.name}.png`, { fullPage: true });
});
