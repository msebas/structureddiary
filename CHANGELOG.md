# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.0.4]

### Added

- Added analysis-copy workflows that retain the source analysis type, output formats, parameters, original start timestamp, and optional LLM configuration.
- Added a dedicated API endpoint for retrieving copyable analysis settings without exposing the LLM header in normal job lists.
- Added direct downloads for individual artifacts and an integrated Nextcloud report view for HTML analysis results.
- Added authenticated, report-local navigation for HTML reports: linked report pages are rendered as integrated views and linked images and stylesheets resolve to their matching analysis artifacts.
- Added report fixtures and expanded Cypress, backend unit, and integration coverage for analysis copying, artifact handling, and integrated reports.

### Changed

- Reworked the analysis header so the selected analysis can be copied directly into a new analysis form.
- Replaced the client-side `srcdoc` report transformation and message-based navigation with a backend-integrated report view.
- Added Content Security Policy and iframe sandboxing for integrated reports; active report content is removed while report data, local styles, images, and navigation remain available.
- Added DOM and libxml PHP extension requirements for server-side report integration.
- Updated the OpenAPI specifications, translations, and app metadata for version `0.0.4`.

### Fixed

- Fixed analysis report navigation being blocked by Nextcloud's Content Security Policy.
- Fixed report previews recursively embedding the Nextcloud application when an artifact response was misrouted.
- Fixed integrated reports loading unauthenticated in an iframe by preserving the same origin needed for Nextcloud session cookies.
- Fixed artifact links in the analysis detail view so they download the selected artifact rather than opening it inline.

## [0.0.3]

### Added

- Added the first analysis workspace with analysis creation, listing, detail views, status display, result previews, and artifact downloads.
- Added analysis job persistence, lifecycle handling, cancel/delete support, cron processing, and artifact metadata storage.
- Added integration with an external Python analysis service, including job submission, health checks, status callbacks, diary/entry export endpoints, result collection, and cleanup.
- Added admin settings for configuring the analysis service URL, service secret, and output folder.
- Added analysis artifact storage in Nextcloud Files, ZIP downloads, filtered artifact downloads, and inline artifact viewing.
- Added OpenAPI scopes for administration, Python analysis callbacks, and the combined API.

### Missing/Known Bugs

- Analysis artifact display and controls in the App are broken. Only access via the file app works.
- After submitting a new analysis the list is not updated.

### Changed

- Updated app metadata for the `0.0.3` development release and Nextcloud 32+ compatibility.
- Extended routing, navigation, API services, store types, and workspace headers to include analysis workflows.
- Added stable UUIDs for analysis jobs while keeping Nextcloud job IDs distinct from Python service job IDs.

### Tests

- Added backend unit and integration coverage for analysis jobs, artifacts, export payloads, service configuration, Python service client behavior, and callback access checks.
- Added Cypress component and e2e coverage for the analysis workspace.

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
