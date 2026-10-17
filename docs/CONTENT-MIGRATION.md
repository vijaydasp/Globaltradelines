# Source content and migration boundaries

## Available input

The workspace contained public Django/Wagtail templates, CSS/JavaScript, an old Wagtail admin theme, a company logo and five favicon PNGs. There was no Python model/backend code, Wagtail database export, media upload directory, product dataset, brochure PDF, social API configuration or legal text dataset.

The source inventory in `IMPLEMENTATION-INVENTORY.md` lists the original page/block templates and browser interactions. The Laravel release includes converted public Blade views and a new original admin. The old Wagtail admin and its branding CSS are excluded.

## Seeded from the templates

- Global Trade Lines name, logo and favicon assets.
- Main navigation labels and structure: Home, Products, Brands, Careers, Contact Us and Gallery.
- Products mega-menu groups and brand mega-menu presentation, including View All labels and existing list/filter routes.
- Hero fallback copy: “Industrial & Electrical”, “Solutions”, the project-supply sentence and the quotation/catalogue actions.
- The original external Unsplash hero image URL. It remains a configurable external fallback and is not represented as an uploaded business photograph.
- Trusted Brands and Product Categories section headings/descriptions.
- About heading, highlighted text, descriptive paragraph and CTA.
- Template statistics and trust badges, with the review caveat below.
- Footer headings, company description, developer credit, legal page titles and contact fallback text.
- Existing public interface labels, empty states, careers fallback copy and brochure/search presentation.

Hero fallback links that were `#` in the template now use the existing contact/product routes. Copyright uses the current year. Dynamic page/product titles and trusted configured canonical URLs replace request-derived metadata.

## Business details to verify

The template contains **27 years' experience, 2,500 happy customers, 5,000 products and 100 brands**, plus **ISO Certified Partner** and **Verified Quality** badges. These are source claims, not independently verified business facts or migrated database totals. They are preserved as editable homepage items. Review, correct or unpublish them before launch. Automatic record counts are available as an alternative for suitable statistics.

The footer contains fallback **Kochi, Kerala, India** and **info@globaltradelines.co.in** values. These are seeded as editable values and need owner verification. A fallback phone number present in the templates is deliberately not activated because it could be an example. Business hours, real phone numbers, social accounts, map URLs and notification recipient were not available and remain unset.

The seed's founding year of 1999 comes from the supplied “since 1999” text. It is editable and used only when an automatic years statistic is selected.

## Data that could not be migrated

Products, brands, categories, subcategories, database-sourced hero images, additional product images, about imagery, feature cards, gallery posts/albums, job vacancies and brochures were referenced dynamically but not supplied as actual records. These modules start empty. No invented product, partner, customer, employee or job records are seeded.

Privacy Policy, Terms and Conditions and Data Deletion page titles are created as **drafts**. Their missing legal text is not fabricated. Their navigation/footer destinations remain hidden until the pages are published.

The dashboard reports actual database counts and will therefore differ from the original homepage's manually entered promotional statistics.

## Preservation limits

The public stylesheet and gallery stylesheet are copied from the supplied files. Home and Brands inline styles are extracted into maintained stylesheets. PHP views preserve component classes, page structures, catalogue/sidebar layouts, gallery carousels, mega menus and animation hooks. The original browser code is adapted for database-controlled sliders, statistics, form state and accessibility.

There is no running original backend or original rendered dataset with which to perform a complete pixel-by-pixel comparison. Empty datasets and unavailable images necessarily change what can be displayed. The known homepage fallback image remains an external dependency; choose an uploaded owned image to make that background local.

Known routes and query parameters visible in the frontend are retained. Unknown historical routes from the missing backend cannot be inferred with certainty. Before switching a live domain, obtain its existing URL list and configure any required redirects.

The gallery manages local records and outbound social links. It does not reconstruct missing social-feed integrations or API credentials. The careers page uses email/external applications; no historical submissions were available or migrated.
