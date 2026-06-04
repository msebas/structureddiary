# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.0.2]

### Added

- Added alarm sound discovery, persistence, API support, OpenAPI definitions, and backend tests.
- Added mobile and compact responsive workspace behavior with center overlays for diaries, entries, questions, and answer history.
- Added question reordering with UI feedback and guarded save/discard behavior.
- Added question version browsing and routing from the question list and detail view.
- Added richer diary management display for shares, schedules, statistics, reminders, and description layout.
- Added question label/display-text editing, synced display-text handling, template-text editing by question type, and default min/max values.
- Added entry pagination support and batch entry loading in the API/store.
- Added test seed scripts and generated fixture export support for Cypress/fresh-install testing.

### Changed

- Set limits for rating type questions, min hast be => 0 and max <= 50 
- Reworked the main workspace into independently scrolling center and right columns.
- Improved mobile navigation/sidebar actions, including New diary and Diary overlay buttons.
- Improved entry and question edit flows, including save buttons in edit dialogs and create-specific headings.
- Improved entry list date formatting so single entries per day show only the date and same-day duplicates include time.
- Improved share permission display to show effective permissions while omitting redundant read permission.
- Improved markdown rendering/editing behavior for question display text, template text, and answers.
- Moved OpenAPI update tooling into `scripts/` and included scripts in app packaging.
- Updated metadata for the `0.0.2` release.

### Fixed

- Fixed long labels, display text, and list entries overflowing their containers.
- Fixed compact diary overlay access and overlapping mobile navigation controls.
- Fixed right-sidebar entry/question list scrolling with many entries.
- Fixed boolean defaults being treated as unfilled while creating entries.
- Fixed read-only diaries showing write-only entry actions.
- Fixed question deletion routing and avoided loading versions for a question after it was deleted.
- Fixed question list refresh after editing creates a new current question version.
- Fixed canceling question creation so it returns to the selected diary overview.
- Fixed backend validation for question min/max ranges.
- Fixed double entry creation when answer creation fails because of invalid inputs

### Tests

- Added and expanded Cypress component and e2e coverage for entry editing, entry lists, headers, question detail/edit/list behavior, route synchronization, navigation, management flows, and responsive overlays.
- Added backend unit and integration tests for alarm sounds, diary/question/entry controllers and mappers, validation, and fixture export.

## [0.0.1] - 2026-05-10

- Alpha release with most features present.
