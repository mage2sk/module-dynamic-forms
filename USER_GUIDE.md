# Panth Dynamic Forms — User Guide

This guide walks a Magento store administrator through every screen
and setting of the Panth Dynamic Forms extension. No coding required.

---

## Table of contents

1. [Installation](#1-installation)
2. [Verifying the extension is active](#2-verifying-the-extension-is-active)
3. [Configuration](#3-configuration)
4. [Creating a form](#4-creating-a-form)
5. [Form types: Page vs Widget](#5-form-types-page-vs-widget)
6. [The field builder](#6-the-field-builder)
7. [Field types reference](#7-field-types-reference)
8. [Email notifications](#8-email-notifications)
9. [SEO settings](#9-seo-settings)
10. [Using forms as widgets](#10-using-forms-as-widgets)
11. [Managing submissions](#11-managing-submissions)
12. [Form styling](#12-form-styling)
13. [Theme compatibility](#13-theme-compatibility)
14. [Troubleshooting](#14-troubleshooting)

---

## 1. Installation

### Composer (recommended)

```bash
composer require mage2kishan/module-dynamic-forms
bin/magento module:enable Panth_Core Panth_DynamicForms
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

### Manual zip

1. Download the extension package zip
2. Extract to `app/code/Panth/DynamicForms`
3. Make sure `app/code/Panth/Core` is also present
4. Run the same `module:enable ... cache:flush` commands above

---

## 2. Verifying the extension is active

After installation, confirm:

1. **Configuration page exists** — Stores > Configuration > Panth Extensions > Dynamic Forms
2. **Admin menu exists** — Panth > Dynamic Forms > Manage Forms
3. **Module is enabled** — `bin/magento module:status Panth_DynamicForms`

---

## 3. Configuration

Navigate to **Stores > Configuration > Panth Extensions > Dynamic Forms**.

### General group

| Setting | Default | Description |
|---|---|---|
| **Enable Module** | Yes | Master switch for the entire Dynamic Forms module |
| **Allowed File Extensions** | jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,csv,txt,zip | Comma-separated list of allowed upload extensions |
| **Max File Size (MB)** | 5 | Maximum file upload size in megabytes |

### Email group

| Setting | Default | Description |
|---|---|---|
| **Sender Email Identity** | General Contact | Store email identity used as the From address |
| **Admin Email Template** | Default admin notification | Template for admin notification emails |
| **Auto Reply Email Template** | Default auto-reply | Template for customer auto-reply emails |

### Display group

| Setting | Default | Description |
|---|---|---|
| **Enable AJAX Submit** | Yes | Submit forms via AJAX without page reload |

---

## 4. Creating a form

1. Navigate to **Panth > Dynamic Forms > Manage Forms**
2. Click **Add New Form**
3. Fill in the General section:

| Field | Required | Description |
|---|---|---|
| **Form Name (Admin)** | Yes | Internal name visible only in admin |
| **Form Usage** | Yes | Page, Widget, or Both |
| **URL Key** | Conditional | Required for Page and Both types (e.g., `contact-us` creates `/pages/contact-us`) |
| **Frontend Title** | No | Title shown to customers |
| **Description** | No | Description shown below the title |
| **Active** | Yes | Enable/disable the form |
| **Store ID** | Yes | Store view assignment (0 = All Store Views) |

4. Add fields using the Field Builder (see section 6)
5. Configure email settings (see section 8)
6. Configure SEO settings if needed (see section 9)
7. Click **Save** or **Save & Continue Edit**

---

## 5. Form types: Page vs Widget

### Standalone Page
- Creates a dedicated page at `/pages/{url_key}`
- URL Key is required
- SEO fields available
- Best for: contact forms, quote forms, application forms

### Widget Only
- No dedicated URL created
- URL Key not needed
- Embed via Widget on CMS pages, blocks, or anywhere widgets are supported
- Best for: newsletter signups, sidebar forms, popup forms

### Both
- Has standalone page URL AND can be embedded as widget
- URL Key required
- Best for: forms that need both a direct link and CMS embedding

---

## 6. The field builder

The field builder is in the **Form Fields** section of the form editor.

### Adding a field

1. Click **+ Add New Field** at the bottom
2. Set the field type, label, and name
3. Configure required, width, placeholder, and default value
4. For select/radio/checkbox types, add options using the options builder
5. Click **Show Advanced Settings** for CSS class and validation rules

### Reordering fields

Drag fields by the handle (hamburger icon) to reorder. Sort order updates
automatically.

### Deleting a field

Click the X button on any field. The field is removed when you save.

### Field width / grid layout

- **Full** — 100% width (1 column)
- **Half** — 50% width (2 columns side by side)
- **Third** — 33% width (3 columns side by side)

On mobile, all fields stack to full width automatically.

---

## 7. Field types reference

| Type | Description | Notes |
|---|---|---|
| **Text** | Single-line text input | Placeholder and default value supported |
| **Email** | Email input | Validates email format on client and server |
| **Phone** | Phone input | Validates phone format (7-20 digits with +/-/() allowed) |
| **Number** | Numeric input | Validates numeric value |
| **Date** | Date picker | Uses native browser date input |
| **Textarea** | Multi-line text area | Resizable, configurable rows |
| **WYSIWYG** | Rich text area | Renders as textarea on frontend |
| **Select** | Dropdown | Define options with label and value |
| **Multi-Select** | Multiple selection dropdown | Hold Ctrl/Cmd to select multiple |
| **Checkbox** | Checkbox(es) | Single toggle or multiple options |
| **Radio** | Radio buttons | Define options, only one selectable |
| **File** | File upload | Drag-and-drop, progress bar, AJAX upload |
| **Hidden** | Hidden field | Default value only, not visible to user |

---

## 8. Email notifications

Configure in the **Email Settings** section of the form editor.

### Admin notification

| Field | Description |
|---|---|
| **Admin Notification Email** | Email address that receives submissions |
| **Admin Email CC** | CC recipients (comma-separated) |
| **Admin Email BCC** | BCC recipients (comma-separated) |

The notification includes: form name, submission date, customer info,
IP address, store name, and all field values in a formatted table.
File uploads appear as clickable download links.

### Customer auto-reply

| Field | Description |
|---|---|
| **Enable Auto Reply** | Toggle on/off |
| **Auto Reply Subject** | Email subject line |
| **Auto Reply Body** | Email body text |

Auto-reply requires an email-type field in the form where the customer
enters their email address.

---

## 9. SEO settings

Available for forms with Standalone Page or Both type. Located in
the **SEO** fieldset.

| Field | Description |
|---|---|
| **Meta Title** | Page title for search engines (falls back to Frontend Title) |
| **Meta Description** | Meta description tag |
| **Meta Keywords** | Comma-separated keywords |
| **Meta Robots** | index,follow / noindex,follow / etc. |

Automatic SEO features:
- Canonical URL tag added automatically
- JSON-LD structured data (WebPage + ContactPage schema)

---

## 10. Using forms as widgets

### Method 1: Page Builder / WYSIWYG Editor

1. Edit a CMS page or block
2. Click **Insert Widget**
3. Select **Dynamic Form** from Widget Type
4. Choose your form from the dropdown
5. Optionally toggle Show Title and Show Description
6. Click **Insert Widget** and save

### Method 2: Widget code

Paste into any WYSIWYG editor or CMS block:

```
{{widget type="Panth\DynamicForms\Block\Widget\DynamicForm" form_id="1" show_title="1" show_description="1"}}
```

| Parameter | Values | Description |
|---|---|---|
| `form_id` | Number | ID of the form (visible in the form listing grid) |
| `show_title` | 1 or 0 | Show or hide the form title |
| `show_description` | 1 or 0 | Show or hide the description |

---

## 11. Managing submissions

### Viewing submissions

1. Go to **Panth > Dynamic Forms > Manage Forms**
2. The **Submissions** column shows the count per form
3. Click **View Submissions** in the Actions dropdown

### Submission detail view

Click **View** on any submission to see:
- All submitted field values in a table
- File uploads as clickable download links
- Status dropdown (New / Read / Replied / Closed)
- Admin Notes text area with AJAX save

### Status workflow

| Status | Description |
|---|---|
| **New** | Freshly submitted, not yet reviewed |
| **Read** | Admin has viewed the submission (auto-set on view) |
| **Replied** | Admin has responded to the customer |
| **Closed** | Submission is resolved |

### Mass actions

Select multiple submissions and use Delete mass action for bulk cleanup.

---

## 12. Form styling

The form uses CSS custom properties for easy theming:

```css
--df-primary: #0D9488;      /* Primary color */
--df-primary-h: #0F766E;    /* Primary hover */
--df-error: #DC2626;        /* Error color */
--df-success: #16A34A;      /* Success color */
--df-border: #D1D5DB;       /* Input border */
--df-radius: 8px;           /* Border radius */
--df-gap: 16px;             /* Grid gap */
```

Override these in your theme CSS to match your store design.

---

## 13. Theme compatibility

### Hyva Theme
- Uses Alpine.js for all interactivity
- Template auto-selected: `form_hyva.phtml`
- No configuration needed

### Luma Theme
- Uses vanilla JavaScript (no jQuery dependency)
- Template auto-selected: `form.phtml`
- Same visual design as Hyva version

The module automatically detects the active theme and selects the
correct template. No manual configuration required.

---

## 14. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| Form not showing on frontend | Form is inactive or URL key not set | Check Active = Yes and URL Key is set for Page/Both types |
| Widget not rendering | Wrong form_id or form is inactive | Verify form_id matches an active form, clear cache |
| Emails not sending | Missing admin email or mail transport | Set Admin Notification Email in form settings, verify SMTP config |
| File uploads failing | PHP limits too low | Increase `upload_max_filesize` and `post_max_size` in php.ini |
| Wrong template (Hyva vs Luma) | Cache | Run `bin/magento cache:flush` and `rm -rf generated/code/Panth/` |
| URL key conflict | Key already used by another form or URL rewrite | Choose a different URL key |

---

## Support

For all questions, bug reports, or feature requests:

- **Email:** kishansavaliyakb@gmail.com
- **Website:** https://kishansavaliya.com
- **WhatsApp:** +91 84012 70422

Response time: 1-2 business days for paid licenses.
