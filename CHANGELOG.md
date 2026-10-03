# Changelog

All notable changes to this extension are documented here. The format
is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.2.8] - 2026-10-03

### Fixed
- Saving a form whose field list was not posted, or was posted as unreadable data, no longer deletes all of the form's fields while reporting success. The save is rolled back with an error message and the existing fields are kept. A new form without fields still saves.
- A field validation pattern that contains a `/` (for example `^\d+/\d+$`) no longer rejects every value on submit; unescaped slashes in the pattern are escaped before matching.
- The admin Delete button on the form edit page escapes the translated confirmation text for JavaScript, so a translation containing an apostrophe or quote no longer breaks the click handler.
