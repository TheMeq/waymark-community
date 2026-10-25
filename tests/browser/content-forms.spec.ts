import { expect, test, type Page } from '@playwright/test';
import { resolve } from 'node:path';

async function signIn(page: Page) {
    await page.goto('/login');
    await page.getByLabel('Email address').fill('media.admin@example.test');
    await page.getByLabel('Password').fill('password');
    await Promise.all([
        page.waitForURL('**/new-here'),
        page.getByRole('button', { name: 'Sign in', exact: true }).click(),
    ]);
}

test('holiday create, image upload, edit and publication persist visibly', async ({ page }) => {
    await signIn(page);
    await page.goto('/admin/holidays/create');
    await expect(page.getByLabel('Slug', { exact: true })).toHaveCount(0);
    await page.getByLabel('Title').fill('Browser coastal holiday');
    await page.getByLabel('Starts at').fill('2026-11-06T16:00');
    await page.getByLabel('Ends at').fill('2026-11-09T10:00');
    await page.getByLabel('Pricing type').selectOption('fixed');
    await page.getByLabel('Price amount').fill('100');
    await page.getByLabel('Currency').fill('GBP');
    await page.getByLabel('Image description').fill('Walkers on the coast');
    const uploaded = page.waitForResponse((response) => response.request().method() === 'POST'
        && response.url().includes('/update') && Boolean(response.request().postData()?.includes('_finishUpload')));
    await page.getByRole('group', { name: 'Upload image', exact: true }).locator('input[type="file"]').setInputFiles(resolve('public/images/demo/hero-walkers-768.webp'));
    await uploaded;
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/holidays\/\d+\/edit$/);
    await expect(page.getByText('Holiday draft created', { exact: true })).toBeVisible();
    await expect(page.getByText('Status: Draft', { exact: true })).toBeVisible();
    await expect(page.getByText('Saved local image.', { exact: true })).toBeVisible();
    await page.getByLabel('Destination', { exact: true }).fill('Coastal trails');
    const saved = page.waitForResponse((response) => response.request().method() === 'POST'
        && response.url().includes('/update') && Boolean(response.request().postData()?.includes('"method":"save"')));
    await page.getByRole('button', { name: 'Save changes', exact: true }).click();
    await saved;
    await page.reload();
    await expect(page.getByLabel('Destination', { exact: true })).toHaveValue('Coastal trails');
    await expect(page.getByText('Saved local image.', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Publish holiday', exact: true }).click();
    await expect(page.getByText('Status: Published', { exact: true })).toBeVisible();
    await expect(page.getByText('Holiday published', { exact: true })).toBeVisible();
});

test('social create, edit and publication persist visibly', async ({ page }) => {
    await signIn(page);
    await page.goto('/admin/socials/create');
    await expect(page.getByLabel('Slug', { exact: true })).toHaveCount(0);
    await page.getByLabel('Title').fill('Browser supper');
    await page.getByLabel('Starts at').fill('2026-11-06T19:00');
    await page.getByLabel('Venue name').fill('Village hall');
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/socials\/\d+\/edit$/);
    await expect(page.getByText('Social draft created', { exact: true })).toBeVisible();
    await page.getByLabel('Venue name').fill('Community hall');
    const saved = page.waitForResponse((response) => response.request().method() === 'POST'
        && response.url().includes('/update') && Boolean(response.request().postData()?.includes('"method":"save"')));
    await page.getByRole('button', { name: 'Save changes', exact: true }).click();
    await saved;
    await page.reload();
    await expect(page.getByLabel('Venue name')).toHaveValue('Community hall');
    await page.getByRole('button', { name: 'Publish social', exact: true }).click();
    await expect(page.getByText('Status: Published', { exact: true })).toBeVisible();
    await expect(page.getByText('Social published', { exact: true })).toBeVisible();
});
