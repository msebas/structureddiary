# Generated fixtures

Integration tests write reviewed backend payload examples here.

These files are intentionally separate from `cypress/fixtures/` so running PHP tests never overwrites Cypress fixtures. Copy files from `tests/generated-fixtures/cypress/` to `cypress/fixtures/` only after reviewing ID changes and updating ID-dependent Cypress intercepts if needed.

The PHP integration container writes these files as `www-data`. If the export test fails with a permission error, make `tests/generated-fixtures/cypress/` writable for the container user or copy artifacts out of the container in the test runner.
