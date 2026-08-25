import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';

test('guided installer is accessible, responsive, and keyboard operable', async ({ page }, testInfo) => {
    await page.goto('/setup');

    await expect(page.getByRole('heading', { name: 'Set up Waymark Community' })).toBeVisible();
    await expect(page.getByText('Step 1 of 11')).toBeVisible();

    const begin = page.getByRole('button', { name: 'Begin setup' });
    await begin.focus();
    await expect(begin).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/setup\/server-checks$/);
    await expect(page.getByRole('heading', { name: 'Server checks' })).toBeVisible();

    const dimensions = await page.getByRole('button', { name: 'Continue' }).evaluate((element) => {
        const style = getComputedStyle(element);
        const rect = element.getBoundingClientRect();

        return { height: rect.height, visible: style.visibility !== 'hidden' && style.display !== 'none' };
    });
    expect(dimensions.visible).toBe(true);
    expect(dimensions.height).toBeGreaterThanOrEqual(44);

    const accessibility = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();
    expect(accessibility.violations).toEqual([]);

    await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
    expect(await page.evaluate(() => document.documentElement.scrollWidth))
        .toBeLessThanOrEqual(await page.evaluate(() => document.documentElement.clientWidth));
    await page.evaluate(() => { document.documentElement.style.fontSize = ''; });

    const screenshotDirectory = resolve('test-results/phase9/screenshots');
    await mkdir(screenshotDirectory, { recursive: true });
    await page.screenshot({
        path: resolve(screenshotDirectory, `installer-server-checks-${testInfo.project.name}.png`),
        fullPage: true,
    });
});

test('module choices use normal checkboxes with aligned accessible touch targets', async ({ page }) => {
    await page.goto('/_dev/setup/modules');

    const walks = page.getByRole('checkbox', { name: /Walks/ });
    const socials = page.getByRole('checkbox', { name: 'Socials' });
    await expect(walks).toBeChecked();
    await expect(walks).toBeDisabled();
    await expect(socials).toBeChecked();

    const geometry = await socials.evaluate((element) => {
        const input = element.getBoundingClientRect();
        const label = element.closest('label')!.getBoundingClientRect();
        const style = getComputedStyle(element);

        return {
            inputWidth: input.width,
            inputHeight: input.height,
            labelHeight: label.height,
            centreDifference: Math.abs((input.top + input.height / 2) - (label.top + label.height / 2)),
            accent: style.accentColor,
        };
    });
    expect(geometry.inputWidth).toBeGreaterThanOrEqual(14);
    expect(geometry.inputWidth).toBeLessThanOrEqual(24);
    expect(geometry.inputHeight).toBeGreaterThanOrEqual(14);
    expect(geometry.inputHeight).toBeLessThanOrEqual(24);
    expect(geometry.labelHeight).toBeGreaterThanOrEqual(44);
    expect(geometry.centreDifference).toBeLessThanOrEqual(8);
    expect(geometry.accent).not.toBe('auto');

    await socials.focus();
    await expect(socials).toBeFocused();
    await page.keyboard.press('Space');
    await expect(socials).not.toBeChecked();

    const accessibility = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();
    expect(accessibility.violations).toEqual([]);

    await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
    expect(await page.evaluate(() => document.documentElement.scrollWidth))
        .toBeLessThanOrEqual(await page.evaluate(() => document.documentElement.clientWidth));
});

test('required guidance, optional email controls, and recovery-key generation remain usable', async ({ page }) => {
    await page.goto('/_dev/setup/database');
    await expect(page.getByText('Fields marked * are required.')).toBeVisible();
    await expect(page.getByText(/must be able to create and change its schema/)).toBeVisible();

    await page.goto('/_dev/setup/mail');
    await page.getByRole('radio', { name: 'Set up email later' }).check();
    await expect(page.getByLabel('SMTP host')).toBeHidden();
    expect(await page.getByLabel('SMTP host').getAttribute('required')).toBeNull();

    await page.goto('/_dev/setup/advanced');
    const generatedResponse = page.waitForResponse((response) => response.url().includes('/setup/recovery-key'));
    await page.getByRole('button', { name: 'Generate secure recovery key' }).click();
    expect((await generatedResponse).status()).toBe(200);
    const recoveryKey = page.locator('#recovery-key');
    await expect(recoveryKey).not.toHaveValue('');
    const generated = await recoveryKey.inputValue();
    expect(generated.length).toBeGreaterThanOrEqual(24);
    expect(generated).toMatch(/[a-z]/);
    expect(generated).toMatch(/[A-Z]/);
    expect(generated).toMatch(/[0-9]/);
    expect(generated).toMatch(/[^A-Za-z0-9]/);
    await expect(page.getByLabel('Confirm recovery key')).toHaveValue(generated);
});
