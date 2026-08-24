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

test('administrator can review health and enter the guarded restore workflow', async ({ page }, testInfo) => {
    await signIn(page);
    await page.goto('/admin/system-health');

    await expect(page.getByRole('heading', { name: 'System health' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Backups' }).last()).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Missing-media repair queue' })).toBeVisible();
    await expect(page.getByText('Technical logs and stack traces are not shown here.')).toBeVisible();

    const accessibility = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();
    expect(accessibility.violations).toEqual([]);

    const screenshotDirectory = resolve('test-results/phase9/screenshots');
    await mkdir(screenshotDirectory, { recursive: true });
    await page.screenshot({
        path: resolve(screenshotDirectory, `system-health-${testInfo.project.name}.png`),
        fullPage: true,
    });

    await page.getByRole('link', { name: 'Guided restore' }).click();
    await expect(page.getByRole('heading', { name: 'Confirm your password' })).toBeVisible();
    await page.getByLabel('Password').fill('password');
    await Promise.all([
        page.waitForURL('**/admin/backup-restore'),
        page.getByRole('button', { name: 'Continue' }).click(),
    ]);
    await expect(page.getByRole('heading', { name: 'Restore a backup' }).first()).toBeVisible();
    await expect(page.getByText('RESTORE WAYMARK')).toBeVisible();

    await page.goto('/admin/update-centre');
    await expect(page.getByRole('heading', { name: 'Updates' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Check now' })).toBeVisible();
});
