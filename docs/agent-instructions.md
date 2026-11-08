# Pencilino: Instructions for AI Agents Building the Theme

<!-- pencilino:file-notice -->
> **Read this first.** This file holds the default instructions only. The site owner can add their own instructions in WordPress (Pencilino > AI instructions). Those additions are not in this file, and they win when they conflict with it. Get the complete instructions with the `pencilino/get-instructions` ability. If you cannot reach it, stop and tell the user.
<!-- /pencilino:file-notice -->

## Role

You are building or modifying a custom WordPress theme that integrates with the Pencilino plugin.

This is an AI-first alternative to the traditional page-builder workflow. You handle design, layout, new elements, functionality, and initial content in theme code. Pencilino gives clients protected frontend editing for all static site content after the theme is built.

Build normal, maintainable WordPress theme code. Pencilino is a controlled content layer, not a drag-and-drop layout system.

These instructions change over time. Every time you change an existing Pencilino theme, read them again, even if you built the theme yourself. Bring the parts you work on in line with the current instructions, and say in your summary what you changed beyond the request.

## Choose the starting path

Every Pencilino theme starts through one of two paths. Determine which path applies before coding. If the user has not made it clear, ask them to choose.

### Path 1: Start from an idea

Use this path when there is no finished HTML template. Work from the user's design brief, content, brand assets, references, and functional requirements.

1. Inspect everything the user supplied and the existing WordPress installation.
2. Confirm the intended pages, primary action, navigation approach, dynamic content, integrations, and important design constraints.
3. Create the information architecture and visual direction before building when those decisions are not already defined.
4. Build the custom theme and its responsive behaviour directly in WordPress.
5. Apply the complete Pencilino contract in this document from the first field onward.

Do not require an HTML prototype as an intermediate deliverable. It is valid to design and build the WordPress theme directly.

### Path 2: Convert an HTML design

Use this path when the user supplies an existing HTML design. That can be a single HTML file, an HTML prototype with several files, or a whole folder. Use everything that comes with it: HTML, CSS, JavaScript, fonts, images, and any build files or dependencies. If something it needs is missing, ask for it.

Before converting, inventory:

- every intended public page and template;
- every section, heading, paragraph, label, button, link, image, form, and navigation item;
- local and external CSS, JavaScript, fonts, images, icons, and other assets;
- responsive behaviour, animation, and interactive states;
- content that is static, content that belongs to WordPress, and content owned by another plugin or API.

Then split the static template into maintainable WordPress theme files, enqueue its assets through WordPress, preserve the design and responsive behaviour, and replace static client-facing content with the correct Pencilino integration. Do not paste the complete HTML into Gutenberg, a single PHP string, or one unmaintainable template file.

### Requirements shared by both paths

The agent needs access to the complete WordPress project, not only a screenshot or isolated HTML file. It also needs a way to create pages, configure WordPress, and import Media Library files through WP-CLI, an API, an integration, or the WordPress admin.

If optional installed skills are relevant to the task, name the ones you propose to use and ask the user before applying them.

Both paths finish with the same required checks at the end of this document.

## Pencilino theme compatibility contract

Pencilino does not automatically make an ordinary WordPress theme editable. You must build and maintain a Pencilino-compatible theme that follows this contract from the start.

The plugin and theme have separate responsibilities:

- Pencilino provides the frontend editing interface, storage, validation, permissions, and WordPress Media Library integration.
- The theme registers every static client-facing content field, supplies stable field IDs, and defines the layout and presentation constraints around those fields.
- WordPress, ACF, JetEngine, WooCommerce, and other providers continue to own their dynamic or structured content.

Do not finish a theme by adding Pencilino to only the most important headings or images. Pencilino compatibility applies to the complete rendered theme. Every new section or component added during later development must follow the same contract.

## Pencilino v2 client workflow

Focus mode and private comments do not require new theme integration. Keep the existing managed-region links pointed at the real WordPress, ACF, or WooCommerce editor. Static Pages stay editable through the pencil. Clients keep their normal WordPress role (Editor for pages and posts, Shop manager for WooCommerce products), and Focus mode only gives them a simpler top bar and a start on the website.

Comments are private feedback owned by Pencilino, separate from public WordPress comments and saved text changes. They can attach to any visible element. Prefer stable element IDs where available so feedback markers can find the same section after layout changes; this is optional, not a compatibility requirement.

## Hidden states for editors

Some elements only appear for visitors after an action, such as a form error, a thank-you message or a "Copied" label. Give these elements the `pencil-reveal` class together with the `hidden` attribute.

Pencilino shows them with a dashed outline, only for editors who switched on Pencilino > Features > Show hidden states. The choice is saved per user and is off by default. The plugin adds the `pencil-show-hidden` class to the body through `body_class()`, so the theme must call `body_class()`.

The reveal rule lives in the plugin. Do not add reveal CSS to the theme. A theme may set `--pencil-reveal-color` to change the outline color.

## Required safe theme integration

The website must keep rendering its initial content if Pencilino is temporarily unavailable or deactivated. Never let a missing plugin function cause a fatal error on the public site.

- Put theme-prefixed wrapper functions in `functions.php` for text, rich text, buttons, images, and managed regions.
- Inside each wrapper, call the matching Pencilino helper only when `function_exists()` confirms that it is available.
- Otherwise render the supplied default value with the correct escaping and the same theme-controlled classes and surrounding layout.
- Do not redeclare functions whose names begin with `pencil_`; those belong to the plugin.
- After building, temporarily deactivate Pencilino and verify that every public page still loads without PHP errors. Then reactivate Pencilino and verify editing.

Minimal text-wrapper pattern:

```php
function project_text( $id, $args = array() ) {
    if ( function_exists( 'pencilino_text' ) ) {
        pencilino_text( $id, $args );
        return;
    }

    echo esc_html( isset( $args['default'] ) ? $args['default'] : '' );
}
```

Use a prefix derived from the real theme slug instead of `project_`, and apply the same pattern to the other Pencilino field types. The fallback exists for resilience; Pencilino remains a required plugin for frontend editing.

## Content continuity

You may write the initial content for a Pencilino-owned field by providing its `default` value. If a client later saves a replacement through Pencilino, that saved value becomes the current content and must take precedence over the theme default.

When continuing development:

- Preserve existing Pencilino field IDs and field types.
- Treat the rendered or saved Pencilino value as the latest version of the content.
- Do not change a theme default with the expectation that it will overwrite a client's saved value.
- Keep client content intact while changing layout, markup, styling, or functionality around it.
- Use a new Pencilino field only for genuinely new content, with a stable descriptive ID and an appropriate initial default.

Content continuity depends on stable field IDs and Pencilino storage. It does not require copying content into theme files or synchronising Pencilino with the AI tool.

## Non-negotiable rules

1. The theme owns layout, HTML structure, CSS, JavaScript, responsive behaviour, animations, and functionality.
2. Every content value has one owner and one source of truth.
3. Use Pencilino helpers for every static client-facing content value authored in the theme. You may provide the initial value, but a client's saved Pencilino value overrides that default.
4. Never copy, mirror, or synchronise data from ACF, JetEngine, WooCommerce, WordPress, or external APIs into Pencilino.
5. Never make layout, CSS, HTML, classes, spacing, or typography editable through Pencilino.
6. Use semantic HTML and keep the markup around Pencilino helper calls under theme control.
7. Do not add editor-only interfaces for visitors; Pencilino handles edit-mode metadata and assets.
8. Import every static client-facing image into the WordPress Media Library and render it through a stable Pencilino image field. Never leave such images in the theme directory.
9. Give every Pencilino image field a design-defined aspect ratio and enforce a fixed image frame. Replacing an image must never change the component's dimensions or page layout.
10. Create a normal WordPress Page record for every public page. A theme template by itself is not a complete page.
11. Make the homepage a real published Page and assign it as the static front page in WordPress.
12. Do not use Gutenberg block content to build Pencilino-managed page layouts.

## Shared parts: header, footer and repeated sections

A part that appears on more than one page lives in exactly one file. The default for every page, including every new page, is the one global header and the one global footer.

- The `<head>`, site header and navigation go in `header.php`. The site footer and `wp_footer()` go in `footer.php`. Every template calls `get_header()` and `get_footer()`.
- Do not paste the header, footer or any repeated section into each template. A new page uses the global ones without being asked.
- Only when the user asks for something different on a specific page, for example a landing page with a simpler menu or no navigation, build that variation for that page only. Prefer an argument over a copy: `get_header( null, array( 'solid' => false ) )`, read in `header.php` as `$args`. If the difference is bigger, use a named file (`header-landing.php` with `get_header( 'landing' )`). Say in your summary which pages differ and why.
- Set page-type body classes with the `body_class` filter in `functions.php`, not in each template.
- Other repeated sections (call-to-action band, newsletter block, card) go in `template-parts/` and are loaded with `get_template_part()`.
- Pencilino fields and managed regions in a shared part keep one ID, so the client edits them once for the whole site.
## Pages, URLs, and templates

WordPress Pages remain the source of truth for page administration and routing even though Gutenberg is not used as the page builder.

For every public page:

1. Create or reuse a normal WordPress Page record.
2. Set its administrative title, slug, publication status, parent, and template assignment.
3. Build its layout with the correct theme template, using WordPress template hierarchy conventions.
4. Register all static rendered content through Pencilino fields.
5. Verify that the page appears under **Pages** and that its public URL renders the intended template.

For the homepage:

1. Create or reuse a published Page named **Home**.
2. Set WordPress to display a static page and assign **Home** as `page_on_front`.
3. Put the homepage layout in `front-page.php`, not in the fallback `index.php`.
4. Keep the Page's block content empty and hide the Gutenberg content editor when it is not used.

The WordPress Page record owns its dashboard entry, status, slug, URL, hierarchy, and template assignment. The theme owns the layout. Pencilino owns the static content shown by the template. Do not store a full rendered page in `post_content`, and do not leave a public template without a corresponding Page record.

If several pages share one design, such as individual coffee-flavour pages, create a Page record for every flavour and use a stable page-specific Pencilino scope so their content remains separate. Use a custom post type instead only when the items form a genuine structured collection rather than normal site pages.

To scope a field to its page, pass `'scope' => 'page'` to every Pencilino helper in the shared template. Pencilino then stores a separate value for each page, under `page-{page ID}.{field ID}`. Decide this when the field is first created: adding or removing `scope` later changes the stored ID and disconnects any saved client value.

## Templates beyond the pages

A theme is not finished with only its pages. Also give the theme a template for each of these, styled with the same design system, so the site never falls back to the plain WordPress look:

- `404.php`: a friendly message and a link back to the homepage.
- `search.php` and the search form: the results, and a clear message when nothing is found.
- `home.php`: the blog index, only when the site has posts. A site without a blog does not need it.
- `single.php`, `archive.php` and pagination: only when the site has posts or another content type that visitors can browse.

The text on these templates follows the same ownership rules as everything else: static text goes through Pencilino helpers.

## Navigation

Confirm which navigation approach the user wants before implementing it. Pencilino supports both approaches.

### Theme-defined navigation

Use this for a tightly controlled, fully agentic site where navigation structure belongs to the theme.

- Create every destination as a real WordPress Page.
- Generate internal URLs with `home_url()`, `get_permalink()`, or other WordPress URL functions. Never hardcode the site's domain.
- Render static navigation labels with stable `pencilino_text()` fields through the theme's safe wrapper.
- Use `pencilino_button()` when both the label and URL should be client-editable. It outputs an anchor and may be styled as a plain text link when appropriate.

### WordPress-managed navigation

Use this when the user wants to manage menu items, labels, URLs, nesting, and order through WordPress.

1. Register named menu locations with `register_nav_menus()`.
2. Render them with `wp_nav_menu()` using semantic theme-controlled containers and classes.
3. Create and assign the initial menu through WordPress rather than relying on an accidental page-list fallback.
4. Treat the menu data as WordPress-owned content. Do not duplicate its labels or URLs in Pencilino fields.
5. Mark the rendered menu as one WordPress-managed region and link its edit action to the appropriate WordPress menu screen when that screen exists.
6. Verify that adding, removing, nesting, reordering, and renaming menu items in WordPress updates the frontend correctly.

Example registration and rendering:

```php
register_nav_menus(
    array(
        'primary' => __( 'Primary navigation', 'project-theme' ),
    )
);

project_managed_region_open(
    array(
        'provider' => 'wordpress',
        'label'    => __( 'Editable in WordPress Menus', 'project-theme' ),
        'edit_url' => admin_url( 'nav-menus.php' ),
    )
);

wp_nav_menu(
    array(
        'theme_location' => 'primary',
        'container'      => false,
        'fallback_cb'    => false,
    )
);

project_managed_region_close();
```

In production, call the managed-region helpers through the theme's safe wrappers described above.

## Theme identity and presentation

Every generated theme must look intentional in **Appearance → Themes**.

- Use a website-specific theme name, such as `{Website name} by Pencilino`. Never leave names such as “Test Theme”, “AI Theme”, or “HTML Conversion”.
- Add an accurate description, version, author, and text domain to the `style.css` theme header.
- Use a stable, sanitized folder name and matching text domain.
- Add a 1200 × 900 `screenshot.png` to the theme root.
- Prefer a custom screenshot that reflects the finished website and includes a subtle Pencilino maker mark.
- If a custom screenshot cannot be created, copy `assets/img/default-theme-screenshot.png` from the active Pencilino plugin into the theme root as `screenshot.png`.
- Never reference the screenshot from the plugin directory. Copy it so the theme remains a complete package.
- Logged-in clients and admins see the WordPress toolbar at the top of the website. WordPress pushes the page down to make room, but a header with `position: absolute`, `fixed`, or `sticky` ignores that space. Give such a header `top: var(--wp-admin--admin-bar--height, 0px)` so it starts below the toolbar. WordPress sets this variable itself; without a toolbar it is 0.

## Decide who owns content before writing code

Use this decision process for every content region:

```text
Does an existing system own this value?
│
├─ Yes: render that system's value normally.
│        Mark the surrounding output as a Pencilino managed region.
│        Do not create a Pencilino field for it.
│
└─ No: is it static client-facing site content?
         │
         ├─ Yes: render it with the appropriate Pencilino helper.
         │       This includes labels, numbering, captions, and decorative text.
         │       Provide initial content as its default when needed.
         │       Preserve any saved client value during later development.
         └─ No: keep non-content markup or configuration under theme control.
```

Existing owners include WordPress posts, options, ACF, JetEngine, WooCommerce, and external APIs.

Do not use visual importance or intended meaning to decide whether static content is editable. If changing a hard-coded value would change what a visitor reads, register it with Pencilino.

## Pencilino-owned content

Use descriptive, stable IDs in this format:

```text
{scope}.{section}.{field}
```

Examples:

```text
home.hero.title
home.hero.description
home.hero.cta
global.contact.phone
```

Do not use random IDs, presentation-only IDs, or IDs that change when markup changes.

The field ID is also what allows development to continue from the client's latest content. Renaming or replacing it creates a different field and can disconnect the saved value.

Use the type that matches the content. The example below uses `project_` as a placeholder theme prefix; replace it with the real theme prefix and implement these wrappers as described above.

```php
<section class="hero">
    <div class="hero__content">
        <h1>
            <?php project_text( 'home.hero.title', [
                'label'      => 'Hero heading',
                'default'    => 'Build better websites',
                'max_length' => 80,
            ] ); ?>
        </h1>

        <?php project_richtext( 'home.hero.description', [
            'label'   => 'Hero description',
            'default' => 'We design and build websites that are easy to maintain.',
            'class'   => 'hero__description',
        ] ); ?>

        <?php project_button( 'home.hero.cta', [
            'label'   => 'Primary call to action',
            'default' => [
                'text' => 'Start a project',
                'url'  => '/contact',
            ],
            'class'   => 'site-button site-button--primary',
            'after'   => '<span class="site-button__icon" aria-hidden="true">→</span>',
        ] ); ?>
    </div>

    <div class="hero__visual">
        <?php project_image( 'home.hero.image', [
            'label'           => 'Hero image',
            'aspect_ratio'    => '3/2',
            'object_fit'      => 'cover',
            'object_position' => '50% 50%',
        ] ); ?>
    </div>
</section>
```

Keep the surrounding section, classes, and component structure in the theme. Pencilino field options may declare required content constraints such as an image slot's aspect ratio, fit, and initial focal position; all other styling and layout remain in the theme.

### Field types

- `pencilino_text()`: one line of plain text, such as headings, labels, numbers, and navigation labels. It outputs only the text, so wrap it in the element the design needs. Options: `label`, `default`, `max_length` (default 160).
- `pencilino_richtext()`: paragraphs with bold, italic, links, and bulleted or numbered lists, such as descriptions and body copy. It always outputs `<div class="pencil-richtext">` plus any `class` you pass, for visitors and editors alike. Place it only where a block element is allowed, never inside `<p>`, a heading, `<span>`, or `<a>`. Style its inner `p`, `ul`, `ol`, and `a` elements in the theme. Options: `label`, `default` (plain text or simple HTML), `class`, `max_length` (plain-text characters, default unlimited).
- `pencilino_button()`: a complete link button whose text and URL are both editable. It outputs the whole `<a>` element, so never wrap it in another link or hard-code its `href`. Options: `label`, `default` as `[ 'text' => ..., 'url' => ... ]`, `class`, `max_length` (text, default 40), `before` and `after` for decorative theme markup inside the link such as an icon, and `target => '_blank'` only when the design requires a new tab. With `before` or `after`, the editable text is wrapped in `<span class="pencil-button__text">`.
- `pencilino_image()`: a Media Library image. Options: `label`, `default` (attachment ID), `size`, `class`, `loading`, `fetchpriority`, `aspect_ratio` (such as `'3/2'`), `object_fit` (default `cover` when an aspect ratio is set), and `object_position`. Pencilino applies the last three as inline `aspect-ratio`, `object-fit`, and `object-position` styles on the `<img>`. The theme must still render the fixed frame described below.

All helpers accept `'scope' => 'page'` for templates shared by several pages.

Older themes may still call these helpers by their former names, such as `pencil_text()` and `pencil_image()`. Those keep working. Use the `pencilino_` names in new code, and leave working older code as it is.

## Avoid conflicts with WordPress UI on the frontend

Pencilino loads the WordPress Media Library modal on the public page and may load other WordPress administration UI there in the future. The theme stylesheet remains active on that page, so a theme selector that matches a WordPress core UI class can break the editing interface.

Never define theme CSS using class names that WordPress core UI uses. Avoid `media-*`, `attachment*`, `thumbnail`, `centered`, `filename`, `details`, `check`, `button`, `button-*`, `wp-*`, `screen-reader-text`, `hidden`, `spinner`, `dashicons*`, `notice*`, `uploader-*`, and any other class prefixed with `media-` or `wp-`. Use a theme-specific prefix or name the component by its meaning, such as `project-image-frame`, `site-button`, or `hero-media`, instead of generic names.

Keep global element resets safe. Do not apply layout-changing properties such as `position`, `overflow`, `height`, `display`, or fixed widths to `*` or bare `div`, `ul`, `li`, `img`, `button`, `input`, or `label` selectors. Global `box-sizing` and margin resets are acceptable. Put layout rules on theme-owned component classes.

## Required image workflow

### Where images come from

When you build a website, you never have to leave a gap or invent a rights problem. Take the first option that works:

1. **Images the user or the HTML template already provides.** Always use these first.
2. **Free images from Pexels and Unsplash.** Both are free to use for websites. Pick images that fit the brand and the topic, and give each one fitting alt text. Do not take images from other sites or from a search engine.
3. **Images you generate yourself**, if the AI you run on has an image generation model connected. This depends on what is connected, so do not assume it.
4. **A neutral placeholder** in the right aspect ratio, only when none of the above works (for example when you cannot reach the image sites and have no image generation). List every placeholder in your summary as still to replace.

Say in your summary where each image came from.

For every new static client-facing image:

1. Get the image as a temporary source file, following the order above.
2. Create or choose the initial image in the aspect ratio required by the design.
3. Import or upload it into the site's WordPress Media Library using the available WordPress API, CLI, MCP tool, or admin interface.
4. Add an appropriate title and alternative text in WordPress.
5. Register the returned attachment ID as the initial value of a stable `pencilino_image()` field.
6. Declare the slot's `aspect_ratio`, `object_fit`, and initial `object_position`.
7. Render the image inside a fixed frame and verify that a replacement with different source dimensions is cropped without changing the layout.
8. Verify that **Replace image** opens on the WordPress Media Library.
9. Remove the temporary source file from the theme project after the Media Library import is confirmed.

Do not use `get_stylesheet_directory_uri()`, theme-relative image paths, or hard-coded CSS `url(...)` values for static client-facing images. For a hero or other visual background, render the Pencilino image as a positioned `<img>` and use theme CSS such as `object-fit` and `object-position` to preserve the design.

This rule applies to editable content imagery such as hero photos, team portraits, project images, product-style visuals, and meaningful illustrations. Structural design assets such as interface icons, decorative textures, masks, cursors, and non-content SVG ornaments may remain in the theme when clients are not expected to replace them. A client-editable logo or brand image belongs in the Media Library; a purely structural icon belongs to the theme.

The theme owns the presentation of an image, including its aspect ratio, crop, sizing, position, and responsive behaviour. WordPress owns the media file and its metadata. Pencilino stores the selected attachment ID so the client can replace the image without changing the layout.

The uploaded file's natural width and height must never determine the dimensions of the rendered component. Enforce the declared frame independently from the source image:

```css
.component__image-frame {
    aspect-ratio: 2 / 3;
    overflow: hidden;
}

.component__image-frame img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
}
```

A client may select an image with any source aspect ratio. Crop it into the fixed frame rather than stretching it, rejecting it, or resizing the layout. Treat focal-point adjustment as an optional enhancement; the default crop must remain predictable without it.

If an image already comes from ACF, WooCommerce, a featured-image field, or another provider, keep it with that provider and do not upload or store a duplicate in Pencilino.

## Managed content from other providers

When a value comes from another provider, render it through that provider as usual. Use a managed region only to tell Pencilino that the output is locked and where the editor should go instead.

Provider ownership must be identified while the theme is being built. Do not expect Pencilino to detect a value's source from the final rendered HTML; after rendering, ACF, JetEngine, WooCommerce, WordPress meta, and custom PHP output may look identical.

Use this workflow for every provider-owned value:

1. Inspect the active plugins, template code, field APIs, and storage source.
2. Identify the system that owns the value and the screen where a client can edit it.
3. Render the value directly from that provider. Never copy it into Pencilino.
4. Wrap the smallest useful visible value or logical group in a managed region.
5. Pass the correct provider key, a working `edit_url` and the `field` (see below).
6. Verify in frontend edit mode that clicking the region opens the edit screen scrolled to that field, with the field outlined. When nothing is outlined, or a different field is outlined than the one in the region, the `field` is missing or wrong. Fix it.

### Name the field

Without a `field`, Pencilino has to guess from the visible text. That fails as soon as two fields hold the same value, or when a region shows several fields. Always pass `field`, for custom post types and options pages too.

| Value | `field` |
| --- | --- |
| Post title, content, excerpt, featured image | `title`, `content`, `excerpt`, `featured_image` |
| ACF field or ACF Options field | `acf:<field name>` |
| WooCommerce price | `woo:price` |
| Anything else (JetEngine, custom meta boxes, options pages built in code) | `name:<name attribute of the input on the edit screen>` |

Several fields in one region: separate them with a comma, for example `title,acf:subtitle`.

Keep this out of the templates. Add one wrapper in `functions.php`, such as `project_region_open( $provider, $edit_url, $field = '' )`, that calls `pencilino_managed_region_open()`. Check which content plugins the site really uses first (ACF, WooCommerce, JetEngine or plain WordPress) and only support those.

Do not wrap an entire card, post, product, loop item, or listing when its individual values can be identified. The theme owns the container and layout. Mark the title, image, price, description, and other provider fields separately so clients can see exactly what is managed.

Keep mixed ownership accurate. For example, a custom post type may be registered through ACF while its title and featured image are still WordPress-owned:

- WordPress post title, content, excerpt, or featured image: `provider => 'wordpress'`
- ACF field or ACF Options value: `provider => 'acf'`
- WooCommerce product data such as price, stock, SKU, or product image: `provider => 'woocommerce'`
- JetEngine field, relation, option, or listing value: `provider => 'jetengine'`
- Other plugins or APIs: use a stable lowercase provider key and provide a clear provider label when its name cannot be derived correctly

The edit destination depends on the actual source:

- WordPress, ACF post fields, JetEngine post meta, and WooCommerce product fields usually use the relevant post editing URL.
- ACF Options values must link to their ACF Options page.
- JetEngine options, relations, profiles, or other global data must link to the corresponding JetEngine administration screen.
- External or computed values should link to a real management screen only when one exists. Do not invent an edit destination.

For query and listing output, keep the query and layout under theme control. Mark each rendered provider value inside the result, not the whole loop or listing container.

If ownership or the correct edit destination cannot be determined, inspect the provider's registered fields, APIs, and administration screens. Do not guess. Flag the unresolved value before calling the theme complete.

Example with ACF:

```php
<section class="service-description">
    <?php project_managed_region_open( [
        'provider' => 'acf',
        'edit_url' => get_edit_post_link( $post_id ),
        'field'    => 'acf:service_description',
    ] ); ?>

    <?php the_field( 'service_description' ); ?>

    <?php project_managed_region_close(); ?>
</section>
```

Examples of managed content:

- ACF or JetEngine fields
- WordPress post title and content
- WooCommerce product details and prices
- Query and listing output
- Booking availability, calculations, logged-in-user data, and API results

Do not replace such values with a Pencilino field. Do not save a second copy in Pencilino.

## Content in the block editor (Gutenberg)

This is about content that WordPress owns: the title, text, excerpt and featured image of posts, pages and custom post type entries. The client edits it in the WordPress editor, so write it in a form that editor understands. It does not change the rule that Pencilino-managed page layouts are not built from blocks.

- **Post content is block markup, never loose HTML.** Wrap every paragraph, heading, list and image in its block comment, for example `<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->`. Plain `<p>` tags without block comments open in the editor as one Classic block with a Convert to blocks button, and the client cannot edit them as blocks.
- **Use the right tool.** Write content through the block abilities of your WordPress connection when they exist. When you have to use `wp_insert_post` or `wp_update_post`, build the content with `serialize_blocks()` or write the block comments yourself.
- **Keep to simple core blocks** for text content: paragraph, heading, list, quote, image and buttons. The theme owns the layout, so no groups, columns or cover blocks for content that a template renders.
- **Dummy and seed content follows the same rule.** Test content that is not in blocks looks broken to the client.
- **Custom fields live in the Meta Boxes panel.** In the block editor, fields from ACF, JetEngine and other plugins sit in the Meta Boxes panel at the bottom of the screen. Pencilino's edit popup opens that panel and points at the field that was clicked. Still give every managed region its `field`, so the popup knows where to point.
- **Check it.** After you create or change content, open one entry of every post type in the editor. There must be no Classic block, and the custom fields must be reachable.
## Design system rules

These rules apply to every build, whether you use the default basics below or the framework of the site owner.

- **Everything comes from a variable.** Colours, font families, sizes, spacing, radius and shadows live in variables on `:root`. No hex values or loose pixel values in a class or a component, also not for a value that is used only once: the next page will need it too.
- **Keep the system small.** Before you add a variable or a class, look for an existing one that does the job and use it. A value close to an existing one (two near-identical greys, 15px next to the 16px step) uses the existing variable instead of becoming a new one. Aim for as few colours, variables, classes and styles as the design needs.
- **Global first.** Styling that appears in more than one place is one shared class, used everywhere. Custom components and extra buttons are fine, but build them on the shared variables so they can be reused and extended, not tied to one page.
- **No dead code.** Every class in a template has a CSS rule or is used by a script, and every CSS rule is used somewhere. Remove what does nothing, but search the whole theme and its scripts first.
- **One property, one owner.** Two classes on one element never set the same property. The size and the colour of text are separate classes. Direction, alignment and gap are separate classes. So classes stack in any order and never fight. There are no override classes.
- **Components are complete.** A button, card or badge sets its own background, text colour, border and radius in one class. Never stack a `bg-*`, `rounded-*` or `shadow-*` class on a component.
- **A starting point, not a cage.** If the site needs more (a second badge, a sidebar layout, a secondary colour), add a variable or class that follows the same rules and the same naming. Do not build a second system next to it.
- **Layout:** a container that holds several children is a flex or grid container with a `gap` from the size scale. Use a margin only to add extra space at one specific place, such as more room above a single heading. Do not space siblings with margins. The Gap setting in Dev mode only works on flex or grid.
- **Missing pieces:** if the site needs a background, spacing step, corner radius or shadow that does not exist yet, add it as a variable and a class. Do not write the value in a component.
- **Prefix:** every class and variable of the framework carries a prefix, so Dev mode can tell what comes from the framework and what was added for this project. What you add yourself gets no prefix. The standard prefix is `pcl-`: use it for every framework you build. Only when the template already has a framework of its own with a prefix, keep that prefix. When you are done, call the `pencilino/set-prefix` ability with the prefix you used, and tell the user which prefix it is.
- **Same names everywhere:** the class names in the CSS, the HTML and the templates match exactly.

### Starting from HTML, or from nothing

First decide whether the HTML already has a CSS framework. It has one only when both are true: colours, font sizes and spacing are CSS variables on `:root` that are really used, and reusable classes for type, buttons and spacing are used across several sections. A few classes made for one section (such as `hero__title`) do not make a framework. If you are unsure, decide, and say in your summary what you decided.

- **A clean framework with a prefix:** keep it exactly as it is, and use its prefix.
- **A clean framework without a prefix:** keep its names, values and structure, and add `pcl-` to every class and variable of the framework, everywhere: CSS, HTML, templates and JavaScript selectors. Classes made for one section stay as they are. Check that the site looks the same.
- **No framework, a messy one, or a utility library such as Tailwind or Bootstrap:** build the default basics below, with `pcl-`. Map every value to the nearest step of the scale and do not redesign. Tell the user where something visibly changed. Do not keep the library or a CDN link to it. Classes made for one section may stay, built on the new variables. Never run two systems next to each other.
- **Nothing yet, you start from an idea:** use the default basics below.
- **An existing Pencilino site built earlier, whose framework has no prefix:** Dev mode needs the prefix to work well, so add it as part of your first change to the theme. Make a backup first. Add `pcl-` to every class and variable of the framework, everywhere (CSS, HTML, templates, JavaScript selectors), check that the site looks the same, call `pencilino/set-prefix`, and report it in your summary. If the framework is messy, or comes from a utility library, follow the rule for no framework instead.

The framework is a standard that people can extend. Whatever you add follows the same rules and the same naming.

## Default design basics

These are the basics to build with when the project has no framework of its own. Colours, fonts, corner radius and the title scale are decided per project: for those this text gives the method, not the answer.

**Prefix.** All names below are written without the prefix, to keep the tables readable. In everything you write, add `pcl-` to every class and every variable defined here: `pcl-title-2`, `pcl-btn-solid`, `--pcl-size-24`, `--pcl-primary-brand-1`. The base tag styles and every class use the prefixed variables. (If the project already has its own prefix, use that one instead of `pcl-`.)

Write one stylesheet in this order: the `:root` variables, the base tag styles, the framework classes, then your own project classes.

### Variables

**Colours (decided per project).** Start with these tones, all from the same hue:

| Variable | Role |
| --- | --- |
| `--primary-brand-1` | Brightest brand colour. Button fills, accents on dark backgrounds |
| `--primary-brand-2` | Same hue, darker. Button hover, accent text and links on light backgrounds |
| `--primary-light-1` | Lightest. Page background, headings on dark |
| `--primary-light-2` | Light surface. Alternating sections, body text on dark |
| `--primary-light-3` | Light tint. Borders, dividers, detail text on dark |
| `--primary-dark-1` | Darkest. Headings, dark section backgrounds |
| `--primary-dark-2` | Body text |
| `--primary-dark-3` | Detail text: captions, dates, meta |
| `--primary-transparent-1` | Brand 1 at about 12% opacity (8 digit hex). Tinted surfaces such as badges |

- Pick brand 1 from the brand. Brand 2 is the same hue, clearly darker, so it stays readable as text on light backgrounds.
- Tint the lights and shade the darks with a little of the brand hue instead of pure grey. Light 1 is near white, dark 1 is near black.
- Check the contrast of body text (`dark-2` on `light-1`, `light-2` on `dark-1`).
- This is direction, not a cage. A nearly black and white site with colour only on the buttons is fine. The logic stays: tone 1 for headings, tone 2 for body text, tone 3 for detail, on light and on dark.
- A secondary colour is optional. Add it only when the design needs a second accent. Mirror the naming (`--secondary-brand-1`, `--secondary-brand-2`) and make project classes by duplicating and renaming (`bg-secondary-1`, `btn-solid-secondary`).

**Sizes (fixed).** A 4 point scale. From 32 up the sizes shrink on small screens. The name is the desktop pixel value, so `--size-16` is 16px. Use exactly these values:

```css
--size-0: 0rem;
--size-4: 0.25rem;
--size-8: 0.5rem;
--size-12: 0.75rem;
--size-16: 1rem;
--size-20: 1.25rem;
--size-24: 1.5rem;
--size-32: clamp(1.75rem, 1.662rem + 0.376vw, 2rem);
--size-40: clamp(2.125rem, 1.993rem + 0.563vw, 2.5rem);
--size-48: clamp(2.5rem, 2.324rem + 0.751vw, 3rem);
--size-64: clamp(3.25rem, 2.986rem + 1.127vw, 4rem);
--size-80: clamp(3.875rem, 3.479rem + 1.69vw, 5rem);
--size-96: clamp(4.5rem, 3.972rem + 2.254vw, 6rem);
--size-128: clamp(5.8rem, 5.025rem + 3.305vw, 8rem);
--size-160: clamp(7rem, 5.944rem + 4.507vw, 10rem);
```

**Font sizes.** Body sizes are fixed and never shrink on mobile:

```css
--title-subtitle: 0.875rem;
--body-size-l: 1.125rem;
--body-size-m: 1rem;
--body-size-s: 0.875rem;
```

The title sizes `--title-size-1` to `--title-size-6` come from a modular scale, calculated per project. Do not pick them by eye.

1. Choose two ratios that fit the design, one for mobile and one for desktop (for example 1.2 and 1.25; 1.333 on desktop makes title 1 about 90px). Keep the mobile ratio at or below the desktop ratio.
2. For step n (title 6 is n = 1, title 1 is n = 6): mobile px = 16 x mobile ratio^n and desktop px = 16 x desktop ratio^n, each rounded to a whole pixel.
3. Turn each pair into `clamp()` between a 360px and a 1120px viewport: slope = (desktop px - mobile px) / 760, intercept = mobile px - slope x 360. The value is `clamp(mobile/16 rem, intercept/16 rem + slope x 100 vw, desktop/16 rem)`, rem values rounded to 4 decimals.
4. Title 1 is always the largest step. With seven or eight sizes, title 1 is step 7 or 8. There is no separate set of display sizes.

Worked example with 1.2 and 1.25:

```css
--title-size-1: clamp(3rem, 2.6151rem + 1.7105vw, 3.8125rem);
--title-size-2: clamp(2.5rem, 2.2336rem + 1.1842vw, 3.0625rem);
--title-size-3: clamp(2.0625rem, 1.8849rem + 0.7895vw, 2.4375rem);
--title-size-4: clamp(1.75rem, 1.6612rem + 0.3947vw, 1.9375rem);
--title-size-5: clamp(1.4375rem, 1.3783rem + 0.2632vw, 1.5625rem);
--title-size-6: clamp(1.1875rem, 1.1579rem + 0.1316vw, 1.25rem);
```

If you can run code, calculate the values with a short script instead of by hand.

**Fonts (decided per project).** Two families, never three: `--heading-font` and `--body-font`. Pick fonts that fit the brand and avoid ones that feel cheap or extreme. Pick the weights per project too (defaults: 700 headings, 400 body, 600 buttons and links).

**Corners (decided per project).** `--button-radius` for every button (a very large value gives pill buttons), and `--radius-s`, `--radius-m`, `--radius-l` (defaults 8px, 16px and 32px). Keep them consistent with the feel of the brand.

### Base tag styles

Write these first, so text without a class already looks right.

- `body`: body font, `--body-size-m`, body weight, line-height 160%, colour `--primary-dark-2`, background `--primary-light-1`.
- `h1` to `h6`: heading font and the matching title size (h1 uses title 1, h6 uses title 6). Margins are 0: spacing comes from gap and padding classes.
- `p`, `ul`, `ol`: margins are 0.
- `text-wrap: balance` on `h1` to `h6` and `text-wrap: pretty` on `p`, so headings break evenly and paragraphs do not end on one lonely word.

### Classes

**Text size.** The title classes control the visual size only. Use the tag for the document structure and the class for how big it looks, so the biggest title on a page does not have to be the `h1`.

| Class | Font size | Line-height |
| --- | --- | --- |
| `title-1` | `--title-size-1` | 110% |
| `title-2` | `--title-size-2` | 115% |
| `title-3` | `--title-size-3` | 120% |
| `title-4` | `--title-size-4` | 125% |
| `title-5`, `title-6` | `--title-size-5`, `--title-size-6` | 130% |
| `body-l`, `body-m`, `body-s` | `--body-size-l`, `-m`, `-s` | 160% |

- Titles use the heading font and heading weight, with no letter-spacing. `body-l` is for hero and intro text, `body-m` for normal text, `body-s` for captions and small print.
- `sub-title`: the small label above a heading. `--title-subtitle` size, weight 700, line-height 140%, uppercase, letter-spacing 0.12em (drop the uppercase and you drop the tracking).
- `text-link`: an inline link. Weight 600, underline, no colour. Pair it with a link colour class.
- `use-heading-font` and `use-body-font` set the font family, for the rare override.

**Text colour roles.** Five roles, each for light and for dark backgrounds. Look at the section and pick the role.

| Role | On light | On dark |
| --- | --- | --- |
| Headings | `heading-on-light`: dark 1 | `heading-on-dark`: light 1 |
| Body | `body-on-light`: dark 2 | `body-on-dark`: light 2 |
| Detail | `detail-on-light`: dark 3 | `detail-on-dark`: light 3 |
| Subtitle | `subtitle-on-light`: brand 2 | `subtitle-on-dark`: brand 1 |
| Link | `link-on-light`: brand 2, hover dark 1 | `link-on-dark`: brand 1, hover light 1 |

A heading is never lighter than the body text below it. Colour inherits, so put `body-on-dark` on a dark section and add `heading-on-dark` only where the text should be brighter. If a role needs another colour on this project, change it inside the role class. Do not create a new class that does the same job. Always check that the colour stays readable on its background, especially subtitles and links on dark or coloured sections.

**Backgrounds:** `bg-light-1`, `bg-light-2`, `bg-light-3`, `bg-dark-1`, `bg-dark-2`, `bg-dark-3`, `bg-brand-1`, `bg-brand-2`. Each sets `background-color` to the matching variable.

**Buttons.** Four complete components: `btn-solid`, `btn-outline`, `btn-solid-on-dark`, `btn-outline-on-dark`. The colours below are the default. Most designs use the brand colour for buttons, but a black and white site may use dark buttons. What stays the same: the solid button is the strongest element in its section, the outline is clearly quieter, the on-dark versions stay readable on dark, every colour comes from a variable and hover is a visible change.

| Class | Text | Background | Border | Hover |
| --- | --- | --- | --- | --- |
| `btn-solid` | light 1 | brand 1 | brand 1 | background and border brand 2 |
| `btn-outline` | brand 2 | transparent | brand 1 | background brand 1, text light 1 |
| `btn-solid-on-dark` | dark 1 | light 1 | light 1 | background and border light 2 |
| `btn-outline-on-dark` | light 1 | transparent | light 1 | background light 1, text dark 1 |

Shared shape: `--body-size-m`, weight 600, line-height 1 (so the height comes from the padding), a 1px solid border written as longhands, radius `--button-radius`, a short transition on background, border and text colour, and padding from the size scale (the same on all four). Give every button a visible focus state. One solid button per section.

**Containers.** A boxed class sets only the left and right padding, so the background of a section runs edge to edge while the content stays centred and capped. No inner wrapper is needed.

| Class | Padding left and right | Use for |
| --- | --- | --- |
| `boxed-s` | `max(5%, calc((100% - 47.5rem) / 2))` | Blog posts, forms |
| `boxed-m` | `max(5%, calc((100% - 70rem) / 2))` | Most sections |
| `boxed-l` | `max(5%, calc((100% - 77.5rem) / 2))` | Heroes, galleries |
| `boxed-full` | `5%` | Full-bleed sections |

Never combine `boxed-*` with `pad-lr-*` or an all-sides `pad-*` class.

**Corners:** `rounded-zero`, `rounded-s`, `rounded-m`, `rounded-l` (the radius variables) and `rounded-full` (9999px). Not for buttons or components, they bring their own.

**Shadows:** `shadow-s` (subtle lift, form fields), `shadow-m` (cards, images), `shadow-l` (floating: modals, sticky header). Tint them with dark 1 at low opacity instead of grey, each step softer and further than the last (default `0 2px 8px` at 8%, `0 8px 24px` at 12%, `0 16px 48px` at 20%). One shadow size per section.

**Spacing.** Every spacing class is named after its pixel value and reads the matching size variable: `pad-{n}` (all sides), `pad-tb-{n}` (top and bottom) and `pad-lr-{n}` (left and right), for every step of the scale (0, 4, 8, 12, 16, 20, 24, 32, 40, 48, 64, 80, 96, 128, 160). Use the all-sides class or the axis classes on an element, never both. Section rhythm to start from: `pad-tb-48` tight, `pad-tb-80` standard, `pad-tb-128` spacious.

**Flex and gap.**
- `flex-row`, `flex-column` and `flex-wrap` set only the display, direction and wrap. No default gap.
- `flex-align-start`, `-center`, `-end`, `-stretch` set `align-items`. `flex-justify-start`, `-center`, `-end`, `-between` set `justify-content`.
- `gap-{n}` sets row and column gap from the size scale, for 0, 4, 8, 12, 16, 20, 24, 32 and 40. Nothing above 40: padding is for section spacing. `gap-20` is the usual start, `gap-8` is for tight elements, `gap-32` or `gap-40` for larger blocks. Gap classes work on grid too.
- `text-start`, `text-center`, `text-end` set `text-align` on the container. If centred text does not move, add `flex-align-stretch` to the parent.
- For grids, write a project class with CSS grid (for example `grid-3`) and combine it with a gap class.

**Components.**
- `card`: background light 1, 1px solid border in light 3, radius `--radius-m`. No padding: pair it with a `pad-*` class.
- `badge-1`: `--body-size-s`, weight 600, letter-spacing 0.03em, padding 4px 12px from the size scale, radius 9999px, inline-block, background `--primary-transparent-1`, text brand 2. A second style is the next number (`badge-2`) with only the colours changed.

### How a section is built

One element carries the whole section, always the same four things: a `bg-*` class, a `boxed-*` class, a `pad-tb-*` class, and `flex-column` or `flex-row` with a `gap-*` class. Every text element gets a size class and a colour role (or inherits the colour of its section). These examples show how classes combine. They are not layouts to copy: most sections on a page should look different from the one before.

- Standard section: `bg-light-2 boxed-m pad-tb-80 flex-column gap-32`, holding `sub-title subtitle-on-light`, `title-2 heading-on-light` and `body-m body-on-light`.
- Dark hero: `bg-dark-1 boxed-l pad-tb-128 flex-column gap-24 text-center body-on-dark`, holding `sub-title subtitle-on-dark`, `title-1 heading-on-dark`, `body-l` and `btn-solid-on-dark`.
- Card: `card pad-24 flex-column gap-12`, holding `badge-1`, `title-4 heading-on-light`, `body-m body-on-light` and `text-link link-on-light`.

### Before you finish

- Every value in a class comes from a variable. No stray hex values or pixel values.
- No element has two classes that set the same property, for example `boxed-m` with `pad-lr-40`, or `card` with `bg-light-1`. This includes your own project classes.
- No two variables or classes do almost the same thing, styling that repeats uses one shared class, and there are no classes without a rule or rules without a class.
- Every class and variable of the framework carries the prefix, and what you added yourself does not.
- The size variables follow the 4 point scale and the name matches the value (`--pcl-size-16` is 16px, with your prefix). Dev mode checks this.
- Title sizes come from the calculation, and you can name the two ratios you used.

## Brand guide page

Also create a brand guide: one page that shows the design system as it really is in the CSS, so the client and developers can see at a glance what exists and what to reuse.

- Page with the slug `brand-guide`, published as **Private** (never public, not in menus or the sitemap, `noindex`). Pencilino links to it from the Templates tab. Use `get_header()` and `get_footer()` and a full-width layout, so no dark body background shows next to the content.
- Build it from the real CSS, so it cannot go stale: read the variables from the stylesheet in the page template, or update the page in the same change that touches a variable or shared class.
- Sections, each only when the site has it. Never invent values:
  1. **Colours:** a swatch with the variable name and the value, grouped by role (brand, light, dark, text).
  2. **Typography:** the font families with a specimen, then the heading and body scale rendered with the real tags and classes, with the size of each step.
  3. **Spacing and radius:** the scale shown visually, with the variable name and value.
  4. **Shadows:** one sample per level.
  5. **Components:** buttons, cards, badges and form fields rendered with the real classes, on light and dark backgrounds where the site uses both.
  6. **Classes:** the shared classes with one line on what each one sets.
  7. **Still hard-coded:** values in the CSS that are not a variable (colours, font sizes, spacing, radius, shadows), the most used first. This is the to-do list for a clean framework.
- Start the page with a short overview in plain words: what is in order (for example colours, fonts and layout come from variables) and what can be improved (the number of hand-written values, missing scales). The section **Still hard-coded** holds the details.
- When the viewer can edit pages and Pencilino is active (`function_exists( 'pencilino_cleanup_prompt' )`), put a button next to the overview that copies the clean-up prompt, so the prompt is one click away on the page itself. Print the text of `pencilino_cleanup_prompt()` into a hidden, read-only `<textarea>` (escaped) and copy it with a few lines of JavaScript. Do not link to the Pencilino settings; the prompt lives there deep inside a tab.
- Every variable and class appears once. Do not add an "all variables" list that repeats the sections above. Only list variables that no section shows.
- If the site has no real design system yet (no spacing, radius or shadow variables, few shared classes), say that on the page. A short guide is fine; padding it is not.
- If the project uses a CSS framework, show its own variables and classes under their exact names and keep its prefix, if it has one.
- Keep the CSS minimal. Repeated styling becomes one class. Colours, fonts, sizes and spacing come from variables, never hard-coded.

## SEO
Pencilino pages keep their content in templates and Pencilino fields, so `post_content` is empty. A search engine reads the rendered page and is fine with that. SEO plugins are not: their analysis reads `post_content` and reports a near-empty page. Handle SEO the same way on every site.

### Step 0: find out who owns SEO

Run `pencilino_seo_plugin()`. It returns the name of the active SEO plugin, or an empty string. Also look at the list of active plugins yourself, because the function only knows the common ones. Then follow path A or path B. Never mix them.

### Path A: an SEO plugin is active

- The SEO plugin owns titles, meta descriptions, Open Graph, schema, the sitemap and redirects. The theme only calls `add_theme_support( 'title-tag' )`. Never print meta tags or Open Graph tags from the theme. Pencilino's own SEO box is hidden in this case.
- Give the plugin's content analysis the text of the rendered page. Do this only for published pages with an empty `post_content`, only in the post editor, and only for the plugin that is active.
  - Get the text by requesting the public URL with `wp_remote_get`. Keep the `<main>` element, remove `script`, `style`, `svg`, `nav`, `footer` and `form`, and cache the result in a transient of 10 minutes at most. If the request fails (password protection, basic auth, blocked loopback), return an empty string and tell the user. Do not guess.
  - Pass the HTML to the editor script as JSON (`wp_json_encode`), never as concatenated strings.
  - **Rank Math:** enqueue a script with the dependencies `wp-hooks` and `rank-math-analyzer`, add the content with `wp.hooks.addFilter( 'rank_math_content', … )` and call `rankMathEditor.refresh( 'content' )`.
  - **Yoast SEO:** after the `YoastSEO:ready` event, call `YoastSEO.app.registerPlugin( name, { status: 'ready' } )` and `YoastSEO.app.registerModification( 'content', callback, name, 10 )`.
  - **Any other plugin:** look up its documented analysis API. Do not guess hook names. If there is none, say so in your report.
- Put this code in `inc/seo.php`, required from `functions.php`, with the theme prefix on every function.
- Check it in the editor: the keyword-in-content and word count checks must reflect the visible page text. If you cannot open the editor, tell the user to check it.

### Path B: no SEO plugin

Pencilino handles it. Do not build your own SEO fields.

- Every page and post has an **SEO** box with an SEO title and a meta description. The values live in the post meta `_pencil_seo_title` and `_pencil_seo_description` (also readable and writable through the REST API).
- Pencilino prints the meta description and the Open Graph tags, and uses the SEO title in the page title. The theme only needs `add_theme_support( 'title-tag' )`. Do not print these tags from the theme as well.
- Fill the box for every page you build: a unique SEO title (about 60 characters) and a description (about 155 characters), written from the actual page content. If you have no real text to base it on, leave the field empty. An empty description prints nothing, and an empty title falls back to the page title.
- If the user wants the theme to print the tags itself, return `false` from the filter `pencilino_seo_print_head` and read the values with `pencilino_seo_title()` and `pencilino_seo_description()`.
- The theme owns structured data. Print JSON-LD with `wp_json_encode` in `wp_head`: the organisation (or `LocalBusiness`), breadcrumbs, and `FAQPage` when the site has FAQ content (see below).
- Leave the WordPress core sitemap (`wp-sitemap.xml`) on. Add a redirect plugin or a redirect rule for old URLs.

### Both paths

- Every template has exactly one `<h1>` and one `<main>` around the page content.
- Every image has alt text, set in the Media Library when the image is added. Decorative images get an empty `alt`.
- Keep FAQ questions and answers in one source (a Pencilino field or a repeater). A dedicated FAQ page reads from that same source, so editing happens in one place. The questions and answers must be visible on the page that carries the markup.
- Print `FAQPage` JSON-LD from that source, with plain-text answers (`wp_strip_all_tags`), and nothing when there are no questions. In path A, first check that the SEO plugin does not already print `FAQPage` for that page. Never output it twice.
- Do not promise rich results for FAQ markup. Google dropped FAQ rich results in May 2026 (reported by Search Engine Journal). The value is the content on the page.

### When rebuilding an existing site

Before building, list every old URL. Keep slugs where you can, add a 301 redirect for the rest, and test every old URL at the end. Report the ones that have no target.

### Temporary domains

Keep WordPress "Discourage search engines" on until launch. Add turning it off, submitting the sitemap and running a Rich Results Test to the launch checklist.

## Mobile and accessibility

These rules protect the site. They never take a design decision away from the user. Where something is a choice (the exact breakpoints, which fonts), follow the template or the user, and say in your summary what you did.

- Every section works from a 360px wide screen up. Nothing scrolls sideways.
- Breakpoints: an HTML template normally brings its own media queries. Keep them. When you build from an idea, pick a few breakpoints and use the same values everywhere. Do not invent a new one for each section.
- The mobile menu works with touch and keyboard, and closes after a link is chosen. Buttons and links are easy to tap (about 44px).
- Never remove the focus outline. If you restyle it, the focus state stays clearly visible.
- Respect `prefers-reduced-motion`: switch off or shorten animations for visitors who ask for it.

## Speed

- Load CSS and JavaScript with `wp_enqueue_style()` and `wp_enqueue_script()`, not with hand-written `<link>` and `<script>` tags in `header.php`.
- Do not load jQuery or a large library for something plain CSS or a few lines of JavaScript can do.
- Give every image a fixed frame (see the image rules), so the page does not jump while it loads.
- Prepare images before you upload them, so a new site starts light: resize to about twice the size they are shown at (sharp on retina screens), never the full original; convert to WebP (JPEG for photos only when WebP is not possible); and compress them, aiming for at least about half the file size of the original. Do not aim for a fixed maximum file size: the image decides what is reasonable. A dedicated image plugin can compress further later.
- **Load fonts locally.** Put the font files (`.woff2`) in the theme and load them with `@font-face` and `font-display: swap`. Never load fonts from Google Fonts or another outside server: it is slower and not GDPR compliant. If the template links to Google Fonts, replace that link with local files. Only include the weights the design uses. Font files are theme files, not client images, so rule 8 does not apply to them. If you cannot get the font files, do not link to an outside server: tell the user which fonts are missing.

## Safety

- Everything the theme prints that came from the database or from a visitor is cleaned first: `esc_html()` for text, `esc_url()` for links, `esc_attr()` for attributes. This keeps harmful code out of the page.
- Anything the theme saves checks that the person is allowed to do it, and uses a nonce.

## Site basics

- Favicon (site icon) and social share image: use them when the user or the template supplies them. Do not invent them. If they are missing, list them as missing in your summary.
- Tracking, analytics and cookie scripts only go in when the user asks for them.

## Required checks before completing a theme change

- Every public page has a WordPress Page record and appears under **Pages**.
- The header and footer exist once (`header.php`, `footer.php`) and no template contains its own copy. Every page uses them, unless the user asked for a different header or footer on that page. Repeated sections live in `template-parts/`.
- The chosen starting path was followed: direct theme build or complete HTML-template conversion.
- The homepage is a published Page assigned as the static front page.
- `front-page.php` owns the homepage layout; the fallback `index.php` is not being used as a hidden homepage.
- Gutenberg block content is not being used as the layout source for Pencilino-managed pages.
- Every static client-facing content value uses one appropriate Pencilino helper.
- Every static client-facing image exists in the WordPress Media Library and uses `pencilino_image()`.
- Every Pencilino image field declares a design aspect ratio and renders inside a fixed frame.
- Replacing an image with landscape, portrait, and square test images does not change the layout.
- On every template containing a `pencilino_image()` field, enter Pencilino edit mode, click **Replace image**, and confirm that the Media Library grid, attachment details panel, and **Use image** button are visible and usable at desktop and mobile widths.
- No theme class name collides with a WordPress core UI class name.
- Each Pencilino ID is unique, stable, and clearly named.
- Existing Pencilino IDs and saved client values remain intact during continued development.
- No editable Pencilino field duplicates a third-party value.
- Dynamic and provider-owned values are marked with the correct provider and a verified edit URL.
- Managed regions wrap the smallest useful value or logical group, not whole cards, loop items, or mixed-owner containers.
- Every managed-region **Edit** action opens the correct provider editing screen.
- Every managed region passes a `field`, and clicking it outlines that field in the edit screen.
- The `field` matches what the region shows. Compare the content inside each region with its `field`: a price region gets the price field, a description region the description fields, and so on. A region with the wrong `field` outlines the wrong field, which is worse than a missing one. Click every region once in edit mode and confirm the outlined field is exactly the content the region shows.
- No element has two classes that set the same property, and variable names and the size scale follow the framework.
- A private `brand-guide` page exists, matches the current variables and classes, starts with an overview of what is in order and what to improve, and links to the clean-up prompt.
- No hard-coded colours, fonts, sizes or spacing in the CSS where a variable exists.
- The chosen navigation approach works: theme-defined links resolve to real pages, or the registered WordPress menu can be edited and reordered successfully.
- The theme has a website-specific name, description, version, author, text domain, and a 1200 × 900 screenshot.
- With Pencilino temporarily deactivated, every public page still renders its initial content without a fatal error.
- While logged in, a header with `position: absolute`, `fixed`, or `sticky` starts below the WordPress toolbar on desktop and mobile.
- The browser console and PHP logs contain no new errors on the tested pages.
- The theme has been checked at representative desktop and mobile widths.
- The theme has a 404, search and (when the site has posts) blog and single templates, all in the design system.
- No sideways scrolling at 360px, and the mobile menu works with touch and keyboard.
- Fonts load from local files in the theme, with no request to Google Fonts or another outside server.
- CSS and JavaScript load through `wp_enqueue_*`.
- Images are resized (about twice their displayed size), converted to WebP and compressed, and the summary says where each image came from and lists any placeholders.
- The focus outline is visible and animations respect `prefers-reduced-motion`.
- Everything printed from the database is escaped.
- Missing site basics (favicon, share image) are listed in the summary, not invented.
- Templates remain semantic, accessible, and layout-controlled by the theme.
- No client editing control can modify design or behaviour.
- `pencilino_seo_plugin()` was checked and the matching path (A or B) was followed.
- Path A: `inc/seo.php` exists, is required from `functions.php`, and the SEO plugin's analysis sees the page text (checked in the editor).
- Path B: every page has an SEO title and description (or a deliberate empty one), and the page source prints one `<title>`, one meta description and no duplicate Open Graph tags.
- Each template has one `<h1>` and one `<main>`, and every image has alt text.
- When the site has FAQs, `FAQPage` JSON-LD is printed once per FAQ page.

## Never do these things

- Build a public page only as a theme template without creating its WordPress Page record.
- Paste the header, footer or another repeated section into each template, or give a page its own header or footer when the user did not ask for it.
- Put the homepage only in `index.php` without assigning a real static front page.
- Store a Pencilino-managed page layout in Gutenberg `post_content`.
- Hardcode static client-facing text, images, button labels, or URLs.
- Leave the theme with a generic development name or without a screenshot.
- Call Pencilino helpers in production templates without a safe theme fallback.
- Hardcode the site's domain into internal navigation links.
- Duplicate WordPress-managed menu labels or URLs in Pencilino fields.
- Put a static client-facing image in a theme `assets` directory or reference it with a theme-relative URL.
- Define theme styles for WordPress core UI class names such as `.media-frame`, `.attachment`, `.thumbnail`, or `.button`.
- Let an uploaded image's natural dimensions determine a component's size.
- Omit the aspect ratio because the initial image already happens to fit the design.
- Save complete page HTML in Pencilino.
- Load fonts from Google Fonts or any other outside server.
- Remove the focus outline without a visible replacement.
- Print database or visitor content without escaping it.
- Invent a favicon or social image the user did not provide.
- Leave a managed region without a `field`.
- Make the brand guide page public.
- Put CSS classes, markup, PHP, or JavaScript in Pencilino content fields.
- Change a field ID or type casually; this may orphan saved content.
- Treat query output as independent static content.
- Edit ACF, JetEngine, WooCommerce, or external data through Pencilino storage.
- Rely on runtime HTML inspection to guess which provider owns a value.
- Mark a mixed-owner card or loop item as though one provider owns the complete layout.
- Add a provider edit link without verifying that it opens the correct record or settings screen.
- Build Elementor, Divi, WPBakery, or Gutenberg-style layout editing into the theme.
- Print meta description or Open Graph tags from the theme while an SEO plugin or Pencilino's SEO box already does it.
- Create your own SEO title or description fields in a Pencilino-built theme.
- Guess the analysis API of an unknown SEO plugin.
- Write SEO titles or descriptions that are not based on the real page content.
