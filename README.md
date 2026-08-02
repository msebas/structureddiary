# Structured Diary

Structured Diary is a Nextcloud app for collecting recurring, schema-driven
personal data and analyzing it over time. It is intended for diaries that work
more like a regular survey than a free-form journal: a diary defines its
questions once, and every entry answers the applicable questions.

The app supports Nextcloud 32 through 34 and is licensed under
[AGPL-3.0-or-later](LICENSE).

## Features

- Can create diaries with descriptions, entry schedules, reminders, sharing, and
  per-diary permissions.
- Versioned questions: Existing answers retain the question version that
  was in effect when they were written.
- Collects text (including Markdown), boolean, rating, number, integer, time,
  select, and editable-select answers.
- Keeps answer history when an answer is changed, allowing to browse earlier versions.
- Allows managing analysis reports (create, copy, cancel, delete) from the App.
- Analysis (with optional LLM support) is carried out by an external analysis service:
  - See https://github.com/msebas/structureddiary_analysis
- HTML analysis reports could be viewed inside Nextcloud. Other analysis reports could be 
  downloaded (as ZIP or individual files) from the app or directly from Nextcloud 
  Files App or sync. 
  - Reports are automatically shared to anyone with analysis permission on the diary.  
- An Android App for mobile entry creation.
  - See https://github.com/msebas/structureddiary_android_app 

## Requirements

- Nextcloud 32, 33, or 34
- PHP 8.1 or newer with the `dom` and `libxml` extensions
- Node.js 24 and npm 11 for frontend development
- Composer for PHP development dependencies and tooling

## Development setup

Uses [Nextcloud Docker Dev](https://github.com/nextcloud/nextcloud-docker-dev).


An additional Nextcloud instance is required for the included wrappers for the
project’s Docker-based Nextcloud test environment:

```bash
bash tests/bin/test_unit.sh
bash tests/bin/test_integration.sh
bash tests/bin/test_cypress_component.sh
bash tests/bin/test_cypress_e2e.sh
```

The integration suite deliberately drops and recreates Structured Diary tables
for isolation. Do not run two integration test processes against the same test
database at once.

**DO NOT RUN INTEGRATION TESTS ON A PRODUCTION NEXTCLOUD INSTANCE/DB**.

Run the optional end-to-end analysis-service workflow with:

```bash
ANALYSIS_SERVICE_SECRET=replace-with-test-secret \
  bash tests/bin/test_analysis_service_integration.sh
```

See [tests/generated-fixtures/README.md](tests/generated-fixtures/README.md)
for the workflow around generated backend fixtures.

## API and packaging

The app exposes OCS endpoints for the user workspace, administration, and the
analysis-service callback flow. Regenerate the OpenAPI description after API
changes with:

```bash
bash scripts/update_openapi.sh
```

`scripts/pack.sh` builds the distributable app archive. Release history is in
[CHANGELOG.md](CHANGELOG.md).

## Resources

- [Nextcloud developer documentation](https://docs.nextcloud.com/server/latest/developer_manual/)
- [Nextcloud app store developer guide](https://nextcloudappstore.readthedocs.io/en/latest/developer.html)
