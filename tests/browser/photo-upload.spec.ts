import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

async function signIn(page: Page) {
    await page.goto('/login');
    await page.getByLabel('Email address').fill('morgan.leader@example.test');
    await page.getByLabel('Password').fill('password');
    await Promise.all([
        page.waitForURL('**/new-here'),
        page.getByRole('button', { name: 'Sign in' }).click(),
    ]);
}

test('photo upload keeps each failed file available for an accessible retry', async ({ page }, testInfo) => {
    await signIn(page);
    await page.goto('/photos/upload?event=1');

    await expect(page.getByRole('heading', { name: 'Share photos' })).toBeVisible();
    await expect(page.getByLabel('Add to')).toHaveValue('event:1');
    await expect(page).toHaveScreenshot(`photo-upload-${testInfo.project.name}.png`, { fullPage: true });
    let requests = 0;
    await page.route('**/photos/upload', async (route) => {
        requests++;
        if (requests === 2) {
            await route.fulfill({ status: 503, contentType: 'application/json', body: '{}' });
            return;
        }
        await route.continue();
    });
    const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgYGAAAAAEAAGjChXjAAAAAElFTkSuQmCC', 'base64');
    await page.getByLabel('Photos', { exact: true }).setInputFiles([
        { name: 'first.png', mimeType: 'image/png', buffer: png },
        { name: 'retry.png', mimeType: 'image/png', buffer: png },
    ]);
    await expect(page.getByText('first.png')).toBeVisible();
    await expect(page.getByText('Ready')).toHaveCount(2);
    await page.getByRole('button', { name: 'Upload photos' }).click();
    await expect(page.getByRole('button', { name: 'Retry' })).toBeVisible();
    await expect(page.getByText('first.png').locator('..')).toContainText('Submitted');
    expect(requests).toBe(2);
    await page.getByRole('button', { name: 'Retry' }).focus();
    await expect(page.getByRole('button', { name: 'Retry' })).toBeFocused();
    await page.getByRole('button', { name: 'Retry' }).click();
    await expect(page.getByText('retry.png').locator('..')).toContainText('Submitted');
    expect(requests).toBe(3);

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();
    expect(results.violations).toEqual([]);

    await page.goto('/photos/upload?event=1');
    await page.keyboard.press('Tab');
    await expect(page.getByRole('link', { name: 'Skip to main content' })).toBeFocused();
    await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
});
