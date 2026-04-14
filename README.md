# Panth_DynamicForms - Dynamic Form Builder for Magento 2

A full-featured dynamic form builder module for Magento 2 that supports both Hyva and Luma themes. Create unlimited custom forms with drag-and-drop field builder, embed them as standalone pages or widgets, and manage submissions from the admin panel.

---

## Table of Contents

1. [Features](#features)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Creating a Form](#creating-a-form)
5. [Form Types: Page vs Widget](#form-types-page-vs-widget)
6. [Using Forms as Widgets](#using-forms-as-widgets)
7. [Field Types](#field-types)
8. [Field Builder](#field-builder)
9. [Email Notifications](#email-notifications)
10. [SEO Settings](#seo-settings)
11. [Content Above/Below Form](#content-abovebelow-form)
12. [Managing Submissions](#managing-submissions)
13. [Form Styling](#form-styling)
14. [Theme Compatibility](#theme-compatibility)
15. [Troubleshooting](#troubleshooting)

---

## Features

- Admin CRUD interface for creating and managing forms
- Drag-and-drop field builder with 13 field types
- 3 form usage modes: Standalone Page, Widget Only, or Both
- Frontend rendering for both Hyva (Alpine.js) and Luma (vanilla JS) themes
- AJAX form submission with client-side and server-side validation
- File upload with drag-and-drop and progress bar
- Admin email notifications with full submission details
- Customer auto-reply emails
- Per-form submission management with status tracking and admin notes
- CMS content above/below the form (Page Builder compatible)
- SEO fields: meta title, description, keywords, robots, canonical URL, JSON-LD
- Widget support for embedding forms on any CMS page or block
- Fully responsive design with CSS variables
- Custom URL keys for standalone form pages (e.g., `/pages/contact-us`)

---

## Installation

The module is located at:
```
app/code/Panth/DynamicForms/
```

After placing the files, run:
```bash
php bin/magento setup:upgrade
php bin/magento cache:flush
```

---

## Configuration

Navigate to **Stores > Configuration > Panth Extensions > Dynamic Forms** to configure global settings:

<!-- Screenshot: Admin > Stores > Configuration > Dynamic Forms settings panel -->

- **Enable Module** - Enable/disable the entire Dynamic Forms module
- **Sender Email Identity** - Select which store email identity sends notifications (General, Sales, Support, etc.)
- **Admin Email Template** - Select the email template for admin notifications
- **Auto Reply Email Template** - Select the email template for customer auto-replies

---

## Creating a Form

1. Navigate to **Panth > Dynamic Forms > Manage Forms** in the admin panel

<!-- Screenshot: Admin sidebar showing Panth > Dynamic Forms menu -->

2. Click **"Add New Form"** button

<!-- Screenshot: Form listing page with "Add New Form" button -->

3. Fill in the **General** section:

<!-- Screenshot: General section of form editor -->

| Field | Description |
|-------|-------------|
| **Form Name (Admin)** | Internal name visible only in admin panel |
| **Form Usage** | Choose how the form will be used (see [Form Types](#form-types-page-vs-widget)) |
| **URL Key** | URL path for standalone pages (e.g., `contact-us` creates `/pages/contact-us`) |
| **Frontend Title** | Title displayed to customers on the frontend |
| **Description** | Optional description shown below the title |
| **Active** | Enable/disable the form |
| **Store ID** | Store view assignment (0 = All Store Views) |

4. Add fields using the **Field Builder** (see [Field Builder](#field-builder))

5. Click **Save** or **Save and Continue Edit**

---

## Form Types: Page vs Widget

When creating a form, you must choose a **Form Usage** type:

### Standalone Page
- Creates a dedicated page with its own URL
- Accessible at `/pages/{url_key}` (e.g., `/pages/get-quote`)
- URL Key is **required**
- SEO fields are available (meta title, description, robots, canonical URL)
- Best for: Contact forms, quote request forms, application forms

### Widget Only
- No dedicated URL is created
- URL Key is **not needed** (cleared automatically)
- Form can only be embedded via Widget on CMS pages, CMS blocks, or anywhere widgets are supported
- Best for: Newsletter signups, sidebar forms, popup forms, forms embedded within existing pages

### Both
- Has its own standalone page URL **and** can also be embedded as a widget
- URL Key is **required**
- SEO fields are available for the standalone page
- Best for: Forms that need both a direct link and embedding on other pages

<!-- Screenshot: Form Usage dropdown showing all 3 options -->

---

## Using Forms as Widgets

There are two ways to embed a form as a widget:

### Method 1: Using Page Builder / WYSIWYG Editor (Recommended)

1. Go to **Content > Pages** (or **Content > Blocks**)
2. Edit the page/block where you want the form
3. In the Page Builder or WYSIWYG editor, click **"Insert Widget"**
4. Select **"Dynamic Form"** from the Widget Type dropdown
5. Choose your form from the **"Select Form"** dropdown
6. Optionally configure:
   - **Show Form Title** - Yes/No
   - **Show Form Description** - Yes/No
7. Click **"Insert Widget"** to add it
8. Save the page

<!-- Screenshot: Insert Widget dialog with Dynamic Form selected -->
<!-- Screenshot: Widget options - form selection, show title, show description -->

### Method 2: Using Widget Code (Advanced)

You can paste the widget code directly into any WYSIWYG editor or CMS block:

```
{{widget type="Panth\DynamicForms\Block\Widget\DynamicForm" form_id="1" show_title="1" show_description="1"}}
```

**Parameters:**
| Parameter | Values | Description |
|-----------|--------|-------------|
| `form_id` | Number | The ID of the form (find it in the form listing grid) |
| `show_title` | `1` or `0` | Show or hide the form title |
| `show_description` | `1` or `0` | Show or hide the form description |

**Example:** Embed form ID 3 without title:
```
{{widget type="Panth\DynamicForms\Block\Widget\DynamicForm" form_id="3" show_title="0" show_description="0"}}
```

### Finding the Form ID

The Form ID is visible in the first column of the form listing grid at **Panth > Dynamic Forms > Manage Forms**.

<!-- Screenshot: Form listing grid highlighting the ID column -->

---

## Field Types

The form builder supports 13 field types:

| Field Type | Description | Options |
|------------|-------------|---------|
| **Text** | Single-line text input | Placeholder, default value |
| **Email** | Email input with validation | Validates email format |
| **Phone** | Phone number input | Validates phone format |
| **Number** | Numeric input | Min/max validation |
| **Date** | Date picker | Native date input |
| **Textarea** | Multi-line text area | Rows, placeholder |
| **WYSIWYG** | Rich text area | Same as textarea on frontend |
| **Select** | Dropdown select | Define options (one per line) |
| **Multi-select** | Multiple selection dropdown | Define options (one per line) |
| **Checkbox** | Checkbox(es) | Single or multiple options |
| **Radio** | Radio buttons | Define options (one per line) |
| **File** | File upload with drag-and-drop | Max 10MB, progress bar |
| **Hidden** | Hidden field | Default value only |

---

## Field Builder

The field builder is located in the **Form Fields** section of the form editor.

<!-- Screenshot: Field builder with several fields added -->

### Adding a Field

1. Click the **"Add Field"** button at the bottom of the field builder
2. Configure the field:

| Setting | Description |
|---------|-------------|
| **Field Type** | Select from 13 available types |
| **Label** | The label displayed above the field |
| **Name** | HTML name attribute (auto-generated from label, or custom) |
| **Placeholder** | Placeholder text inside the input |
| **Default Value** | Pre-filled value |
| **Required** | Toggle to make field mandatory |
| **Width** | Full (100%), Half (50%), or Third (33%) |
| **Options** | For select/radio/checkbox: one option per line |

### Field Width / Grid Layout

Fields support 3 width options to create multi-column layouts:
- **Full** - Spans the entire form width (1 column)
- **Half** - Takes 50% width (2 columns side by side)
- **Third** - Takes 33% width (3 columns side by side)

On mobile screens, all fields automatically stack to full width.

<!-- Screenshot: Form with half-width fields showing 2-column layout -->

### Reordering Fields

Drag and drop fields to reorder them. The sort order is saved automatically.

### Deleting a Field

Click the delete/remove button on any field to remove it. The field and its data will be deleted when you save the form.

---

## Email Notifications

Configure email notifications in the **Email Settings** section:

<!-- Screenshot: Email Settings section in form editor -->

### Admin Notification Email

| Field | Description |
|-------|-------------|
| **Admin Notification Email** | Email address that receives submission notifications |
| **Admin Email CC** | CC recipients (comma-separated for multiple) |
| **Admin Email BCC** | BCC recipients (comma-separated for multiple) |

The admin notification email includes:
- Form name
- Submission date
- Customer name and email
- Customer IP address
- Store name
- All submitted field values in a formatted table
- File download links for uploaded files

<!-- Screenshot: Admin notification email received in inbox -->

### Customer Auto-Reply

| Field | Description |
|-------|-------------|
| **Enable Auto Reply** | Toggle to enable/disable auto-reply |
| **Auto Reply Subject** | Email subject line |
| **Auto Reply Body** | Email body text |

**Note:** Auto-reply requires the form to have an email field where the customer enters their email address. The customer_email is captured from the first email-type field in the form.

---

## SEO Settings

Available for forms with **Standalone Page** or **Both** form type. Located in the **SEO** fieldset:

<!-- Screenshot: SEO fieldset in form editor -->

| Field | Description |
|-------|-------------|
| **Meta Title** | Page title for search engines (falls back to Frontend Title) |
| **Meta Description** | Meta description tag |
| **Meta Keywords** | Comma-separated keywords |
| **Meta Robots** | index,follow / noindex,follow / index,nofollow / noindex,nofollow |

### Automatic SEO Features

The module automatically adds:
- **Canonical URL** - `<link rel="canonical" href=".../pages/{url_key}"/>`
- **JSON-LD Structured Data** - WebPage + ContactPage schema with name, description, URL, dates

---

## Content Above/Below Form

Use the **Content** section to add CMS content (HTML, widgets, images) above and/or below the form fields.

<!-- Screenshot: Content section with Page Builder WYSIWYG editor -->

This uses the standard Magento Page Builder / WYSIWYG editor, so you can:
- Add formatted text, headings, images
- Insert other widgets
- Add CMS blocks
- Use Page Builder rows, columns, banners, etc.

---

## Managing Submissions

### Viewing Submissions

1. Navigate to **Panth > Dynamic Forms > Manage Forms**
2. The **Submissions** column shows the count of submissions per form
3. Click **"View Submissions"** in the Actions dropdown to see all submissions for a form

<!-- Screenshot: Form listing showing Submissions count column -->

### Submission List

The submissions grid shows:
- Submission ID
- Customer Name
- Customer Email
- Status (New, Read, Replied, Spam)
- Created date

<!-- Screenshot: Submissions listing grid -->

### Viewing a Submission

Click **"View"** on any submission to see the full details:

<!-- Screenshot: Submission detail view -->

- All submitted field values displayed in a table
- File uploads shown as clickable download links
- Status dropdown to update status (New / Read / Replied / Spam)
- Admin Notes text area for internal notes
- Save Notes button (AJAX save without page reload)

### Submission Status Workflow

| Status | Description |
|--------|-------------|
| **New** | Freshly submitted, not yet reviewed |
| **Read** | Admin has reviewed the submission |
| **Replied** | Admin has responded to the customer |
| **Spam** | Marked as spam |

### Mass Actions

Select multiple submissions and use mass actions:
- **Delete** - Permanently delete selected submissions

---

## Form Styling

The form uses CSS custom properties (variables) for easy theming. The default theme uses a teal/green color scheme.

### CSS Variables

```css
--df-primary: #0D9488;      /* Primary color (buttons, focus rings) */
--df-primary-h: #0F766E;    /* Primary hover color */
--df-error: #DC2626;        /* Error/required indicator color */
--df-success: #16A34A;      /* Success message color */
--df-bg: #fff;              /* Card background */
--df-border: #D1D5DB;       /* Input border color */
--df-focus: #0D9488;        /* Focus ring color */
--df-label: #374151;        /* Label text color */
--df-text: #111827;         /* Input text color */
--df-muted: #6B7280;        /* Description/placeholder color */
--df-input-bg: #fff;        /* Input background */
--df-radius: 8px;           /* Border radius */
--df-gap: 16px;             /* Grid gap between fields */
```

### Custom Styling via Admin

Use the **Styling** section in the form editor to add custom JSON styles:

```json
{"background": "#f5f5f5", "padding": "40px"}
```

---

## Theme Compatibility

### Hyva Theme
- Uses Alpine.js for interactivity
- Template: `form_hyva.phtml`
- Automatically detected via layout handles and theme path

### Luma Theme
- Uses vanilla JavaScript (no jQuery dependency)
- Template: `form.phtml`
- Same visual design as Hyva version
- Fully responsive

The module automatically selects the correct template based on the active theme. No manual configuration needed.

---

## Troubleshooting

### Form not showing on frontend
1. Check that the form is set to **Active = Yes**
2. For standalone pages, verify the **URL Key** is set and **Form Usage** is "Standalone Page" or "Both"
3. For widgets, ensure the widget is properly inserted in the CMS page/block
4. Run `php bin/magento cache:flush`

### Emails not sending
1. Check **Stores > Configuration > Panth Extensions > Dynamic Forms** - ensure module is enabled
2. Verify the form has an **Admin Notification Email** set in Email Settings
3. Check `var/log/system.log` for error messages
4. Verify your mail transport is configured (SMTP, Mailhog, etc.)

### File uploads failing
1. Check PHP `upload_max_filesize` and `post_max_size` settings
2. Ensure the `pub/media/dynamicforms/uploads/` directory exists and is writable
3. Max file size is 10MB by default

### Form showing wrong template (Hyva vs Luma)
The template is auto-detected. If it's wrong:
1. Clear all caches: `php bin/magento cache:flush`
2. Remove generated code: `rm -rf generated/code/Panth/`
3. The detection checks for `hyva_default` layout handle and theme path

### Widget code not rendering
1. Ensure the form_id in the widget code matches an existing, active form
2. The widget code format must be exactly: `{{widget type="Panth\DynamicForms\Block\Widget\DynamicForm" form_id="X" show_title="1" show_description="1"}}`
3. Clear cache after adding the widget

---

## Module Structure

```
app/code/Panth/DynamicForms/
|-- Block/
|   |-- Adminhtml/Form/         # Admin form buttons
|   |-- Widget/DynamicForm.php  # Frontend widget block
|-- Controller/
|   |-- Adminhtml/Form/         # Admin CRUD controllers
|   |-- Adminhtml/Submission/   # Admin submission controllers
|   |-- Form/                   # Frontend controllers (View, Submit, Upload)
|   |-- Router.php              # Custom URL router for /pages/{url_key}
|-- Helper/Data.php             # Email sending, config helpers
|-- Model/                      # Form, Field, Submission models & resource models
|-- Ui/                         # Data providers, grid columns
|-- etc/                        # Module config, DI, routes, ACL, email templates
|-- view/
|   |-- adminhtml/              # Admin layouts, templates, UI components
|   |-- frontend/               # Frontend templates (Hyva + Luma), email templates
```

---

## Database Tables

| Table | Description |
|-------|-------------|
| `panth_dynamic_form` | Form definitions (name, URL key, settings, SEO) |
| `panth_dynamic_form_field` | Form fields (type, label, options, validation) |
| `panth_dynamic_form_submission` | Submission records (customer info, status, notes) |
| `panth_dynamic_form_submission_value` | Individual field values per submission |
