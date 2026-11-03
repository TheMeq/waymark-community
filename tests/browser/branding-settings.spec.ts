import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';
import { resolve } from 'node:path';

async function signIn(page: Page) {
    await page.goto('/login');
    await page.getByLabel('Email address').fill('media.admin@example.test');
    await page.getByLabel('Password').fill('password');
    await Promise.all([page.waitForURL('**/new-here'), page.getByRole('button', { name: 'Sign in' }).click()]);
}

test('branding preview uses unsaved admin values at each representative viewport', async ({ page }, testInfo) => {
    await signIn(page);
    await page.goto('/admin/branding');
    await expect(page.getByRole('heading', { name: 'Branding' })).toBeVisible();

    await page.getByLabel('Group name').fill('Unsaved Pathfinders');
    await page.getByRole('button', { name: 'Preview branding' }).click();

    const previewRegion = page.getByRole('region', { name: 'Unsaved preview' });
    await expect(previewRegion).toBeVisible();
    for (const viewport of ['desktop', 'tablet', 'mobile']) {
        await expect(previewRegion.getByRole('link', { name: new RegExp(`^${viewport}$`, 'i') })).toHaveAttribute('href', new RegExp(`viewport=${viewport}`));
    }
    await expect(page).toHaveScreenshot(`branding-settings-${testInfo.project.name}.png`, { fullPage: true });

    const viewport = testInfo.project.name;
    const previewUrl = await previewRegion.getByRole('link', { name: new RegExp(`^${viewport}$`, 'i') }).getAttribute('href');
    expect(previewUrl).not.toBeNull();
    await page.goto(previewUrl!);
    await expect(page.getByText(`${viewport[0].toUpperCase()}${viewport.slice(1)} preview`)).toBeVisible();
    await expect(page.getByText('Unsaved Pathfinders').first()).toBeVisible();

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();
    expect(results.violations).toEqual([]);
    await expect(page).toHaveScreenshot(`branding-preview-${testInfo.project.name}.png`, { fullPage: true });

    await page.goto('/');
    await expect(page.getByText('Unsaved Pathfinders')).toHaveCount(0);
});

test('uploads managed logo and favicon', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'One desktop browser flow proves the critical branding upload contract.');

    await signIn(page);
    await page.goto('/admin/branding');

    const localLogo = page.getByLabel('Upload a local logo (recommended)');
    await localLogo.focus();
    await page.keyboard.press('Space');
    await expect(localLogo).toBeChecked();
    await page.locator('[id="form.logo_upload"] input[type="file"]').setInputFiles({
        name: 'transparent-logo.png',
        mimeType: 'image/png',
        buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAEAAAAAwCAYAAAChS3wfAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAA+ElEQVRoge2awQ3DIAxF027TKbpD1+g8XaM7dIqu05MlhBKwAzZ2/d85IvwXIwXMtgEAAABpuVi+7PG8f7nPvl+fm+ZcCFUBksA9tIRMFzAz9BEzZUwTYBG8ZoaIYQErgteMiBgS4CE8cVbCKQGegtdIRYgFeA5PSCSIBEQIT3AlsAVECk9wJFwtJuIZVgVE/PpErwq6AiKHJ1oS0i+B9AKaS+Afyp84WgbpKwACVk9gNekF4D/AciIeSS8AewHuQBElYDvMACdC0oEjSFA7EyQ8S1A/FS7xJMK0L1DiQcKyzlBJ2t5gTdru8B4p7we08HhDBAAAQGZ+N/tkLuYZnesAAAAASUVORK5CYII=', 'base64'),
    });
    await expect(page.getByText('Temporary logo preview selected.')).toBeVisible();
    await expect(page.locator('[id="form.logo_upload"] .filepond--image-preview')).toBeVisible();

    const localFavicon = page.getByLabel('Upload a local favicon (recommended)');
    await localFavicon.focus();
    await page.keyboard.press('Space');
    await expect(localFavicon).toBeChecked();
    await page.locator('[id="form.favicon_upload"] input[type="file"]').setInputFiles(resolve('public/images/pwa/icon-192.png'));
    await expect(page.getByText('Temporary favicon preview selected.')).toBeVisible();
    await expect(page.locator('[id="form.favicon_upload"] .filepond--image-preview')).toBeVisible();

    for (const target of [page.getByRole('button', { name: 'Save branding' }), page.locator('[id="form.logo_upload"]')]) {
        const box = await target.boundingBox();
        expect(box).not.toBeNull();
        expect(box!.height).toBeGreaterThanOrEqual(44);
    }

    await page.getByRole('button', { name: 'Save branding' }).click();
    await expect(page.getByText('Branding saved')).toBeVisible();
    await page.reload();
    await expect(page.locator('[data-testid="managed-image-preview"][data-slot="logo"] img')).toBeVisible();
    await expect(page.locator('[data-testid="managed-image-preview"][data-slot="favicon"] img')).toBeVisible();
    await expect(page.getByLabel('Upload a local logo (recommended)')).toBeChecked();
    await expect(page.getByLabel('Upload a local favicon (recommended)')).toBeChecked();
    await expect(page.locator('[data-testid="managed-image-status"][data-slot="logo"]')).toContainText('Saved local logo.');
    await expect(page.locator('[data-testid="managed-image-status"][data-slot="favicon"]')).toContainText('Saved local favicon.');

    await page.goto('/');
    const logo = page.locator('header img[alt=""]').first();
    await expect(logo).toHaveAttribute('src', /\/media\/\d+\/image\/medium$/);
    const favicon = page.locator('head link[rel="icon"]');
    const savedFaviconUrl = await favicon.getAttribute('href');
    expect(savedFaviconUrl).toMatch(/\/media\/\d+\/image\/favicon$/);
    await expect(favicon).toHaveAttribute('type', 'image/png');

    await page.goto('/admin/branding');
    await page.locator('[id="form.favicon_upload"] input[type="file"]').setInputFiles(resolve('public/images/demo/woodland-walk-768.webp'));
    await expect(page.getByText('Temporary favicon preview selected.')).toBeVisible();
    await page.getByRole('button', { name: 'Save branding' }).click();
    const faviconField = page.locator('[data-field-wrapper]').filter({ has: page.locator('[id="form.favicon_upload"]') });
    await expect(faviconField.getByText(/must be square/i)).toBeVisible();
    await expect(page.locator('[data-testid="managed-image-status"][data-slot="favicon"]')).toHaveAttribute('role', 'status');
    await expect(page.locator('[data-testid="managed-image-status"][data-slot="favicon"]')).toHaveAttribute('aria-live', 'polite');

    await page.goto('/');
    await expect(page.locator('head link[rel="icon"]')).toHaveAttribute('href', savedFaviconUrl!);

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();
    expect(results.violations).toEqual([]);
});
