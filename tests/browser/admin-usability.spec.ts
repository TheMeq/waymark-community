import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';

async function signIn(page: Page) {
    await page.goto('/login');
    await page.getByLabel('Email address').fill('media.admin@example.test');
    await page.getByLabel('Password').fill('password');
    await Promise.all([
        page.waitForURL('**/new-here'),
        page.getByRole('button', { name: 'Sign in' }).click(),
    ]);
}

async function expectAccessible(page: Page) {
    const accessibility = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();
    expect(accessibility.violations).toEqual([]);
}

test('task-focused dashboard and sidebar work responsively', async ({ page }, testInfo) => {
    await signIn(page);
    await page.goto('/admin');

    await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Getting started' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Dismiss' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Add a walk' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Manage photos' })).toBeVisible();
    await expect(page.getByText('Upcoming events')).toBeVisible();
    await expect(page.getByRole('link', { name: 'GitHub' })).toHaveCount(0);

    const screenshotDirectory = resolve('test-results/post-install/screenshots');
    await mkdir(screenshotDirectory, { recursive: true });
    await page.screenshot({
        path: resolve(screenshotDirectory, `admin-dashboard-${testInfo.project.name}.png`),
        fullPage: true,
    });

    if (testInfo.project.name !== 'desktop') {
        await page.getByRole('button', { name: 'Expand sidebar' }).click();
    }
    await expect(page.getByText('Events', { exact: true })).toBeVisible();
    await expect(page.getByText('Community', { exact: true })).toBeVisible();
    await expect(page.getByText('Group settings', { exact: true })).toBeVisible();
    await expect(page.getByText('System', { exact: true })).toBeVisible();
    await expectAccessible(page);
    await page.screenshot({
        path: resolve(screenshotDirectory, `admin-sidebar-${testInfo.project.name}.png`),
        fullPage: testInfo.project.name === 'desktop',
    });
});

test('add walk wizard preserves data through Back and Next without overflow at 200 percent text', async ({ page }, testInfo) => {
    await signIn(page);
    await page.goto('/admin/walks/create');

    await expect(page.getByText('When and where', { exact: true })).toBeVisible();
    await page.getByLabel('Title').fill('Accessible browser walk');
    await page.getByLabel('Slug').fill(`accessible-browser-walk-${testInfo.project.name}`);
    await page.getByLabel('Starts at').fill('2026-09-10T09:30');
    await page.getByRole('button', { name: 'Next' }).click();
    await expect(page.getByText('Walk details', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Back' }).click();
    await expect(page.getByLabel('Title')).toHaveValue('Accessible browser walk');

    await page.evaluate(() => {
        document.documentElement.style.fontSize = '200%';
    });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
    await page.keyboard.press('Tab');
    expect(await page.evaluate(() => document.activeElement?.tagName)).not.toBe('BODY');
    await expectAccessible(page);

    const screenshotDirectory = resolve('test-results/post-install/screenshots');
    await mkdir(screenshotDirectory, { recursive: true });
    await page.screenshot({
        path: resolve(screenshotDirectory, `add-walk-wizard-${testInfo.project.name}.png`),
        fullPage: true,
    });
});
