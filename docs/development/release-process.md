# Release Process Direction

A release is reproducibly built from a clean workspace.

Expected phases:

1. validate repository cleanliness;
2. install locked PHP dependencies;
3. install locked frontend dependencies;
4. compile frontend;
5. run developer test suite;
6. run accessibility/performance/visual checks;
7. assemble production tree;
8. remove development-only material;
9. inject version/build metadata;
10. validate package allow/deny manifest;
11. smoke-test package installation;
12. create Shared Hosting ZIP;
13. create checksum;
14. publish release metadata and admin-friendly notes.

A release fails if required runtime files are missing or prohibited sensitive/development files are present.
