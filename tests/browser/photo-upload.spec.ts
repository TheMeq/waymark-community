import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Browser, type Page } from '@playwright/test';

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
    await page.goto('/photos/upload?event=2');

    await expect(page.getByRole('heading', { name: 'Share photos' })).toBeVisible();
    await expect(page.getByLabel('Add to')).toHaveValue('event:2');
    await expect(page).toHaveScreenshot(`photo-upload-${testInfo.project.name}.png`, { fullPage: true });
    let requests = 0;
    const requestBodies: string[] = [];
    await page.route('**/photos/upload', async (route) => {
        requests++;
        requestBodies.push(route.request().postData() ?? '');
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
    expect(requestBodies).toHaveLength(3);
    for (const body of requestBodies) {
        expect(body).toContain('name="batch_size"');
        expect(body).toContain('\r\n\r\n2\r\n');
        expect(body).not.toContain('name="defer_processing"');
    }

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();
    expect(results.violations).toEqual([]);

    await page.goto('/photos/upload?event=2');
    await page.keyboard.press('Tab');
    await expect(page.getByRole('link', { name: 'Skip to main content' })).toBeFocused();
    await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
});

test('no-JavaScript upload fallback returns focus to its error summary', async ({ browser }: { browser: Browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await signIn(page);
    await page.goto('/photos/upload?event=2');
    await page.getByLabel('Photos', { exact: true }).setInputFiles({ name: 'broken.png', mimeType: 'image/png', buffer: Buffer.from('broken') });
    await Promise.all([
        page.waitForURL('**/photos/upload*'),
        page.getByRole('button', { name: 'Upload photos' }).click(),
    ]);
    const alert = page.getByRole('alert');
    await expect(alert).toContainText('Please review the photo upload.');
    await expect(alert).toBeFocused();
    await context.close();
});

test('three-file threshold defers real uploads without retries or duplicate requests', async ({ page }) => {
    await signIn(page);
    await page.goto('/photos/upload?event=2');
    const requestBodies: string[] = [];
    await page.route('**/photos/upload', async (route) => {
        requestBodies.push(route.request().postData() ?? '');
        await route.continue();
    });
    const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgYGAAAAAEAAGjChXjAAAAAElFTkSuQmCC', 'base64');
    await page.getByLabel('Photos', { exact: true }).setInputFiles([
        { name: 'deferred-one.png', mimeType: 'image/png', buffer: png },
        { name: 'deferred-two.png', mimeType: 'image/png', buffer: png },
        { name: 'deferred-three.png', mimeType: 'image/png', buffer: png },
    ]);

    await page.getByRole('button', { name: 'Upload photos' }).click();

    await expect.poll(() => page.getByText('Processing', { exact: true }).evaluateAll((elements) => elements.filter((element) => getComputedStyle(element).display !== 'none').length)).toBe(3);
    await expect(page.getByRole('button', { name: 'Retry' })).toHaveCount(0);
    expect(requestBodies).toHaveLength(3);
    for (const body of requestBodies) {
        expect(body).toContain('name="defer_processing"');
        expect(body).toContain('\r\n\r\n1\r\n');
        expect(body).toContain('name="batch_size"');
        expect(body).toContain('\r\n\r\n3\r\n');
    }
});

test('uploader can delete a pending photo and request removal of a published photo', async ({ page }) => {
    await signIn(page);
    await page.goto('/photos/upload?event=2');
    const ownPhotos = page.getByRole('region', { name: 'Your photos' });
    const pending = ownPhotos.locator('article').filter({ has: page.getByText('Pending', { exact: true }) }).first();
    const published = ownPhotos.locator('article').filter({ has: page.getByText('Approved', { exact: true }) }).first();

    await expect(pending.getByRole('button', { name: 'Delete pending photo' })).toBeVisible();
    await pending.getByRole('button', { name: 'Delete pending photo' }).click();
    await expect(page.getByText('Your pending photo has been deleted.')).toBeVisible();

    await published.getByLabel(`Removal details (optional)`).fill('Please remove this browser fixture.');
    await published.getByRole('button', { name: 'Request removal' }).click();
    await expect(page.getByText('Your removal request has been sent to the moderators.')).toBeVisible();
});
