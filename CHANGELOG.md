# Changelog

All notable changes to this extension are documented here. The format
is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.2.10] - 2026-10-06

### Changed
- Form fields on both stores follow the shared form style. Inputs and selects are 46px tall and white, with a 1px #D4D4D4 border, 8px corners, 16px text at every width (15px on desktop before) and a 2px teal focus ring. Textareas use 16px text. Labels are 14px semibold in #171717. Help and error text is 14px (12px before), with errors in #B91C1C.
- The submit button is 44px tall (48px and full width on phones) with 15px semibold text. Before, it was about 52px tall with 16px bold text.
- The form card has a 1px border, 12px corners and no shadow. The form title is 28px bold (24px on phones) and the description is 16px. On a standalone form page the page title is 36px (28px on phones).
- The file upload area uses a 1px dashed border with 14px text.

### Added
- Unit tests for the form styles in the Luma and Hyva templates.
