# Magento 2 Dynamic Forms

Dynamic Forms lets a store administrator build custom forms in the Magento admin, publish them as standalone pages or embed them with a widget, and review the submissions in an admin grid. Each form has its own fields, success message, redirect URL, notification email and optional auto-reply. Submissions are stored in the database and can be filtered, annotated and given a status.

The module ships two storefront templates: one for Hyva (Alpine.js) and one for Luma (jQuery). The active theme is detected through Panth_Core and the matching template is chosen automatically.

Product page: [kishansavaliya.com/magento-2-dynamic-forms.html](https://kishansavaliya.com/magento-2-dynamic-forms.html)

## Features

- Form builder in the admin with drag-and-drop reordering of fields.
- 13 field types: Text, Textarea, Email, Phone, Number, Dropdown (Select), Multi-Select, Checkbox, Radio Buttons, File Upload, Date, Hidden and WYSIWYG Editor.
- Per-field label, name, placeholder, default value, required flag, CSS class, width (Full Width, Half Width, One Third Width), options for choice fields and validation rules in JSON.
- Server-side validation of required fields, email format, phone format, numeric values, choice fields against their configured options, a 65535-character limit per value, and the JSON rules `min_length`, `max_length`, `min`, `max`, `pattern` and `pattern_message`.
- Three placement modes per form: "Standalone Page (has its own URL)", "Widget Only (embed on CMS pages/blocks)" or "Both (standalone page + widget)".
- Standalone pages are served at `/pages/<url_key>` by a custom router, with per-form Meta Title, Meta Description, Meta Keywords, Meta Robots, a canonical link and JSON-LD (WebPage with a ContactPage entity).
- A "Dynamic Form" widget for CMS pages and blocks, with the equivalent `{{widget}}` directive for the WYSIWYG editor.
- CMS content above and below the form, passed through the CMS page filter so directives and widgets work.
- AJAX submission with inline field errors, a success message or a redirect URL.
- File uploads through an AJAX endpoint with an allowed-extension list, a maximum size, a content-type check, and a hard deny-list from Panth_Core for executable extensions.
- Submissions stored in the database with the customer id, email, name, IP address, store id and a status (New, Read, Replied, Closed).
- Admin notification email per form with CC and BCC, and an optional auto-reply to the submitter with placeholders for the name, email, form name and store name.
- Spam protection: a honeypot field and a content guard that silently drops submissions containing link-shortener domains, money-transfer wording, three or more links or a URL in a short field.
- Admin grids for forms (keyword search, filters, mass enable, disable and delete) and submissions (keyword search, filters, mass delete, mass status update) plus a submission detail page with status and internal notes.
- Form key (CSRF) check on every submission.

## Compatibility

| Platform | Versions |
|---|---|
| Magento Open Source | 2.4.4 to 2.4.8 |
| Adobe Commerce | 2.4.4 to 2.4.8 |
| PHP | 8.1, 8.2, 8.3, 8.4 |
| Themes | Hyva, Luma |

Composer constraints from `composer.json`: `magento/module-store` ^101.0, `magento/module-cms` ^104.0, `magento/module-widget` ^101.0, `magento/module-email` ^101.0, `magento/module-backend` ^102.0.

## Requirements

- Magento Open Source or Adobe Commerce 2.4.4 to 2.4.8
- PHP 8.1, 8.2, 8.3 or 8.4
- `mage2kishan/module-core` ^1.0.17 (module `Panth_Core`), installed automatically by Composer
- Magento modules `Magento_Store`, `Magento_Cms`, `Magento_Widget`, `Magento_Email` and `Magento_Backend`

## Installation

```bash
composer require mage2kishan/module-dynamic-forms
bin/magento module:enable Panth_Core Panth_DynamicForms
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

`setup:di:compile` is only needed in production mode. Check the result with:

```bash
bin/magento module:status Panth_DynamicForms
```

## Configuration

Go to Stores > Configuration > Panth Extensions > Dynamic Forms. All settings live under the config path prefix `panth_dynamicforms/`. Settings can be set at default, website and store view scope unless noted.

### General Settings (`panth_dynamicforms/general/`)

| Setting | Default | What it does |
|---|---|---|
| Enable Dynamic Forms | Yes | Master switch. When disabled the router, the page controller, the upload endpoint and the widget block return nothing. |
| Enable reCAPTCHA | No | Present in the configuration form. No code in this version reads this setting. |
| reCAPTCHA Site Key | (empty) | Shown when reCAPTCHA is enabled. Not read by the code in this version. |
| reCAPTCHA Secret Key | (empty) | Shown when reCAPTCHA is enabled. Not read by the code in this version. |
| Allowed File Extensions | jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx | Comma-separated extensions accepted by the upload endpoint. An empty value falls back to the same default list. HTML, SVG, XML, JavaScript and executable types are always refused. |
| Max File Size (MB) | 10 | Maximum upload size in megabytes. An empty or invalid value falls back to 10. |
| Upload Directory | dynamicforms/uploads | Default scope only. Path under `pub/media` for new uploads. Only letters, digits, hyphens, underscores and slashes are accepted; any other value falls back to `dynamicforms/uploads`. |
| Delete Unused Uploads After (hours) | 24 | Default scope only. The hourly cron job `panth_dynamicforms_clean_orphan_uploads` deletes files in the Upload Directory that no submission refers to once they are older than this. Only files with the random 32-character names this module gives uploads are touched. 0 turns the cleanup off. |

### Spam Protection (`panth_dynamicforms/spam/`)

| Setting | Default | What it does |
|---|---|---|
| Enable Honeypot Field | Yes | Adds a hidden decoy field named `contact_url`. A submission that fills it gets the normal success response, but nothing is saved and no email is sent. |
| Block Spam By Message Content | Yes | Drops submissions containing a link-shortener domain, money-transfer wording, three or more links, or a URL in a short field. The sender sees the normal success response. |
| Additional Blocked Terms | (empty) | One term or domain per line, added to the built-in shortener list. Shown when the content guard is enabled. |

Blocked submissions are written to the Magento log at info level with the reason, form id, IP address and a short sample of the content.

### Email Settings (`panth_dynamicforms/email/`)

| Setting | Default | What it does |
|---|---|---|
| Admin Notification Template | Dynamic Forms - Admin Notification | Email template used for the notification sent to the form's admin address. |
| Admin Notification Sender | General Contact | Store email identity used as the sender of the admin notification. |
| Auto-Reply Template | Dynamic Forms - Customer Auto-Reply | Email template used for the auto-reply. |
| Auto-Reply Sender | General Contact | Store email identity used as the sender of the auto-reply. Falls back to the Admin Notification Sender when empty. |
| Allow Auto-Replies | Yes | Master switch for the per-form auto-reply. No stops every auto-reply in this scope. |
| Max Auto-Replies Per Recipient Per Hour | 2 | Auto-replies sent to one address within an hour, counted from stored submissions. The submission is still saved and the admin is still notified. 0 sends none. |
| Max Auto-Replies Per Client IP Per Hour | 5 | Auto-replies triggered from one client IP within an hour. The IP comes from Magento `RemoteAddress`, so configured proxy headers are honoured. 0 sends none. |

### Display Settings (`panth_dynamicforms/display/`)

| Setting | Default | What it does |
|---|---|---|
| Default Form Layout | 1 Column | Page layout of the standalone form pages (1 column, 2 columns with left bar, 2 columns with right bar). |
| Show Form Title | Yes | Used when the block has no `show_title` value, for example on standalone pages. A widget's own parameter takes precedence. |
| Show Form Description | Yes | Used when the block has no `show_description` value, for example on standalone pages. A widget's own parameter takes precedence. |
| Use AJAX Submission | Yes | Passed to the storefront JavaScript as `ajax_enabled`. |
| Loading Button Text | Submitting... | Text shown on the submit button while the form is being sent. |

### Styling (`panth_dynamicforms/styling/`)

| Setting | Default | What it does |
|---|---|---|
| Use Theme Config Colors | Yes | Present in the configuration form. Not read by the code in this version. |
| Primary Color | #0D9488 | Shown when theme config colors are off. Not read by the code in this version. |
| Error Color | #DC2626 | Shown when theme config colors are off. Not read by the code in this version. |
| Success Color | #16A34A | Shown when theme config colors are off. Not read by the code in this version. |
| Input Border Radius | 8px | Shown when theme config colors are off. Not read by the code in this version. |
| Custom CSS | (empty) | Present in the configuration form. Not read by the code in this version. |

The shipped storefront templates carry their own inline CSS. The per-form "Form Style (JSON)" value is saved with the form but is not applied by the shipped templates either. The Luma template uses the CSS variables `--color-primary` and `--color-primary-darker` when the theme defines them.

### Admin menu

The module adds a "Dynamic Forms" group to the admin menu provided by Panth_Core, with three entries: "Manage Forms", "Form Submissions" and "Configuration".

## Usage

### Creating a form

1. Open Manage Forms and click "Add New Form".
2. In the General section fill in "Form Name (Admin)", choose the "Form Usage" (standalone page, widget only, or both), and enter a "URL Key" for page forms. Set "Frontend Title", "Description", "Active" and "Store ID" (0 means all store views). A form with a store ID other than 0 is only shown, rendered as a widget, and accepted on submit and upload in that store view.
3. Optionally add "Content Above Form" and "Content Below Form" (WYSIWYG, CMS directives allowed).
4. In Form Settings set "Submit Button Text", "Success Message" and an optional "Redirect URL". The redirect URL must be a relative path such as `/thank-you` or a full http or https URL on one of the store's own domains. Other values, such as `javascript:` or another site, are refused when saving, and a stored value that does not pass the check is ignored on the storefront.
5. In Email Settings enter "Admin Notification Email" (plus "Admin Email CC" and "Admin Email BCC", comma-separated), and enable "Enable Auto Reply" with "Auto Reply Subject" and "Auto Reply Body" if wanted.
6. In SEO set "Meta Title", "Meta Description", "Meta Keywords" and "Meta Robots" for page forms.
7. In Form Fields add fields, set their type and properties, drag them into order and save.

The URL key is lowercased and reduced to letters, digits, hyphens and underscores. It must be unique among forms and must not collide with an existing URL rewrite. Widget-only forms have no URL key.

### Field types

Text, Textarea, Email, Phone, Number, Dropdown (Select), Multi-Select, Checkbox, Radio Buttons, File Upload, Date, Hidden and WYSIWYG Editor. Dropdown, Multi-Select, Checkbox and Radio Buttons take a list of options. Multi-value fields are stored as a comma-separated string. WYSIWYG Editor renders as a textarea on the storefront. The field builder exposes a "Validation Rules (JSON)" box per field; supported keys are `min_length`, `max_length`, `min`, `max`, `pattern` and `pattern_message`.

### Placing a form on the storefront

- Standalone page: forms of type page or both are served at `/pages/<url_key>` on the storefront. The page title, meta tags, canonical link and JSON-LD come from the form's SEO fields.
- Widget: in Content > Pages or Content > Blocks choose Insert Widget, pick the "Dynamic Form" widget type, then set "Select Form", "Show Form Title", "Show Form Description" and "Template" (Default or Hyva).
- Directive: paste the following into any CMS page or block, replacing `FORM_ID` with the form id shown in the Manage Forms grid.

```
{{widget type="Panth\DynamicForms\Block\Widget\DynamicForm" form_id="FORM_ID" show_title="1" show_description="1"}}
```

- Layout XML: the block `Panth\DynamicForms\Block\Widget\DynamicForm` accepts a `form_id` argument and picks the theme template itself when no template is set.

The widget renders nothing when the module is disabled, the form is inactive, or the form has no fields.

A widget shows the form title as a heading inside the form card when "Show Form Title" is Yes. The page-level h1 heading and the WebPage JSON-LD are only printed on the standalone page at `/pages/<url_key>`, never by a widget on another page.

### Submissions

Every accepted submission is written to `panth_dynamic_form_submission` with one row per field in `panth_dynamic_form_submission_value`. The customer email is taken from the logged-in customer or, for guests, from the first Email field; the customer name from the logged-in customer or from a field labelled "Name", "Full Name" or "Your Name".

Open Form Submissions to see the grid (columns include ID, Customer Name, Customer Email, Status, IP Address and Submitted At) with keyword search (customer name, email, IP address and notes), filters, mass delete and a mass "Update Status" action. "View Submissions" in the Manage Forms grid opens the grid filtered to that form, and the filter is kept while sorting, filtering and paging. The detail page lists every field value, links uploaded files, and lets you change the status (New, Read, Replied, Closed) and save internal notes without leaving the page. There is no export action in this version.

### Notification emails

- Admin notification: sent to the form's "Admin Notification Email" (with CC and BCC) using the "Dynamic Forms - Admin Notification" template. It contains the form name, date, customer name, email, IP address, store name and a table of submitted values; uploaded files appear as download links. Nothing is sent when the address is empty.
- Auto-reply: sent when "Enable Auto Reply" is on, "Allow Auto-Replies" is Yes, a customer email is known and the per-recipient and per-IP hourly limits allow it, using the "Dynamic Forms - Customer Auto-Reply" template. The subject and body accept the placeholders `{{name}}`, `{{customer_name}}`, `{{email}}`, `{{customer_email}}`, `{{form_name}}` and `{{store_name}}`. For guests, `{{name}}` and `{{customer_name}}` are always "Customer"; the name typed into the form is never repeated in the auto-reply. Logged-in customers get the name on their account.

Email failures are logged and do not block the submission.

### File uploads

File Upload fields send the file to `dynamicforms/form/upload` before the form is submitted. The request must carry the form key, the `form_id` of an active form and the `field_name` of an active File Upload field on that form; custom templates that post to the endpoint need to send both. The upload is checked against `Panth\Core\Security\UploadExtensionPolicy`, "Allowed File Extensions", "Max File Size (MB)" and the detected content type of the file. Files are stored under the configured Upload Directory (default `pub/media/dynamicforms/uploads`) with a random file name, and the submission stores the public media URL of the file. On submit, the file value must name a file that exists in that directory.

## Developer Notes

- Module name: `Panth_DynamicForms`
- Composer package: `mage2kishan/module-dynamic-forms` (version 1.2.2)
- Namespace: `Panth\DynamicForms`
- Frontend route: `dynamicforms` (`form/view`, `form/submit`, `form/upload`); standalone pages are matched by `Controller\Router` (sort order 60) on the pattern `pages/<url_key>`
- Admin route: `panth_dynamicforms` (`form/index|new|edit|save|delete|massDelete|massStatus`, `submission/index|view|delete|massDelete|massStatus|updateStatus`)
- Key classes: `Block\Widget\DynamicForm` (widget block, template selection, anti-spam field injection, `getFormConfig()`, `getFieldsJson()`), `Helper\Data` (config access, `sendAdminNotification()`, `sendAutoReply()`, upload paths), `Model\Spam\ContentGuard`, `Model\Form\SaveDataPreparer`, `Block\Adminhtml\Form\FieldBuilder`, UI data providers under `Ui\DataProvider`
- Custom templates: call `$block->getAntiSpamFieldsHtml()` inside the `<form>`; if a template omits it, the block injects the honeypot into the first `<form>` tag it finds
- Widget id: `panth_dynamic_form`; email templates: `panth_dynamicforms_email_admin_email_template`, `panth_dynamicforms_email_autoreply_email_template`
- Event prefixes: `panth_dynamic_form`, `panth_dynamic_form_field`, `panth_dynamic_form_submission`
- ACL resources: `Panth_DynamicForms::main`, `Panth_DynamicForms::form`, `Panth_DynamicForms::submission`, `Panth_DynamicForms::config`
- Database tables (`etc/db_schema.xml`): `panth_dynamic_form`, `panth_dynamic_form_field`, `panth_dynamic_form_submission`, `panth_dynamic_form_submission_value`. Fields, submissions and values cascade on delete of their parent.
- No plugins, preferences or observers are registered. `etc/di.xml` declares grid collection virtual types; `etc/frontend/di.xml` registers the router.
- Unit tests under `Test/Unit` cover `ContentGuard`, `SaveDataPreparer` and the anti-spam field injection.

## Uninstallation

```bash
bin/magento module:disable Panth_DynamicForms
composer remove mage2kishan/module-dynamic-forms
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

The four `panth_dynamic_form*` tables and their data, the `panth_dynamicforms/*` rows in `core_config_data`, and uploaded files under the Upload Directory (default `pub/media/dynamicforms/uploads`) are not removed. Drop or delete them manually if they are no longer needed.

## Support

- Product page: [kishansavaliya.com/magento-2-dynamic-forms.html](https://kishansavaliya.com/magento-2-dynamic-forms.html)
- Contact: [kishansavaliya.com/contact](https://kishansavaliya.com/contact)
- Email: kishansavaliyakb@gmail.com
- Issues: [GitHub issues](https://github.com/mage2sk/module-dynamic-forms/issues)

## Documentation

[USER_GUIDE.md](USER_GUIDE.md) walks an administrator through installation, configuration, creating a form, form types, the field builder, field types, email notifications, SEO settings, widgets, managing submissions, styling, theme compatibility and troubleshooting.

## License

Commercial software license. See [LICENSE.txt](LICENSE.txt) in this repository.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Links

- Website: [kishansavaliya.com](https://kishansavaliya.com)
- All extensions: [kishansavaliya.com/magento-extensions.html](https://kishansavaliya.com/magento-extensions.html)
- GitHub: [mage2sk/module-dynamic-forms](https://github.com/mage2sk/module-dynamic-forms)
- Packagist: [mage2kishan/module-dynamic-forms](https://packagist.org/packages/mage2kishan/module-dynamic-forms)
