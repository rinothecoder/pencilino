# Pencilino

Frontend content editing for AI-built WordPress themes. The theme owns the design, your client edits the words and images right on the website.

> **Using Pencilino by Rino 2.0.x?** Pencilino by Rino is now called Pencilino, and it has moved to WordPress.org for easier updates. To keep receiving updates, add it once by hand: go to **Plugins → Add New**, search for Pencilino, then install and activate it. Pencilino asks you to deactivate the old version: click the button, then delete "Pencilino by Rino". Your content, change log and comments stay. Users with the old Clients role become Editors with Focus mode, so they keep the simple dashboard.

## What Pencilino does

Pencilino is made for custom WordPress themes built by an AI coding agent such as Codex, Claude, Gemini or Cursor. It gives the agent the rules to build the theme properly, and gives your client a simple editing layer on top.

**For your clients**

- Edit text, rich text, buttons and images on the page with the **Edit** button.
- Images sit in fixed frames, so a new image never breaks the layout.
- Content from other plugins (WordPress, ACF, JetEngine, WooCommerce) opens its own edit screen in a popup, with the clicked field highlighted.
- Private comments with pins and an inbox.
- Focus mode: a simple top bar and a start on the website for the users you choose.

**For you as the developer**

- AI instructions per topic, with your own additions. A connected agent reads them with the `pencilino/get-instructions` ability.
- A clean-up prompt that checks the whole site against the instructions.
- Dev mode: inspect classes, spot hard-coded values and adjust spacing.
- Templates overview, change log, access per role and a simple SEO box.

Visitors never load Pencilino, so it does not make your website slower.

## Requirements

- WordPress 6.4 or newer
- PHP 7.4 or newer
- A custom theme built to use Pencilino's helpers

Pencilino does not make an existing pagebuilder site or block theme editable.

## Installation

Install Pencilino from WordPress.org (**Plugins → Add New**, search for Pencilino), or download the code from this repository. Then open **Pencilino → How to use** in WordPress.

## Theme helpers

The full instructions for coding agents are in [`docs/agent-instructions.md`](docs/agent-instructions.md) and inside WordPress under **Pencilino → AI instructions**.

- `pencilino_text()`
- `pencilino_richtext()`
- `pencilino_button()`
- `pencilino_image()`
- `pencilino_managed_region_open()` and `pencilino_managed_region_close()`

Themes built with the older `pencil_` names, such as `pencil_text()`, keep working.

## Feedback

[Share an idea or report a problem](https://rinodeboer.fillout.com/pencilino)

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
