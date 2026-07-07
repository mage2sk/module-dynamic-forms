# Changelog

All notable changes to this extension are documented here. The format
is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.0.8]

### Changed
- Code cleanup: removed redundant inline comments and docblocks from the PHP source. No functional changes.

## [1.0.7] - 2026-06-18

### Changed
- README rewritten to match current feature set: accurate system.xml configuration
  table (General, Email, Display, Styling groups), correct field type count (13),
  live product URL canonical, and gold-template section order.

## [1.0.6] — Upload extension hardening

### Added
- `Controller/Form/Upload` now calls `Panth\Core\Security\UploadExtensionPolicy::assertSafeExtension()`
  before saving — a hard executable deny-list (`php`, `phtml`, `sh`, `jsp`, …)
  independent of the admin-configurable allowed-extensions field. Requires
  `mage2kishan/module-core ^1.0.17`.

### Changed
- Default allowed-extensions (used only when the admin field is left empty)
  no longer includes `txt` or `zip`. Form uploads are served from a public
  `pub/media` URL, so a benign-but-arbitrary file must not be a
  default-accepted type. Stores that need those types can add them back in
  the admin field. Stores that already configured their own list are
  unaffected.

## [1.0.5] — 2026-06-14

### Fixed

- **Customer auto-reply email no longer prints HTML as literal text.** In
  `view/frontend/email/customer_autoreply.html` the body was output as a bare
  `{{var auto_reply_body}}`. Magento's email template filter applies a default
  `escape` modifier to any `{{var}}` without an explicit modifier, so the
  admin-authored HTML in the auto-reply body was `escapeHtml()`'d and tags such
  as `<p>`, `<br />` and `<ul>` showed up as text. The variable now uses
  `{{var auto_reply_body|raw}}`, consistent with how `submission_fields|raw` is
  already handled in `admin_notification.html`. `auto_reply_body` is
  admin-authored per form, so `|raw` is appropriate.

## [1.0.0] — Initial release

### Added — Admin form builder
- Full CRUD interface for creating and managing dynamic forms
- Drag-and-drop field builder with 13 field types: text, textarea,
  email, phone, number, date, select, multi-select, checkbox, radio,
  file upload, hidden, WYSIWYG
- Three form usage modes: Standalone Page, Widget Only, or Both
- Per-field width control: full, half, or one-third for multi-column layouts
- Sort-order management with drag-and-drop reordering
- Field options builder for select/radio/checkbox/multi-select types
- Custom validation rules per field (min/max length, pattern, etc.)

### Added — Frontend rendering
- Hyva theme support using Alpine.js (auto-detected)
- Luma theme support using vanilla JavaScript (auto-detected)
- AJAX form submission with client-side and server-side validation
- File upload with drag-and-drop, progress bar, and extension validation
- Responsive CSS grid layout with CSS custom properties for theming
- Success message display with optional redirect after submission

### Added — Email notifications
- Admin notification email with formatted HTML table of all submitted fields
- CC/BCC support for admin notifications
- Customer auto-reply email (configurable per form)
- Configurable email sender identity and templates

### Added — Submission management
- Per-form submission listing with filterable grid
- Submission detail view with all field values
- Status workflow: New, Read, Replied, Closed
- AJAX status update without page reload
- Admin notes per submission (AJAX save)
- Mass delete action for bulk cleanup

### Added — SEO and URL routing
- Custom URL keys for standalone form pages (e.g., /pages/contact-us)
- URL key uniqueness validation (across forms and URL rewrites)
- Meta title, description, keywords, and robots per form
- Automatic canonical URL tag
- JSON-LD structured data (WebPage + ContactPage schema)

### Added — Widget support
- Embeddable via Page Builder / WYSIWYG Insert Widget dialog
- Widget parameters: form_id, show_title, show_description
- Direct widget code support for CMS pages and blocks
- CMS content above/below the form (Page Builder compatible)

### Added — Configuration
- Global enable/disable switch
- Configurable allowed file extensions and max file size
- Admin email template selection
- Auto-reply email template selection
- Email sender identity selection

### Compatibility
- Magento Open Source / Commerce / Cloud 2.4.4 - 2.4.8
- PHP 8.1, 8.2, 8.3, 8.4
- Hyva Theme (Alpine.js) and Luma Theme (vanilla JS)

---

## Support

For all questions, bug reports, or feature requests:

- **Email:** kishansavaliyakb@gmail.com
- **Website:** https://kishansavaliya.com
- **WhatsApp:** +91 84012 70422
