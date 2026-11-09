=== Pencilino by Rino ===
Contributors: rinodeboer
Tags: frontend editing, ai, custom theme, content editing, client editing
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Frontend content editing for AI-built WordPress themes. The theme owns the design, your client edits the words and images.

== Description ==

Pencilino by Rino adds frontend content editing to custom WordPress themes built to use its PHP helpers.

A developer or AI coding agent registers editable text, rich text, button and image fields in the theme. Administrators, Editors, and enabled Clients can then edit those fields on the live page using the **Open Pencilino** button.

The theme keeps full control over layout, styling and behaviour. Clients only change content. They cannot break the design, because the design was never theirs to touch.

**One field, one owner**

Content that already belongs to another system stays there. A product price stays in WooCommerce, a custom field stays in ACF, a post title stays in WordPress. Pencilino marks that output as a read-only region with a link to the right edit screen, and never stores a second copy.

**Theme integration for content editing**

Pencilino needs a custom theme that integrates its helpers. A developer or AI coding agent can build that theme. Private comments and client mode require no additional theme changes. The plugin ships with instructions for that agent, in the **Get started** tab. It will not make an existing page builder or block theme editable, and the quality of the site depends on the model you use, not on this plugin.

**What you get**

* Single-line text, rich text (bold, italic, links, lists), buttons with editable text and link, and Media Library images
* Fixed image frames, so a replaced image never changes the layout
* Per-page content for templates shared by several pages
* An **Open Pencilino** button on the frontend for Administrators, Editors, and enabled Clients
* Private comments on any visible element, including sections and backgrounds, with a separate **Comments** inbox and Resolve action
* Optional **Clients** role with a simplified backend editor, native content sidebar, and links back to the website and content list
* A **Changes** tab listing who changed what, where and when, with before and after
* A **Get started** tab with separate workflows for building from an idea or converting an HTML template
* Copyable starter prompts and complete instructions for your AI coding agent
* A reusable default theme screenshot for projects that do not yet have a custom one
* PHP helpers for the theme: `pencil_text()`, `pencil_richtext()`, `pencil_button()`, `pencil_image()`, and `pencil_managed_region_open()` / `pencil_managed_region_close()`

== Installation ==

1. Install and activate Pencilino.
2. Open **Pencilino → Get started** and choose whether to start from an idea or convert an existing HTML template.
3. Copy the matching starter prompt and give it to a coding tool such as Codex, Claude, Cursor, or another agent with access to the WordPress project.
4. Activate the theme the agent built. Check that every public page appears under **Pages** and that the homepage is set as the static front page.
5. Open the site while logged in as an Administrator or Editor and click **Open Pencilino**.

== Frequently Asked Questions ==

= Does Pencilino work with my existing theme or page builder? =

No. Pencilino only works with a theme that was built to use its helpers. Sites built with a page builder or with block templates are not supported.

= Who can edit content? =

Administrators, Editors, and users assigned the Clients role when client mode is enabled. Enable client mode under **Pencilino → Settings**, then assign the role in WordPress Users. Clients edit static page content through the pencil and use existing provider links for posts, products, and frontend-facing custom post types. Backend Pages, plugins, themes, and settings stay unavailable. Use the `pencil_can_edit` filter for custom access rules.

= Is my content safe when the theme changes? =

Yes. Each field has a stable ID, and a saved value always wins over the theme's default. When the AI redesigns a section later, the client's content stays.

== Changelog ==

= 2.0.5 =
* Pencilino has moved to WordPress.org. This version shows a notice with a link to install it there. Nothing else changed.

= 2.0.4 =
* Public GitHub release updates and a Check for updates button in Settings. No site token required.

= 2.0.3 =
* Keep client logout separate from the pencil, visible only while editing.
* Add a clear website return button to the client logged-out screen.

= 2.0.2 =
* Use an accessible toggle for client mode and add client logout links.

= 2.0.1 =
* Position editing and comment popups beside the clicked point or saved pinpoint.

= 2.0.0 =
* Private frontend comments on content, sections, and backgrounds with a separate Comments inbox and Resolve action.
* Optional Clients role with simplified WordPress post, product, and custom post type editing.
* Existing theme helpers, saved content, and change history remain compatible.


= 1.0.0 =
* First stable release of Pencilino's frontend content editing workflow.

= 0.9.2 =
* Refined the History, About and Release notes screens so they share the same page structure.
* Made the active frontend editing control easier to recognise.
* Clarified the setup steps and added a website link field to both starter prompts.

= 0.9.1 =
* Protect the frontend WordPress Media Library from common theme CSS class collisions.
* Warn developers when the media modal remains collapsed after opening.
* Add theme-building guidance and checks for avoiding WordPress core UI class names and unsafe global CSS resets.

= 0.9 =
* First public release.
