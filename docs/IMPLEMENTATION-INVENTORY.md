# Source inventory

The supplied workspace contains frontend templates and static assets only. There is no Python backend, database export, uploaded product media, brochure PDF, or business-record dataset. No unavailable records are claimed as migrated.

## Public templates

`base.html` supplies metadata, Bootstrap 5.3.3, Font Awesome, typography, search overlay, floating actions, header and footer. Header includes Products and Brands mega menus, mobile toggle, search and quotation link. Footer includes company, categories, contact, legal links and developer credit.

Page layouts: home, general content/about/contact/products, brands, product detail, careers, gallery, brochures, legal pages and search. Reusable blocks: hero, cards, contact, CTA, image/text, rich text, section heading, statistics, testimonials and managed sections.

Homepage: hero slides, scrolling trusted brands, animated solutions/category cards, about text and two images, four statistics and feature cards. Extracted template text is seed content. Database-sourced slides, brands, products, images and section items were unavailable. Original statistics (27 years, 2,500 customers, 5,000 products, 100 brands) and trust badges are preserved as source claims for review, not reported as verified records or dashboard totals.

## Behaviors and assets

- `styles.css`, `gallery.css`, page-local styles: retain public design, breakpoints and component classes.
- `site.js`: dropdown/mobile menus, animated header search, reveal/count-up, hero, brand carousel, sticky header, back-to-top.
- `gallery.js`: gallery scrolling, tabs, video previews and accessibility.
- `quote-form.js`: submission state and confirmation; replace backend URL and CSRF field.
- `product-images.js`: old admin-only bulk images workflow; replace with original custom media manager.
- Assets: one company logo and five favicon PNGs. Hero fallback references an external Unsplash image. No original uploaded media are present.
- Catalogue query parameters: `brand`, `category`, `sub_category`, `view`, `q`; brochure filters: `brand`, `q`, `page`.
- Existing SEO: canonical, Open Graph, Twitter cards and Organization structured data. Replace request-derived canonical with configured trusted application URL.
- All SiteSettings data (contacts, colors, logos, socials, map, hours) become typed settings. Template fallback contact values are recorded as unverified; the phone fallback is not activated.

## Implementation mapping

Laravel 13 with PHP 8.3+, Laravel routing/controllers, Blade views, Eloquent and Laravel's database layer, metadata-driven typed CRUD with relational tables, isolated drafts and revisions, server-enforced roles, typed settings, private media storage and visual builders. Original Wagtail admin files are not included in the release ZIP. The earlier source remains preserved under `storage/native-source-backup` in the development workspace.

Production target: Apache/XAMPP/cPanel. No Node/Python service or build step is required. Point the document root at `public/` where possible; the root rewrite configuration supports shared-hosting subdirectories.
