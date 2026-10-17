# Managing your website

Sign in at your website's `/admin/login`. The sidebar groups everyday tasks by website content, catalogue, settings and administration. Counts on Overview come from MySQL. Empty modules are expected until you add the business data that was missing from the supplied frontend files.

## Homepage and pages

Open **Homepage** to see named section cards in website order. Edit a section's heading, description, images, button labels and links, then save a draft or publish. Use **Manage slides** for hero backgrounds, video/poster, text alignment, overlay and slide duration. Hero autoplay is a section setting.

Use **Manage items & images** for manual category cards, selected brands, statistics, trust badges, features and testimonials. Brand/category sections can instead pull published catalogue records automatically. Automatic statistics count published products/brands/categories or calculate years from Company Information's founding year. Other numbers are entered manually.

Drag cards to reorder them, or use the arrow buttons. Ordering saves immediately. Changing a section to Unpublished hides it. To delete a section, first remove its related items/slides; the database protects related content from accidental deletion.

Under **Pages**, create general or specialized pages, set a unique slug, edit the page hero and rich text, and use the page's **Edit page sections** link to add reusable layouts. The Homepage's primary hero comes from its Hero Slider section. A page can be duplicated; the copy and its content remain hidden behind the draft parent until you publish it. The original homepage cannot be deleted or replaced by a second homepage.

The rich-text toolbar supports headings, paragraphs, emphasis, links and lists. Pasted content is treated as text and HTML is sanitized on the server. You do not need to edit raw HTML.

## Drafts, preview and revisions

- **Save draft** stores a separate working copy and leaves the existing published record intact.
- **Publish changes** applies the record's edited values to the live website.
- **Unpublish** hides that record from public routes and queries.
- **Publish on (UTC)** optionally keeps a published record hidden until the selected time.
- **Preview** on a page/product opens an authenticated draft preview. The link does not give anonymous visitors access.
- **Revision history** restores an earlier content version as a draft, so you can review it before publishing.

Drafts belong to individual records. A page, each section, each slide and each item can have separate drafts. Publishing the page does not automatically publish its children. A published child of an unpublished page is still inaccessible publicly. A section's public status controls the visibility of its slides/items.

Navigation, footer records, settings, media metadata/replacement, product images/specifications and ordering use immediate saves. Replacing a shared media file changes every place that uses it. Upload a new file and select it in a draft when you want the existing file to remain unchanged.

## Navbar and mega menus

Go to **Navbar & Menus**. Add a named menu, then add its menu items. Multiple enabled menus contribute to the header.

For an item, choose its label, icon, style, destination and device visibility. Select a parent by name to nest it. The server rejects cycles and parents in a different menu. A parent must be a Dropdown or Mega menu.

For a Products-style mega menu, create groups and choose automatic Categories, Subcategories, Featured Products, Recently Added Products or Brands. Set a display limit, heading, image/icon and View All link. For manual groups, select their links individually. The Brands layout uses the original scrolling brand presentation. Group and item order can be changed using drag handles or arrow buttons.

Page, category, subcategory, brand and product destinations use record selectors. New pages appear in these selectors immediately, but links to unpublished destination records are not rendered publicly. Custom URLs must use a supported scheme or a site path beginning with `/`.

## Catalogue

Create **Brands** and **Categories**, then **Subcategories** with a selected parent. Optional brand associations are available for subcategories.

In **Products**, enter a name and slug, select classification, choose/upload the main image, edit formatted description and badge, and configure links, SEO and publishing. The subcategory must belong to the selected category. Save the product before adding its additional images and specifications through the editor's related controls. Additional image/specification changes are immediate for a published product.

The public catalogue supports `brand`, `category`, `sub_category`, `q`, `view` and `page` parameters. Existing product list/filter routes are retained where their behavior could be determined from templates. Missing original backend URL definitions prevent guarantees about unknown historical product URLs; document any old URLs before deploying and add redirects if needed.

### CSV import/export

Export products to obtain the supported column headings. The CSV covers catalogue fields; it is not a full database backup of SEO, revisions, additional images or specifications.

Prepare brands/categories/subcategories before importing. Upload images in Media Library first and put their original filename or media ID in `image`; the importer does not fetch arbitrary remote files. Use `slug` to identify an existing product, especially when its web address differs from its name. Without a slug, one is generated from `product`.

Upload up to 1,000 rows / 2 MB, inspect the validation report and proposed creates/updates, then confirm. Invalid rows are reported and prevent the preview from being committed. Existing-record updates require the separate update checkbox. Changes to an existing product after preview trigger a conflict rather than silently overwriting newer work. The final summary reports created, updated and unsaved rows. `is_active=1` publishes the imported product; use `0` for unpublished records.

## Media and gallery

The **Media Library** supports multiple file selection, drag/drop uploads, progress, thumbnails, searching, type filtering, metadata, replacement and URL copying. Allowed formats are JPEG, PNG, WebP, GIF, PDF, MP4 and WebM. File extension, MIME type, size and relevant content signatures are checked server-side. SVG, executable and mismatched files are rejected.

Edit media to set a useful title and alternative text. The usage panel identifies current references; files in use by content or drafts cannot be deleted. Seeded logo/favicon assets remain ordinary public static files; newly uploaded files live in private storage and are served by the PHP media endpoint when authorized or publicly used.

In **Gallery**, create albums, then image/video entries with titles, captions, optional category and post links. Use the multiple-image upload panel to create draft entries in a chosen album. Review and publish each entry. Albums can represent a social platform and link to its profile, but the CMS does not fetch social feeds automatically. Uploaded gallery images support a lightbox; external post/video links open their destination.

## Brochures and careers

Brochures use a required PDF from Media Library, optional cover image, description, brand/category and publishing state. Unpublished documents are not offered through the public brochure download route.

Careers include department, location, employment type, experience, rich-text description/responsibilities/qualifications and an application email or URL. Past-deadline jobs are removed from public openings. Applications are made by email or the configured external link; this implementation has no CV-upload/application-storage module.

## Enquiries

Submissions are stored before optional email notification is attempted. The admin list supports search, status/date filters and CSV export. Open an enquiry to mark it Unread, Read or Replied and manage internal notes. Customer details are available only to roles with enquiry permissions.

The notification column shows `disabled`, `accepted` or `failed`. `accepted` means the configured PHP mail transport accepted the notification; it is not delivery confirmation. Internal notes are not public. Related records may need removal before deleting a parent enquiry.

## Footer and settings

**Footer Management** controls named columns, links, order and visibility. Manual columns use selected pages/catalogue destinations or custom URLs; automatic columns show categories, brands or contact details. Bottom placement preserves the separate legal-link area. Logos, description, copyright and developer credit are configured in the linked footer/company settings.

Use Company Information, Contact Information, Header Settings, Social Media, Appearance, SEO and Integrations for their named settings. Website Labels controls public interface copy, including search, catalogue empty states, careers text, form labels and success messages. Appearance exposes supported theme colors rather than arbitrary CSS.

SEO editors support title/description, canonical URL, social title/description/image and noindex. Sitemap and robots output are generated dynamically. Structured data is generated from configured company and product content; there is no arbitrary script-injection field.

## Users and permissions

Initial roles are Super Administrator, Content Editor, Product Manager and Enquiry Manager. Their server-enforced permissions determine which modules they can read or change. The Users permission is administrative authority: it can manage accounts and role assignments, so grant it only to trusted administrators.

Change your own password through the profile menu. Super Administrators can create users and define roles. The system blocks disabling/deleting your own active account and prevents removing the last active Super Administrator. Password/account updates invalidate previous sessions.

Activity Logs records administrative mutations and sign-ins. It is read-only in the admin and does not include plaintext passwords or reset tokens. Back up your database and private upload directory regularly.
