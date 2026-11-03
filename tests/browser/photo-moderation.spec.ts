import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

async function signIn(page: Page) {
    await page.goto('/login');
    await page.getByLabel('Email address').fill('photo.moderator@example.test');
    await page.getByLabel('Password').fill('password');
    await Promise.all([
        page.waitForURL('**/new-here'),
        page.getByRole('button', { name: 'Sign in' }).click(),
    ]);
}

test('authorised moderation workflow is operable and accessible at every review viewport', async ({ page }, testInfo) => {
    test.setTimeout(60_000);

    await signIn(page);
    await page.goto('/admin/photo-moderation');

    await expect(page.getByRole('heading', { name: 'Photo moderation' })).toBeVisible();
    await expect(page.getByText('Browser moderation photo 1', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Approve' }).first()).toBeVisible();
    await expect(page.getByRole('button', { name: 'Reject' }).first()).toBeVisible();
    await expect(page.getByRole('button', { name: 'Rotate' }).first()).toBeVisible();
    await expect(page.getByRole('button', { name: 'Edit' }).first()).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Published photos' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Open photo reports' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Feature' }).first()).toBeVisible();
    await expect(page.getByRole('button', { name: 'Remove' }).first()).toBeVisible();
    await expect(page.locator('nav').filter({ hasText: 'Next' }).first()).toBeVisible();

    const photoNumber = { desktop: 1, tablet: 2, mobile: 3 }[testInfo.project.name] ?? 1;
    const caption = `Browser moderation photo ${photoNumber}`;
    const pendingRow = page.locator('article').filter({ has: page.getByText(caption, { exact: true }) });
    const pendingPreview = pendingRow.getByRole('img', { name: `Preview: ${caption}` });
    await expect(pendingPreview).toBeVisible();
    expect(await pendingPreview.evaluate((image: HTMLImageElement) => image.naturalWidth)).toBeGreaterThan(0);
    const published = page.getByRole('region', { name: 'Published photos' }).locator('article').filter({ hasText: 'Browser published moderation photo' });
    await expect(published.getByRole('img', { name: 'Preview: Browser published moderation photo' })).toBeVisible();
    await expect(published.getByRole('button', { name: 'Edit' })).toBeVisible();
    await expect(published.getByRole('button', { name: 'Rotate' })).toBeVisible();
    const rotate = pendingRow.getByRole('button', { name: 'Rotate' });
    await rotate.focus();
    await expect(rotate).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(pendingPreview).toHaveAttribute('style', /rotate\(90deg\)/);
    await expect(page.getByText('Photo approved')).toHaveCount(0);

    const reportNumber = { desktop: 1, tablet: 2, mobile: 3 }[testInfo.project.name] ?? 1;
    const reportRow = page.locator('article').filter({ hasText: `Browser moderation report ${reportNumber}` });
    await expect(reportRow).toBeVisible();
    await reportRow.getByRole('button', { name: 'Remove photo' }).click();
    const removedNotification = page.getByText('Reported photo removed');
    await expect(removedNotification).toBeVisible();
    await expect(removedNotification).toBeHidden({ timeout: 10_000 });

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();
    expect(results.violations).toEqual([]);

    await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
    const width = await page.evaluate(() => ({
        client: document.documentElement.clientWidth,
        scroll: document.documentElement.scrollWidth,
    }));
    expect(width.scroll).toBeLessThanOrEqual(width.client);
});
