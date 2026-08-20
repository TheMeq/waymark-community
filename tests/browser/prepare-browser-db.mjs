import { mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { spawnSync } from 'node:child_process';

const database = resolve('test-results/playwright-browser.sqlite');
mkdirSync(resolve('test-results'), { recursive: true });
rmSync(database, { force: true });
writeFileSync(database, '');

for (const [command, args] of [
    ['php', ['artisan', 'migrate:fresh', '--force']],
    ['php', ['tests/browser/seed-public-walks.php']],
]) {
    const result = spawnSync(command, args, { env: process.env, stdio: 'inherit' });

    if (result.status !== 0) {
        throw new Error(`Browser test setup failed while running ${command} ${args.join(' ')}`);
    }
}
