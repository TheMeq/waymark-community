import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { createHmac } from 'node:crypto';

test('account security settings are accessible and visually stable', async ({ page }, testInfo) => {
    const email = `security-${testInfo.project.name}@example.test`;

    await page.goto('/register');
    await page.getByLabel('Your name').fill('Alex Walker');
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByLabel('Confirm password').fill('password');
    await Promise.all([
        page.waitForURL('**/new-here'),
        page.getByRole('button', { name: 'Create account' }).click(),
    ]);

    await page.goto('/account/security');
    await page.getByLabel('Password').fill('password');
    await Promise.all([
        page.waitForURL('**/account/security'),
        page.getByRole('button', { name: 'Continue' }).click(),
    ]);

    await expect(page.getByRole('heading', { name: 'Account security' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Set up two-factor authentication' })).toBeVisible();

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();

    expect(results.violations).toEqual([]);
    await expect(page).toHaveScreenshot(`account-security-${testInfo.project.name}.png`, {
        fullPage: true,
    });

    await page.evaluate(() => {
        document.body.style.zoom = '2';
    });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});

test('sensitive two-factor confirmation is keyboard-accessible and visually stable', async ({ page }, testInfo) => {
    await page.goto('/login');
    await page.getByLabel('Email address').fill('security-browser@example.test');
    await page.getByLabel('Password').fill('password');
    await Promise.all([
        page.waitForURL('**/two-factor-challenge'),
        page.getByRole('button', { name: 'Sign in' }).click(),
    ]);

    await page.getByLabel('Authentication code').fill(totp('JBSWY3DPEHPK3PXP'));
    await Promise.all([
        page.waitForURL('**/new-here'),
        page.getByRole('button', { name: 'Continue' }).click(),
    ]);

    await page.goto('/account/sensitive-confirmation');
    await page.getByLabel('Password').fill('password');
    await Promise.all([
        page.waitForURL('**/account/sensitive-confirmation'),
        page.getByRole('button', { name: 'Continue' }).click(),
    ]);

    const code = page.getByLabel('Authentication code');
    await code.focus();
    await page.keyboard.press('Tab');
    await expect(page.getByRole('button', { name: 'Continue' })).toBeFocused();

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();

    expect(results.violations).toEqual([]);
    await expect(page).toHaveScreenshot(`sensitive-confirmation-${testInfo.project.name}.png`, {
        fullPage: true,
    });

    await page.evaluate(() => {
        document.body.style.zoom = '2';
    });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});

function totp(secret: string): string {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';

    for (const character of secret.replace(/=+$/, '').toUpperCase()) {
        bits += alphabet.indexOf(character).toString(2).padStart(5, '0');
    }

    const key = Buffer.from(bits.match(/.{1,8}/g)?.map((octet) => Number.parseInt(octet.padEnd(8, '0'), 2)) ?? []);
    const counter = Math.floor(Date.now() / 30_000);
    const counterBuffer = Buffer.alloc(8);
    counterBuffer.writeUInt32BE(Math.floor(counter / 0x1_0000_0000), 0);
    counterBuffer.writeUInt32BE(counter >>> 0, 4);

    const digest = createHmac('sha1', key).update(counterBuffer).digest();
    const offset = digest[digest.length - 1] & 0x0f;
    const value = ((digest[offset] & 0x7f) << 24)
        | (digest[offset + 1] << 16)
        | (digest[offset + 2] << 8)
        | digest[offset + 3];

    return String(value % 1_000_000).padStart(6, '0');
}
