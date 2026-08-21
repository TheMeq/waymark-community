import { expect, test } from '@playwright/test';

test('public photo reporting form is usable at every review viewport', async ({ page }, testInfo) => {
    await page.goto('/photos/22/report');
    await expect(page.getByRole('heading', { name: 'Report a photo' })).toBeVisible();
    await page.getByLabel('Reason').selectOption('privacy');
    await page.getByLabel('Details').fill('Please review this photo.');
    await page.getByRole('button', { name: 'Send report' }).click();
    await expect(page.getByText('Thank you. Your report has been received.')).toBeVisible();
    await expect(page).toHaveScreenshot(`photo-report-${testInfo.project.name}.png`, { fullPage: true });
});
