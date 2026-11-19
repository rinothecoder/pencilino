=== Pencilino ===
Contributors: rinodeboer
Tags: frontend editing, ai, custom theme, content editing, client editing
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Frontend content editing for AI-built WordPress themes. The theme owns the design, your client edits the words and images.

== Description ==

Pencilino gives your clients a simple way to edit their website. They click **Edit** on the page, change a text or an image, and save. No dashboard, no pagebuilder, no way to break the design.

It is made for custom WordPress themes built by an AI coding agent such as Codex, Claude, Gemini or Cursor. Pencilino gives that agent the rules to build the theme properly, and gives your client the editing layer on top.

**For your clients**

* Edit text, rich text, buttons and images right on the page, with the **Edit** button
* Images sit in fixed frames, so a new image never breaks the layout
* Content from other plugins (WordPress, ACF, JetEngine, WooCommerce) opens its own edit screen in a popup, with the clicked field highlighted
* Private comments on any part of the page, with pins and an inbox
* **Focus mode** for the users you choose: a simple top bar and a start on the website instead of the dashboard

**For you as the developer**

* **AI instructions:** default rules for front-end editing, templates, content, design system, mobile, speed, safety, SEO and checks. Add your own rules per topic. A connected agent reads everything itself with the `pencilino/get-instructions` ability.
* **Clean-up prompt:** one prompt that makes your agent check the whole site against the instructions and fix what slipped, ending with a short summary in plain words
* **Dev mode:** inspect the classes on any element, spot hard-coded values and adjust spacing with your own design system
* **Templates:** see every template of the theme and where it is used
* **Change log:** who changed what, where and when, with before and after
* **Access:** choose which user roles may edit on the website
* Works with your SEO plugin: Rank Math and Yoast SEO get the text of your real pages for their content checks, also when you install them later

**Good to know**

* **Your website stays fast.** Pencilino only loads for people who are logged in and allowed to edit. Visitors never load it.
* **Turning Pencilino off is safe.** Your agent builds a normal WordPress theme, so every page keeps showing its content without Pencilino.
* **A theme you can hand over.** One design system, real WordPress pages and images in the Media Library, so another developer can keep building.

Pencilino does not make an existing pagebuilder site or block theme editable. It works with themes built to use its helpers.

== Installation ==

1. Install and activate Pencilino.
2. Connect your AI tool to the website through an MCP plugin or solution of your choice.
3. Open **Pencilino → How to use**, copy the starter prompt for your starting point (an idea or an existing HTML design) and give it to your agent.
4. Before you launch, run the clean-up prompt.
5. Open the website while logged in and click **Edit**.

== Frequently Asked Questions ==

= Does Pencilino work with my existing theme or pagebuilder? =

No. Pencilino works with a theme that was built to use its helpers. Sites built with a pagebuilder or with block templates are not supported.

= Who can edit content? =

Administrators and Editors by default. Choose other roles under **Pencilino → Features → Access**. For clients, pick them under **Focus mode** for a simpler dashboard. Developers can use the `pencilino_can_edit` filter for custom rules.

= Does Pencilino make my website slower? =

No. Visitors never load Pencilino. The editing tools only load for people who are logged in and allowed to edit.

= What happens when I turn Pencilino off? =

Your website keeps working and every page keeps showing its content. You lose the editing and the AI instructions, so we recommend keeping it on.

= How does my AI agent know the rules? =

The starter prompts tell the agent to read the instructions with the `pencilino/get-instructions` ability. It returns the default instructions together with your own additions.

= Is my content safe when the theme changes? =

Yes. Each field has a stable ID, and a saved value always wins over the theme's default.

= I used Pencilino by Rino from GitHub. How do I switch? =

Install Pencilino from WordPress.org and activate it. Pencilino asks you to deactivate the old version: click the button, then delete "Pencilino by Rino". Your content, change log and comments stay. Users with the old Clients role become Editors with Focus mode.

== Changelog ==

= 2.3.0 =
* New: bulk actions for comments. Tick comments or use Select all (it covers every page) to resolve many at once, or to reopen or delete resolved comments in one go. Resolve, Reopen and Delete on a single comment work instantly, without a page reload.
* New: administrators can delete resolved comments for good, one at a time or in bulk. Bulk delete asks first, and a comment must be resolved before it can be deleted.
* New: the Open and Resolved filters show how many comments they hold, and an Undo link follows a bulk resolve or reopen.
* New: drag the Edit and Dev bubble anywhere on the page when it is in the way, for example on a phone. It remembers where you left it in that browser.
* New: Rank Math and Yoast SEO get the text of the rendered page in the editor, so their content checks work on Pencilino pages, also when you install them later.
* New: when a theme has no editor styles, the block editor gets a clean system-font style instead of the browser's default serif font. The filter pencilino_editor_fallback_styles turns it off.
* New: the AI instructions ask in one question which plugins to use for custom post types, forms, a shop, bookings, memberships or courses, SEO and custom code, and the agent installs the chosen plugins.
* New: the AI instructions turn designs that repeat, like a team card, into components: one template part and one component class built from variables.
* New: the AI instructions prepare every site for translation plugins. All visible text stays real text in the HTML, and every theme ships its own wpml-config.xml.
* Changed: Pencilino no longer has its own SEO box. Your SEO plugin does the SEO.
* Changed: the AI instructions put site functionality, such as post types and custom form handling, in a snippet plugin instead of the theme, so a redesign never takes it away and you can switch each piece off.
* Changed: custom-built forms get a honeypot, a time check, a rate limit and server-side checks against spam from the start, and keep working with page caching.
* Changed: shops, bookings, memberships and courses are always built with a plugin, never in theme code.
* Changed: themes style the block editor with their own fonts.
* Changed: the clean-up prompt also checks that the theme keeps showing edited texts when Pencilino is off.
* Changed: a cleaner default theme screenshot with the current Pencilino icon.
* Fixed: the Access tile in the Features overview opens Access.
* Fixed: comment pins no longer show on top of the Dev mode panel.
* Fixed: Plugin Check notices.

= 2.2.5 =
* New: the pencilino_saved action fires after every saved field (field ID and value), so a site can clear its page cache.
* Changed: the plugin link points to the WordPress.org page instead of GitHub.
* Changed: the agent instructions now tell themes to read saved Pencilino texts straight from the database when Pencilino is off, so client edits stay visible and only editing stops.

= 2.2.4 =
* New: the list-templates ability tells agents when the page list of a template is cut off (pages_truncated and a short note).

= 2.2.3 =
* Changed: the list-templates ability lists 20 pages per template by default instead of 10.

= 2.2.2 =
* New: the list-changes ability for AI agents can page back through the whole activity log and filter by field, user, page and date.
* New: the list-comments ability can return open, resolved or all comments.

= 2.2.1 =
* New: a wpml-config.xml, so translation plugins that read it (tested with Polylang) list the Pencilino texts for translation.
* Changed: on a translated page (not the default language) Pencilino stays off, so saving can no longer overwrite the original text. Translate in the translation plugin. The filter pencilino_is_default_language changes this.

= 2.2.0 =
* First release on WordPress.org. Pencilino by Rino is now called Pencilino, and updates come from WordPress.org.
* New: AI instructions tab with default rules per topic and your own additions. Agents read them with the pencilino/get-instructions ability.
* New: a clean-up prompt that checks the whole site against the instructions and ends with a short summary.
* New: Dev mode for administrators: inspect classes, spot hard-coded values and adjust spacing.
* New: Templates tab, Access settings per role, and an SEO box for sites without an SEO plugin.
* New: How to use page with four phases and starter prompts that fill in the website address.
* Changed: content from other plugins opens the full edit screen in a popup, with the clicked field highlighted.
* Changed: users with the old Clients role become Editors with Focus mode automatically.
* Changed: the Edit button replaces the Open Pencilino button.
* Changed: the AI instructions now tell agents to load Media Library images by their ID, never by a hard-coded file URL, so images keep working when a file name changes (for example after WebP conversion).
* Improved: visitors never load Pencilino and never cause database writes.

= 2.0.4 =
* Last version on GitHub as Pencilino by Rino.

== Upgrade Notice ==

= 2.2.0 =
Coming from Pencilino by Rino on GitHub? Install this version, then deactivate and delete the old one when Pencilino asks. Your content stays.
