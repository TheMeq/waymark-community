import { expect, test } from '@playwright/test';
import { resolve } from 'node:path';

test('affiliate logo saves and moves between header and footer', async ({ page }, testInfo) => {
    await page.goto('/login');
    await page.getByLabel('Email address').fill('media.admin@example.test');
    await page.getByLabel('Password').fill('password');
    await Promise.all([page.waitForURL('**/new-here'), page.getByRole('button', { name: 'Sign in' }).click()]);
    await page.goto('/admin/branding');
    await page.getByLabel('Affiliation name', { exact: true }).fill('Trail Alliance');
    await page.getByLabel('Affiliation URL', { exact: true }).fill('https://example.org/alliance');
    await page.getByLabel('Header, to the right of Join us').check();
    await page.getByLabel('Upload a local affiliate logo (recommended)').check();
    const uploaded = page.waitForResponse(response => response.url().includes('/update') && response.request().postData()?.includes('_finishUpload') === true);
    await page.locator('[id="form.affiliation_logo_upload"] input[type="file"]').setInputFiles(resolve('public/images/pwa/icon-192.png'));
    await expect(page.locator('[id="form.affiliation_logo_upload"] .filepond--image-preview')).toBeVisible();
    await uploaded;
    await Promise.all([
        page.waitForResponse(response => response.url().includes('/update') && response.request().postData()?.includes('"method":"save"') === true),
        page.getByRole('button', { name: 'Save branding' }).click(),
    ]);
    await page.reload();
    await expect(page.locator('[data-slot="affiliation_logo"] img')).toBeVisible();
    await page.goto('/');
    const logo = page.locator('header').getByRole('img', { name: 'Trail Alliance' }).filter({ visible: true });
    await expect(logo).toBeVisible();
    await expect(page.locator('footer').getByRole('link', { name: 'Trail Alliance' })).toHaveCount(0);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await page.screenshot({ path: `test-results/affiliate-header-${testInfo.project.name}.png` });
    await page.goto('/admin/branding');
    await page.getByLabel('Footer', { exact: true }).check();
    await Promise.all([
        page.waitForResponse(response => response.url().includes('/update') && response.request().postData()?.includes('"method":"save"') === true),
        page.getByRole('button', { name: 'Save branding' }).click(),
    ]);
    await page.goto('/');
    await expect(page.locator('footer').getByRole('img', { name: 'Trail Alliance' })).toBeVisible();
    await expect(page.locator('header').getByRole('link', { name: 'Trail Alliance' })).toHaveCount(0);
});

