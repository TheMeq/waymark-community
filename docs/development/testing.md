# Developer and Release Testing

Testing is a developer/release responsibility. Deployed shared-host installations do not run the test suite.

Required layers:

- backend unit/feature;
- auth/permission;
- validation;
- browser/end-to-end critical journeys;
- accessibility automation plus manual keyboard checks where appropriate;
- responsive visual regression;
- performance regression/budgets;
- MySQL/MariaDB matrix;
- setup/install;
- Shared Hosting Release ZIP smoke test;
- update/migration/rollback;
- backup/restore;
- PWA/mobile upload;
- photo moderation.

The implementation plan must give exact commands and acceptance criteria.
