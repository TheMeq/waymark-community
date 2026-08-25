import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

const repositoryRoot = resolve(import.meta.dirname, '../..');

test('production package matrix is archive-gated and covers success plus controlled rollback', async () => {
    const source = await readFile(resolve(repositoryRoot, 'tests/Integration/Operations/ProductionReleasePackageMatrixTest.php'), 'utf8');

    assert.match(source, /WAYMARK_PREVIOUS_RELEASE_ARCHIVE/);
    assert.match(source, /WAYMARK_CURRENT_RELEASE_ARCHIVE/);
    assert.match(source, /ReleaseApplicationExtractor/);
    assert.match(source, /ReleasePackageVerifier/);
    assert.match(source, /ApplicationFileTransaction/);
    assert.match(source, /public-html/);
    assert.match(source, /matching deployment layouts/);
    assert.match(source, /test_real_prior_package_upgrades_with_migrations_data_and_media_retained/);
    assert.match(source, /test_controlled_activation_failure_rolls_real_package_files_and_database_back_to_prior_release/);
});
