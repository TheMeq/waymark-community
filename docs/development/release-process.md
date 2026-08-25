# Release Process

Build from an exact clean commit after the required acceptance authority. Do not tag or publish while an independent acceptance gate remains open.

1. Install tracked Composer/npm lockfiles and run the complete PHP 8.3, MySQL 8.4, MariaDB 11.4, browser, accessibility, PWA, performance and repository gates.
2. Build and verify the shared-hosting artifact:

   ```shell
   php scripts/build-release.php
   php scripts/verify-release.php dist/waymark-community-1.0.1-shared-hosting.zip
   ```

3. Run ZIP-only clean installs on empty MySQL and MariaDB databases. The extracted runtime must not borrow Composer, Node, tests or source files.
4. Exercise the production-package upgrade and controlled rollback matrix from the approved previous package to the candidate.
5. Record source SHA, versions, manifest, package SHA-256, verification reports, test summaries, staging checks and deliberate deviations.
6. Create a safe exact-commit source review ZIP separately from the production artifact and obtain independent approval.
7. Only authorised release work after approval may create the public Git tag, sign/publish artifacts and update the stable feed. A dated changelog entry may be prepared in the independently reviewed release candidate, but it does not make that version public.

The builder uses `git archive`, locked dependencies and a temporary workspace. It packages production `vendor/`, compiled assets, installer/updater/recovery and migrations, while excluding source tests, build tools, secrets and runtime state. See [`../deployment/release-packaging.md`](../deployment/release-packaging.md) for commands and package contents.
